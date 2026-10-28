<?php

namespace Database\Factories;

use App\Enums\TestimonialStatus;
use App\Models\Contact;
use App\Models\Testimonial;
use App\Models\User;
use App\Support\TestimonialSeal;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Testimonial>
 */
class TestimonialFactory extends Factory
{
    protected $model = Testimonial::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'contact_id' => Contact::factory(),
            'project_id' => null,
            'company_id' => null,
            'invited_by_user_id' => User::factory(),
            'status' => TestimonialStatus::Invited,
            'invite_token_hash' => TestimonialSeal::hashInviteToken(TestimonialSeal::newInviteToken()),
            'invite_expires_at' => now()->addDays(30),
        ];
    }

    public function submitted(int $stars = 5, string $body = 'Serviço impecável, entrega no prazo.'): static
    {
        return $this->state(function () use ($stars, $body) {
            $submittedAt = now()->subHour();

            return [
                'status' => TestimonialStatus::Submitted,
                'original_stars' => $stars,
                'original_body' => $body,
                'original_display_name' => 'Maria Cliente',
                'submitted_at' => $submittedAt,
                'invite_token_hash' => null,
                'invite_expires_at' => null,
                'verification_code' => TestimonialSeal::newVerificationCode(),
            ];
        })->afterCreating(function (Testimonial $testimonial) {
            $testimonial->forceFill([
                'source_digest' => TestimonialSeal::digest(TestimonialSeal::sourcePayload($testimonial)),
            ]);
            $testimonial->forceFill([
                'result_digest' => TestimonialSeal::digest(TestimonialSeal::resultPayload($testimonial)),
            ]);
            $testimonial->forceFill([
                'seal' => TestimonialSeal::mac(
                    (string) $testimonial->source_digest,
                    (string) $testimonial->result_digest,
                ),
            ])->save();
        });
    }

    public function approved(): static
    {
        return $this->submitted()->state(fn (array $attrs) => [
            'status' => TestimonialStatus::Approved,
            'published_stars' => $attrs['original_stars'] ?? 5,
            'published_body' => $attrs['original_body'] ?? 'Serviço impecável, entrega no prazo.',
            'published_display_name' => $attrs['original_display_name'] ?? 'Maria Cliente',
            'moderated_at' => now(),
        ]);
    }

    public function archived(): static
    {
        return $this->submitted()->state(fn (array $attrs) => [
            'status' => TestimonialStatus::Archived,
            'published_stars' => $attrs['original_stars'] ?? 5,
            'published_body' => $attrs['original_body'] ?? 'Serviço impecável, entrega no prazo.',
            'published_display_name' => $attrs['original_display_name'] ?? 'Maria Cliente',
            'moderated_at' => now(),
        ]);
    }
}
