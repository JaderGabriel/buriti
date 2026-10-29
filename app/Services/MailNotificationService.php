<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Models\User;
use App\Services\Telegram\TelegramApiClient;
use App\Support\MailHtml;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class MailNotificationService
{
    public function __construct(
        private TelegramApiClient $telegram,
        private SettingService $settings,
        private UserNotificationService $inbox,
    ) {}

    public function notifyOutgoing(
        string $to,
        string $subject,
        string $body,
        bool $reply = false,
        ?string $cc = null,
        ?string $folder = null,
    ): void {
        $summary = self::summarize($body);
        $title = $reply ? 'E-mail respondido' : 'E-mail enviado';
        $url = $this->mailboxUrl($folder);
        $lines = array_filter([
            $reply ? '↩️ <b>E-mail respondido</b>' : '📤 <b>E-mail enviado</b>',
            '',
            '<b>Para:</b> '.$this->escape($to),
            filled($cc) ? '<b>Cc:</b> '.$this->escape((string) $cc) : null,
            '<b>Assunto:</b> '.$this->escape($subject !== '' ? $subject : '(sem assunto)'),
            $summary !== '' ? '' : null,
            $summary !== '' ? '<i>'.$this->escape($summary).'</i>' : null,
            '',
            '🔗 <a href="'.$this->escape($url).'">Abrir no correio</a>',
        ], fn ($line) => $line !== null);

        $this->pushTelegram(implode("\n", $lines));

        if ($reply) {
            $this->pushInbox(
                $title,
                $this->inboxLine($to, $subject, $summary),
                $url,
            );
        }
    }

    /**
     * @param  array{from: string, subject: string, snippet: string, folder?: string, uid?: int}  $message
     */
    public function notifyIncoming(array $message): void
    {
        $from = (string) ($message['from'] ?? '');
        $subject = (string) ($message['subject'] ?? '');
        $summary = self::summarize((string) ($message['snippet'] ?? ''));
        $folder = (string) ($message['folder'] ?? 'INBOX');
        $uid = (int) ($message['uid'] ?? 0);
        $url = $this->mailboxUrl($folder, $uid > 0 ? $uid : null);
        $title = 'E-mail recebido';
        $lines = array_filter([
            '📥 <b>E-mail recebido</b>',
            '',
            '<b>De:</b> '.$this->escape($from !== '' ? $from : '(remetente desconhecido)'),
            '<b>Assunto:</b> '.$this->escape($subject !== '' ? $subject : '(sem assunto)'),
            $summary !== '' ? '' : null,
            $summary !== '' ? '<i>'.$this->escape($summary).'</i>' : null,
            '',
            '🔗 <a href="'.$this->escape($url).'">Abrir no correio</a>',
        ], fn ($line) => $line !== null);

        $this->pushTelegram(implode("\n", $lines));
        $this->pushInbox(
            $title,
            $this->inboxLine($from, $subject, $summary),
            $url,
        );
    }

    public static function summarize(string $htmlOrText, int $limit = 280): string
    {
        $text = MailHtml::toText($htmlOrText);
        if ($text === '') {
            $text = trim(html_entity_decode(strip_tags($htmlOrText), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;
        $text = trim($text);

        return $text === '' ? '' : Str::limit($text, $limit, '…');
    }

    private function pushInbox(string $title, string $body, string $url): void
    {
        try {
            $this->inbox->notifyAdmins(NotificationType::Mail, $title, $body !== '' ? $body : null, $url);
        } catch (Throwable $e) {
            Log::warning('Falha ao registrar notificação de e-mail', [
                'title' => $title,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function pushTelegram(string $html): void
    {
        if (! $this->telegram->configured()) {
            return;
        }

        foreach ($this->chatIds() as $chatId) {
            try {
                $this->telegram->sendMessage($chatId, $html);
            } catch (Throwable $e) {
                Log::warning('Falha ao notificar Telegram sobre e-mail', [
                    'chat_id' => $chatId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /** @return list<string> */
    private function chatIds(): array
    {
        $ids = User::query()
            ->where('is_admin', true)
            ->where('is_active', true)
            ->whereNotNull('telegram_chat_id')
            ->pluck('telegram_chat_id')
            ->map(fn ($id) => trim((string) $id))
            ->filter()
            ->values();

        $notify = trim((string) ($this->settings->get('telegram_notify_chat_id') ?? ''));
        if ($notify !== '') {
            $ids->push($notify);
        }

        return $ids->unique()->values()->all();
    }

    private function mailboxUrl(string $folder = 'INBOX', ?int $uid = null): string
    {
        return route('admin.mailbox.index', array_filter([
            'pasta' => $folder !== '' ? $folder : 'INBOX',
            'uid' => $uid,
        ]));
    }

    private function inboxLine(string $peer, string $subject, string $summary): string
    {
        return implode(' · ', array_filter([
            $peer !== '' ? $peer : null,
            $subject !== '' ? $subject : '(sem assunto)',
            $summary !== '' ? $summary : null,
        ]));
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
