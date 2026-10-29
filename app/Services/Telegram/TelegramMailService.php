<?php

namespace App\Services\Telegram;

use App\Models\ContactMessage;
use App\Models\User;
use App\Services\CrmMailboxService;
use App\Services\MailboxStore;
use App\Support\MailDate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class TelegramMailService
{
    private const DRAFT_TTL = 1800;

    public function __construct(
        private CrmMailboxService $mailbox,
        private MailboxStore $store,
    ) {}

    public function usage(): string
    {
        return implode("\n", [
            '✉️ <b>Correio</b>',
            '',
            '<code>/correio</code> — últimas da caixa de entrada',
            '<code>/email dest@x.com | assunto | texto</code>',
            '<code>/email dest@x.com | assunto</code> — depois envia só o texto',
            '<code>/mensagem responder ID | texto</code>',
            '<code>/mensagem responder ID</code> — depois envia só o texto',
            '<code>/cancelar</code> — aborta a resposta em curso',
        ]);
    }

    public function inboxDigest(User $admin): string
    {
        $this->assertAdmin($admin);

        if (! $this->mailbox->isReady()) {
            return "A conta IMAP ainda não está ligada.\nPodes enviar na mesma com:\n<code>/email dest@x.com | assunto | texto</code>";
        }

        try {
            $items = $this->store->recentInbox(8);
        } catch (Throwable $e) {
            return 'Não consegui ler a INBOX: '.$this->escape($e->getMessage());
        }

        $identity = $this->mailbox->identity();
        $lines = [
            '📥 <b>Caixa de entrada</b>',
            $this->escape((string) ($identity['address'] ?: 'conta')),
            '',
        ];

        if ($items === []) {
            $lines[] = 'A INBOX está vazia.';
        } else {
            foreach ($items as $item) {
                $from = $this->escape(Str::limit((string) ($item['from'] ?? ''), 40));
                $subject = $this->escape(Str::limit((string) ($item['subject'] ?? '(sem assunto)'), 50));
                $when = $this->escape(MailDate::compact((string) ($item['date'] ?? '')));
                $lines[] = "• {$from} · {$subject}".($when !== '' ? " <i>{$when}</i>" : '');
            }
        }

        $lines[] = '';
        $lines[] = 'Enviar: <code>/email dest@x.com | assunto | texto</code>';

        return implode("\n", $lines);
    }

    public function sendCompose(User $admin, string $to, string $subject, string $body): string
    {
        $this->assertAdmin($admin);

        $to = trim($to);
        $subject = trim($subject);
        $body = trim($body);

        if ($to === '' || CrmMailboxService::extractAddresses($to) === []) {
            return 'Indica um destinatário válido.';
        }
        if ($subject === '') {
            return 'Indica o assunto.';
        }
        if ($body === '') {
            return 'A mensagem está vazia.';
        }

        try {
            $folder = $this->mailbox->send($to, $subject, $body, $admin);
        } catch (Throwable $e) {
            return '❌ Falha ao enviar: '.$this->escape($e->getMessage());
        }

        $note = is_string($folder) && $folder !== '' ? ' Cópia em '.$this->escape($folder).'.' : '';

        return '✔️ Enviado para <b>'.$this->escape($to).'</b> — '.$this->escape($subject).$note;
    }

    public function replyToContact(User $admin, ContactMessage $message, string $body): string
    {
        $this->assertAdmin($admin);

        $body = trim($body);
        if ($body === '') {
            return 'A resposta está vazia.';
        }

        $subject = preg_match('/^re:\s/i', (string) $message->subject) === 1
            ? (string) $message->subject
            : 'Re: '.$message->subject;

        try {
            $folder = $this->mailbox->send((string) $message->email, $subject, $body, $admin);
        } catch (Throwable $e) {
            return '❌ Falha ao responder: '.$this->escape($e->getMessage());
        }

        $message->markAsRead();
        $note = is_string($folder) && $folder !== '' ? ' Cópia em '.$this->escape($folder).'.' : '';

        return '✔️ Resposta enviada a <b>'.$this->escape($message->name).'</b> &lt;'.$this->escape((string) $message->email).'&gt;'.$note;
    }

    /**
     * @param  array{type: string, to?: string, subject?: string, id?: int}  $draft
     */
    public function rememberDraft(string $chatId, array $draft): void
    {
        Cache::put($this->draftKey($chatId), $draft, self::DRAFT_TTL);
    }

    /** @return array{type: string, to?: string, subject?: string, id?: int}|null */
    public function peekDraft(string $chatId): ?array
    {
        $draft = Cache::get($this->draftKey($chatId));

        return is_array($draft) ? $draft : null;
    }

    public function forgetDraft(string $chatId): void
    {
        Cache::forget($this->draftKey($chatId));
    }

    public function completeDraft(User $admin, string $chatId, string $text): string
    {
        $draft = $this->peekDraft($chatId);
        if ($draft === null) {
            return 'Não há rascunho de e-mail em curso.';
        }

        $type = (string) ($draft['type'] ?? '');

        if ($type === 'contact') {
            $message = ContactMessage::query()->find((int) ($draft['id'] ?? 0));
            if (! $message) {
                $this->forgetDraft($chatId);

                return 'Mensagem do site não encontrada.';
            }

            $reply = $this->replyToContact($admin, $message, $text);
            if (str_starts_with($reply, '✔️')) {
                $this->forgetDraft($chatId);
            }

            return $reply;
        }

        if ($type === 'compose') {
            $to = (string) ($draft['to'] ?? '');
            $subject = trim((string) ($draft['subject'] ?? ''));
            $body = trim($text);

            if ($subject === '' && str_contains($body, '|')) {
                [$subject, $body] = array_map('trim', explode('|', $body, 2));
            }

            if ($subject === '') {
                $subject = 'Mensagem BURI-TI';
            }

            $reply = $this->sendCompose($admin, $to, $subject, $body);
            if (str_starts_with($reply, '✔️')) {
                $this->forgetDraft($chatId);
            }

            return $reply;
        }

        $this->forgetDraft($chatId);

        return 'Rascunho inválido. Envie /email de novo.';
    }

    public function startContactReply(User $admin, string $chatId, ContactMessage $message): string
    {
        $this->assertAdmin($admin);
        $this->rememberDraft($chatId, ['type' => 'contact', 'id' => $message->id]);

        return implode("\n", [
            "✉️ Resposta a <b>#{$message->id}</b> {$this->escape($message->name)}",
            'Para: '.$this->escape((string) $message->email),
            'Assunto: '.$this->escape('Re: '.$message->subject),
            '',
            'Envie o texto da mensagem agora, ou <code>/cancelar</code>.',
        ]);
    }

    public function startCompose(User $admin, string $chatId, string $to, string $subject): string
    {
        $this->assertAdmin($admin);
        $this->rememberDraft($chatId, [
            'type' => 'compose',
            'to' => $to,
            'subject' => $subject,
        ]);

        return implode("\n", [
            '✉️ Rascunho para <b>'.$this->escape($to).'</b>',
            'Assunto: '.$this->escape($subject !== '' ? $subject : '(defina no texto: assunto | corpo)'),
            '',
            'Envie o texto agora, ou <code>/cancelar</code>.',
        ]);
    }

    private function draftKey(string $chatId): string
    {
        return 'telegram:mail-draft:'.$chatId;
    }

    private function assertAdmin(User $admin): void
    {
        if (! $admin->can('manageMail')) {
            throw new RuntimeException('O serviço de e-mail é exclusivo do admin.');
        }
    }

    private function escape(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
