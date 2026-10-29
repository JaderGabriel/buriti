<?php

namespace App\Services;

use App\Enums\ContactStatus;
use App\Enums\NotificationType;
use App\Enums\TestimonialStatus;
use App\Models\Contact;
use App\Models\Project;
use App\Models\Testimonial;
use App\Models\User;
use App\Support\TestimonialSeal;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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

    /**
     * @return Collection<int, Testimonial>
     */
    public function publicApproved(): Collection
    {
        return Testimonial::query()
            ->approvedPublic()
            ->with(['project:id,name,featured_on_home,is_public'])
            ->leftJoin('projects', 'projects.id', '=', 'testimonials.project_id')
            ->orderByRaw('CASE WHEN projects.featured_on_home = ? AND projects.is_public = ? THEN 0 ELSE 1 END', [true, true])
            ->orderByDesc('testimonials.published_stars')
            ->orderByDesc('testimonials.submitted_at')
            ->orderByDesc('testimonials.id')
            ->select('testimonials.*')
            ->get()
            ->filter(fn (Testimonial $testimonial) => $testimonial->authenticity()['ok'] === true)
            ->values();
    }

    public function sampleCount(): int
    {
        return Testimonial::query()->samples()->count();
    }

    /**
     * @return Collection<int, Testimonial>
     */
    public function plantSamples(User $admin): Collection
    {
        $this->purgeSamples();

        $featured = Project::query()->public()->featuredOnHome()->orderBy('featured_sort')->orderBy('id')->first();
        $other = Project::query()
            ->public()
            ->where('featured_on_home', false)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();

        if ($featured === null) {
            $featured = Project::factory()->public()->create([
                'name' => 'Projeto em destaque (amostra)',
                'featured_on_home' => true,
                'information' => 'Projeto sintético só para testar depoimentos na vitrine.',
            ]);
        }

        if ($other === null) {
            $other = Project::factory()->public()->create([
                'name' => 'Projeto de portfólio (amostra)',
                'featured_on_home' => false,
                'information' => 'Projeto sintético só para testar o indicador no card.',
            ]);
        }

        $contact = Contact::query()->firstOrCreate(
            ['email' => 'amostra.depoimentos@buriti.test'],
            [
                'name' => 'Cliente de amostra BURI-TI',
                'status' => ContactStatus::Active,
                'notes' => 'Contato sintético para testes visuais de depoimento. Pode apagar com as amostras.',
            ],
        );

        $rows = [
            [
                'project' => $featured,
                'stars' => 5,
                'name' => 'Rita Amostra',
                'body' => 'Entrega no prazo, comunicação clara e o painel ficou simples de usar no dia a dia da equipe.',
                'submitted' => now()->subDays(2),
            ],
            [
                'project' => $featured,
                'stars' => 5,
                'name' => 'Paulo Amostra',
                'body' => 'O projeto saiu do papel com escopo visível. Cada etapa tinha responsável e o acompanhamento foi próximo.',
                'submitted' => now()->subDays(8),
            ],
            [
                'project' => $featured,
                'stars' => 4,
                'name' => 'Helena Amostra',
                'body' => 'Integração estável e documentação objetiva. Ajustes pontuais depois da entrega, sempre com retorno rápido.',
                'submitted' => now()->subDays(16),
            ],
            [
                'project' => $other,
                'stars' => 4,
                'name' => 'Carlos Amostra',
                'body' => 'Sistema sob contrato, com código privado, mas o resultado operacional ficou evidente para a gestão.',
                'submitted' => now()->subDays(21),
            ],
            [
                'project' => $other,
                'stars' => 3,
                'name' => 'Joana Amostra',
                'body' => 'Bom trabalho técnico. Houve um atraso no início, depois o ritmo de entrega se estabilizou bem.',
                'submitted' => now()->subDays(40),
            ],
        ];

        $created = collect();

        foreach ($rows as $row) {
            $created->push(Testimonial::factory()->approved()->sample()->create([
                'contact_id' => $contact->id,
                'project_id' => $row['project']->id,
                'company_id' => $row['project']->company_id,
                'invited_by_user_id' => $admin->id,
                'moderated_by_user_id' => $admin->id,
                'original_stars' => $row['stars'],
                'original_body' => $row['body'],
                'original_display_name' => $row['name'],
                'published_stars' => $row['stars'],
                'published_body' => $row['body'],
                'published_display_name' => $row['name'],
                'submitted_at' => $row['submitted'],
                'moderated_at' => $row['submitted']->copy()->addHour(),
                'moderation_notes' => 'Amostra de teste visual (is_sample).',
            ]));
        }

        $this->audit->record('testimonial.samples.planted', null, [
            'count' => $created->count(),
        ], null, $admin->id);

        return $created;
    }

    public function purgeSamples(?User $admin = null): int
    {
        $ids = Testimonial::query()->samples()->pluck('id');
        $count = $ids->count();

        if ($count === 0) {
            return 0;
        }

        Testimonial::query()->whereIn('id', $ids)->delete();

        $this->audit->record('testimonial.samples.purged', null, [
            'count' => $count,
        ], null, $admin?->id);

        return $count;
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
