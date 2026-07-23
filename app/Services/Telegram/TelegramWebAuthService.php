<?php

namespace App\Services\Telegram;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class TelegramWebAuthService
{
    private const TTL_SECONDS = 300;

    private const CACHE_PREFIX = 'telegram:web-login:';

    public function __construct(private TelegramApiClient $api) {}

    public function enabled(): bool
    {
        return $this->api->configured() && filled($this->botUsername());
    }

    public function botUsername(): ?string
    {
        $configured = trim((string) config('services.telegram.bot_username', ''));
        if ($configured !== '') {
            return ltrim($configured, '@');
        }

        if (! $this->api->configured()) {
            return null;
        }

        $cached = Cache::get('telegram:bot-username');
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $me = $this->api->getMe();
        if (! ($me['ok'] ?? false)) {
            return null;
        }

        $username = trim((string) data_get($me, 'result.username', ''));
        if ($username === '') {
            return null;
        }

        Cache::put('telegram:bot-username', $username, 3600);

        return $username;
    }

    /** @return array{token: string, deep_link: string, expires_in: int, complete_url: string, status_url: string} */
    public function createChallenge(): array
    {
        $token = Str::random(40);

        Cache::put($this->cacheKey($token), [
            'status' => 'pending',
            'user_id' => null,
            'created_at' => now()->timestamp,
        ], self::TTL_SECONDS);

        return [
            'token' => $token,
            'deep_link' => $this->deepLink($token),
            'expires_in' => self::TTL_SECONDS,
            'complete_url' => route('login.telegram.complete', ['token' => $token]),
            'status_url' => route('login.telegram.status', ['token' => $token]),
        ];
    }

    public function deepLink(string $token): string
    {
        $username = $this->botUsername() ?? 'share';

        return 'https://t.me/'.$username.'?start=weblogin_'.$token;
    }

    /** @return array{status: string, complete_url?: string} */
    public function status(string $token): array
    {
        $payload = Cache::get($this->cacheKey($token));

        if (! is_array($payload)) {
            return ['status' => 'expired'];
        }

        $status = (string) ($payload['status'] ?? 'pending');

        if ($status === 'ready') {
            return [
                'status' => 'ready',
                'complete_url' => route('login.telegram.complete', ['token' => $token]),
            ];
        }

        return ['status' => $status];
    }

    public function approve(string $token, User $user): bool
    {
        $key = $this->cacheKey($token);
        $payload = Cache::get($key);

        if (! is_array($payload) || ($payload['status'] ?? null) !== 'pending') {
            return false;
        }

        Cache::put($key, [
            'status' => 'ready',
            'user_id' => $user->id,
            'created_at' => $payload['created_at'] ?? now()->timestamp,
        ], self::TTL_SECONDS);

        return true;
    }

    public function deny(string $token, string $reason = 'denied'): void
    {
        $key = $this->cacheKey($token);
        $payload = Cache::get($key);

        if (! is_array($payload)) {
            return;
        }

        Cache::put($key, [
            'status' => $reason,
            'user_id' => null,
            'created_at' => $payload['created_at'] ?? now()->timestamp,
        ], self::TTL_SECONDS);
    }

    public function consume(string $token): ?User
    {
        $key = $this->cacheKey($token);
        $payload = Cache::get($key);

        if (! is_array($payload) || ($payload['status'] ?? null) !== 'ready') {
            return null;
        }

        $userId = (int) ($payload['user_id'] ?? 0);
        Cache::forget($key);

        if ($userId <= 0) {
            return null;
        }

        $user = User::query()->find($userId);

        if (! $user || ! $user->is_admin || ! $user->is_active) {
            return null;
        }

        return $user;
    }

    /**
     * Valida o payload do Telegram Login Widget.
     *
     * @param  array<string, mixed>  $data
     * @return array{id: string, first_name?: string, username?: string}|null
     */
    public function verifyWidgetAuth(array $data): ?array
    {
        $hash = (string) ($data['hash'] ?? '');
        if ($hash === '' || ! $this->api->configured()) {
            return null;
        }

        $authDate = (int) ($data['auth_date'] ?? 0);
        if ($authDate < 1 || abs(now()->timestamp - $authDate) > self::TTL_SECONDS * 2) {
            return null;
        }

        $check = collect($data)
            ->except(['hash'])
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->map(fn ($value, $key) => $key.'='.$value)
            ->sortKeys()
            ->implode("\n");

        $secretKey = hash('sha256', (string) config('services.telegram.bot_token'), true);
        $calculated = hash_hmac('sha256', $check, $secretKey);

        if (! hash_equals($calculated, $hash)) {
            return null;
        }

        $id = (string) ($data['id'] ?? '');
        if ($id === '') {
            return null;
        }

        return [
            'id' => $id,
            'first_name' => isset($data['first_name']) ? (string) $data['first_name'] : null,
            'username' => isset($data['username']) ? (string) $data['username'] : null,
        ];
    }

    public function findLinkedAdmin(string $telegramId): ?User
    {
        $user = User::findByTelegramChatId($telegramId);

        if (! $user || ! $user->is_admin || ! $user->is_active) {
            return null;
        }

        return $user;
    }

    private function cacheKey(string $token): string
    {
        return self::CACHE_PREFIX.$token;
    }
}
