<?php

namespace Tests\Feature\Admin;

use App\Enums\NotificationType;
use App\Mail\CrmOutgoingMail;
use App\Mail\ImapClient;
use App\Mail\ImapConfig;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\CrmMailboxService;
use App\Services\ImapMailboxStore;
use App\Services\MailInboxPollService;
use App\Services\MailNotificationService;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Support\ScriptedImapTransport;
use Tests\TestCase;

class MailNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create([
            'telegram_chat_id' => '424242',
        ]);
        $this->fakeTelegramApi();
        app(SettingService::class)->putMany([
            'mail_mailer' => 'smtp',
            'mail_host' => 'mail.exemplo.com',
            'mail_username' => 'contato@exemplo.com',
            'mail_from_name' => 'BURI-TI',
        ]);
        app(SettingService::class)->putSecret('mail_password', 'segredo-imap');
        Cache::flush();
    }

    public function test_sent_mail_posts_telegram_summary_without_inbox_item(): void
    {
        $this->scriptArchiveImap();
        Mail::fake();

        $this->actingAs($this->admin)
            ->post(route('admin.mailbox.send'), [
                'to' => 'ana@cliente.com',
                'subject' => 'Proposta comercial',
                'body' => '<p>Segue o briefing do projeto de consultoria.</p>',
                'signature' => '0',
            ])
            ->assertRedirect();

        Mail::assertSent(CrmOutgoingMail::class);
        $this->assertTelegramSaw('E-mail enviado', 'ana@cliente.com', 'Proposta comercial', 'briefing do projeto');
        $this->assertDatabaseMissing('user_notifications', [
            'type' => NotificationType::Mail->value,
        ]);
    }

    public function test_replied_mail_posts_telegram_summary_and_inbox_item(): void
    {
        $this->scriptArchiveImap();
        Mail::fake();

        app(CrmMailboxService::class)->send(
            'ana@cliente.com',
            'Re: Proposta',
            '<p>Aceitamos o prazo combinado na segunda.</p>',
            $this->admin,
            withSignature: false,
            isReply: true,
        );

        $this->assertTelegramSaw('E-mail respondido', 'ana@cliente.com', 'Re: Proposta', 'prazo combinado');
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $this->admin->id,
            'type' => NotificationType::Mail->value,
            'title' => 'E-mail respondido',
        ]);
        $notification = UserNotification::query()->first();
        $this->assertNotNull($notification);
        $this->assertStringContainsString('prazo combinado', (string) $notification->body);
        $this->assertStringContainsString('/correio', (string) $notification->url);
    }

    public function test_inbox_poll_seeds_cursor_without_notifying_backlog(): void
    {
        $this->scriptImap([
            '* 11 EXISTS',
            '* OK [UIDVALIDITY 7]',
            '* OK [UIDNEXT 12]',
            'A1 OK SELECT completed',
            '* BYE',
            'A2 OK LOGOUT completed',
        ]);

        $result = app(MailInboxPollService::class)->poll();

        $this->assertSame(0, $result['notified']);
        $this->assertSame(11, $result['seeded']);
        $this->assertSame(0, Http::recorded()->count());
        $this->assertDatabaseMissing('user_notifications', [
            'type' => NotificationType::Mail->value,
        ]);
    }

    public function test_inbox_poll_notifies_new_message_on_telegram_and_inbox(): void
    {
        Cache::forever(MailInboxPollService::CURSOR_KEY, [
            'uidvalidity' => 7,
            'uid' => 4,
        ]);
        $raw = "From: Ada <ada@cliente.com>\r\nSubject: Preciso de proposta\r\n\r\nSegue o briefing do projeto.";
        $this->scriptInboxUid(5, $raw);

        $result = app(MailInboxPollService::class)->poll();

        $this->assertSame(1, $result['notified']);
        $this->assertTelegramSaw('E-mail recebido', 'ada@cliente.com', 'Preciso de proposta', 'briefing do projeto');
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $this->admin->id,
            'type' => NotificationType::Mail->value,
            'title' => 'E-mail recebido',
        ]);
    }

    public function test_inbox_poll_does_not_notify_same_uid_twice(): void
    {
        $raw = "From: Ada <ada@cliente.com>\r\nMessage-ID: <once@exemplo.com>\r\nSubject: Preciso de proposta\r\n\r\nSegue o briefing do projeto.";
        Cache::forever(MailInboxPollService::CURSOR_KEY, [
            'uidvalidity' => 7,
            'uid' => 4,
        ]);
        $this->scriptInboxUid(5, $raw);

        $first = app(MailInboxPollService::class)->poll();
        $this->assertSame(1, $first['notified']);

        Cache::forever(MailInboxPollService::CURSOR_KEY, [
            'uidvalidity' => 7,
            'uid' => 4,
        ]);
        $this->scriptInboxUid(5, $raw);

        $second = app(MailInboxPollService::class)->poll();
        $this->assertSame(0, $second['notified']);
        $this->assertSame(1, $second['skipped']);
        $this->assertSame(1, $this->telegramSentCount());
        $this->assertSame(1, UserNotification::query()->where('type', NotificationType::Mail->value)->count());
    }

    public function test_inbox_poll_skips_copy_with_the_same_message_id(): void
    {
        $rawA = "From: Ada <ada@cliente.com>\r\nMessage-ID: <dup@exemplo.com>\r\nSubject: Preciso de proposta\r\n\r\nPrimeira copia.";
        $rawB = "From: Ada <ada@cliente.com>\r\nMessage-ID: <dup@exemplo.com>\r\nSubject: Preciso de proposta\r\n\r\nSegunda copia.";
        Cache::forever(MailInboxPollService::CURSOR_KEY, [
            'uidvalidity' => 7,
            'uid' => 4,
        ]);
        $this->scriptImap([
            '* 6 EXISTS',
            '* OK [UIDVALIDITY 7]',
            '* OK [UIDNEXT 7]',
            'A1 OK SELECT completed',
            '* 6 EXISTS',
            'A2 OK SELECT completed',
            '* SEARCH 5 6',
            'A3 OK SEARCH completed',
            '* 6 EXISTS',
            'A4 OK SELECT completed',
            '* 1 FETCH (UID 5 FLAGS () BODY.PEEK[] {'.strlen($rawA).'}',
            ')',
            'A5 OK Fetch completed',
            '* 6 EXISTS',
            'A6 OK SELECT completed',
            '* 1 FETCH (UID 6 FLAGS () BODY.PEEK[] {'.strlen($rawB).'}',
            ')',
            'A7 OK Fetch completed',
            '* BYE',
            'A8 OK LOGOUT completed',
        ], $rawA.$rawB);

        $result = app(MailInboxPollService::class)->poll();

        $this->assertSame(1, $result['notified']);
        $this->assertSame(1, $result['skipped']);
        $this->assertSame(1, $this->telegramSentCount());
        $this->assertSame(1, UserNotification::query()->where('type', NotificationType::Mail->value)->count());
    }

    public function test_incoming_telegram_sends_once_when_notify_chat_matches_admin(): void
    {
        app(SettingService::class)->putMany([
            'telegram_notify_chat_id' => '424242',
        ]);

        app(MailNotificationService::class)->notifyIncoming([
            'from' => 'Ada <ada@cliente.com>',
            'subject' => 'Preciso de proposta',
            'snippet' => 'Segue o briefing do projeto.',
            'folder' => 'INBOX',
            'uid' => 8,
        ]);

        $this->assertSame(1, $this->telegramSentCount());
    }

    public function test_inbox_poll_skips_mail_from_own_address(): void
    {
        Cache::forever(MailInboxPollService::CURSOR_KEY, [
            'uidvalidity' => 7,
            'uid' => 4,
        ]);
        $raw = "From: BURI-TI <contato@exemplo.com>\r\nSubject: Cópia enviada\r\n\r\nTexto próprio.";
        $this->scriptInboxUid(5, $raw);

        $result = app(MailInboxPollService::class)->poll();

        $this->assertSame(0, $result['notified']);
        $this->assertSame(1, $result['skipped']);
        $this->assertSame(0, Http::recorded()->count());
        $this->assertDatabaseMissing('user_notifications', [
            'type' => NotificationType::Mail->value,
        ]);
    }

    public function test_summarize_strips_html_to_plain_preview(): void
    {
        $this->assertSame(
            'Olá mundo. Prazo ok.',
            MailNotificationService::summarize('<p>Olá <strong>mundo</strong>.</p><p>Prazo ok.</p>'),
        );
    }

    public function test_summarize_omits_signature_from_inbox_line(): void
    {
        $this->assertSame(
            'Olá.',
            MailNotificationService::summarize(
                '<p>Olá.</p><div class="gmail_signature">Ada Lovelace<br>Cliente SA</div>'
            ),
        );
    }

    public function test_incoming_telegram_separates_body_from_signature(): void
    {
        $html = '<p>Segue o briefing do projeto.</p>'
            .'<div class="gmail_signature"><p>Ada Lovelace</p><p>Cliente SA</p>'
            .'<img src="cid:mail-phone" alt="Telefone"><img src="cid:mail-whatsapp" alt="WhatsApp"></div>'
            .'<p>Esta mensagem e quaisquer anexos são de propriedade da BURI-TI.</p>';

        app(MailNotificationService::class)->notifyIncoming([
            'from' => 'Ada <ada@cliente.com>',
            'subject' => 'Preciso de proposta',
            'snippet' => $html,
            'folder' => 'INBOX',
            'uid' => 9,
        ]);

        Http::assertSent(function ($request) {
            $text = (string) ($request->data()['text'] ?? '');
            $bodyStart = strpos($text, '<blockquote>');
            $bodyEnd = strpos($text, '</blockquote>');
            $this->assertNotFalse($bodyStart);
            $this->assertNotFalse($bodyEnd);
            $quoted = substr($text, $bodyStart, $bodyEnd - $bodyStart);

            return str_contains($request->url(), 'sendMessage')
                && str_contains($text, 'E-mail recebido')
                && str_contains($quoted, 'briefing do projeto')
                && ! str_contains($quoted, 'Ada Lovelace')
                && ! str_contains($quoted, 'Esta mensagem e quaisquer anexos')
                && str_contains($text, "—\n<i>Ada Lovelace\nCliente SA</i>")
                && ! str_contains($text, 'Telefone');
        });
    }

    private function assertTelegramSaw(string $title, string $peer, string $subject, string $snippet): void
    {
        Http::assertSent(function ($request) use ($title, $peer, $subject, $snippet) {
            $text = (string) ($request->data()['text'] ?? '');

            return str_contains($request->url(), 'sendMessage')
                && (string) ($request->data()['chat_id'] ?? '') === '424242'
                && str_contains($text, $title)
                && str_contains($text, $peer)
                && str_contains($text, $subject)
                && str_contains($text, $snippet);
        });
    }

    private function scriptArchiveImap(): void
    {
        $this->scriptImap([
            '* LIST (\\HasNoChildren \\Sent) "/" Sent',
            'A1 OK LIST completed',
            '+',
            'A2 OK APPEND completed',
            '* BYE',
            'A3 OK LOGOUT completed',
        ]);
    }

    private function scriptInboxUid(int $uid, string $raw): void
    {
        $this->scriptImap([
            '* '.$uid.' EXISTS',
            '* OK [UIDVALIDITY 7]',
            '* OK [UIDNEXT '.($uid + 1).']',
            'A1 OK SELECT completed',
            '* '.$uid.' EXISTS',
            'A2 OK SELECT completed',
            '* SEARCH '.$uid,
            'A3 OK SEARCH completed',
            '* '.$uid.' EXISTS',
            'A4 OK SELECT completed',
            '* 1 FETCH (UID '.$uid.' FLAGS () BODY.PEEK[] {'.strlen($raw).'}',
            ')',
            'A5 OK Fetch completed',
            '* BYE',
            'A6 OK LOGOUT completed',
        ], $raw);
    }

    private function telegramSentCount(): int
    {
        return collect(Http::recorded())->filter(
            fn ($pair) => str_contains($pair[0]->url(), 'sendMessage')
        )->count();
    }

    /**
     * @param  list<string>  $replies
     */
    private function scriptImap(array $replies, string $bytes = ''): void
    {
        $transport = new ScriptedImapTransport($replies, $bytes);
        app(ImapMailboxStore::class)->connectUsing(
            fn (ImapConfig $config) => new ImapClient($transport)
        );
    }
}
