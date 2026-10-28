<?php

namespace App\Services;

use App\Models\ContactMessage;
use App\Models\QaRun;
use App\Models\User;
use App\Services\Telegram\TelegramApiClient;
use App\Services\Telegram\TelegramBotService;
use App\Support\QaCatalog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Throwable;

class QaLabService
{
    public function __construct(
        private TelegramApiClient $telegramApi,
        private TelegramBotService $telegramBot,
        private AuditLogger $audit,
    ) {}

    public function start(User $user, string $command): QaRun
    {
        $item = QaCatalog::find($command);
        abort_unless($item !== null, 422, 'Comando de teste desconhecido.');

        if ($command === 'suite.phpunit' && $this->suiteIsRunning()) {
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
            $result = match ($run->command) {
                'suite.phpunit' => $this->runSuite(),
                'telegram.ping' => $this->telegramPing(),
                'telegram.resend_last' => $this->telegramResendLast(),
                'telegram.webhook' => $this->telegramWebhook(),
                'health.up' => $this->httpProbe(rtrim((string) config('app.url'), '/').'/up', 'Health /up'),
                'health.home' => $this->httpProbe((string) url('/'), 'Página inicial'),
                'health.login' => $this->httpProbe((string) route('login'), 'Tela de login'),
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
            ->where('command', 'suite.phpunit')
            ->where('status', QaRun::STATUS_RUNNING)
            ->exists();
    }

    /**
     * @return array{ok: bool, summary: string, output: string, meta: array<string, mixed>}
     */
    private function runSuite(): array
    {
        $phpunit = base_path('vendor/bin/phpunit');
        $bin = base_path('bin/test');

        if (! is_file($phpunit)) {
            return [
                'ok' => false,
                'error' => true,
                'summary' => 'PHPUnit não está instalado neste ambiente.',
                'output' => 'vendor/bin/phpunit em falta. Instale as dependências de desenvolvimento (composer install sem --no-dev).',
                'meta' => ['phpunit' => false],
            ];
        }

        set_time_limit(180);

        $result = Process::path(base_path())
            ->timeout(180)
            ->run([is_file($bin) ? $bin : $phpunit, '--colors=never']);

        $output = trim($result->output()."\n".$result->errorOutput());
        $parsed = $this->parsePhpunit($output);
        $ok = $result->successful() && ($parsed['failures'] + $parsed['errors']) === 0;

        $summary = $ok
            ? sprintf('Suíte OK — %d testes, %d asserções.', $parsed['tests'], $parsed['assertions'])
            : sprintf(
                'Suíte falhou (exit %d) — %d testes, %d falhas, %d erros.',
                $result->exitCode() ?? 1,
                $parsed['tests'],
                $parsed['failures'],
                $parsed['errors'],
            );

        return [
            'ok' => $ok,
            'summary' => $summary,
            'output' => $output !== '' ? $output : '(sem saída)',
            'meta' => array_merge($parsed, [
                'exit_code' => $result->exitCode(),
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
     * @return array{ok: bool, summary: string, output: string, meta: array<string, mixed>}
     */
    private function telegramNotConfigured(): array
    {
        return [
            'ok' => false,
            'summary' => 'Bot Telegram sem token.',
            'output' => 'Defina TELEGRAM_BOT_TOKEN no .env.',
            'meta' => ['configured' => false],
        ];
    }

    private function redact(string $text): string
    {
        foreach ([
            (string) config('services.telegram.bot_token'),
            (string) config('services.telegram.webhook_secret'),
        ] as $secret) {
            if ($secret !== '') {
                $text = str_replace($secret, '***', $text);
            }
        }

        return $text;
    }
}
