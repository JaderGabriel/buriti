<?php

namespace App\Services;

use App\Mail\CrmOutgoingMail;
use App\Mail\ImapClient;
use App\Mail\ImapConfig;
use App\Mail\Rfc822Message;
use App\Models\User;
use App\Support\MailBranding;
use App\Support\MailContacts;
use Illuminate\Http\UploadedFile;
use Illuminate\Mail\SentMessage;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Symfony\Component\Mime\Address as MimeAddress;
use Symfony\Component\Mime\Email;
use Throwable;

class CrmMailboxService
{
    public function __construct(
        private ImapMailboxStore $imap,
        private MailSettingsService $mail,
        private SettingService $settings,
        private AuditLogger $audit,
        private MailAnnotationService $annotate,
    ) {}

    public function isReady(): bool
    {
        $this->mail->apply();

        return $this->imap->isConfigured();
    }

    public function identity(): array
    {
        $this->mail->apply();

        $signature = $this->personalSignature();

        return [
            'address' => (string) config('mail.from.address'),
            'name' => (string) config('mail.from.name'),
            'signature' => $signature,
            'signature_block' => MailBranding::block($signature, (string) config('mail.from.name')),
        ];
    }

    /**
     * @return array{
     *     ready: bool,
     *     folders: list<array<string, mixed>>,
     *     folder: string,
     *     listing: array<string, mixed>,
     *     selected: ?array<string, mixed>,
     *     identity: array<string, mixed>,
     *     error: ?string
     * }
     */
    public function workspace(string $folder, int $page = 1, ?int $uid = null, ?string $query = null): array
    {
        $identity = $this->identity();
        if (! $this->imap->isConfigured()) {
            return [
                'ready' => false,
                'folders' => [],
                'folder' => 'INBOX',
                'listing' => ['messages' => [], 'exists' => 0, 'page' => 1, 'pages' => 1],
                'selected' => null,
                'identity' => $identity,
                'error' => null,
            ];
        }

        try {
            return $this->imap->usingClient(function (ImapClient $client, ImapConfig $config) use ($folder, $page, $uid, $query, $identity) {
                $folders = $this->foldersFrom($client, $config);
                $folder = $this->resolveFolder($folders, $folder);
                $listing = $client->listMessages(
                    $folder,
                    $page,
                    40,
                    $query,
                    is_string($query) ? MailContacts::emailsMatching($query) : [],
                );
                $selected = null;

                if ($uid !== null && $uid > 0) {
                    $fetched = $client->fetchMessage($folder, $uid);
                    if (! $fetched['seen']) {
                        try {
                            $client->addFlags($folder, $uid, '\\Seen');
                            $fetched['seen'] = true;
                        } catch (Throwable) {
                            // leitura continua mesmo se o STORE falhar
                        }
                    }
                    $selected = $this->presentMessage($fetched, $folder);
                }

                $account = (string) ($identity['address'] ?? '');
                $listing['messages'] = $this->annotate->decorate($account, $folder, $listing['messages'] ?? []);
                if (is_array($selected)) {
                    $selected = $this->annotate->hydrate($account, $selected);
                }

                return [
                    'ready' => true,
                    'folders' => $folders,
                    'folder' => $folder,
                    'listing' => $listing,
                    'selected' => $selected,
                    'identity' => $identity,
                    'error' => null,
                ];
            });
        } catch (Throwable $e) {
            return [
                'ready' => true,
                'folders' => [],
                'folder' => $folder !== '' ? $folder : 'INBOX',
                'listing' => ['messages' => [], 'exists' => 0, 'page' => 1, 'pages' => 1],
                'selected' => null,
                'identity' => $identity,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @param  list<UploadedFile>  $attachments
     */
    public function send(string $to, string $subject, string $body, User $admin, bool $withSignature = true, ?string $cc = null, ?string $inReplyTo = null, ?string $replyFolder = null, ?int $replyUid = null, ?string $bcc = null, array $attachments = []): ?string
    {
        $this->assertAdmin($admin);
        $this->mail->apply();
        $this->mail->assertDeliverable();

        $signature = $withSignature ? $this->personalSignature() : '';
        $mailable = new CrmOutgoingMail($subject, $body, $signature, $inReplyTo, $this->filePayloads($attachments));
        $pending = Mail::to($this->splitAddresses($to));
        if (filled($cc)) {
            $pending->cc($this->splitAddresses($cc));
        }
        if (filled($bcc)) {
            $pending->bcc($this->splitAddresses($bcc));
        }
        $sent = $pending->send($mailable);

        if ($replyFolder && $replyUid) {
            try {
                $this->imap->usingClient(function (ImapClient $client) use ($replyFolder, $replyUid) {
                    $client->addFlags($replyFolder, $replyUid, '\\Answered');
                });
            } catch (Throwable) {
                // envio já saiu
            }
        }

        $this->audit->record('mailbox.sent', null, [
            'to' => $to,
            'subject' => $subject,
        ], null, $admin->id);

        $identity = $this->identity();
        $raw = $this->capturedRfc822($sent, $identity, $to, $subject, $body, $signature);

        return $this->archiveOutgoing($raw);
    }

    /**
     * @param  list<UploadedFile>  $attachments
     */
    public function saveDraft(string $to, string $subject, string $body, User $admin, bool $withSignature = true, array $attachments = []): string
    {
        $this->assertAdmin($admin);
        $this->mail->apply();
        $identity = $this->identity();
        $signature = $withSignature ? $this->personalSignature() : '';
        $raw = $this->draftRfc822($identity, $to, $subject, $body, $signature, $attachments);

        return $this->imap->usingClient(function (ImapClient $client) use ($raw, $admin, $subject) {
            $folder = $this->roleFolder($client, 'drafts') ?? 'Drafts';
            try {
                $client->append($folder, $raw, '\\Seen \\Draft');
            } catch (Throwable) {
                $client->createFolder($folder);
                $client->append($folder, $raw, '\\Seen \\Draft');
            }
            $this->audit->record('mailbox.draft', null, ['subject' => $subject], null, $admin->id);

            return $folder;
        });
    }

    public function destroy(string $folder, int $uid, User $admin): void
    {
        $this->destroyMany($folder, [$uid], $admin);
    }

    /**
     * @param  list<int>  $uids
     */
    public function destroyMany(string $folder, array $uids, User $admin): void
    {
        $this->assertAdmin($admin);
        $uids = $this->cleanUids($uids);
        $this->imap->usingClient(function (ImapClient $client) use ($folder, $uids) {
            $trash = $this->roleFolder($client, 'trash');
            $role = ImapClient::classifyFolder($folder, '');
            if ($trash !== null && $role !== 'trash') {
                try {
                    $client->copyUids($folder, $uids, $trash);
                } catch (Throwable) {
                    // apaga na pasta actual se o Lixo não existir
                }
            }
            $client->setFlags($folder, $uids, '\\Deleted', true);
            $client->expunge();
        });

        $this->audit->record('mailbox.deleted', null, [
            'folder' => $folder,
            'uids' => $uids,
        ], null, $admin->id);
    }

    public function move(string $folder, int $uid, string $destination, User $admin): void
    {
        $this->moveMany($folder, [$uid], $destination, $admin);
    }

    /**
     * @param  list<int>  $uids
     */
    public function moveMany(string $folder, array $uids, string $destination, User $admin): void
    {
        $this->assertAdmin($admin);
        $uids = $this->cleanUids($uids);
        $this->imap->usingClient(function (ImapClient $client) use ($folder, $uids, $destination) {
            $client->copyUids($folder, $uids, $destination);
            $client->setFlags($folder, $uids, '\\Deleted', true);
            $client->expunge();
        });
    }

    public function toggleSeen(string $folder, int $uid, bool $seen, User $admin): void
    {
        $this->setFlag($folder, [$uid], '\\Seen', $seen, $admin);
    }

    /**
     * @param  list<int>  $uids
     */
    public function setFlag(string $folder, array $uids, string $flag, bool $add, User $admin): void
    {
        $this->assertAdmin($admin);
        $uids = $this->cleanUids($uids);
        $this->imap->usingClient(
            fn (ImapClient $client) => $client->setFlags($folder, $uids, $flag, $add)
        );
    }

    /**
     * @param  list<int>  $uids
     * @return list<array<string, mixed>>
     */
    public function headersFor(string $folder, array $uids, User $admin): array
    {
        $this->assertAdmin($admin);
        $uids = $this->cleanUids($uids);
        if ($uids === []) {
            return [];
        }

        return $this->imap->usingClient(function (ImapClient $client) use ($folder, $uids) {
            $messages = $client->listedMessagesByUids($folder, $uids);

            return array_map(function (array $message) use ($folder) {
                $message['folder'] = $folder;

                return $message;
            }, $messages);
        });
    }

    public function createFolder(string $name, User $admin): void
    {
        $this->assertAdmin($admin);
        $this->imap->usingClient(fn (ImapClient $client) => $client->createFolder($name));
    }

    public function saveSignature(string $signature, User $admin): void
    {
        $this->assertAdmin($admin);
        $this->settings->putMany(['mail_signature' => mb_substr($signature, 0, 4000)]);
    }

    public function attachment(string $folder, int $uid, int $index, User $admin): array
    {
        $this->assertAdmin($admin);

        return $this->imap->usingClient(function (ImapClient $client) use ($folder, $uid, $index) {
            $fetched = $client->fetchMessage($folder, $uid);
            $attachments = $fetched['parsed']->attachments;
            if (! isset($attachments[$index])) {
                throw new RuntimeException('Anexo não encontrado.');
            }

            return $attachments[$index];
        });
    }

    /**
     * @param  list<array<string, mixed>>  $folders
     */
    private function resolveFolder(array $folders, string $requested): string
    {
        $requested = trim($requested);
        if ($requested === '') {
            return 'INBOX';
        }

        foreach ($folders as $folder) {
            if (strcasecmp((string) $folder['name'], $requested) === 0) {
                return (string) $folder['name'];
            }
        }

        return $folders[0]['name'] ?? 'INBOX';
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function foldersFrom(ImapClient $client, ImapConfig $config): array
    {
        $boxes = $client->listMailboxes();
        $folders = [];

        foreach ($boxes as $box) {
            if (str_contains(strtolower($box['attrs']), '\\noselect')) {
                continue;
            }

            $role = ImapClient::classifyFolder($box['name'], $box['attrs']);
            if ($role === 'sent' && $config->sentFolder && strcasecmp($box['name'], $config->sentFolder) !== 0) {
                // keep both; preferred sent still classified
            }

            $status = ['messages' => 0, 'unseen' => 0];
            try {
                $status = $client->status($box['name']);
            } catch (Throwable) {
                // pasta listada mesmo sem STATUS
            }

            $folders[] = [
                'name' => $box['name'],
                'role' => $role,
                'label' => ImapClient::folderLabel($box['name'], $role),
                'unseen' => $status['unseen'],
                'messages' => $status['messages'],
            ];
        }

        usort($folders, function (array $a, array $b): int {
            $order = ['inbox' => 0, 'drafts' => 1, 'sent' => 2, 'archive' => 3, 'junk' => 4, 'trash' => 5, 'other' => 6];

            return ($order[$a['role']] ?? 6) <=> ($order[$b['role']] ?? 6)
                ?: strcasecmp((string) $a['label'], (string) $b['label']);
        });

        return $folders;
    }

    private function roleFolder(ImapClient $client, string $role): ?string
    {
        foreach ($client->listMailboxes() as $box) {
            if (ImapClient::classifyFolder($box['name'], $box['attrs']) === $role) {
                return $box['name'];
            }
        }

        return match ($role) {
            'sent' => 'Sent',
            'drafts' => 'Drafts',
            'trash' => 'Trash',
            default => null,
        };
    }

    /**
     * @param  array{uid: int, flags: list<string>, seen: bool, flagged?: bool, raw: string, parsed: Rfc822Message}  $fetched
     * @return array<string, mixed>
     */
    private function presentMessage(array $fetched, string $folder): array
    {
        $parsed = $fetched['parsed'];
        $html = $parsed->html !== '' ? Rfc822Message::safeHtml($parsed->html) : '';

        return [
            'uid' => $fetched['uid'],
            'folder' => $folder,
            'from' => $parsed->from(),
            'to' => $parsed->to(),
            'cc' => $parsed->cc(),
            'subject' => $parsed->subject() !== '' ? $parsed->subject() : '(sem assunto)',
            'date' => $parsed->date(),
            'message_id' => $parsed->messageId(),
            'text' => $parsed->text,
            'html' => $html,
            'preview' => $parsed->preview(),
            'seen' => $fetched['seen'],
            'flagged' => (bool) ($fetched['flagged'] ?? false),
            'attachments' => array_map(
                fn (array $item, int $i) => [
                    'index' => $i,
                    'name' => $item['name'],
                    'type' => $item['type'],
                    'size' => $item['size'],
                ],
                $parsed->attachments,
                array_keys($parsed->attachments),
            ),
        ];
    }

    /**
     * @return list<string>
     */
    public static function extractAddresses(string $raw): array
    {
        preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $raw, $matches);

        return array_values(array_unique(array_map('strtolower', $matches[0] ?? [])));
    }

    /**
     * @return list<string>
     */
    private function splitAddresses(string $raw): array
    {
        $emails = self::extractAddresses($raw);

        if ($emails === []) {
            throw new RuntimeException('Indica pelo menos um destinatário válido.');
        }

        return $emails;
    }

    /**
     * @param  array{address: string, name: string, signature: string}  $identity
     * @param  list<UploadedFile>  $attachments
     */
    private function draftRfc822(array $identity, string $to, string $subject, string $body, string $signature, array $attachments = []): string
    {
        $from = $identity['name'] !== ''
            ? sprintf('"%s" <%s>', addcslashes($identity['name'], '"'), $identity['address'])
            : $identity['address'];
        $text = trim($body.($signature !== '' ? "\n\n-- \n".$signature : ''));
        $text .= "\n\n-- \n".MailBranding::legalNotice();

        if ($attachments === []) {
            $encoded = function_exists('mb_encode_mimeheader')
                ? mb_encode_mimeheader($subject, 'UTF-8', 'Q')
                : $subject;

            return implode("\r\n", [
                'From: '.$from,
                'To: '.$to,
                'Subject: '.$encoded,
                'Date: '.now()->toRfc2822String(),
                'MIME-Version: 1.0',
                'Content-Type: text/plain; charset=UTF-8',
                'Content-Transfer-Encoding: 8bit',
                '',
                $text,
            ]);
        }

        $email = (new Email)
            ->from(new MimeAddress($identity['address'], $identity['name'] ?: $identity['address']))
            ->to(...$this->splitAddresses($to))
            ->subject($subject)
            ->text($text)
            ->date(now());

        foreach ($this->filePayloads($attachments) as $file) {
            $email->attachFromPath($file['path'], $file['name'], $file['mime']);
        }

        return $email->toString();
    }

    /**
     * @param  list<UploadedFile>|array<int, UploadedFile|null>  $files
     * @return list<array{path: string, name: string, mime: string}>
     */
    private function filePayloads(array $files): array
    {
        $payloads = [];
        foreach ($files as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }
            $payloads[] = [
                'path' => $file->getRealPath() ?: $file->getPathname(),
                'name' => $file->getClientOriginalName() ?: 'anexo',
                'mime' => $file->getMimeType() ?: 'application/octet-stream',
            ];
        }

        return $payloads;
    }

    /**
     * @param  list<int|string>  $uids
     * @return list<int>
     */
    private function cleanUids(array $uids): array
    {
        $clean = [];
        foreach ($uids as $uid) {
            $value = (int) $uid;
            if ($value > 0) {
                $clean[$value] = $value;
            }
        }

        return array_values($clean);
    }

    public function lastSentArchive(): array
    {
        return $this->imap->lastArchiveStatus();
    }

    private function personalSignature(): string
    {
        $stored = trim((string) ($this->settings->get('mail_signature') ?: ''));
        if ($stored !== '') {
            return $stored;
        }

        return MailBranding::personal(
            (string) config('mail.from.name'),
            (string) config('mail.from.address'),
            (string) ($this->settings->get('contact_phone') ?: ''),
            MailBranding::siteUrl(),
        );
    }

    private function capturedRfc822(mixed $sent, array $identity, string $to, string $subject, string $body, string $signature): string
    {
        try {
            if ($sent instanceof SentMessage) {
                return $sent->getSymfonySentMessage()->getOriginalMessage()->toString();
            }
        } catch (Throwable) {
            // cai no rascunho RFC822
        }

        return $this->draftRfc822($identity, $to, $subject, $body, $signature);
    }

    private function archiveOutgoing(string $rfc822): ?string
    {
        if ($this->imap->shouldArchiveSent()) {
            try {
                return $this->imap->storeSent($rfc822);
            } catch (Throwable $e) {
                report($e);
            }
        }

        if ($this->imap->providerSavesSentAutomatically()) {
            try {
                return $this->imap->peekSentFolder();
            } catch (Throwable) {
                return null;
            }
        }

        return null;
    }

    private function assertAdmin(User $admin): void
    {
        abort_unless($admin->can('manageMail'), 403, 'O serviço de e-mail é exclusivo do admin.');
    }
}
