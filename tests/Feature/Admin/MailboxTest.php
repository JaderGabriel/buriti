<?php

namespace Tests\Feature\Admin;

use App\Mail\CrmOutgoingMail;
use App\Mail\ImapClient;
use App\Mail\ImapConfig;
use App\Models\ContactMessage;
use App\Models\User;
use App\Services\ImapMailboxStore;
use App\Services\SettingService;
use App\Support\AdminNavigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\ScriptedImapTransport;
use Tests\TestCase;

class MailboxTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
    }

    public function test_guest_and_staff_cannot_open_mailbox(): void
    {
        $this->get(route('admin.mailbox.index'))
            ->assertRedirect(route('login'));

        $staff = User::factory()->staff()->create();
        $this->assertFalse($staff->canManageMail());
        $this->assertNotContains('admin.mailbox.index', $this->navRoutes($staff));
        $this->assertSame([], AdminNavigation::search('correio', $staff));
        $this->assertSame([], AdminNavigation::search('smtp', $staff));

        $this->actingAs($staff)
            ->get(route('jirau.home'))
            ->assertOk()
            ->assertDontSee('>Correio</span>', false)
            ->assertDontSee(route('admin.mailbox.index'), false)
            ->assertDontSee(route('admin.settings.mail'), false);

        foreach ([
            ['get', 'admin.mailbox.index'],
            ['get', 'admin.mailbox.compose'],
            ['get', 'admin.mailbox.attachment'],
            ['post', 'admin.mailbox.send'],
            ['post', 'admin.mailbox.destroy'],
            ['post', 'admin.mailbox.move'],
            ['post', 'admin.mailbox.seen'],
            ['post', 'admin.mailbox.folders.store'],
            ['put', 'admin.mailbox.signature'],
        ] as [$method, $name]) {
            $this->actingAs($staff)
                ->{$method}(route($name))
                ->assertRedirect(route('jirau.home'));
        }
    }

    public function test_admin_sees_setup_when_imap_is_not_configured(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.mailbox.index'))
            ->assertOk()
            ->assertSee('Configurar conta', false)
            ->assertSee('Correio', false);
    }

    public function test_mailbox_lists_inbox_from_imap(): void
    {
        $this->configureMailbox();
        $this->scriptImap([
            '* LIST (\\HasNoChildren) "/" INBOX',
            'A1 OK LIST completed',
            '* STATUS "INBOX" (MESSAGES 0 UNSEEN 0)',
            'A2 OK STATUS completed',
            '* 0 EXISTS',
            'A3 OK SELECT completed',
            '* BYE',
            'A4 OK LOGOUT completed',
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.mailbox.index'))
            ->assertOk()
            ->assertSee('Caixa de entrada', false)
            ->assertSee('Escrever', false)
            ->assertSee('contato@exemplo.com', false)
            ->assertSee('Nenhuma mensagem nesta pasta', false);
    }

    public function test_admin_can_send_from_mailbox_identity(): void
    {
        $this->configureMailbox();
        Mail::fake();

        $this->actingAs($this->admin)
            ->post(route('admin.mailbox.send'), [
                'to' => 'qa@exemplo.com',
                'subject' => 'Proposta',
                'body' => 'Segue o texto.',
                'signature' => '1',
            ])
            ->assertRedirect(route('admin.mailbox.index', ['pasta' => 'INBOX']))
            ->assertSessionHas('success');

        Mail::assertSent(CrmOutgoingMail::class, function (CrmOutgoingMail $mail) {
            return $mail->hasTo('qa@exemplo.com')
                && $mail->envelope()->subject === 'Proposta'
                && $mail->hasFrom('contato@exemplo.com');
        });
    }

    public function test_admin_can_save_signature(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.mailbox.signature'), [
                'mail_signature' => "Jader\nBURI-TI",
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame("Jader\nBURI-TI", app(SettingService::class)->get('mail_signature'));
    }

    public function test_compose_page_opens(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.mailbox.compose', ['para' => 'ana@exemplo.com']))
            ->assertOk()
            ->assertSee('Nova mensagem', false)
            ->assertSee('ana@exemplo.com', false)
            ->assertSee('Assinatura', false)
            ->assertSee('Cco', false);
    }

    public function test_compose_from_site_message_quotes_original(): void
    {
        $message = ContactMessage::factory()->create([
            'name' => 'Carla Site',
            'email' => 'carla@empresa.com',
            'subject' => 'Orçamento institucional',
            'message' => 'Preciso de um site novo.',
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.mailbox.compose', ['mensagem' => $message->id]))
            ->assertOk()
            ->assertSee('Resposta ao site', false)
            ->assertSee('carla@empresa.com', false)
            ->assertSee('Re: Orçamento institucional', false)
            ->assertSee('Preciso de um site novo.', false);
    }

    public function test_admin_can_send_with_bcc_and_mark_contact_message_read(): void
    {
        $this->configureMailbox();
        Mail::fake();

        $message = ContactMessage::factory()->create([
            'email' => 'carla@empresa.com',
            'subject' => 'Orçamento institucional',
            'read_at' => null,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.mailbox.send'), [
                'to' => 'carla@empresa.com',
                'bcc' => 'copia@exemplo.com',
                'subject' => 'Re: Orçamento institucional',
                'body' => 'Segue a proposta.',
                'signature' => '1',
                'contact_message_id' => $message->id,
            ])
            ->assertRedirect(route('admin.mailbox.index', ['pasta' => 'INBOX']))
            ->assertSessionHas('success');

        Mail::assertSent(CrmOutgoingMail::class, function (CrmOutgoingMail $mail) {
            return $mail->hasTo('carla@empresa.com')
                && $mail->hasBcc('copia@exemplo.com');
        });
        $this->assertNotNull($message->fresh()->read_at);
    }

    public function test_comercial_nav_lists_mailbox(): void
    {
        $this->assertTrue($this->admin->canManageMail());
        $this->assertContains('admin.mailbox.index', $this->navRoutes($this->admin));
        $this->assertContains('admin.settings.mail', $this->navRoutes($this->admin));

        $this->actingAs($this->admin)
            ->get(route('admin.mailbox.index'))
            ->assertOk()
            ->assertSee('>Correio</span>', false)
            ->assertSee('>Mensagens</span>', false)
            ->assertSee('>E-mail</span>', false);
    }

    private function configureMailbox(): void
    {
        $settings = app(SettingService::class);
        $settings->putMany([
            'mail_mailer' => 'smtp',
            'mail_host' => 'mail.exemplo.com',
            'mail_username' => 'contato@exemplo.com',
            'mail_from_name' => 'BURI-TI',
        ]);
        $settings->putSecret('mail_password', 'segredo-imap');
    }

    /**
     * @return list<string>
     */
    private function navRoutes(User $user): array
    {
        return collect(AdminNavigation::modules($user))
            ->flatMap(fn (array $module) => $module['items'] ?? [])
            ->pluck('route')
            ->all();
    }

    /**
     * @param  list<string>  $replies
     */
    private function scriptImap(array $replies): void
    {
        $transport = new ScriptedImapTransport($replies);
        app(ImapMailboxStore::class)->connectUsing(
            fn (ImapConfig $config) => new ImapClient($transport)
        );
    }
}
