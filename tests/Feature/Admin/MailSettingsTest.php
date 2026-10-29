<?php

namespace Tests\Feature\Admin;

use App\Mail\TestMail;
use App\Models\Setting;
use App\Models\User;
use App\Services\MailboxStore;
use App\Services\SettingService;
use App\Support\AdminNavigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MailSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
    }

    public function test_admin_can_open_mail_settings_from_sistema(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('>MAIL</span>', false)
            ->assertSee('>E-mail</span>', false);

        $this->actingAs($this->admin)
            ->get(route('admin.settings.edit'))
            ->assertOk()
            ->assertSee('MAIL (SMTP, IMAP e teste)', false)
            ->assertSee(route('admin.settings.mail'), false);

        $this->actingAs($this->admin)
            ->get(route('admin.settings.mail'))
            ->assertOk()
            ->assertSee('>MAIL</span>', false)
            ->assertSee('Sistema · MAIL', false)
            ->assertSee('Enviar e-mail de teste', false)
            ->assertSee('não são enviados por e-mail', false)
            ->assertSee('Convites de depoimento', false)
            ->assertSee('Abrir caixa', false);

        $this->actingAs($this->admin)
            ->getJson(route('admin.search', ['q' => 'smtp']))
            ->assertOk()
            ->assertJsonFragment([
                'title' => 'MAIL',
                'type' => 'página',
                'subtitle' => 'Sistema',
            ]);
    }

    public function test_guest_and_staff_cannot_open_mail_settings(): void
    {
        $this->get(route('admin.settings.mail'))
            ->assertRedirect(route('login'));

        $this->post(route('admin.settings.mail.inbox'))
            ->assertRedirect(route('login'));

        $staff = User::factory()->staff()->create();
        $this->assertFalse($staff->canManageMail());
        $this->assertSame([], AdminNavigation::search('smtp', $staff));
        $this->assertSame([], AdminNavigation::search('imap', $staff));

        $this->actingAs($staff)
            ->get(route('jirau.home'))
            ->assertOk()
            ->assertDontSee('>MAIL</span>', false)
            ->assertDontSee('>E-mail</span>', false)
            ->assertDontSee(route('admin.settings.mail'), false);

        $this->actingAs($staff)
            ->get(route('admin.settings.mail'))
            ->assertRedirect(route('jirau.home'));

        $this->actingAs($staff)
            ->put(route('admin.settings.mail.update'), [
                'mail_mailer' => 'log',
            ])
            ->assertRedirect(route('jirau.home'));

        $this->actingAs($staff)
            ->post(route('admin.settings.mail.inbox'))
            ->assertRedirect(route('jirau.home'));
    }

    public function test_admin_can_update_mail_settings_and_keep_password(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.settings.mail.update'), [
                'mail_mailer' => 'smtp',
                'mail_host' => 'smtp.exemplo.com',
                'mail_port' => '587',
                'mail_encryption' => 'tls',
                'mail_username' => 'nao-responda@exemplo.com',
                'mail_password' => 'segredo-smtp',
                'mail_from_address' => 'nao-responda@exemplo.com',
                'mail_from_name' => 'BURI-TI',
            ])
            ->assertRedirect(route('admin.settings.mail'))
            ->assertSessionHas('success');

        $settings = app(SettingService::class);
        $this->assertSame('smtp', $settings->get('mail_mailer'));
        $this->assertSame('smtp.exemplo.com', $settings->get('mail_host'));
        $this->assertSame('587', $settings->get('mail_port'));
        $this->assertSame('tls', $settings->get('mail_encryption'));
        $this->assertSame('segredo-smtp', $settings->getSecret('mail_password'));
        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.exemplo.com', config('mail.mailers.smtp.host'));
        $this->assertSame(587, (int) config('mail.mailers.smtp.port'));
        $this->assertSame('smtp', config('mail.mailers.smtp.scheme'));

        $this->actingAs($this->admin)
            ->put(route('admin.settings.mail.update'), [
                'mail_mailer' => 'log',
                'mail_host' => 'smtp.exemplo.com',
                'mail_port' => '587',
                'mail_encryption' => 'tls',
                'mail_username' => 'nao-responda@exemplo.com',
                'mail_password' => '',
                'mail_from_address' => 'nao-responda@exemplo.com',
                'mail_from_name' => 'BURI-TI',
            ])
            ->assertRedirect(route('admin.settings.mail'));

        $this->assertSame('log', app(SettingService::class)->get('mail_mailer'));
        $this->assertSame('segredo-smtp', app(SettingService::class)->getSecret('mail_password'));
        $this->assertSame('log', config('mail.default'));
    }

    public function test_admin_can_send_test_email(): void
    {
        Mail::fake();

        $this->actingAs($this->admin)
            ->post(route('admin.settings.mail.test'), [
                'to' => 'qa@exemplo.com',
            ])
            ->assertRedirect(route('admin.settings.mail'))
            ->assertSessionHas('success');

        Mail::assertSentCount(1);
        Mail::assertSent(TestMail::class, function (TestMail $mail) {
            $html = $mail->render();

            return $mail->hasTo('qa@exemplo.com')
                && $mail->envelope()->subject === 'BURI-TI — e-mail de teste'
                && str_contains($html, 'Nenhum convite de depoimento')
                && str_contains($html, 'Lei nº 13.709/2018')
                && str_contains($html, '#eef2f6')
                && ! str_contains($html, 'background:#0a1628');
        });
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'mail.test',
            'user_id' => $this->admin->id,
        ]);
    }

    public function test_staff_cannot_send_test_email(): void
    {
        Mail::fake();
        $staff = User::factory()->withoutAdminAccess()->create();

        $this->actingAs($staff)
            ->post(route('admin.settings.mail.test'), [
                'to' => 'qa@exemplo.com',
            ])
            ->assertRedirect(route('jirau.home'));

        Mail::assertNothingSent();
    }

    public function test_update_rejects_gmail_from_unless_host_is_gmail_smtp(): void
    {
        $this->actingAs($this->admin)
            ->from(route('admin.settings.mail'))
            ->put(route('admin.settings.mail.update'), [
                'mail_mailer' => 'smtp',
                'mail_host' => '127.0.0.1',
                'mail_port' => '25',
                'mail_encryption' => 'none',
                'mail_from_address' => 'qa@gmail.com',
                'mail_from_name' => 'BURI-TI',
            ])
            ->assertRedirect(route('admin.settings.mail'))
            ->assertSessionHasErrors('mail_from_address');

        $this->actingAs($this->admin)
            ->put(route('admin.settings.mail.update'), [
                'mail_mailer' => 'smtp',
                'mail_host' => 'smtp.gmail.com',
                'mail_port' => '587',
                'mail_encryption' => 'tls',
                'mail_username' => 'qa@gmail.com',
                'mail_from_address' => 'qa@gmail.com',
                'mail_from_name' => 'BURI-TI',
            ])
            ->assertRedirect(route('admin.settings.mail'))
            ->assertSessionHas('success')
            ->assertSessionDoesntHaveErrors();

        $this->actingAs($this->admin)
            ->put(route('admin.settings.mail.update'), [
                'mail_mailer' => 'smtp',
                'mail_host' => 'mail.exemplo.com',
                'mail_port' => '587',
                'mail_encryption' => 'tls',
                'mail_username' => 'contato@exemplo.com',
                'mail_from_address' => 'qa@gmail.com',
                'mail_from_name' => 'BURI-TI',
            ])
            ->assertRedirect(route('admin.settings.mail'))
            ->assertSessionHas('success')
            ->assertSessionDoesntHaveErrors();

        $this->assertSame('contato@exemplo.com', app(SettingService::class)->get('mail_from_address'));
        $this->assertSame('contato@exemplo.com', config('mail.from.address'));
    }

    public function test_send_test_is_blocked_for_gmail_from_without_gmail_smtp(): void
    {
        app(SettingService::class)->putMany([
            'mail_mailer' => 'sendmail',
            'mail_from_address' => 'qa@gmail.com',
        ]);

        Mail::fake();

        $this->actingAs($this->admin)
            ->from(route('admin.settings.mail'))
            ->post(route('admin.settings.mail.test'), [
                'to' => 'qa@exemplo.com',
            ])
            ->assertRedirect(route('admin.settings.mail'))
            ->assertSessionHas('error');

        Mail::assertNothingSent();
    }

    public function test_admin_can_preview_inbox_via_imap(): void
    {
        $store = \Mockery::mock(MailboxStore::class);
        $store->shouldReceive('recentInbox')->once()->with(8)->andReturn([
            ['from' => 'ana@exemplo.com', 'subject' => 'Ola', 'date' => 'hoje'],
        ]);
        $this->app->instance(MailboxStore::class, $store);

        $this->actingAs($this->admin)
            ->from(route('admin.settings.mail'))
            ->post(route('admin.settings.mail.inbox'))
            ->assertRedirect(route('admin.settings.mail'))
            ->assertSessionHas('success')
            ->assertSessionHas('mail_inbox');
    }

    public function test_mail_page_explains_gmail_authentication(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.settings.mail'))
            ->assertOk()
            ->assertSee('550 5.7.26', false)
            ->assertSee('contato@localhost', false);
    }

    public function test_update_rejects_invalid_mailer_port_and_from(): void
    {
        $this->actingAs($this->admin)
            ->from(route('admin.settings.mail'))
            ->put(route('admin.settings.mail.update'), [
                'mail_mailer' => 'ses',
                'mail_port' => '0',
                'mail_encryption' => 'starttls',
                'mail_from_address' => 'nao-e-email',
            ])
            ->assertRedirect(route('admin.settings.mail'))
            ->assertSessionHasErrors([
                'mail_mailer',
                'mail_port',
                'mail_encryption',
                'mail_from_address',
            ]);
    }

    public function test_ssl_overlay_uses_smtps_and_password_is_encrypted(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.settings.mail.update'), [
                'mail_mailer' => 'smtp',
                'mail_host' => 'smtp.exemplo.com',
                'mail_port' => '465',
                'mail_encryption' => 'ssl',
                'mail_username' => 'nao-responda@exemplo.com',
                'mail_password' => 'segredo-smtp',
                'mail_from_address' => 'nao-responda@exemplo.com',
                'mail_from_name' => 'BURI-TI',
            ])
            ->assertRedirect(route('admin.settings.mail'));

        $this->assertSame('smtps', config('mail.mailers.smtp.scheme'));
        $this->assertSame(465, (int) config('mail.mailers.smtp.port'));

        $stored = Setting::query()->where('key', 'mail_password')->value('value');
        $this->assertIsString($stored);
        $this->assertNotSame('segredo-smtp', $stored);

        $this->actingAs($this->admin)
            ->get(route('admin.settings.mail'))
            ->assertOk()
            ->assertSee('Definida', false)
            ->assertDontSee('segredo-smtp', false);
    }

    public function test_mail_page_prefills_contact_email_as_test_recipient(): void
    {
        app(SettingService::class)->putMany([
            'contact_email' => 'contato@buriti.test',
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.settings.mail'))
            ->assertOk()
            ->assertSee('contato@buriti.test', false);
    }

    public function test_test_email_requires_valid_recipient(): void
    {
        Mail::fake();

        $this->actingAs($this->admin)
            ->from(route('admin.settings.mail'))
            ->post(route('admin.settings.mail.test'), [
                'to' => 'nao-e-email',
            ])
            ->assertRedirect(route('admin.settings.mail'))
            ->assertSessionHasErrors('to');

        Mail::assertNothingSent();
    }
}
