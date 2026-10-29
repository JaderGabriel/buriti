<?php

namespace App\Services;

use App\Mail\TestMail;
use App\Models\ContactMessage;
use App\Models\QaRun;
use App\Models\Testimonial;
use App\Models\User;
use App\Services\Telegram\TelegramApiClient;
use App\Services\Telegram\TelegramBotService;
use App\Support\QaCatalog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Throwable;

class QaLabService
{
    public function __construct(
        private TelegramApiClient $telegramApi,
        private TelegramBotService $telegramBot,
        private AuditLogger $audit,
        private GoogleCalendarService $google,
    ) {}

    public function start(User $user, string $command): QaRun
    {
        $item = QaCatalog::find($command);
        abort_unless($item !== null, 422, 'Comando de teste desconhecido.');

        if (($item['kind'] ?? '') === 'suite' && $this->suiteIsRunning()) {
            abort(409, 'Já há uma suíte a correr.');
        }

        $run = QaRun::query()->create([
            'user_id' => $user->id,
            'kind' => $item['kind'],
            'command' => $command,
            'status' => QaRun::STATUS_RUNNING,
            'summary' => $item['label'],
            'started_at' => now(),
        ]);

        $this->audit->record('qa.run', $run, [
            'summary' => $item['label'],
            'command' => $command,
        ], null, $user->id);

        return $run;
    }

    public function execute(QaRun $run): QaRun
    {
        try {
            $item = QaCatalog::find($run->command);
            $result = match ($item['runner'] ?? '') {
                'phpunit' => $this->runSuite($item['payload']['args'] ?? []),
                'http' => $this->httpProbe(
                    $this->resolveProbeUrl($item['payload'] ?? []),
                    (string) ($item['label'] ?? $run->command),
                ),
                'http.pack' => $this->httpProbePack($item['payload']['targets'] ?? QaCatalog::publicTargets()),
                'telegram.ping' => $this->telegramPing(),
                'telegram.resend_last' => $this->telegramResendLast(),
                'telegram.webhook' => $this->telegramWebhook(),
                'module' => $this->moduleHealth((string) ($item['label'] ?? $run->command), $item['payload'] ?? []),
                'ops' => $this->opsHealth(),
                'migrations' => $this->migrationsStatus(),
                'google' => $this->googleStatus(),
                'testimonials.seals' => $this->testimonialSeals(),
                'mail.config' => $this->mailConfig(),
                default => [
                    'ok' => false,
                    'summary' => 'Comando desconhecido.',
                    'output' => $run->command,
                    'meta' => [],
                ],
            };
        } catch (Throwable $e) {
            $result = [
                'ok' => false,
                'error' => true,
                'summary' => $e->getMessage(),
                'output' => $e->getMessage(),
                'meta' => ['exception' => $e::class],
            ];
        }

        $run->forceFill([
            'status' => ($result['error'] ?? false)
                ? QaRun::STATUS_ERROR
                : (($result['ok'] ?? false) ? QaRun::STATUS_PASSED : QaRun::STATUS_FAILED),
            'summary' => Str::limit((string) ($result['summary'] ?? ''), 500, ''),
            'output' => $this->redact(Str::limit((string) ($result['output'] ?? ''), 180_000, "\n…")),
            'meta' => $result['meta'] ?? [],
            'finished_at' => now(),
        ])->save();

        return $run->fresh() ?? $run;
    }

    public function suiteIsRunning(): bool
    {
        return QaRun::query()
            ->where('kind', 'suite')
            ->where('status', QaRun::STATUS_RUNNING)
            ->exists();
    }

    /**
     * @param  list<string>  $args
     * @return array{ok: bool, summary: string, output: string, meta: array<string, mixed>}
     */
    private function runSuite(array $args = []): array
    {
        $phpunit = base_path('vendor/bin/phpunit');
        if (! is_file($phpunit)) {
            return [
                'ok' => false,
                'error' => true,
                'summary' => 'PHPUnit não está instalado neste ambiente.',
                'output' => 'vendor/bin/phpunit em falta. Instale as dependências de desenvolvimento (composer install sem --no-dev).',
                'meta' => ['phpunit' => false],
            ];
        }

        $php = $this->cliPhpBinary();
        if ($php === null) {
            return [
                'ok' => false,
                'error' => true,
                'summary' => 'PHP CLI indisponível para a suíte.',
                'output' => "O painel corre em php-fpm/CGI. PHPUnit precisa de um binário CLI (SAPI=cli), senão só imprime «Content-type: text/html» e sai com código 1.\nProcurei php8.4, php-cli, ea-php, alt-php e /usr/bin/php com PHP_SAPI=cli, sem GATEWAY_INTERFACE.",
                'meta' => ['php' => false],
            ];
        }

        $sqliteFlags = $this->sqliteIniFlags();
        if (! extension_loaded('pdo_sqlite') && $sqliteFlags === []) {
            return [
                'ok' => false,
                'error' => true,
                'summary' => 'PDO SQLite indisponível neste servidor.',
                'output' => 'A suíte usa SQLite em memória e não toca na base de produção. Instale php-sqlite ou coloque ext/pdo_sqlite.so (carregado pelo ./bin/php).',
                'meta' => ['sqlite' => false],
            ];
        }

        ignore_user_abort(true);
        set_time_limit(180);

        $command = array_merge([$php], $sqliteFlags, [$phpunit, '--colors=never'], $args);

        $result = Process::path(base_path())
            ->timeout(180)
            ->idleTimeout(120)
            ->env($this->phpunitEnv())
            ->run($command);

        $stdout = trim($result->output());
        $stderr = trim($result->errorOutput());
        $chunks = [
            'Comando: '.implode(' ', $command),
            'Exit: '.($result->exitCode() ?? 'n/d'),
        ];
        if ($stdout !== '') {
            $chunks[] = "--- stdout ---\n".$stdout;
        }
        if ($stderr !== '') {
            $chunks[] = "--- stderr ---\n".$stderr;
        }
        if ($stdout === '' && $stderr === '') {
            $chunks[] = '(sem saída)';
        }
        $output = implode("\n", $chunks);
        $parsed = $this->parsePhpunit($stdout."\n".$stderr);
        $exit = $result->exitCode() ?? 1;
        $cgi = $this->looksLikeCgiOutput($stdout."\n".$stderr);
        $ok = ! $cgi && $result->successful() && $parsed['tests'] > 0 && ($parsed['failures'] + $parsed['errors']) === 0;

        if ($ok) {
            $summary = sprintf('Suíte OK — %d testes, %d asserções.', $parsed['tests'], $parsed['assertions']);
        } elseif ($cgi) {
            $summary = 'Suíte não rodou: PHP CGI em vez de CLI (Content-type).';
        } elseif ($parsed['tests'] === 0) {
            $summary = sprintf('Suíte não rodou (exit %d). PHPUnit não executou testes — veja a saída.', $exit);
        } else {
            $summary = sprintf(
                'Suíte falhou (exit %d) — %d testes, %d falhas, %d erros.',
                $exit,
                $parsed['tests'],
                $parsed['failures'],
                $parsed['errors'],
            );
        }

        return [
            'ok' => $ok,
            'summary' => $summary,
            'output' => $output,
            'meta' => array_merge($parsed, [
                'exit_code' => $exit,
                'php' => $php,
            ]),
        ];
    }

    /**
     * @return array{tests: int, assertions: int, failures: int, errors: int}
     */
    private function parsePhpunit(string $output): array
    {
        $tests = 0;
        $assertions = 0;
        $failures = 0;
        $errors = 0;

        if (preg_match('/OK \((\d+) tests?, (\d+) assertions?\)/', $output, $match)) {
            $tests = (int) $match[1];
            $assertions = (int) $match[2];
        }

        if (preg_match('/Tests:\s*(\d+)/', $output, $match)) {
            $tests = (int) $match[1];
        }
        if (preg_match('/Assertions:\s*(\d+)/', $output, $match)) {
            $assertions = (int) $match[1];
        }
        if (preg_match('/Failures:\s*(\d+)/', $output, $match)) {
            $failures = (int) $match[1];
        }
        if (preg_match('/Errors:\s*(\d+)/', $output, $match)) {
            $errors = (int) $match[1];
        }

        return [
            'tests' => $tests,
            'assertions' => $assertions,
            'failures' => $failures,
            'errors' => $errors,
        ];
    }

    private function cliPhpBinary(): ?string
    {
        foreach ($this->cliPhpCandidates() as $bin) {
            if ($this->isCliPhp($bin)) {
                return $bin;
            }
        }

        return null;
    }

    /** @return list<string> */
    private function cliPhpCandidates(): array
    {
        $list = [
            '/usr/bin/php8.4',
            '/usr/bin/php8.4-cli',
            '/usr/bin/php84',
            '/usr/bin/php-cli',
            '/usr/local/bin/php8.4',
            '/usr/local/bin/php',
            '/opt/cpanel/ea-php84/root/usr/bin/php',
            '/opt/alt/php84/usr/bin/php',
            '/usr/bin/php8.3',
            '/opt/cpanel/ea-php83/root/usr/bin/php',
            '/opt/alt/php83/usr/bin/php',
            '/usr/bin/php',
        ];

        foreach ([
            '/opt/cpanel/ea-php*/root/usr/bin/php',
            '/opt/alt/php*/usr/bin/php',
            '/usr/bin/php*[0-9]',
            '/usr/bin/php*-cli',
        ] as $pattern) {
            foreach (glob($pattern) ?: [] as $path) {
                $list[] = $path;
            }
        }

        if (PHP_BINARY !== '') {
            $list[] = PHP_BINARY;
        }

        $unique = [];
        foreach ($list as $bin) {
            if ($bin !== '' && ! isset($unique[$bin])) {
                $unique[$bin] = $bin;
            }
        }

        return array_values($unique);
    }

    private function isCliPhp(string $bin): bool
    {
        if (! is_file($bin) || ! is_executable($bin)) {
            return false;
        }

        $base = strtolower(basename($bin));
        if (str_contains($base, 'fpm') || str_contains($base, 'cgi') || str_contains($base, 'phpdbg') || str_contains($base, 'lsphp')) {
            return false;
        }

        try {
            $result = Process::timeout(8)
                ->env($this->cliIsolatedEnv())
                ->run([$bin, '-n', '-d', 'display_errors=0', '-r', 'echo PHP_SAPI;']);
        } catch (Throwable) {
            return false;
        }

        $lines = preg_split('/\R/', trim($result->output())) ?: [];
        $sapi = strtolower((string) array_pop($lines));

        return $sapi === 'cli';
    }

    private function looksLikeCgiOutput(string $output): bool
    {
        $start = ltrim($output);

        return str_starts_with($start, 'Content-type:')
            || str_starts_with($start, 'Content-Type:');
    }

    /** @return list<string> */
    private function sqliteIniFlags(): array
    {
        $flags = [];

        foreach (['pdo_sqlite', 'sqlite3'] as $extension) {
            $so = base_path('ext/'.$extension.'.so');
            if (is_file($so)) {
                $flags[] = '-d';
                $flags[] = 'extension='.$so;
            }
        }

        return $flags;
    }

    /**
     * @return array<string, string|false>
     */
    private function phpunitEnv(): array
    {
        return $this->cliIsolatedEnv() + [
            'APP_ENV' => 'testing',
            'APP_KEY' => (string) config('app.key'),
            'APP_URL' => 'http://localhost',
            'APP_MAINTENANCE_DRIVER' => 'file',
            'BCRYPT_ROUNDS' => '4',
            'BROADCAST_CONNECTION' => 'null',
            'CACHE_STORE' => 'array',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
            'DB_URL' => '',
            'DB_HOST' => '',
            'DB_PORT' => '',
            'DB_USERNAME' => '',
            'DB_PASSWORD' => '',
            'MAIL_MAILER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'SESSION_DRIVER' => 'array',
            'SESSION_ENCRYPT' => 'false',
            'PULSE_ENABLED' => 'false',
            'TELESCOPE_ENABLED' => 'false',
            'NIGHTWATCH_ENABLED' => 'false',
            'TELEGRAM_BOT_TOKEN' => '',
            'TELEGRAM_WEBHOOK_SECRET' => '',
        ];
    }

    /**
     * CGI vars inherited from php-fpm make /usr/bin/php wrappers exec php-cgi.
     *
     * @return array<string, string|false>
     */
    private function cliIsolatedEnv(): array
    {
        return [
            'PATH' => '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin',
            'HOME' => sys_get_temp_dir(),
            'TMPDIR' => sys_get_temp_dir(),
            'GATEWAY_INTERFACE' => false,
            'FCGI_ROLE' => false,
            'REDIRECT_STATUS' => false,
            'REDIRECT_URL' => false,
            'SCRIPT_FILENAME' => false,
            'SCRIPT_NAME' => false,
            'SCRIPT_URI' => false,
            'SCRIPT_URL' => false,
            'DOCUMENT_ROOT' => false,
            'CONTEXT_DOCUMENT_ROOT' => false,
            'CONTEXT_PREFIX' => false,
            'REQUEST_METHOD' => false,
            'REQUEST_URI' => false,
            'QUERY_STRING' => false,
            'CONTENT_TYPE' => false,
            'CONTENT_LENGTH' => false,
            'PATH_INFO' => false,
            'PATH_TRANSLATED' => false,
            'SERVER_SOFTWARE' => false,
            'SERVER_PROTOCOL' => false,
            'PHP_SELF' => false,
        ];
    }

    /**
     * @return array{ok: bool, summary: string, output: string, meta: array<string, mixed>}
     */
    private function telegramPing(): array
    {
        if (! $this->telegramApi->configured()) {
            return $this->telegramNotConfigured();
        }

        $chatIds = $this->telegramBot->notificationChatIds();
        if ($chatIds === []) {
            return [
                'ok' => false,
                'summary' => 'Nenhum chat de notificação configurado.',
                'output' => 'Defina TELEGRAM_NOTIFY_CHAT_ID ou vincule um admin com /login no bot.',
                'meta' => ['chats' => 0],
            ];
        }

        $text = '🧪 <b>Teste BURI-TI</b> — ping do módulo Testes.'."\n".
            'Se você lê isto, o bot está a enviar mensagens.';

        $sent = 0;
        $failed = [];
        foreach ($chatIds as $chatId) {
            if ($this->telegramApi->sendMessage($chatId, $text)) {
                $sent++;
            } else {
                $failed[] = $chatId;
            }
        }

        $ok = $sent > 0 && $failed === [];

        return [
            'ok' => $ok,
            'summary' => $ok
                ? sprintf('Ping enviado a %d chat(s).', $sent)
                : sprintf('Ping parcial: %d ok, %d falhou.', $sent, count($failed)),
            'output' => 'Chats: '.implode(', ', $chatIds).
                ($failed !== [] ? "\nFalhou: ".implode(', ', $failed) : ''),
            'meta' => [
                'sent' => $sent,
                'failed' => count($failed),
                'chats' => $chatIds,
            ],
        ];
    }

    /**
     * @return array{ok: bool, summary: string, output: string, meta: array<string, mixed>}
     */
    private function telegramResendLast(): array
    {
        if (! $this->telegramApi->configured()) {
            return $this->telegramNotConfigured();
        }

        $message = ContactMessage::query()->latest('id')->first();
        if (! $message) {
            return [
                'ok' => false,
                'summary' => 'Não há mensagem CRM para reenviar.',
                'output' => 'O inbox do site está vazio.',
                'meta' => ['message_id' => null],
            ];
        }

        $this->telegramBot->notifyContactMessage($message);

        return [
            'ok' => true,
            'summary' => sprintf('Reenviada mensagem #%d (%s).', $message->id, $message->name),
            'output' => implode("\n", array_filter([
                'ID: '.$message->id,
                'De: '.$message->name,
                'E-mail: '.$message->email,
                $message->phone ? 'Telefone: '.$message->phone : null,
                'Assunto: '.$message->subject,
                'Chats: '.implode(', ', $this->telegramBot->notificationChatIds()),
            ])),
            'meta' => [
                'message_id' => $message->id,
                'email' => $message->email,
            ],
        ];
    }

    /**
     * @return array{ok: bool, summary: string, output: string, meta: array<string, mixed>}
     */
    private function telegramWebhook(): array
    {
        if (! $this->telegramApi->configured()) {
            return $this->telegramNotConfigured();
        }

        $me = $this->telegramApi->getMe();
        $info = $this->telegramApi->getWebhookInfo();
        $ok = (bool) ($me['ok'] ?? false);

        $username = is_array($me['result'] ?? null) ? (string) ($me['result']['username'] ?? '') : '';
        $url = is_array($info['result'] ?? null) ? (string) ($info['result']['url'] ?? '') : '';
        $lastError = is_array($info['result'] ?? null) ? (string) ($info['result']['last_error_message'] ?? '') : '';

        $lines = [
            'getMe: '.(($me['ok'] ?? false) ? 'ok' : ($me['description'] ?? 'falhou')),
            $username !== '' ? 'Bot: @'.$username : null,
            'getWebhookInfo: '.(($info['ok'] ?? false) ? 'ok' : ($info['description'] ?? 'falhou')),
            'URL: '.($url !== '' ? $url : '(vazio)'),
            $lastError !== '' ? 'Último erro: '.$lastError : 'Último erro: nenhum',
        ];

        return [
            'ok' => $ok,
            'summary' => $ok
                ? ($username !== '' ? 'Bot @'.$username.(filled($url) ? ' com webhook.' : ' sem webhook.') : 'Bot autenticado.')
                : (string) ($me['description'] ?? 'Token inválido ou API indisponível.'),
            'output' => implode("\n", array_filter($lines)),
            'meta' => [
                'username' => $username !== '' ? $username : null,
                'webhook_set' => $url !== '',
                'last_error' => $lastError !== '' ? $lastError : null,
            ],
        ];
    }

    /**
     * @return array{ok: bool, summary: string, output: string, meta: array<string, mixed>}
     */
    private function httpProbe(string $url, string $label): array
    {
        $response = Http::timeout(12)
            ->withHeaders(['User-Agent' => 'BURI-TI QA Lab'])
            ->get($url);

        $status = $response->status();
        $ok = $response->successful();
        $bytes = strlen($response->body());

        return [
            'ok' => $ok,
            'summary' => sprintf('%s → HTTP %d (%s).', $label, $status, $ok ? 'ok' : 'falhou'),
            'output' => implode("\n", [
                'GET '.$url,
                'HTTP '.$status,
                'Bytes: '.$bytes,
            ]),
            'meta' => [
                'url' => $url,
                'status' => $status,
                'bytes' => $bytes,
            ],
        ];
    }

    /**
     * @param  list<array{label: string, route?: string, path?: string}>  $targets
     * @return array{ok: bool, summary: string, output: string, meta: array<string, mixed>}
     */
    private function httpProbePack(array $targets): array
    {
        $checks = [];
        $lines = [];
        $okCount = 0;

        foreach ($targets as $target) {
            $label = (string) ($target['label'] ?? 'GET');
            $probe = $this->httpProbe($this->resolveProbeUrl($target), $label);
            $ok = (bool) ($probe['ok'] ?? false);
            if ($ok) {
                $okCount++;
            }
            $status = $probe['meta']['status'] ?? 0;
            $url = $probe['meta']['url'] ?? '';
            $checks[] = [
                'name' => $label,
                'ok' => $ok,
                'detail' => 'HTTP '.$status,
            ];
            $lines[] = sprintf('%s %s → HTTP %s', $ok ? 'OK' : 'FALHOU', $label, $status);
            $lines[] = '  '.$url;
        }

        $total = count($targets);
        $ok = $total > 0 && $okCount === $total;

        return [
            'ok' => $ok,
            'summary' => sprintf('Telas públicas: %d/%d OK.', $okCount, $total),
            'output' => implode("\n", $lines),
            'meta' => [
                'ok_count' => $okCount,
                'total' => $total,
                'checks' => $checks,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, summary: string, output: string, meta: array<string, mixed>}
     */
    private function moduleHealth(string $label, array $payload): array
    {
        $checks = [];
        $lines = [];

        foreach ($payload['routes'] ?? [] as $name) {
            $name = (string) $name;
            $exists = Route::has($name);
            $checks[] = ['name' => 'Rota '.$name, 'ok' => $exists, 'detail' => $exists ? 'registada' : 'em falta'];
            $lines[] = ($exists ? 'OK' : 'FALHOU').' rota '.$name;
        }

        foreach ($payload['views'] ?? [] as $view) {
            $view = (string) $view;
            $exists = View::exists($view);
            $checks[] = ['name' => 'View '.$view, 'ok' => $exists, 'detail' => $exists ? 'existe' : 'em falta'];
            $lines[] = ($exists ? 'OK' : 'FALHOU').' view '.$view;
        }

        foreach ($payload['counts'] ?? [] as $caption => $class) {
            $caption = (string) $caption;
            try {
                if (! is_string($class) || ! class_exists($class)) {
                    throw new \RuntimeException('modelo desconhecido');
                }
                $count = $class::query()->count();
                $checks[] = ['name' => $caption, 'ok' => true, 'detail' => (string) $count];
                $lines[] = 'OK '.$caption.': '.$count;
            } catch (Throwable $e) {
                $checks[] = ['name' => $caption, 'ok' => false, 'detail' => $e->getMessage()];
                $lines[] = 'FALHOU '.$caption.': '.$e->getMessage();
            }
        }

        $failed = collect($checks)->where('ok', false)->count();
        $ok = $checks !== [] && $failed === 0;

        return [
            'ok' => $ok,
            'summary' => $ok
                ? $label.': '.count($checks).' checagens OK.'
                : $label.': '.$failed.' falha(s) em '.count($checks).' checagens.',
            'output' => implode("\n", $lines),
            'meta' => [
                'failed' => $failed,
                'total' => count($checks),
                'checks' => $checks,
            ],
        ];
    }

    /**
     * @return array{ok: bool, summary: string, output: string, meta: array<string, mixed>}
     */
    private function mailConfig(): array
    {
        $mail = app(MailSettingsService::class);
        $settings = app(SettingService::class);
        $snap = $mail->snapshot();
        $encoded = json_encode($snap, JSON_UNESCAPED_UNICODE);
        $secret = $settings->getSecret('mail_password');

        $checks = [];
        $lines = [];

        foreach (['admin.settings.mail', 'admin.settings.mail.update', 'admin.settings.mail.test', 'admin.settings.mail.inbox', 'admin.mailbox.index'] as $name) {
            $exists = Route::has($name);
            $checks[] = ['name' => 'Rota '.$name, 'ok' => $exists, 'detail' => $exists ? 'registada' : 'em falta'];
            $lines[] = ($exists ? 'OK' : 'FALHOU').' rota '.$name;
        }

        foreach (['admin.settings.mail', 'mail.test', 'admin.mailbox.index'] as $view) {
            $exists = View::exists($view);
            $checks[] = ['name' => 'View '.$view, 'ok' => $exists, 'detail' => $exists ? 'existe' : 'em falta'];
            $lines[] = ($exists ? 'OK' : 'FALHOU').' view '.$view;
        }

        $mailable = class_exists(TestMail::class);
        $checks[] = ['name' => 'Mailable TestMail', 'ok' => $mailable, 'detail' => $mailable ? 'existe' : 'em falta'];
        $lines[] = ($mailable ? 'OK' : 'FALHOU').' mailable TestMail';

        $noPasswordKey = ! array_key_exists('password', $snap) && ! array_key_exists('mail_password', $snap);
        $checks[] = ['name' => 'Snapshot sem senha', 'ok' => $noPasswordKey, 'detail' => $noPasswordKey ? 'ausente' : 'vazou chave'];
        $lines[] = ($noPasswordKey ? 'OK' : 'FALHOU').' snapshot sem senha';

        $leaked = is_string($secret) && $secret !== '' && is_string($encoded) && str_contains($encoded, $secret);
        $checks[] = ['name' => 'Segredo não vaza', 'ok' => ! $leaked, 'detail' => $leaked ? 'texto da senha no snapshot' : 'ok'];
        $lines[] = ($leaked ? 'FALHOU' : 'OK').' segredo não vaza';

        $mailer = (string) ($snap['mailer'] ?? '');
        $checks[] = ['name' => 'Mailer', 'ok' => $mailer !== '', 'detail' => $mailer !== '' ? $mailer : 'vazio'];
        $lines[] = ($mailer !== '' ? 'OK' : 'FALHOU').' mailer '.$mailer;

        $ready = (bool) ($snap['ready'] ?? false);
        $checks[] = ['name' => 'SMTP preenchido', 'ok' => true, 'detail' => $ready ? 'pronto' : 'incompleto'];
        $lines[] = 'OK SMTP '.($ready ? 'pronto' : 'incompleto');

        $hasPassword = (bool) ($snap['has_password'] ?? false);
        $checks[] = ['name' => 'Senha no painel', 'ok' => true, 'detail' => $hasPassword ? 'definida' : 'não definida'];
        $lines[] = 'OK senha '.($hasPassword ? 'definida' : 'não definida');

        $failed = collect($checks)->where('ok', false)->count();
        $ok = $checks !== [] && $failed === 0;

        return [
            'ok' => $ok,
            'summary' => $ok
                ? 'SMTP: overlay e rotas OK (mailer '.$mailer.').'
                : 'SMTP: '.$failed.' falha(s) em '.count($checks).' checagens.',
            'output' => implode("\n", $lines),
            'meta' => [
                'failed' => $failed,
                'total' => count($checks),
                'mailer' => $mailer,
                'ready' => $ready,
                'has_password' => $hasPassword,
                'checks' => $checks,
            ],
        ];
    }

    /**
     * @return array{ok: bool, summary: string, output: string, meta: array<string, mixed>}
     */
    private function testimonialSeals(): array
    {
        $rows = Testimonial::query()->orderBy('id')->get();
        $checks = [];
        $lines = [];
        $sealed = 0;
        $failed = 0;

        foreach ($rows as $row) {
            $code = $row->verification_code ?: '#'.$row->id;
            $status = $row->status->value;

            if ($row->source_digest === null) {
                $checks[] = [
                    'name' => 'Convite '.$code,
                    'ok' => true,
                    'detail' => $status.' (ainda sem selo)',
                ];
                $lines[] = 'OK convite '.$code.': '.$status;

                continue;
            }

            $sealed++;
            $ok = (bool) ($row->authenticity()['ok'] ?? false);
            if (! $ok) {
                $failed++;
            }

            $checks[] = [
                'name' => 'Selo '.$code,
                'ok' => $ok,
                'detail' => $ok ? 'íntegro ('.$status.')' : 'divergente ('.$status.')',
            ];
            $lines[] = ($ok ? 'OK' : 'FALHOU').' '.$code.' '.$status;
        }

        if ($rows->isEmpty()) {
            $checks[] = ['name' => 'Inventário', 'ok' => true, 'detail' => 'nenhum depoimento'];
            $lines[] = 'OK sem depoimentos ainda.';
        }

        $ok = $failed === 0 && $checks !== [];

        return [
            'ok' => $ok,
            'summary' => $failed === 0
                ? 'Selos: '.$sealed.' íntegros em '.$rows->count().' depoimento(s).'
                : 'Selos: '.$failed.' divergência(s) em '.$sealed.' selados.',
            'output' => implode("\n", $lines),
            'meta' => [
                'failed' => $failed,
                'sealed' => $sealed,
                'total' => $rows->count(),
                'checks' => $checks,
            ],
        ];
    }

    /**
     * @return array{ok: bool, summary: string, output: string, meta: array<string, mixed>}
     */
    private function opsHealth(): array
    {
        $checks = [];
        $lines = [];

        try {
            DB::connection()->getPdo();
            DB::select('select 1 as ok');
            $detail = (string) config('database.default');
            $checks[] = ['name' => 'Base de dados', 'ok' => true, 'detail' => $detail];
            $lines[] = 'OK base: '.$detail;
        } catch (Throwable $e) {
            $checks[] = ['name' => 'Base de dados', 'ok' => false, 'detail' => $e->getMessage()];
            $lines[] = 'FALHOU base: '.$e->getMessage();
        }

        try {
            $key = 'qa:ping:'.Str::random(8);
            Cache::put($key, 'ok', 30);
            $hit = Cache::get($key) === 'ok';
            Cache::forget($key);
            $detail = (string) config('cache.default');
            $checks[] = ['name' => 'Cache', 'ok' => $hit, 'detail' => $detail];
            $lines[] = ($hit ? 'OK' : 'FALHOU').' cache: '.$detail;
        } catch (Throwable $e) {
            $checks[] = ['name' => 'Cache', 'ok' => false, 'detail' => $e->getMessage()];
            $lines[] = 'FALHOU cache: '.$e->getMessage();
        }

        try {
            $path = 'qa/ping-'.Str::random(6).'.txt';
            Storage::disk('local')->put($path, 'ok');
            $hit = Storage::disk('local')->get($path) === 'ok';
            Storage::disk('local')->delete($path);
            $checks[] = ['name' => 'Storage local', 'ok' => $hit, 'detail' => 'local'];
            $lines[] = ($hit ? 'OK' : 'FALHOU').' storage local';
        } catch (Throwable $e) {
            $checks[] = ['name' => 'Storage local', 'ok' => false, 'detail' => $e->getMessage()];
            $lines[] = 'FALHOU storage: '.$e->getMessage();
        }

        $appKey = filled(config('app.key'));
        $checks[] = ['name' => 'APP_KEY', 'ok' => $appKey, 'detail' => $appKey ? 'definida' : 'vazia'];
        $lines[] = ($appKey ? 'OK' : 'FALHOU').' APP_KEY';

        $queue = (string) config('queue.default');
        $mail = (string) config('mail.default');
        $session = (string) config('session.driver');
        $checks[] = ['name' => 'Fila', 'ok' => true, 'detail' => $queue];
        $checks[] = ['name' => 'Mail', 'ok' => true, 'detail' => $mail];
        $checks[] = ['name' => 'Sessão', 'ok' => true, 'detail' => $session];
        $lines[] = 'Fila: '.$queue;
        $lines[] = 'Mail: '.$mail;
        $lines[] = 'Sessão: '.$session;

        $telegram = $this->telegramApi->configured();
        $checks[] = ['name' => 'Telegram token', 'ok' => true, 'detail' => $telegram ? 'definido' : 'em falta'];
        $lines[] = 'Telegram token: '.($telegram ? 'definido' : 'em falta');

        $google = $this->google->integrationStatus();
        $checks[] = ['name' => 'Google Agenda', 'ok' => true, 'detail' => (string) ($google['label'] ?? '')];
        $lines[] = 'Google: '.($google['label'] ?? '');

        $failed = collect($checks)->where('ok', false)->count();
        $ok = $failed === 0;

        return [
            'ok' => $ok,
            'summary' => $ok
                ? 'Stack OK — DB, cache, disco e chave.'
                : 'Stack com '.$failed.' falha(s).',
            'output' => implode("\n", $lines),
            'meta' => [
                'failed' => $failed,
                'total' => count($checks),
                'checks' => $checks,
            ],
        ];
    }

    /**
     * @return array{ok: bool, summary: string, output: string, meta: array<string, mixed>}
     */
    private function migrationsStatus(): array
    {
        $migrator = app('migrator');
        $migrator->setConnection(config('database.default'));

        if (! $migrator->repositoryExists()) {
            return [
                'ok' => false,
                'summary' => 'Repositório de migrations em falta.',
                'output' => 'A tabela de migrations não existe.',
                'meta' => ['pending' => null],
            ];
        }

        $files = $migrator->getMigrationFiles([database_path('migrations')]);
        $ran = $migrator->getRepository()->getRan();
        $pending = array_values(array_diff(array_keys($files), $ran));
        $ok = $pending === [];

        $lines = [
            'Corridas: '.count($ran),
            'Ficheiros: '.count($files),
            'Pendentes: '.count($pending),
        ];
        foreach ($pending as $name) {
            $lines[] = '  - '.$name;
        }

        return [
            'ok' => $ok,
            'summary' => $ok
                ? sprintf('Migrations em dia (%d).', count($ran))
                : sprintf('%d migration(s) pendente(s).', count($pending)),
            'output' => implode("\n", $lines),
            'meta' => [
                'ran' => count($ran),
                'files' => count($files),
                'pending' => count($pending),
            ],
        ];
    }

    /**
     * @return array{ok: bool, summary: string, output: string, meta: array<string, mixed>}
     */
    private function googleStatus(): array
    {
        $status = $this->google->integrationStatus();
        $level = (int) ($status['level'] ?? 0);

        $lines = [
            'Nível: '.$level.' — '.($status['label'] ?? ''),
            'OAuth app: '.(($status['oauth_app'] ?? false) ? 'sim' : 'não'),
            'API ligada: '.(($status['api'] ?? false) ? 'sim' : 'não'),
            'Embed: '.(($status['embed'] ?? false) ? 'sim' : 'não'),
            'Atalho: '.(($status['deep_link'] ?? false) ? 'sim' : 'não'),
            'Calendário: '.(($status['calendar_id'] ?? '') !== '' ? $status['calendar_id'] : '(vazio)'),
            'Próximo: '.($status['next_step'] ?? ''),
        ];

        return [
            'ok' => $level >= 1,
            'summary' => 'Google Agenda: '.($status['label'] ?? 'desconhecido'),
            'output' => implode("\n", $lines),
            'meta' => [
                'level' => $level,
                'oauth_app' => (bool) ($status['oauth_app'] ?? false),
                'api' => (bool) ($status['api'] ?? false),
                'embed' => (bool) ($status['embed'] ?? false),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveProbeUrl(array $payload): string
    {
        if (filled($payload['route'] ?? null)) {
            return (string) route((string) $payload['route']);
        }

        $path = (string) ($payload['path'] ?? '/');
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return rtrim((string) config('app.url'), '/').'/'.ltrim($path, '/');
    }

    /**
     * @return array{ok: bool, summary: string, output: string, meta: array<string, mixed>}
     */
    private function telegramNotConfigured(): array
    {
        return [
            'ok' => false,
            'summary' => 'Bot Telegram sem token.',
            'output' => 'Defina o token do bot em Admin → Integrações (ou TELEGRAM_BOT_TOKEN).',
            'meta' => ['configured' => false],
        ];
    }

    private function redact(string $text): string
    {
        foreach ([
            (string) config('services.telegram.bot_token'),
            (string) config('services.telegram.webhook_secret'),
            (string) config('services.google.client_secret'),
            (string) config('services.google.refresh_token'),
            (string) config('services.trello.api_key'),
            (string) config('services.trello.token'),
            (string) config('services.notion.token'),
            (string) app(SettingService::class)->getSecret('mail_password'),
        ] as $secret) {
            if ($secret !== '') {
                $text = str_replace($secret, '***', $text);
            }
        }

        return $text;
    }
}
