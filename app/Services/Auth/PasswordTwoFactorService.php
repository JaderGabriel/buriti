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

    public function __construct(
        private TelegramApiClient $api,
        private TotpService $totp,
    ) {}

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
        $preferIos = $user->hasIosTwoFactor();
        $telegramAvailable = $this->telegramAvailableFor($user);

        if (! $preferIos && ! $telegramAvailable) {
            if (! $this->enabled()) {
                return [
                    'ok' => false,
                    'message' => 'Configure o Telegram ou vincule o app Senhas do iPhone no perfil para concluir o acesso.',
                ];
            }

            return [
                'ok' => false,
                'message' => 'Esta conta ainda não tem verificação em dois fatores. No perfil, vincule o iPhone (Senhas) ou, no bot, use /login e-mail | senha uma vez.',
            ];
        }

        if ($preferIos) {
            $this->storeChallenge($user, $remember, $request, null, true, $telegramAvailable, false);

            return ['ok' => true];
        }

        $issued = $this->issueTelegram($user, $remember, $request);
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
        if (! $user || ! $this->telegramAvailableFor($user)) {
            return [
                'ok' => false,
                'message' => 'O Telegram não está disponível para esta conta.',
                'remaining' => 0,
            ];
        }

        $issued = $this->issueTelegram($user, (bool) ($payload['remember'] ?? false), $request, $this->tokenFrom($request), $payload);
        if ($issued === null) {
            return [
                'ok' => false,
                'message' => 'Não foi possível enviar o código no Telegram. Tente de novo em instantes.',
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
        $user = User::query()->find($payload['user_id'] ?? null);
        if (! $user || ! $user->is_admin || ! $user->is_active) {
            $this->forget($request);

            return [
                'ok' => false,
                'message' => 'Esta conta não pode entrar no painel.',
                'locked' => true,
            ];
        }

        $matched = strlen($normalized) === 6 && (
            $this->matchesIos($user, $normalized) || $this->matchesTelegram($token, $payload, $normalized)
        );

        if (! $matched) {
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

    public function prefersIos(array $payload): bool
    {
        return (bool) ($payload['prefer_ios'] ?? false);
    }

    public function telegramChannelAvailable(array $payload): bool
    {
        if (array_key_exists('telegram_available', $payload)) {
            return (bool) $payload['telegram_available'];
        }

        return true;
    }

    public function telegramSent(array $payload): bool
    {
        return is_string($payload['hash'] ?? null) && $payload['hash'] !== '';
    }

    /**
     * @param  array<string, mixed>|null  $existing
     * @return array{token: string, code: string}|null
     */
    private function issueTelegram(User $user, bool $remember, Request $request, ?string $existingToken = null, ?array $existing = null): ?array
    {
        $chatId = trim((string) $user->telegram_chat_id);
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        if ($chatId === '' || ! $this->sendCode($chatId, $code, $user->name)) {
            return null;
        }

        $token = $this->storeChallenge(
            $user,
            $remember,
            $request,
            $code,
            (bool) ($existing['prefer_ios'] ?? $user->hasIosTwoFactor()),
            true,
            true,
            $existingToken,
        );

        return ['token' => $token, 'code' => $code];
    }

    private function storeChallenge(
        User $user,
        bool $remember,
        Request $request,
        ?string $telegramCode,
        bool $preferIos,
        bool $telegramAvailable,
        bool $telegramJustSent,
        ?string $existingToken = null,
    ): string {
        $token = $existingToken ?: Str::random(40);
        $hash = null;
        if (is_string($telegramCode) && $telegramCode !== '') {
            $hash = hash_hmac('sha256', $telegramCode, $this->hmacKey($token));
        }

        Cache::put($this->cacheKey($token), [
            'user_id' => $user->id,
            'hash' => $hash,
            'attempts' => 0,
            'remember' => $remember,
            'sent_at' => $telegramJustSent ? now()->timestamp : 0,
            'prefer_ios' => $preferIos,
            'telegram_available' => $telegramAvailable,
        ], self::TTL_SECONDS);

        $request->session()->put(self::SESSION_KEY, $token);

        return $token;
    }

    private function telegramAvailableFor(User $user): bool
    {
        return $this->enabled() && $user->hasTelegramTwoFactor();
    }

    private function matchesIos(User $user, string $code): bool
    {
        if (! $user->hasIosTwoFactor()) {
            return false;
        }

        $secret = (string) $user->totp_secret;
        $step = $this->totp->matchingStep($secret, $code);
        if ($step === null) {
            return false;
        }

        $last = $user->totp_last_step;
        if ($last !== null && $step <= (int) $last) {
            return false;
        }

        $user->forceFill(['totp_last_step' => $step])->save();

        return true;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function matchesTelegram(string $token, array $payload, string $code): bool
    {
        $expected = (string) ($payload['hash'] ?? '');
        if ($expected === '') {
            return false;
        }

        return hash_equals($expected, hash_hmac('sha256', $code, $this->hmacKey($token)));
    }

    private function sendCode(string $chatId, string $code, string $name): bool
    {
        $safeName = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeCode = htmlspecialchars($code, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $verifyUrl = route('login.two-factor', absolute: true);

        $text = implode("\n", [
            '🔐 <b>Código de verificação BURI-TI</b>',
            '',
            $safeCode.' is your BURI-TI verification code.',
            '',
            'Olá, '.$safeName.'.',
            'Toque no código para copiar e volte à tela de login. No iPhone, o teclado pode sugerir o código neste campo.',
            '',
            '<code>'.$safeCode.'</code>',
            '',
            'Válido por 5 minutos. Se não foi você, ignore esta mensagem.',
        ]);

        $replyMarkup = null;
        if (str_starts_with($verifyUrl, 'https://')) {
            $replyMarkup = [
                'inline_keyboard' => [[
                    [
                        'text' => 'Abrir tela de verificação',
                        'url' => $verifyUrl,
                    ],
                ]],
            ];
        }

        return $this->api->sendMessage($chatId, $text, $replyMarkup);
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
