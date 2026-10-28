<?php

namespace App\Services;

use App\Mail\TestMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
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
        $ready = $mailer === 'smtp' && $host !== '' && $from !== '';

        return [
            'mailer' => $mailer,
            'host' => $host,
            'port' => $port,
            'encryption' => $encryption,
            'username' => $username,
            'from_address' => $from,
            'from_name' => $name,
            'has_password' => $this->settings->hasSecret('mail_password'),
            'ready' => $ready,
            'label' => match ($mailer) {
                'smtp' => $ready ? 'SMTP pronto' : 'SMTP incompleto',
                'log' => 'Apenas log (não sai da máquina)',
                'array' => 'Array (testes)',
                default => $mailer,
            },
            'hint' => $ready
                ? 'Pode enviar um e-mail de teste. Convites de depoimento continuam só com link para copiar.'
                : 'Preencha host, remetente e, se o servidor exigir, usuário e senha. Enquanto o mailer for log, nada é entregue na caixa de entrada.',
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
            'mail_from_address' => $stored['mail_from_address'] ?: $snap['from_address'],
            'mail_from_name' => $stored['mail_from_name'] ?: $snap['from_name'],
        ];
    }

    public function sendTest(string $to, User $admin): void
    {
        $this->apply();

        Mail::to($to)->send(new TestMail);

        $this->audit->record('mail.test', null, [
            'to' => $to,
            'mailer' => (string) config('mail.default'),
        ], null, $admin->id);
    }

    public function trySendTest(string $to, User $admin): array
    {
        try {
            $this->sendTest($to, $admin);

            return ['ok' => true, 'message' => 'E-mail de teste enviado para '.$to.'.'];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => 'Falha ao enviar: '.$e->getMessage()];
        }
    }

    private function filled(string $key): ?string
    {
        $value = $this->settings->get($key);

        return filled($value) ? (string) $value : null;
    }
}
