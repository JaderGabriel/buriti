<?php

namespace App\Services;

use App\Mail\TestMail;
use App\Models\User;
use Illuminate\Mail\SentMessage;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

class MailSettingsService
{
    public function __construct(
        private SettingService $settings,
        private AuditLogger $audit,
    ) {}

    public function apply(): void
    {
        $mailer = $this->filled('mail_mailer');
        if ($mailer !== null) {
            config(['mail.default' => $mailer]);
        }

        $from = $this->filled('mail_from_address');
        if ($from !== null) {
            config(['mail.from.address' => $from]);
        }

        $name = $this->filled('mail_from_name');
        if ($name !== null) {
            config(['mail.from.name' => $name]);
        }

        $host = $this->filled('mail_host');
        if ($host !== null) {
            config(['mail.mailers.smtp.host' => $host]);
        }

        $port = $this->filled('mail_port');
        if ($port !== null) {
            config(['mail.mailers.smtp.port' => (int) $port]);
        }

        $username = $this->filled('mail_username');
        if ($username !== null) {
            config(['mail.mailers.smtp.username' => $username]);
        }

        $identity = self::mailboxAddress($username ?? (string) config('mail.mailers.smtp.username'));
        if ($identity !== null) {
            config(['mail.from.address' => $identity]);
        }

        $password = $this->settings->getSecret('mail_password');
        if (filled($password)) {
            config(['mail.mailers.smtp.password' => $password]);
        }

        $encryption = $this->settings->get('mail_encryption');
        if ($encryption === 'ssl') {
            config(['mail.mailers.smtp.scheme' => 'smtps']);
        } elseif ($encryption === 'tls') {
            config(['mail.mailers.smtp.scheme' => 'smtp']);
        } elseif ($encryption === 'none') {
            config(['mail.mailers.smtp.scheme' => null]);
        }
    }

    /**
     * @return array{
     *     mailer: string,
     *     host: string,
     *     port: int|string,
     *     encryption: string,
     *     username: string,
     *     from_address: string,
     *     from_name: string,
     *     has_password: bool,
     *     ready: bool,
     *     aligned: bool,
     *     suggested_from: string,
     *     imap_host: string,
     *     imap_port: string,
     *     imap_encryption: string,
     *     imap_sent_folder: string,
     *     imap_ready: bool,
     *     saves_sent: bool,
     *     warnings: list<string>,
     *     label: string,
     *     hint: string
     * }
     */
    public function snapshot(): array
    {
        $this->apply();

        $mailer = (string) config('mail.default');
        $host = (string) config('mail.mailers.smtp.host');
        $port = (int) config('mail.mailers.smtp.port');
        $scheme = (string) config('mail.mailers.smtp.scheme');
        $from = (string) config('mail.from.address');
        $name = (string) config('mail.from.name');
        $username = (string) config('mail.mailers.smtp.username');
        $encryption = $this->settings->get('mail_encryption')
            ?: ($scheme === 'smtps' ? 'ssl' : 'tls');
        $alignment = self::identityIssue($from, $mailer, $host, $username);
        $spf = $this->spfHardFailHint($from, $mailer, $host);
        $identity = self::mailboxAddress($username);
        $imapHost = $this->settings->get('mail_imap_host') ?: (string) ImapMailboxStore::derivedImapHost($host);
        $imapPort = $this->settings->get('mail_imap_port') ?: '993';
        $imapEncryption = $this->settings->get('mail_imap_encryption') ?: 'ssl';
        $imapFolder = $this->settings->get('mail_imap_sent_folder') ?: '';
        $savesSent = ImapMailboxStore::hostSavesSentAutomatically($host);
        $imapReady = $identity !== null && $this->settings->hasSecret('mail_password') && $imapHost !== '';
        $warnings = array_values(array_filter([
            $alignment,
            $spf,
            $mailer === 'smtp' && $identity === null
                ? 'O login tem de ser o e-mail completo da conta. O From, o SMTP e o IMAP usam essa mesma identidade — não dá para fingir ser outro endereço.'
                : null,
        ]));
        $ready = $mailer === 'smtp'
            && $host !== ''
            && $identity !== null
            && $this->settings->hasSecret('mail_password')
            && $alignment === null;

        $hint = $alignment
            ?? ($spf
                ?? ($ready
                    ? ($savesSent
                        ? 'SMTP autenticado com a conta '.$identity.'. O provedor já guarda os enviados na caixa.'
                        : 'SMTP autenticado com a conta '.$identity.'. Cada envio é copiado para Enviados via IMAP, visível no mailserver.')
                    : 'Preenche host, conta (e-mail) e senha. O remetente fica preso a essa conta. Enquanto o mailer for log, nada sai da máquina.'));

        return [
            'mailer' => $mailer,
            'host' => $host,
            'port' => $port,
            'encryption' => $encryption,
            'username' => $username,
            'from_address' => $identity ?: $from,
            'from_name' => $name,
            'has_password' => $this->settings->hasSecret('mail_password'),
            'ready' => $ready,
            'aligned' => $alignment === null,
            'suggested_from' => self::suggestedFromAddress(),
            'imap_host' => $imapHost,
            'imap_port' => $imapPort,
            'imap_encryption' => $imapEncryption,
            'imap_sent_folder' => $imapFolder,
            'imap_ready' => $imapReady,
            'saves_sent' => $savesSent,
            'warnings' => $warnings,
            'label' => match ($mailer) {
                'smtp' => $alignment ? 'Conta recusada / desalinhada' : ($ready ? 'Conta pronta' : 'SMTP incompleto'),
                'sendmail' => $alignment ? 'Remetente recusado pelo Gmail' : 'Sendmail local (sem login)',
                'log' => 'Apenas log (não sai da máquina)',
                'array' => 'Array (testes)',
                default => $mailer,
            },
            'hint' => $hint,
        ];
    }

    /**
     * @return array<string, string|null>
     */
    public function formValues(): array
    {
        $snap = $this->snapshot();
        $stored = $this->settings->all();

        $mailer = $stored['mail_mailer'] ?: $snap['mailer'];
        if (! in_array($mailer, ['smtp', 'log', 'sendmail'], true)) {
            $mailer = 'log';
        }

        $encryption = $stored['mail_encryption'] ?: $snap['encryption'];
        if (! in_array($encryption, ['tls', 'ssl', 'none'], true)) {
            $encryption = 'tls';
        }

        return [
            'mail_mailer' => $mailer,
            'mail_host' => $stored['mail_host'] ?: $snap['host'],
            'mail_port' => $stored['mail_port'] ?: (string) $snap['port'],
            'mail_encryption' => $encryption,
            'mail_username' => $stored['mail_username'] ?: $snap['username'],
            'mail_from_address' => self::mailboxAddress($stored['mail_username'] ?: $snap['username'])
                ?: ($stored['mail_from_address'] ?: $snap['from_address']),
            'mail_from_name' => $stored['mail_from_name'] ?: $snap['from_name'],
            'mail_imap_host' => $stored['mail_imap_host'] ?: '',
            'mail_imap_port' => $stored['mail_imap_port'] ?: (string) $snap['imap_port'],
            'mail_imap_encryption' => in_array($stored['mail_imap_encryption'] ?? '', ['ssl', 'tls', 'none'], true)
                ? $stored['mail_imap_encryption']
                : 'ssl',
            'mail_imap_sent_folder' => $stored['mail_imap_sent_folder'] ?: '',
        ];
    }

    public function sendTest(string $to, User $admin): void
    {
        $this->assertAdmin($admin);
        $this->apply();
        $this->assertDeliverable();

        $sent = Mail::to($to)->send(new TestMail);
        $this->archiveTestCopy($sent);

        $this->audit->record('mail.test', null, [
            'to' => $to,
            'mailer' => (string) config('mail.default'),
            'from' => (string) config('mail.from.address'),
        ], null, $admin->id);
    }

    public function trySendTest(string $to, User $admin): array
    {
        $this->assertAdmin($admin);

        try {
            $this->sendTest($to, $admin);

            return ['ok' => true, 'message' => $this->testSuccessMessage($to)];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => 'Falha ao enviar: '.$e->getMessage()];
        }
    }

    /**
     * @return array{ok: bool, message: string, messages: list<array{from: string, subject: string, date: string}>}
     */
    public function inboxPreview(User $admin): array
    {
        $this->assertAdmin($admin);
        $this->apply();

        try {
            $messages = app(MailboxStore::class)->recentInbox(8);
            $box = (string) config('mail.mailers.smtp.username');

            return [
                'ok' => true,
                'message' => $messages === []
                    ? 'IMAP ligado à conta '.$box.'; a INBOX está vazia.'
                    : count($messages).' mensagem(ns) recentes na INBOX de '.$box.'.',
                'messages' => $messages,
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'message' => $e->getMessage(),
                'messages' => [],
            ];
        }
    }

    public function assertDeliverable(): void
    {
        $mailer = (string) config('mail.default');
        $from = (string) config('mail.from.address');
        $host = (string) config('mail.mailers.smtp.host');
        $username = (string) config('mail.mailers.smtp.username');
        $issue = self::identityIssue($from, $mailer, $host, $username)
            ?? $this->spfHardFailHint($from, $mailer, $host);

        if ($issue !== null) {
            throw new RuntimeException($issue);
        }

        if ($mailer === 'smtp' && self::mailboxAddress($username) === null) {
            throw new RuntimeException('Define o login da conta (e-mail completo). O envio usa essa identidade no SMTP e no IMAP.');
        }
    }

    public static function mailboxAddress(?string $username): ?string
    {
        $username = strtolower(trim((string) $username));

        return $username !== '' && filter_var($username, FILTER_VALIDATE_EMAIL) ? $username : null;
    }

    public static function identityIssue(?string $from, ?string $mailer, ?string $host, ?string $username): ?string
    {
        $mailer = strtolower(trim((string) $mailer));
        $username = trim((string) $username);
        $from = strtolower(trim((string) $from));
        $box = self::mailboxAddress($username);

        if ($mailer === 'smtp' && $username !== '' && $box === null) {
            return 'O login SMTP tem de ser o e-mail completo da conta (ex.: contato@buriti.dev.br).';
        }

        if ($mailer === 'smtp' && $box !== null && $from !== '' && $from !== $box) {
            return 'Não é possível enviar a fingir ser outro endereço. O remetente tem de ser a conta autenticada ('.$box.').';
        }

        return self::fromAlignmentIssue($box ?? $from, $mailer, $host);
    }

    /**
     * Gmail (e equivalentes) recusam From de caixa pública se a mensagem não
     * sair pelo SMTP autenticado do próprio provedor — 550 5.7.26 SPF/DKIM.
     */
    public static function fromAlignmentIssue(?string $from, ?string $mailer, ?string $host): ?string
    {
        $from = strtolower(trim((string) $from));
        $mailer = strtolower(trim((string) $mailer));
        $host = strtolower(trim((string) $host));

        if ($from === '' || ! in_array($mailer, ['smtp', 'sendmail'], true)) {
            return null;
        }

        $domain = self::emailDomain($from);
        if ($domain === null) {
            return null;
        }

        $allowedHosts = self::publicMailboxSmtpHosts()[$domain] ?? null;
        if ($allowedHosts === null) {
            return null;
        }

        if ($mailer === 'smtp' && self::hostMatches($host, $allowedHosts)) {
            return null;
        }

        $suggest = self::suggestedFromAddress();

        return 'O Gmail bloqueou (550 5.7.26): remetente @'.$domain.' só é aceite se a mensagem sair pelo SMTP autenticado deles'
            .' (ex.: smtp.gmail.com + senha de app). Este servidor não pode fingir ser o Gmail.'
            .' Troca o remetente para um endereço do teu domínio (ex.: '.$suggest.')'
            .' ou aponta o host para smtp.gmail.com.';
    }

    public static function suggestedFromAddress(): string
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);
        $host = is_string($host) && $host !== '' ? strtolower($host) : 'buriti.dev.br';

        return 'contato@'.$host;
    }

    /**
     * @return array<string, list<string>>
     */
    public static function publicMailboxSmtpHosts(): array
    {
        return [
            'gmail.com' => ['smtp.gmail.com', 'smtp.googlemail.com'],
            'googlemail.com' => ['smtp.gmail.com', 'smtp.googlemail.com'],
            'outlook.com' => ['smtp.office365.com', 'smtp-mail.outlook.com'],
            'hotmail.com' => ['smtp.office365.com', 'smtp-mail.outlook.com'],
            'live.com' => ['smtp.office365.com', 'smtp-mail.outlook.com'],
            'yahoo.com' => ['smtp.mail.yahoo.com'],
            'icloud.com' => ['smtp.mail.me.com'],
            'me.com' => ['smtp.mail.me.com'],
            'proton.me' => ['smtp.protonmail.ch'],
            'protonmail.com' => ['smtp.protonmail.ch'],
        ];
    }

    private function spfHardFailHint(string $from, string $mailer, string $host): ?string
    {
        if (! app()->isProduction() || ! in_array($mailer, ['smtp', 'sendmail'], true)) {
            return null;
        }

        $domain = self::emailDomain($from);
        if ($domain === null) {
            return null;
        }

        $allowed = self::publicMailboxSmtpHosts()[$domain] ?? null;
        if ($allowed !== null && $mailer === 'smtp' && self::hostMatches($host, $allowed)) {
            return null;
        }

        $spf = $this->spfRecord($domain);
        if ($spf === null) {
            return 'O domínio '.$domain.' não publica SPF. O Gmail exige SPF ou DKIM (550 5.7.26).'
                .' Cria um TXT, por exemplo: v=spf1 ip4:'.$this->sendingIpv4Hint().' a ~all';
        }

        if (preg_match('/^v=spf1\s+-all$/i', $spf) === 1) {
            return 'O DNS de '.$domain.' tem SPF "v=spf1 -all": nenhum IP pode enviar.'
                .' O Gmail recusa com 550 5.7.26. Altera o TXT para incluir este servidor, por exemplo:'
                .' v=spf1 ip4:'.$this->sendingIpv4Hint().' a ~all';
        }

        return null;
    }

    private function spfRecord(string $domain): ?string
    {
        $records = @dns_get_record($domain, DNS_TXT);
        if (! is_array($records)) {
            return null;
        }

        foreach ($records as $record) {
            $txt = strtolower(trim((string) ($record['txt'] ?? '')));
            if (str_starts_with($txt, 'v=spf1')) {
                return $txt;
            }
        }

        return null;
    }

    private function sendingIpv4Hint(): string
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return 'SEU.IP.PUBLICO';
        }

        $ip = gethostbyname($host);

        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? $ip : 'SEU.IP.PUBLICO';
    }

    private static function emailDomain(string $email): ?string
    {
        $at = strrchr($email, '@');
        if ($at === false) {
            return null;
        }

        $domain = strtolower(ltrim($at, '@'));

        return $domain !== '' ? $domain : null;
    }

    /**
     * @param  list<string>  $allowed
     */
    private static function hostMatches(string $host, array $allowed): bool
    {
        foreach ($allowed as $candidate) {
            if ($host === $candidate || str_ends_with($host, '.'.$candidate)) {
                return true;
            }
        }

        return false;
    }

    private function testSuccessMessage(string $to): string
    {
        $from = (string) config('mail.from.address');
        $message = 'E-mail de teste enviado para '.$to.' como '.$from.'.';
        $mailbox = app(MailboxStore::class);
        $status = $mailbox->lastArchiveStatus();

        if ($status['stored']) {
            return $message.' Cópia na pasta Enviados ('.$status['folder'].'), visível no mailserver.';
        }

        if ($mailbox->providerSavesSentAutomatically()) {
            return $message.' O provedor já guarda os enviados nessa conta.';
        }

        if (is_string($status['error']) && $status['error'] !== '') {
            return $message.' A cópia IMAP em Enviados falhou: '.$status['error'];
        }

        return $message;
    }

    private function archiveTestCopy(mixed $sent): void
    {
        $store = app(MailboxStore::class);
        if ($store->lastArchiveStatus()['stored'] || ! $store->shouldArchiveSent()) {
            return;
        }

        try {
            if ($sent instanceof SentMessage) {
                $store->storeSent($sent->getSymfonySentMessage()->getOriginalMessage()->toString());
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function filled(string $key): ?string
    {
        $value = $this->settings->get($key);

        return filled($value) ? (string) $value : null;
    }

    private function assertAdmin(User $admin): void
    {
        abort_unless($admin->canManageMail(), 403, 'O serviço de e-mail é exclusivo do admin.');
    }
}
