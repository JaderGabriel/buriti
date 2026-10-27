<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Services\Telegram\TelegramApiClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class PasswordTwoFactorService
{
    public const SESSION_KEY = 'login_2fa_token';

    public const TTL_SECONDS = 300;

    public const MAX_ATTEMPTS = 5;

    public const RESEND_SECONDS = 45;

    public function __construct(private TelegramApiClient $api) {}

    public function enabled(): bool
    {
        return $this->api->configured();
    }

    public function hasChallenge(Request $request): bool
    {
        $token = $this->tokenFrom($request);

        return is_string($token) && is_array(Cache::get($this->cacheKey($token)));
    }

    /**
     * @return array{ok: true}|array{ok: false, message: string}
     */
    public function start(User $user, bool $remember, Request $request): array
    {
        if (! $this->enabled()) {
            return [
                'ok' => false,
                'message' => 'O Telegram não está configurado para enviar o código de verificação.',
            ];
        }

        $chatId = trim((string) $user->telegram_chat_id);
        if ($chatId === '') {
            return [
                'ok' => false,
                'message' => 'Esta conta ainda não tem Telegram vinculado. No bot, use /login e-mail | senha uma vez e tente de novo.',
            ];
        }

        $issued = $this->issue($user, $remember, $chatId, $request);
        if ($issued === null) {
            return [
                'ok' => false,
                'message' => 'Não foi possível enviar o código no Telegram. Tente de novo em instantes.',
            ];
        }

        return ['ok' => true];
    }

    /**
     * @return array{ok: true}|array{ok: false, message: string, remaining: int}
     */
    public function resend(Request $request): array
    {
        $payload = $this->payload($request);
        if ($payload === null) {
            return [
                'ok' => false,
                'message' => 'A verificação expirou. Entre com usuário e senha de novo.',
                'remaining' => 0,
            ];
        }

        $wait = $this->resendWait($payload);
        if ($wait > 0) {
            return [
                'ok' => false,
                'message' => 'Aguarde '.$wait.'s para reenviar o código.',
                'remaining' => $wait,
            ];
        }

        $user = User::query()->find($payload['user_id'] ?? null);
        $chatId = trim((string) ($user?->telegram_chat_id ?? ''));
        if (! $user || $chatId === '') {
            $this->forget($request);

            return [
                'ok' => false,
                'message' => 'Não foi possível reenviar o código. Entre de novo.',
                'remaining' => 0,
            ];
        }

        $issued = $this->issue($user, (bool) ($payload['remember'] ?? false), $chatId, $request, $this->tokenFrom($request));
        if ($issued === null) {
            return [
                'ok' => false,
                'message' => 'Não foi possível reenviar o código no Telegram. Tente de novo em instantes.',
                'remaining' => self::RESEND_SECONDS,
            ];
        }

        return ['ok' => true];
    }

    /**
     * @return array{ok: true, user: User, remember: bool}|array{ok: false, message: string, locked?: bool}
     */
    public function verify(Request $request, string $code): array
    {
        $token = $this->tokenFrom($request);
        $payload = $this->payload($request);

        if ($token === null || $payload === null) {
            return [
                'ok' => false,
                'message' => 'A verificação expirou. Entre com usuário e senha de novo.',
                'locked' => true,
            ];
        }

        $attempts = (int) ($payload['attempts'] ?? 0);
        if ($attempts >= self::MAX_ATTEMPTS) {
            $this->forget($request);

            return [
                'ok' => false,
                'message' => 'Muitas tentativas. Entre com usuário e senha de novo.',
                'locked' => true,
            ];
        }

        $normalized = preg_replace('/\D+/', '', $code) ?? '';
        $expected = (string) ($payload['hash'] ?? '');
        $matches = strlen($normalized) === 6
            && hash_equals($expected, hash_hmac('sha256', $normalized, $this->hmacKey($token)));

        if (! $matches) {
            $payload['attempts'] = $attempts + 1;
            Cache::put($this->cacheKey($token), $payload, self::TTL_SECONDS);

            $left = self::MAX_ATTEMPTS - (int) $payload['attempts'];
            if ($left <= 0) {
                $this->forget($request);

                return [
                    'ok' => false,
                    'message' => 'Muitas tentativas. Entre com usuário e senha de novo.',
                    'locked' => true,
                ];
            }

            return [
                'ok' => false,
                'message' => 'Código inválido. '.$left.' tentativa'.($left === 1 ? '' : 's').' restante'.($left === 1 ? '' : 's').'.',
            ];
        }

        $user = User::query()->find($payload['user_id'] ?? null);
        if (! $user || ! $user->is_admin || ! $user->is_active) {
            $this->forget($request);

            return [
                'ok' => false,
                'message' => 'Esta conta não pode entrar no painel.',
                'locked' => true,
            ];
        }

        $remember = (bool) ($payload['remember'] ?? false);
        $this->forget($request);

        return [
            'ok' => true,
            'user' => $user,
            'remember' => $remember,
        ];
    }

    public function forget(Request $request): void
    {
        $token = $this->tokenFrom($request);
        if (is_string($token)) {
            Cache::forget($this->cacheKey($token));
        }

        $request->session()->forget(self::SESSION_KEY);
    }

    public function resendWait(array $payload): int
    {
        $sentAt = (int) ($payload['sent_at'] ?? 0);
        $elapsed = max(0, now()->timestamp - $sentAt);

        return max(0, self::RESEND_SECONDS - $elapsed);
    }

    /** @return array<string, mixed>|null */
    public function payload(Request $request): ?array
    {
        $token = $this->tokenFrom($request);
        if (! is_string($token)) {
            return null;
        }

        $payload = Cache::get($this->cacheKey($token));

        return is_array($payload) ? $payload : null;
    }

    private function tokenFrom(Request $request): ?string
    {
        $token = $request->session()->get(self::SESSION_KEY);

        return is_string($token) && $token !== '' ? $token : null;
    }

    /**
     * @return array{token: string, code: string}|null
     */
    private function issue(User $user, bool $remember, string $chatId, Request $request, ?string $existingToken = null): ?array
    {
        $token = $existingToken ?: Str::random(40);
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        if (! $this->sendCode($chatId, $code, $user->name)) {
            return null;
        }

        Cache::put($this->cacheKey($token), [
            'user_id' => $user->id,
            'hash' => hash_hmac('sha256', $code, $this->hmacKey($token)),
            'attempts' => 0,
            'remember' => $remember,
            'sent_at' => now()->timestamp,
        ], self::TTL_SECONDS);

        $request->session()->put(self::SESSION_KEY, $token);

        return ['token' => $token, 'code' => $code];
    }

    private function sendCode(string $chatId, string $code, string $name): bool
    {
        $safeName = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeCode = htmlspecialchars($code, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $text = implode("\n", [
            '🔐 <b>Código de verificação BURI-TI</b>',
            '',
            'Olá, '.$safeName.'.',
            'Use este código de 6 dígitos para entrar no painel:',
            '',
            '<code>'.$safeCode.'</code>',
            '',
            'Válido por 5 minutos. Se não foi você, ignore esta mensagem.',
        ]);

        return $this->api->sendMessage($chatId, $text);
    }

    private function cacheKey(string $token): string
    {
        return 'auth:password-2fa:'.$token;
    }

    private function hmacKey(string $token): string
    {
        return hash('sha256', (string) config('app.key').'|password-2fa|'.$token);
    }
}
