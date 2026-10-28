<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Enums\TestimonialStatus;
use App\Models\Contact;
use App\Models\Project;
use App\Models\Testimonial;
use App\Models\User;
use App\Support\TestimonialSeal;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;

class TestimonialService
{
    public function __construct(
        private AuditLogger $audit,
        private UserNotificationService $notifications,
    ) {}

    /**
     * @return array{testimonial: Testimonial, token: string, url: string}
     */
    public function invite(Contact $contact, User $admin, ?Project $project = null): array
    {
        $token = TestimonialSeal::newInviteToken();

        $testimonial = Testimonial::query()->create([
            'uuid' => (string) Str::uuid(),
            'contact_id' => $contact->id,
            'project_id' => $project?->id,
            'company_id' => $project?->company_id ?: $contact->company_id,
            'invited_by_user_id' => $admin->id,
            'status' => TestimonialStatus::Invited,
            'invite_token_hash' => TestimonialSeal::hashInviteToken($token),
            'invite_expires_at' => now()->addDays(30),
        ]);

        $this->audit->record('testimonial.invited', $testimonial, [
            'contact_id' => $contact->id,
            'project_id' => $project?->id,
        ]);

        return [
            'testimonial' => $testimonial,
            'token' => $token,
            'url' => route('testimonials.write', $token),
        ];
    }

    public function findOpenInvite(string $token): ?Testimonial
    {
        $hash = TestimonialSeal::hashInviteToken($token);
        $testimonial = Testimonial::query()
            ->where('invite_token_hash', $hash)
            ->where('status', TestimonialStatus::Invited)
            ->first();

        if ($testimonial === null || ! $testimonial->isOpenInvite()) {
            return null;
        }

        return $testimonial;
    }

    public function submit(Testimonial $testimonial, array $input, Request $request): Testimonial
    {
        if (! $testimonial->isOpenInvite()) {
            throw new RuntimeException('Este convite não aceita mais depoimento.');
        }

        $body = self::normalizeBody((string) ($input['body'] ?? ''));
        $stars = (int) ($input['stars'] ?? 0);
        $displayName = self::normalizeName((string) ($input['display_name'] ?? ''))
            ?: self::fallbackName($testimonial);

        $submittedAt = now();

        $testimonial->forceFill([
            'status' => TestimonialStatus::Submitted,
            'original_stars' => $stars,
            'original_body' => $body,
            'original_display_name' => $displayName,
            'submitted_at' => $submittedAt,
            'submitted_ip' => $request->ip(),
            'submitted_user_agent' => Str::limit((string) $request->userAgent(), 512, ''),
            'invite_token_hash' => null,
            'invite_expires_at' => null,
            'verification_code' => $this->uniqueVerificationCode(),
        ]);

        $sourceDigest = TestimonialSeal::digest(TestimonialSeal::sourcePayload($testimonial));
        $testimonial->source_digest = $sourceDigest;
        $resultDigest = TestimonialSeal::digest(TestimonialSeal::resultPayload($testimonial));
        $testimonial->result_digest = $resultDigest;
        $testimonial->seal = TestimonialSeal::mac($sourceDigest, $resultDigest);
        $testimonial->save();

        $this->audit->record('testimonial.submitted', $testimonial, [
            'stars' => $stars,
            'source_digest' => $sourceDigest,
            'verification_code' => $testimonial->verification_code,
        ]);

        $this->notifications->notifyAdmins(
            NotificationType::Testimonial,
            'Novo depoimento de '.$displayName,
            Str::limit($body, 140),
            route('admin.testimonials.show', $testimonial),
        );

        return $testimonial;
    }

    public function moderate(
        Testimonial $testimonial,
        User $admin,
        TestimonialStatus $status,
        array $input = [],
    ): Testimonial {
        if ($testimonial->submitted_at === null || $testimonial->source_digest === null) {
            throw new RuntimeException('Não há texto selado para mediar.');
        }

        if ($testimonial->status === TestimonialStatus::Archived) {
            throw new RuntimeException('Este depoimento está arquivado. A mediação está encerrada.');
        }

        if (! in_array($status, [TestimonialStatus::Approved, TestimonialStatus::Rejected, TestimonialStatus::Archived], true)) {
            throw new RuntimeException('Status de mediação inválido.');
        }

        $useOriginal = ($input['use_original'] ?? false) === true;
        $publishedBody = $useOriginal
            ? (string) $testimonial->original_body
            : self::normalizeBody((string) ($input['published_body'] ?? $testimonial->original_body));
        $publishedStars = $useOriginal
            ? (int) $testimonial->original_stars
            : (int) ($input['published_stars'] ?? $testimonial->original_stars);
        $publishedName = $useOriginal
            ? (string) $testimonial->original_display_name
            : (self::normalizeName((string) ($input['published_display_name'] ?? '')) ?: (string) $testimonial->original_display_name);

        $testimonial->forceFill([
            'status' => $status,
            'published_body' => $status === TestimonialStatus::Approved ? $publishedBody : $testimonial->published_body,
            'published_stars' => $status === TestimonialStatus::Approved ? $publishedStars : $testimonial->published_stars,
            'published_display_name' => $status === TestimonialStatus::Approved ? $publishedName : $testimonial->published_display_name,
            'moderation_notes' => isset($input['moderation_notes'])
                ? Str::limit(trim((string) $input['moderation_notes']), 500, '')
                : $testimonial->moderation_notes,
            'moderated_by_user_id' => $admin->id,
            'moderated_at' => now(),
        ]);

        $this->resealResult($testimonial);
        $testimonial->save();

        $this->audit->record('testimonial.moderated', $testimonial, [
            'status' => $status->value,
            'mediated' => $testimonial->wasMediated(),
            'result_digest' => $testimonial->result_digest,
        ]);

        return $testimonial;
    }

    public function linkProject(Testimonial $testimonial, ?Project $project): Testimonial
    {
        $testimonial->project_id = $project?->id;
        if ($project?->company_id) {
            $testimonial->company_id = $project->company_id;
        }

        if ($testimonial->source_digest) {
            $this->resealResult($testimonial);
        }

        $testimonial->save();

        $this->audit->record('testimonial.project', $testimonial, [
            'project_id' => $project?->id,
        ]);

        return $testimonial;
    }

    public function resealInvite(Testimonial $testimonial): array
    {
        if ($testimonial->status !== TestimonialStatus::Invited) {
            throw new RuntimeException('Só é possível reenviar um convite ainda aberto.');
        }

        $token = TestimonialSeal::newInviteToken();
        $testimonial->forceFill([
            'invite_token_hash' => TestimonialSeal::hashInviteToken($token),
            'invite_expires_at' => now()->addDays(30),
        ])->save();

        return [
            'testimonial' => $testimonial,
            'token' => $token,
            'url' => route('testimonials.write', $token),
        ];
    }

    public function findByVerificationCode(string $code): ?Testimonial
    {
        $normalized = strtoupper(trim($code));

        return Testimonial::query()
            ->where('verification_code', $normalized)
            ->first();
    }

    private function resealResult(Testimonial $testimonial): void
    {
        $resultDigest = TestimonialSeal::digest(TestimonialSeal::resultPayload($testimonial));
        $testimonial->result_digest = $resultDigest;
        $testimonial->seal = TestimonialSeal::mac((string) $testimonial->source_digest, $resultDigest);
    }

    private function uniqueVerificationCode(): string
    {
        for ($i = 0; $i < 8; $i++) {
            $code = TestimonialSeal::newVerificationCode();
            if (! Testimonial::query()->where('verification_code', $code)->exists()) {
                return $code;
            }
        }

        throw new RuntimeException('Não foi possível gerar o código de verificação.');
    }

    public static function normalizeBody(string $body): string
    {
        $body = str_replace(["\r\n", "\r"], "\n", $body);
        $body = trim($body);

        return $body;
    }

    public static function normalizeName(string $name): string
    {
        return trim(preg_replace('/\s+/u', ' ', $name) ?? '');
    }

    public static function fallbackName(Testimonial $testimonial): string
    {
        $name = trim((string) $testimonial->contact?->name);
        if ($name === '') {
            return 'Cliente';
        }

        $parts = preg_split('/\s+/u', $name) ?: [];

        return (string) ($parts[0] ?? 'Cliente');
    }
}
