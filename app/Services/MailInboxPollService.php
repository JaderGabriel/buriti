<?php

namespace App\Services;

use App\Mail\ImapClient;
use App\Mail\Rfc822Message;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class MailInboxPollService
{
    public const CURSOR_KEY = 'mail.inbox.notify.cursor';

    public const LOCK_KEY = 'mail.inbox.notify.lock';

    public const MAX_PER_RUN = 12;

    private const SEEN_TTL_SECONDS = 14 * 24 * 3600;

    public function __construct(
        private ImapMailboxStore $imap,
        private MailSettingsService $mail,
        private MailNotificationService $notifications,
    ) {}

    /**
     * @return array{notified: int, seeded: ?int, skipped: int}
     */
    public function poll(): array
    {
        $this->mail->apply();
        if (! $this->imap->isConfigured()) {
            return ['notified' => 0, 'seeded' => null, 'skipped' => 0];
        }

        if (! Cache::add(self::LOCK_KEY, 1, 120)) {
            return ['notified' => 0, 'seeded' => null, 'skipped' => 0];
        }

        try {
            return $this->imap->usingClient(fn (ImapClient $client) => $this->scanInbox($client));
        } catch (Throwable $e) {
            Log::warning('Falha ao varrer a INBOX para notificações', [
                'error' => $e->getMessage(),
            ]);

            return ['notified' => 0, 'seeded' => null, 'skipped' => 0];
        } finally {
            Cache::forget(self::LOCK_KEY);
        }
    }

    /**
     * @return array{notified: int, seeded: ?int, skipped: int}
     */
    private function scanInbox(ImapClient $client): array
    {
        $meta = $client->select('INBOX');
        $uidvalidity = (int) ($meta['uidvalidity'] ?? 0);
        $uidnext = (int) ($meta['uidnext'] ?? 0);
        $cursor = Cache::get(self::CURSOR_KEY);
        $sameBox = is_array($cursor)
            && (int) ($cursor['uidvalidity'] ?? 0) === $uidvalidity
            && $uidvalidity > 0;

        if (! $sameBox) {
            $high = max(0, $uidnext > 0 ? $uidnext - 1 : 0);
            $this->rememberCursor($uidvalidity, $high);

            return ['notified' => 0, 'seeded' => $high, 'skipped' => 0];
        }

        $after = (int) ($cursor['uid'] ?? 0);
        $uids = array_values(array_unique(array_slice($client->uidsAfter('INBOX', $after), 0, self::MAX_PER_RUN)));
        $own = strtolower((string) config('mail.from.address'));
        $notified = 0;
        $skipped = 0;
        $maxUid = $after;

        foreach ($uids as $uid) {
            $maxUid = max($maxUid, $uid);

            try {
                $fetched = $client->fetchMessage('INBOX', $uid);
            } catch (Throwable $e) {
                Log::warning('Falha ao ler mensagem nova da INBOX', [
                    'uid' => $uid,
                    'error' => $e->getMessage(),
                ]);
                $skipped++;
                $this->rememberCursor($uidvalidity, $maxUid);

                continue;
            }

            $parsed = $fetched['parsed'];
            if (! $parsed instanceof Rfc822Message || $this->isOwn($parsed, $own)) {
                $skipped++;
                $this->rememberCursor($uidvalidity, $maxUid);

                continue;
            }

            if (! $this->claimUid($uidvalidity, $uid) || ! $this->claimMessageId($parsed->messageId())) {
                $skipped++;
                $this->rememberCursor($uidvalidity, $maxUid);

                continue;
            }

            $snippet = $parsed->html !== '' ? $parsed->html : $parsed->text;
            $this->notifications->notifyIncoming([
                'from' => $parsed->from(),
                'subject' => $parsed->subject(),
                'snippet' => $snippet,
                'folder' => 'INBOX',
                'uid' => $uid,
            ]);
            $notified++;
            $this->rememberCursor($uidvalidity, $maxUid);
        }

        $this->rememberCursor($uidvalidity, $maxUid);

        return ['notified' => $notified, 'seeded' => null, 'skipped' => $skipped];
    }

    private function rememberCursor(int $uidvalidity, int $uid): void
    {
        Cache::forever(self::CURSOR_KEY, [
            'uidvalidity' => $uidvalidity,
            'uid' => $uid,
        ]);
    }

    private function claimUid(int $uidvalidity, int $uid): bool
    {
        return Cache::add('mail.inbox.notify.uid:'.$uidvalidity.':'.$uid, 1, self::SEEN_TTL_SECONDS);
    }

    private function claimMessageId(string $messageId): bool
    {
        $normalized = $this->normalizeMessageId($messageId);
        if ($normalized === '') {
            return true;
        }

        return Cache::add('mail.inbox.notify.mid:'.sha1($normalized), 1, self::SEEN_TTL_SECONDS);
    }

    private function normalizeMessageId(string $messageId): string
    {
        $messageId = strtolower(trim($messageId));
        $messageId = trim($messageId, '<>');

        return $messageId;
    }

    private function isOwn(Rfc822Message $parsed, string $own): bool
    {
        if ($own === '') {
            return false;
        }

        return in_array($own, CrmMailboxService::extractAddresses($parsed->from()), true);
    }
}
