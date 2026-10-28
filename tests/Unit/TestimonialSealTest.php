<?php

namespace Tests\Unit;

use App\Enums\TestimonialStatus;
use App\Models\Contact;
use App\Models\Testimonial;
use App\Support\TestimonialSeal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TestimonialSealTest extends TestCase
{
    use RefreshDatabase;

    public function test_digest_is_stable_and_changes_when_body_changes(): void
    {
        $payload = [
            'v' => 1,
            'kind' => 'testimonial.source',
            'body' => 'Texto do cliente',
            'stars' => 5,
        ];

        $this->assertSame(TestimonialSeal::digest($payload), TestimonialSeal::digest($payload));
        $this->assertNotSame(
            TestimonialSeal::digest($payload),
            TestimonialSeal::digest([...$payload, 'body' => 'Texto alterado']),
        );
    }

    public function test_inspect_detects_tampered_original(): void
    {
        $testimonial = Testimonial::factory()->submitted()->create([
            'contact_id' => Contact::factory(),
        ]);

        $this->assertTrue($testimonial->authenticity()['ok']);

        $testimonial->forceFill(['original_body' => 'Texto adulterado'])->save();

        $check = TestimonialSeal::inspect($testimonial->fresh());
        $this->assertFalse($check['source']);
        $this->assertFalse($check['ok']);
    }

    public function test_verification_code_format(): void
    {
        $this->assertMatchesRegularExpression('/^BURI-[0-9A-Z]{4}-[0-9A-Z]{4}$/', TestimonialSeal::newVerificationCode());
        $this->assertSame(64, strlen(TestimonialSeal::newInviteToken()));
    }

    public function test_approved_factory_has_matching_seal(): void
    {
        $testimonial = Testimonial::factory()->approved()->create([
            'contact_id' => Contact::factory(),
            'status' => TestimonialStatus::Approved,
        ]);

        $this->assertTrue($testimonial->fresh()->authenticity()['ok']);
    }

    public function test_inspect_detects_tampered_mac(): void
    {
        $testimonial = Testimonial::factory()->submitted()->create([
            'contact_id' => Contact::factory(),
        ]);

        $testimonial->forceFill(['seal' => str_repeat('0', 64)])->save();

        $check = TestimonialSeal::inspect($testimonial->fresh());
        $this->assertFalse($check['ok']);
        $this->assertFalse($check['seal']);
    }
}
