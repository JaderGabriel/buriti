<?php

namespace App\Services;

use App\Mail\ImapClient;
use App\Mail\ImapConfig;
use Closure;
use RuntimeException;
use Throwable;

class ImapMailboxStore implements MailboxStore
{
    /** @var list<string> */
    private const AUTO_SAVES_SENT = [
        'smtp.gmail.com',
        'smtp.googlemail.com',
        'smtp.office365.com',
        'smtp-mail.outlook.com',
        'smtp.mail.yahoo.com',
        'smtp.mail.me.com',
    ];

    private bool $lastStored = false;

    private ?string $lastFolder = null;

    private ?string $lastError = null;

    /** @var Closure(ImapConfig): ImapClient|null */
    private ?Closure $connector = null;

    public function __construct(
        private SettingService $settings,
        private MailSettingsService $mail,
    ) {}

    /**
     * @param  Closure(ImapConfig): ImapClient  $connector
     */
    public function connectUsing(Closure $connector): static
    {
        $this->connector = $connector;

        return $this;
    }

    public function shouldArchiveSent(): bool
    {
        $this->mail->apply();

        if ((string) config('mail.default') !== 'smtp') {
            return false;
        }

        return $this->imapConfig() !== null;
    }

    public function providerSavesSentAutomatically(): bool
    {
        $this->mail->apply();

        return self::hostSavesSentAutomatically((string) config('mail.mailers.smtp.host'));
    }

    public static function hostSavesSentAutomatically(?string $smtpHost): bool
    {
        return in_array(strtolower(trim((string) $smtpHost)), self::AUTO_SAVES_SENT, true);
    }

    public function storeSent(string $rfc822): string
    {
        $this->lastStored = false;
        $this->lastFolder = null;
        $this->lastError = null;

        $config = $this->imapConfig();
        if ($config === null) {
            throw new RuntimeException('IMAP não está configurado para guardar os enviados.');
        }

        $client = $this->open($config);

        try {
            $folder = $client->resolveSentFolder($config->sentFolder);
            try {
                $client->append($folder, $rfc822);
            } catch (Throwable $first) {
                try {
                    $client->createFolder($folder);
                } catch (Throwable) {
                    // a pasta pode já existir com outro nome
                }
                try {
                    $client->append($folder, $rfc822);
                } catch (Throwable) {
                    throw $first;
                }
            }
            $this->lastStored = true;
            $this->lastFolder = $folder;

            return $folder;
        } catch (Throwable $e) {
            $this->lastError = $e->getMessage();
            throw $e;
        } finally {
            $client->logout();
        }
    }

    public function recentInbox(int $limit = 8): array
    {
        return $this->usingClient(fn (ImapClient $client) => $client->recentInbox($limit));
    }

    public function peekSentFolder(): ?string
    {
        return $this->usingClient(
            fn (ImapClient $client) => ImapClient::pickSentFolder($client->listMailboxes())
        );
    }

    public function isConfigured(): bool
    {
        return $this->imapConfig() !== null;
    }

    /**
     * @template T
     *
     * @param  callable(ImapClient, ImapConfig): T  $callback
     * @return T
     */
    public function usingClient(callable $callback): mixed
    {
        $config = $this->imapConfig();
        if ($config === null) {
            throw new RuntimeException('IMAP: falta host, conta ou senha.');
        }

        $client = $this->open($config);

        try {
            return $callback($client, $config);
        } finally {
            $client->logout();
        }
    }

    public function lastArchiveStatus(): array
    {
        return [
            'stored' => $this->lastStored,
            'folder' => $this->lastFolder,
            'error' => $this->lastError,
        ];
    }

    public function imapConfig(): ?ImapConfig
    {
        $this->mail->apply();

        $username = MailSettingsService::mailboxAddress(
            $this->settings->get('mail_username') ?: (string) config('mail.mailers.smtp.username')
        );
        $password = $this->settings->getSecret('mail_password') ?: (string) config('mail.mailers.smtp.password');
        $host = $this->resolvedHost();

        if ($username === null || $password === '' || $host === null) {
            return null;
        }

        $port = (int) ($this->settings->get('mail_imap_port') ?: 993);
        $encryption = $this->settings->get('mail_imap_encryption') ?: 'ssl';
        if (! in_array($encryption, ['ssl', 'tls', 'none'], true)) {
            $encryption = 'ssl';
        }

        $folder = $this->settings->get('mail_imap_sent_folder');

        return new ImapConfig(
            host: $host,
            port: $port > 0 ? $port : 993,
            encryption: $encryption,
            username: $username,
            password: $password,
            sentFolder: filled($folder) ? $folder : null,
        );
    }

    public static function derivedImapHost(?string $smtpHost): ?string
    {
        $host = strtolower(trim((string) $smtpHost));
        if ($host === '') {
            return null;
        }

        $map = [
            'smtp.gmail.com' => 'imap.gmail.com',
            'smtp.googlemail.com' => 'imap.gmail.com',
            'smtp.office365.com' => 'outlook.office365.com',
            'smtp-mail.outlook.com' => 'outlook.office365.com',
            'smtp.mail.yahoo.com' => 'imap.mail.yahoo.com',
            'smtp.mail.me.com' => 'imap.mail.me.com',
        ];

        if (isset($map[$host])) {
            return $map[$host];
        }

        if (str_starts_with($host, 'smtp.')) {
            return 'imap.'.substr($host, 5);
        }

        return $host;
    }

    private function resolvedHost(): ?string
    {
        $explicit = strtolower(trim((string) $this->settings->get('mail_imap_host')));
        if ($explicit !== '') {
            return $explicit;
        }

        return self::derivedImapHost(
            $this->settings->get('mail_host') ?: (string) config('mail.mailers.smtp.host')
        );
    }

    private function open(ImapConfig $config): ImapClient
    {
        if ($this->connector !== null) {
            return ($this->connector)($config);
        }

        return ImapClient::connect($config);
    }
}
