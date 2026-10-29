<?php

namespace Tests\Feature\Admin;

use App\Mail\CrmOutgoingMail;
use App\Mail\ImapClient;
use App\Mail\ImapConfig;
use App\Models\Contact;
use App\Models\ContactMessage;
use App\Models\IdeaNote;
use App\Models\Jirau;
use App\Models\MailLabel;
use App\Models\MailRecord;
use App\Models\Project;
use App\Models\User;
use App\Services\ImapMailboxStore;
use App\Services\SettingService;
use App\Support\AdminNavigation;
use App\Support\MailHtml;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
        app(SettingService::class)->putMany([
            'contact_email' => 'contato@buriti.dev.br',
            'contact_phone' => '+55 11 99999-0000',
            'contact_whatsapp' => 'buriti_demo',
            'telegram_url' => 'https://t.me/buriti_demo',
            'telegram_handle' => '@buriti_demo',
        ]);
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
            ->assertDontSee('>E-mail</span>', false)
            ->assertDontSee('>MAIL</span>', false)
            ->assertDontSee(route('admin.mailbox.index'), false)
            ->assertDontSee(route('admin.settings.mail'), false);

        foreach ([
            ['get', 'admin.mailbox.index'],
            ['get', 'admin.mailbox.compose'],
            ['get', 'admin.mailbox.contacts'],
            ['get', 'admin.mailbox.attachment'],
            ['post', 'admin.mailbox.send'],
            ['post', 'admin.mailbox.destroy'],
            ['post', 'admin.mailbox.move'],
            ['post', 'admin.mailbox.seen'],
            ['post', 'admin.mailbox.folders.store'],
            ['put', 'admin.mailbox.signature'],
            ['post', 'admin.mailbox.bulk'],
            ['post', 'admin.mailbox.empty'],
            ['post', 'admin.mailbox.marker'],
            ['post', 'admin.mailbox.label'],
            ['post', 'admin.mailbox.link'],
            ['delete', 'admin.mailbox.unlink'],
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
            ->assertSee('Comercial · E-mail', false)
            ->assertSee('Sistema → MAIL', false);
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
            ->assertSee('aria-label="Escrever"', false)
            ->assertSee('Selecionar tudo', false)
            ->assertSee('aria-label="Filtro"', false)
            ->assertSee('Não lidas', false)
            ->assertSee('Com estrela', false)
            ->assertSee('contato@exemplo.com', false)
            ->assertSee('Nenhuma mensagem nesta pasta', false)
            ->assertSee('data-mail-search', false)
            ->assertSee('name="buscar"', false)
            ->assertSee('id="mail-folder-search"', false)
            ->assertSee('Remetente, contato, destinatário ou assunto', false);
    }

    public function test_mailbox_filter_lists_unread_and_empty_folder_actions(): void
    {
        $this->configureMailbox();
        $this->scriptImap([
            '* LIST (\\HasNoChildren) "/" INBOX',
            '* LIST (\\HasNoChildren \\Trash) "/" Trash',
            '* LIST (\\HasNoChildren \\Junk) "/" Spam',
            'A1 OK LIST completed',
            '* STATUS "INBOX" (MESSAGES 0 UNSEEN 0)',
            'A2 OK STATUS completed',
            '* STATUS "Trash" (MESSAGES 4 UNSEEN 0)',
            'A3 OK STATUS completed',
            '* STATUS "Spam" (MESSAGES 2 UNSEEN 0)',
            'A4 OK STATUS completed',
            '* 0 EXISTS',
            'A5 OK SELECT completed',
            '* SEARCH',
            'A6 OK SEARCH completed',
            '* BYE',
            'A7 OK LOGOUT completed',
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.mailbox.index', ['pasta' => 'INBOX', 'filtro' => 'nao-lidas']))
            ->assertOk()
            ->assertSee('Nenhuma mensagem não lida.', false)
            ->assertSee('filtro=nao-lidas', false)
            ->assertSee('Esvaziar lixeira', false)
            ->assertSee('Esvaziar spam', false)
            ->assertDontSee('IMAP script esgotado', false);
    }

    public function test_admin_can_empty_trash(): void
    {
        $this->configureMailbox();
        $this->scriptImap([
            '* 4 EXISTS',
            'A1 OK SELECT completed',
            'A2 OK STORE completed',
            'A3 OK EXPUNGE completed',
            '* BYE',
            'A4 OK LOGOUT completed',
            '* LIST (\\HasNoChildren \\Trash) "/" Trash',
            'A1 OK LIST completed',
            '* STATUS "Trash" (MESSAGES 0 UNSEEN 0)',
            'A2 OK STATUS completed',
            '* 0 EXISTS',
            'A3 OK SELECT completed',
            '* BYE',
            'A4 OK LOGOUT completed',
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.mailbox.empty'), ['pasta' => 'Trash'])
            ->assertRedirect(route('admin.mailbox.index', ['pasta' => 'Trash']))
            ->assertSessionHas('success', 'Lixeira esvaziada.');
    }

    public function test_empty_folder_rejects_inbox(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.mailbox.empty'), ['pasta' => 'INBOX'])
            ->assertSessionHas('error', 'Só a Lixeira e o Spam podem ser esvaziados.');
    }

    public function test_mailbox_folder_search_ignores_admin_query_parameter(): void
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
            ->get(route('admin.mailbox.index', ['pasta' => 'INBOX', 'q' => 'proposta']))
            ->assertOk()
            ->assertSee('Nenhuma mensagem nesta pasta', false)
            ->assertDontSee('Nada encontrado para', false)
            ->assertDontSee('IMAP script esgotado', false);
    }

    public function test_mailbox_folder_search_filters_and_can_clear_to_full_listing(): void
    {
        $this->configureMailbox();
        $this->scriptImap([
            '* LIST (\\HasNoChildren) "/" INBOX',
            'A1 OK LIST completed',
            '* STATUS "INBOX" (MESSAGES 0 UNSEEN 0)',
            'A2 OK STATUS completed',
            '* 0 EXISTS',
            'A3 OK SELECT completed',
            '* SEARCH',
            'A4 OK SEARCH completed',
            '* BYE',
            'A5 OK LOGOUT completed',
        ]);

        $clearUrl = route('admin.mailbox.index', ['pasta' => 'INBOX']);

        $this->actingAs($this->admin)
            ->get(route('admin.mailbox.index', ['pasta' => 'INBOX', 'buscar' => 'proposta']))
            ->assertOk()
            ->assertSee('Nada encontrado para “proposta”.', false)
            ->assertSee('Mostrar todas as mensagens', false)
            ->assertSee('Limpar busca desta pasta', false)
            ->assertSee($clearUrl, false)
            ->assertDontSee('Nenhuma mensagem nesta pasta', false);
    }

    public function test_admin_can_send_from_mailbox_identity(): void
    {
        $this->configureMailbox();
        $this->scriptImap([
            '* LIST (\\HasNoChildren \\Sent) "/" Sent',
            'A1 OK LIST completed',
            '+',
            'A2 OK APPEND completed',
            '* BYE',
            'A3 OK LOGOUT completed',
        ]);
        Mail::fake();

        $this->actingAs($this->admin)
            ->post(route('admin.mailbox.send'), [
                'to' => 'qa@exemplo.com',
                'subject' => 'Proposta',
                'body' => 'Segue o texto.',
                'signature' => '1',
            ])
            ->assertRedirect(route('admin.mailbox.index', ['pasta' => 'Sent']))
            ->assertSessionHas('success');

        Mail::assertSent(CrmOutgoingMail::class, function (CrmOutgoingMail $mail) {
            $html = $mail->render();

            return $mail->hasTo('qa@exemplo.com')
                && $mail->envelope()->subject === 'Proposta'
                && $mail->hasFrom('contato@exemplo.com')
                && str_contains($html, 'Segue o texto.')
                && str_contains($html, 'Lei nº 13.709/2018')
                && str_contains($html, 'cid:logo-buriti')
                && str_contains($html, 'Jader Gabriel')
                && str_contains($html, 'font-style:italic')
                && str_contains($html, 'BURI-TI - Consultoria &amp; Software')
                && str_contains($html, 'cid:mail-phone')
                && str_contains($html, 'cid:mail-whatsapp')
                && str_contains($html, 'cid:mail-telegram')
                && str_contains($html, '/ligar')
                && str_contains($html, 'wa.me/5511999990000')
                && str_contains($html, 't.me/buriti_demo')
                && str_contains($html, 'alt="Telefone"')
                && ! str_contains($html, '99999-0000')
                && ! str_contains($html, 'background:#0a1628');
        });
    }

    public function test_gmail_send_still_appends_a_copy_to_sent(): void
    {
        $settings = app(SettingService::class);
        $settings->putMany([
            'mail_mailer' => 'smtp',
            'mail_host' => 'smtp.gmail.com',
            'mail_username' => 'qa@gmail.com',
            'mail_from_name' => 'BURI-TI',
        ]);
        $settings->putSecret('mail_password', 'app-pass');
        $this->scriptImap([
            '* LIST (\\HasNoChildren \\Sent) "/" "[Gmail]/Sent Mail"',
            'A1 OK LIST completed',
            '+',
            'A2 OK APPEND completed',
            '* BYE',
            'A3 OK LOGOUT completed',
        ]);
        Mail::fake();

        $this->actingAs($this->admin)
            ->post(route('admin.mailbox.send'), [
                'to' => 'qa@exemplo.com',
                'subject' => 'Proposta Gmail',
                'body' => 'Segue o texto.',
                'signature' => '1',
            ])
            ->assertRedirect(route('admin.mailbox.index', ['pasta' => '[Gmail]/Sent Mail']))
            ->assertSessionHas('success');
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
            ->assertSee('Cco', false)
            ->assertSee('Jader Gabriel', false)
            ->assertSee('BURI-TI - Consultoria &amp; Software', false)
            ->assertSee('font-style:italic', false)
            ->assertSee('font-weight:700', false)
            ->assertSee('images/mail/whatsapp.png', false)
            ->assertSee('images/mail/telegram.png', false)
            ->assertSee('aviso LGPD', false)
            ->assertSee('Anexos', false)
            ->assertSee('10 MB', false)
            ->assertSee('name="attachments[]"', false)
            ->assertSee('mail-editor', false)
            ->assertSee('mail-gallery', false)
            ->assertSee('aria-label="Formatação"', false)
            ->assertSee('Imagem no texto', false)
            ->assertSee('Remover anexo', false)
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('mailCompose', false)
            ->assertSee('mailRecipientField', false)
            ->assertSee('data-contacts-url="', false)
            ->assertSee('correio/contatos', false)
            ->assertSee('data-to="ana@exemplo.com"', false)
            ->assertSee('data-mail-picker="to"', false)
            ->assertSee('data-mail-picker="cc"', false)
            ->assertSee('data-mail-picker="bcc"', false)
            ->assertSee('data-seed-email="ana@exemplo.com"', false)
            ->assertSee('data-mail-suggest', false)
            ->assertSee('data-mail-format="bold"', false)
            ->assertSee('data-mail-format="italic"', false)
            ->assertSee('mail-chips', false)
            ->assertDontSee('x-teleport="body"', false)
            ->assertSee('mail-compose-pane', false)
            ->assertSee('mail-compose__fields', false)
            ->assertSee('mail-compose__scroll', false)
            ->assertSee('mail-compose__field', false)
            ->assertSee('aria-label="Enviar"', false)
            ->assertSee('aria-label="Rascunho"', false)
            ->assertSee('Nome, empresa ou e-mail', false)
            ->assertSee('role="combobox"', false)
            ->assertSee('Contatos', false)
            ->assertSee('Buscando contatos', false)
            ->assertSee('Nenhum contato com esse nome', false)
            ->assertSee('data-mail-acao="marker"', false)
            ->assertSee('data-mail-acao="label"', false)
            ->assertDontSee('mail-bulk__go">OK', false)
            ->assertDontSee('<details class="mail-signature" open>', false);
    }

    public function test_empty_compose_does_not_show_attachment_loading(): void
    {
        $html = $this->actingAs($this->admin)
            ->get(route('admin.mailbox.compose'))
            ->assertOk()
            ->assertSee('x-if="items.length > 0"', false)
            ->assertSee('x-if="loadingCount > 0"', false)
            ->assertSee('data-mail-attach-loading', false)
            ->getContent();

        $this->assertSame(1, substr_count($html, 'class="mail-gallery"'));
        $this->assertSame(1, substr_count($html, 'mail-gallery__progress-bar'));
        $this->assertSame(1, substr_count($html, 'data-mail-attach-loading'));
        $this->assertMatchesRegularExpression(
            '/<template[^>]*x-if="items\.length > 0"[^>]*>\s*<div class="mail-gallery">/s',
            $html,
        );
        $this->assertMatchesRegularExpression(
            '/<template[^>]*x-if="loadingCount > 0"[^>]*>\s*<p class="mail-compose__attach-hint" data-mail-attach-loading>/s',
            $html,
        );

        $galleryAt = strpos($html, 'class="mail-gallery"');
        $galleryTemplateAt = strpos($html, 'x-if="items.length > 0"');
        $loadingAt = strpos($html, 'data-mail-attach-loading');
        $loadingTemplateAt = strpos($html, 'x-if="loadingCount > 0"');

        $this->assertNotFalse($galleryAt);
        $this->assertNotFalse($galleryTemplateAt);
        $this->assertNotFalse($loadingAt);
        $this->assertNotFalse($loadingTemplateAt);
        $this->assertLessThan($galleryAt, $galleryTemplateAt);
        $this->assertLessThan($loadingAt, $loadingTemplateAt);
        $this->assertStringContainsString('Até 5 arquivos', $html);
    }

    public function test_admin_can_search_system_contacts_for_compose(): void
    {
        Contact::factory()->create([
            'name' => 'Zelia Costa',
            'email' => 'zelia@empresa.com',
            'company' => 'Costa & Filhos',
        ]);
        Contact::factory()->create([
            'name' => 'Sem Correio',
            'email' => '',
        ]);

        $this->actingAs($this->admin)
            ->getJson(route('admin.mailbox.contacts', ['q' => 'Zelia']))
            ->assertOk()
            ->assertJsonCount(1, 'contacts')
            ->assertJsonPath('contacts.0.email', 'zelia@empresa.com')
            ->assertJsonPath('contacts.0.name', 'Zelia Costa')
            ->assertJsonPath('contacts.0.company', 'Costa & Filhos');

        $this->actingAs($this->admin)
            ->getJson(route('admin.mailbox.contacts', ['q' => 'Z']))
            ->assertOk()
            ->assertJsonPath('contacts.0.email', 'zelia@empresa.com');

        $this->actingAs($this->admin)
            ->getJson(route('admin.mailbox.contacts'))
            ->assertOk()
            ->assertJsonPath('contacts.0.email', 'zelia@empresa.com');
    }

    public function test_mailbox_folder_search_also_matches_contact_email(): void
    {
        Contact::factory()->create([
            'name' => 'Zelia Costa',
            'email' => 'zelia@empresa.com',
        ]);
        $this->configureMailbox();
        $this->scriptImap([
            '* LIST (\\HasNoChildren) "/" INBOX',
            'A1 OK LIST completed',
            '* STATUS "INBOX" (MESSAGES 0 UNSEEN 0)',
            'A2 OK STATUS completed',
            '* 0 EXISTS',
            'A3 OK SELECT completed',
            '* SEARCH',
            'A4 OK SEARCH completed',
            '* SEARCH',
            'A5 OK SEARCH completed',
            '* BYE',
            'A6 OK LOGOUT completed',
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.mailbox.index', ['pasta' => 'INBOX', 'buscar' => 'Zelia']))
            ->assertOk()
            ->assertSee('Nada encontrado para “Zelia”.', false)
            ->assertDontSee('IMAP script esgotado', false);
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
            ->get(route('admin.mailbox.compose', [
                'para' => $message->email,
                'assunto' => 'Re: '.$message->subject,
                'mensagem' => $message->id,
            ]))
            ->assertOk()
            ->assertSee('Resposta ao site', false)
            ->assertSee('data-to="carla@empresa.com"', false)
            ->assertSee('data-to-name="Carla Site"', false)
            ->assertSee('data-seed-email="carla@empresa.com"', false)
            ->assertSee('data-seed-name="Carla Site"', false)
            ->assertSee('data-mail-picker="to"', false)
            ->assertSee('Carla Site', false)
            ->assertSee('name="to"', false)
            ->assertSee('value="carla@empresa.com"', false)
            ->assertSee('carla@empresa.com', false)
            ->assertSee('Re: Orçamento institucional', false)
            ->assertSee('Preciso de um site novo.', false)
            ->assertSee('mail-quote-pane', false)
            ->assertSee('Mensagem original', false)
            ->assertSee('name="quote"', false);
    }

    public function test_send_appends_original_as_muted_quote(): void
    {
        $this->configureMailbox();
        $this->scriptImap([
            '* LIST (\\HasNoChildren \\Sent) "/" Sent',
            'A1 OK LIST completed',
            '+',
            'A2 OK APPEND completed',
            '* BYE',
            'A3 OK LOGOUT completed',
        ]);
        Mail::fake();

        $quote = MailHtml::quoteBlock('Em 01/01/2026, Carla escreveu:', 'Preciso de um site novo.');

        $this->actingAs($this->admin)
            ->post(route('admin.mailbox.send'), [
                'to' => 'carla@empresa.com',
                'subject' => 'Re: Orçamento institucional',
                'body' => '<p>Segue a proposta.</p>',
                'quote' => $quote,
                'signature' => '1',
            ])
            ->assertRedirect(route('admin.mailbox.index', ['pasta' => 'Sent']))
            ->assertSessionHas('success');

        Mail::assertSent(CrmOutgoingMail::class, function (CrmOutgoingMail $mail) {
            $html = $mail->render();

            return $mail->hasTo('carla@empresa.com')
                && str_contains($html, 'Segue a proposta.')
                && str_contains($html, 'Preciso de um site novo.')
                && str_contains($html, 'mail-quote')
                && strpos($html, 'Segue a proposta.') < strpos($html, 'Preciso de um site novo.');
        });
    }

    public function test_admin_can_send_with_bcc_and_mark_contact_message_read(): void
    {
        $this->configureMailbox();
        $this->scriptImap([
            '* LIST (\\HasNoChildren \\Sent) "/" Sent',
            'A1 OK LIST completed',
            '+',
            'A2 OK APPEND completed',
            '* BYE',
            'A3 OK LOGOUT completed',
        ]);
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
            ->assertRedirect(route('admin.mailbox.index', ['pasta' => 'Sent']))
            ->assertSessionHas('success');

        Mail::assertSent(CrmOutgoingMail::class, function (CrmOutgoingMail $mail) {
            return $mail->hasTo('carla@empresa.com')
                && $mail->hasBcc('copia@exemplo.com');
        });
        $this->assertNotNull($message->fresh()->read_at);
    }

    public function test_admin_can_send_with_attachment_under_10mb(): void
    {
        $this->configureMailbox();
        $this->scriptImap([
            '* LIST (\\HasNoChildren \\Sent) "/" Sent',
            'A1 OK LIST completed',
            '+',
            'A2 OK APPEND completed',
            '* BYE',
            'A3 OK LOGOUT completed',
        ]);
        Mail::fake();

        $file = UploadedFile::fake()->create('proposta.pdf', 200, 'application/pdf');

        $this->actingAs($this->admin)
            ->post(route('admin.mailbox.send'), [
                'to' => 'qa@exemplo.com',
                'subject' => 'Proposta com anexo',
                'body' => 'Segue o PDF.',
                'signature' => '1',
                'attachments' => [$file],
            ])
            ->assertRedirect(route('admin.mailbox.index', ['pasta' => 'Sent']))
            ->assertSessionHas('success');

        Mail::assertSent(CrmOutgoingMail::class, function (CrmOutgoingMail $mail) {
            return $mail->hasTo('qa@exemplo.com')
                && count($mail->files) === 1
                && $mail->files[0]['name'] === 'proposta.pdf';
        });
    }

    public function test_send_rejects_attachments_over_10mb(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.mailbox.send'), [
                'to' => 'qa@exemplo.com',
                'subject' => 'Pesado',
                'body' => 'Ficheiro grande.',
                'signature' => '1',
                'attachments' => [UploadedFile::fake()->create('pesado.pdf', 11000, 'application/pdf')],
            ])
            ->assertSessionHasErrors('attachments.0');
    }

    public function test_send_rejects_blank_html_body(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.mailbox.send'), [
                'to' => 'qa@exemplo.com',
                'subject' => 'Vazio',
                'body' => '<p><br></p>',
                'signature' => '1',
            ])
            ->assertSessionHasErrors('body');
    }

    public function test_admin_can_send_html_body_with_inline_image(): void
    {
        $this->configureMailbox();
        $this->scriptImap([
            '* LIST (\\HasNoChildren \\Sent) "/" Sent',
            'A1 OK LIST completed',
            '+',
            'A2 OK APPEND completed',
            '* BYE',
            'A3 OK LOGOUT completed',
        ]);
        Mail::fake();

        $image = UploadedFile::fake()->image('foto.png', 32, 32);

        $this->actingAs($this->admin)
            ->post(route('admin.mailbox.send'), [
                'to' => 'qa@exemplo.com',
                'subject' => 'Proposta com imagem',
                'body' => '<p>Olá <strong>Carla</strong></p><p><img src="cid:img-foto1" alt="foto"></p><script>alert(1)</script>',
                'signature' => '1',
                'inline' => ['img-foto1' => $image],
            ])
            ->assertRedirect(route('admin.mailbox.index', ['pasta' => 'Sent']))
            ->assertSessionHas('success');

        Mail::assertSent(CrmOutgoingMail::class, function (CrmOutgoingMail $mail) {
            $html = $mail->render();

            return $mail->hasTo('qa@exemplo.com')
                && str_contains($html, '<strong>Carla</strong>')
                && str_contains($html, 'cid:img-foto1')
                && ! str_contains(strtolower($html), '<script')
                && count($mail->inline) === 1
                && $mail->inline[0]['cid'] === 'img-foto1';
        });
    }

    public function test_admin_can_star_selected_messages(): void
    {
        $this->configureMailbox();
        $this->scriptImap([
            '* 2 EXISTS',
            'A1 OK SELECT completed',
            'A2 OK STORE completed',
            '* BYE',
            'A3 OK LOGOUT completed',
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
            ->post(route('admin.mailbox.bulk'), [
                'pasta' => 'INBOX',
                'uids' => [11, 12],
                'acao' => 'star',
            ])
            ->assertRedirect(route('admin.mailbox.index', ['pasta' => 'INBOX']))
            ->assertSessionHas('success');
    }

    public function test_admin_can_label_and_link_message_to_project_jirau_and_idea(): void
    {
        $this->configureMailbox();
        $project = Project::factory()->create(['name' => 'Portal interno', 'is_public' => true]);
        $jirau = Jirau::factory()->create(['name' => 'Quadro portal', 'project_id' => $project->id]);
        $idea = IdeaNote::factory()->create(['title' => 'Ideia do contrato', 'user_id' => $this->admin->id]);
        $label = MailLabel::query()->where('slug', 'comercial')->first();
        $this->assertNotNull($label);

        $payload = [
            'pasta' => 'INBOX',
            'uid' => 44,
            'message_id' => '<proposta@buriti>',
            'subject' => 'Proposta comercial',
            'from' => 'ana@cliente.com',
        ];

        $this->actingAs($this->admin)
            ->post(route('admin.mailbox.label'), $payload + ['label_id' => $label->id])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->actingAs($this->admin)
            ->post(route('admin.mailbox.marker'), $payload + ['marker' => 'acompanhar'])
            ->assertRedirect();

        $this->actingAs($this->admin)
            ->post(route('admin.mailbox.link'), $payload + ['kind' => 'project', 'linkable_id' => $project->id])
            ->assertRedirect();

        $this->actingAs($this->admin)
            ->post(route('admin.mailbox.link'), $payload + ['kind' => 'jirau', 'linkable_id' => $jirau->id])
            ->assertRedirect();

        $this->actingAs($this->admin)
            ->post(route('admin.mailbox.link'), $payload + ['kind' => 'idea', 'linkable_id' => $idea->id])
            ->assertRedirect();

        $record = MailRecord::query()->where('rfc_message_id', 'proposta@buriti')->first();
        $this->assertNotNull($record);
        $this->assertSame('acompanhar', $record->marker?->value);
        $this->assertTrue($record->labels->contains('id', $label->id));
        $this->assertSame(3, $record->links()->count());

        $this->actingAs($this->admin)
            ->get(route('admin.projects.edit', $project))
            ->assertOk()
            ->assertSee('E-mails ligados', false)
            ->assertSee('Proposta comercial', false)
            ->assertSee('não entram no portfólio público', false);

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('E-mails ligados', false)
            ->assertDontSee('CRM interno', false);
    }

    public function test_comercial_nav_lists_mailbox(): void
    {
        $this->assertTrue($this->admin->canManageMail());
        $this->assertContains('admin.mailbox.index', $this->navRoutes($this->admin));
        $this->assertContains('admin.settings.mail', $this->navRoutes($this->admin));

        $this->actingAs($this->admin)
            ->get(route('admin.mailbox.index'))
            ->assertOk()
            ->assertSee('>E-mail</span>', false)
            ->assertSee('>Mensagens</span>', false)
            ->assertSee('>MAIL</span>', false);
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
