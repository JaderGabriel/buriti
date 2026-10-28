<?php

namespace Tests\Unit;

use App\Mail\TestMail;
use App\Models\User;
use App\Services\MailSettingsService;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class MailSettingsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_apply_overlays_smtp_and_ssl_scheme(): void
    {
        $settings = app(SettingService::class);
        $settings->putMany([
            'mail_mailer' => 'smtp',
            'mail_host' => 'smtp.exemplo.com',
            'mail_port' => '465',
            'mail_encryption' => 'ssl',
            'mail_username' => 'nao-responda@exemplo.com',
            'mail_from_address' => 'nao-responda@exemplo.com',
            'mail_from_name' => 'BURI-TI',
        ]);
        $settings->putSecret('mail_password', 'segredo-smtp');

        app(MailSettingsService::class)->apply();

        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.exemplo.com', config('mail.mailers.smtp.host'));
        $this->assertSame(465, (int) config('mail.mailers.smtp.port'));
        $this->assertSame('smtps', config('mail.mailers.smtp.scheme'));
        $this->assertSame('nao-responda@exemplo.com', config('mail.mailers.smtp.username'));
        $this->assertSame('segredo-smtp', config('mail.mailers.smtp.password'));
        $this->assertSame('nao-responda@exemplo.com', config('mail.from.address'));
        $this->assertSame('BURI-TI', config('mail.from.name'));
    }

    public function test_tls_and_none_set_expected_schemes(): void
    {
        $settings = app(SettingService::class);
        $mail = app(MailSettingsService::class);

        $settings->putMany(['mail_encryption' => 'tls']);
        $mail->apply();
        $this->assertSame('smtp', config('mail.mailers.smtp.scheme'));

        $settings->putMany(['mail_encryption' => 'none']);
        $mail->apply();
        $this->assertNull(config('mail.mailers.smtp.scheme'));
    }

    public function test_snapshot_never_includes_password_and_flags_ready_smtp(): void
    {
        $settings = app(SettingService::class);
        $settings->putMany([
            'mail_mailer' => 'smtp',
            'mail_host' => 'smtp.exemplo.com',
            'mail_port' => '587',
            'mail_encryption' => 'tls',
            'mail_from_address' => 'nao-responda@exemplo.com',
        ]);
        $settings->putSecret('mail_password', 'nao-pode-aparecer');

        $snap = app(MailSettingsService::class)->snapshot();

        $this->assertTrue($snap['ready']);
        $this->assertTrue($snap['has_password']);
        $this->assertSame('SMTP pronto', $snap['label']);
        $this->assertArrayNotHasKey('password', $snap);
        $this->assertArrayNotHasKey('mail_password', $snap);
        $this->assertStringNotContainsString('nao-pode-aparecer', json_encode($snap));
    }

    public function test_form_values_remap_array_mailer_to_log(): void
    {
        config(['mail.default' => 'array']);

        $form = app(MailSettingsService::class)->formValues();

        $this->assertSame('log', $form['mail_mailer']);
        $this->assertContains($form['mail_encryption'], ['tls', 'ssl', 'none']);
    }

    public function test_send_test_records_audit(): void
    {
        Mail::fake();
        $admin = User::factory()->create();

        $ok = app(MailSettingsService::class)->trySendTest('qa@exemplo.com', $admin);

        $this->assertTrue($ok['ok']);
        Mail::assertSent(TestMail::class);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'mail.test',
            'user_id' => $admin->id,
        ]);
    }

    public function test_try_send_test_swallows_transport_errors(): void
    {
        Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('SMTP recusou'));

        $fail = app(MailSettingsService::class)->trySendTest(
            'qa@exemplo.com',
            User::factory()->create(),
        );

        $this->assertFalse($fail['ok']);
        $this->assertStringContainsString('SMTP recusou', $fail['message']);
    }
}
