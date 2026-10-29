<?php

namespace Tests\Unit;

use App\Mail\TestMail;
use App\Models\User;
use App\Services\MailboxStore;
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
            'mail_from_address' => 'outro@exemplo.com',
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
        $this->assertNotSame('outro@exemplo.com', config('mail.from.address'));
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
            'mail_username' => 'nao-responda@exemplo.com',
            'mail_from_address' => 'nao-responda@exemplo.com',
        ]);
        $settings->putSecret('mail_password', 'nao-pode-aparecer');

        $snap = app(MailSettingsService::class)->snapshot();

        $this->assertTrue($snap['ready']);
        $this->assertTrue($snap['aligned']);
        $this->assertSame([], $snap['warnings']);
        $this->assertTrue($snap['has_password']);
        $this->assertSame('Conta pronta', $snap['label']);
        $this->assertSame('imap.exemplo.com', $snap['imap_host']);
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

    public function test_gmail_from_requires_gmail_smtp(): void
    {
        $this->assertNotNull(MailSettingsService::fromAlignmentIssue(
            'jadergabriel8@gmail.com',
            'sendmail',
            '',
        ));
        $this->assertNotNull(MailSettingsService::fromAlignmentIssue(
            'jadergabriel8@gmail.com',
            'smtp',
            'localhost',
        ));
        $this->assertNull(MailSettingsService::fromAlignmentIssue(
            'jadergabriel8@gmail.com',
            'smtp',
            'smtp.gmail.com',
        ));
        $this->assertNull(MailSettingsService::fromAlignmentIssue(
            'contato@buriti.dev.br',
            'smtp',
            'localhost',
        ));
        $this->assertNull(MailSettingsService::fromAlignmentIssue(
            'jadergabriel8@gmail.com',
            'log',
            '',
        ));
        $this->assertNotNull(MailSettingsService::identityIssue(
            'outro@exemplo.com',
            'smtp',
            'mail.exemplo.com',
            'contato@exemplo.com',
        ));
        $this->assertNull(MailSettingsService::identityIssue(
            'contato@exemplo.com',
            'smtp',
            'mail.exemplo.com',
            'contato@exemplo.com',
        ));
        $this->assertSame('contato@exemplo.com', MailSettingsService::mailboxAddress('Contato@exemplo.com'));
    }

    public function test_snapshot_marks_gmail_from_as_not_ready(): void
    {
        app(SettingService::class)->putMany([
            'mail_mailer' => 'smtp',
            'mail_host' => '127.0.0.1',
            'mail_from_address' => 'jadergabriel8@gmail.com',
        ]);

        $snap = app(MailSettingsService::class)->snapshot();

        $this->assertFalse($snap['ready']);
        $this->assertFalse($snap['aligned']);
        $this->assertSame('Conta recusada / desalinhada', $snap['label']);
        $this->assertNotEmpty($snap['warnings']);
        $this->assertSame('contato@localhost', $snap['suggested_from']);
    }

    public function test_try_send_test_blocks_gmail_from_on_local_smtp(): void
    {
        Mail::fake();
        app(SettingService::class)->putMany([
            'mail_mailer' => 'smtp',
            'mail_host' => '127.0.0.1',
            'mail_from_address' => 'jadergabriel8@gmail.com',
        ]);

        $fail = app(MailSettingsService::class)->trySendTest(
            'qa@exemplo.com',
            User::factory()->create(),
        );

        $this->assertFalse($fail['ok']);
        $this->assertStringContainsString('550 5.7.26', $fail['message']);
        Mail::assertNothingSent();
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

    public function test_sent_mail_is_copied_to_mailbox_when_imap_should_archive(): void
    {
        $store = \Mockery::mock(MailboxStore::class);
        $store->shouldReceive('shouldArchiveSent')->once()->andReturn(true);
        $store->shouldReceive('storeSent')->once()->withArgs(function (string $raw) {
            return str_contains($raw, 'Subject:') || str_contains($raw, 'e-mail de teste');
        });
        $this->app->instance(MailboxStore::class, $store);

        Mail::mailer('array')->to('qa@exemplo.com')->send(new TestMail);
    }
}
