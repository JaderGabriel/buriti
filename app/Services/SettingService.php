<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Throwable;

class SettingService
{
    private const CACHE_KEY = 'buriti.settings.all';

    /** @var list<string> */
    public const KEYS = [
        'contact_email',
        'contact_phone',
        'contact_whatsapp',
        'linkedin_url',
        'github_url',
        'telegram_url',
        'telegram_handle',
        'google_calendar_embed',
        'google_calendar_url',
        'google_calendar_id',
        'google_auto_sync',
        'google_client_id',
        'trello_board_id',
        'trello_board_url',
        'trello_list_todo_id',
        'notion_database_id',
        'notion_workspace_url',
        'notion_default_page_url',
        'telegram_allowed_chat_ids',
        'telegram_notify_chat_id',
    ];

    /** @var list<string> */
    public const SECRET_KEYS = [
        'google_client_secret',
        'google_refresh_token',
    ];

    /** @return array<string, string|null> */
    public function all(): array
    {
        try {
            return Cache::remember(self::CACHE_KEY, 60, function () {
                $stored = Setting::query()->pluck('value', 'key')->all();
                $defaults = $this->defaults();

                $merged = [];
                foreach (self::KEYS as $key) {
                    $merged[$key] = $stored[$key] ?? $defaults[$key] ?? null;
                }

                return $merged;
            });
        } catch (\Throwable) {
            return $this->defaults();
        }
    }

    public function get(string $key, ?string $default = null): ?string
    {
        return $this->all()[$key] ?? $default;
    }

    /** @param array<string, string|null> $values */
    public function putMany(array $values): void
    {
        foreach ($values as $key => $value) {
            if (! in_array($key, self::KEYS, true)) {
                continue;
            }

            if ($key === 'google_calendar_embed') {
                $value = $this->normalizeCalendarSrc($value);
            }

            Setting::query()->updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }

        Cache::forget(self::CACHE_KEY);
    }

    /** @return array<string, string|null> */
    public function defaults(): array
    {
        $contact = config('buriti.contact', []);

        return [
            'contact_email' => $contact['email'] ?? null,
            'contact_phone' => $contact['phone'] ?? null,
            'contact_whatsapp' => $contact['whatsapp'] ?? null,
            'linkedin_url' => $contact['linkedin_url'] ?? null,
            'github_url' => $contact['github_url'] ?? null,
            'telegram_url' => $contact['telegram_url'] ?? null,
            'telegram_handle' => $contact['telegram_handle'] ?? null,
            'google_calendar_embed' => null,
            'google_calendar_url' => config('buriti.google_calendar_url'),
            'google_calendar_id' => 'primary',
            'google_auto_sync' => '0',
            'google_client_id' => null,
            'trello_board_id' => null,
            'trello_board_url' => null,
            'trello_list_todo_id' => null,
            'notion_database_id' => null,
            'notion_workspace_url' => null,
            'notion_default_page_url' => null,
            'telegram_allowed_chat_ids' => null,
            'telegram_notify_chat_id' => null,
        ];
    }

    public function autoSyncEnabled(): bool
    {
        return in_array($this->get('google_auto_sync'), ['1', 'true', 'on', 'yes'], true);
    }

    public function getSecret(string $key): ?string
    {
        if (! in_array($key, self::SECRET_KEYS, true)) {
            return null;
        }

        try {
            $value = Setting::query()->where('key', $key)->value('value');
        } catch (Throwable) {
            return null;
        }

        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (Throwable) {
            // Legacy plaintext fallback (e.g. manually inserted values).
            return $value;
        }
    }

    public function putSecret(string $key, ?string $value): void
    {
        if (! in_array($key, self::SECRET_KEYS, true)) {
            return;
        }

        if ($value === null || $value === '') {
            $this->forgetSecret($key);

            return;
        }

        Setting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => Crypt::encryptString($value)]
        );

        Cache::forget(self::CACHE_KEY);
    }

    public function forgetSecret(string $key): void
    {
        if (! in_array($key, self::SECRET_KEYS, true)) {
            return;
        }

        Setting::query()->where('key', $key)->delete();
        Cache::forget(self::CACHE_KEY);
    }

    public function hasSecret(string $key): bool
    {
        return filled($this->getSecret($key));
    }

    public function calendarSrc(): ?string
    {
        return $this->normalizeCalendarSrc($this->get('google_calendar_embed'));
    }

    private function normalizeCalendarSrc(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        if (preg_match('/src=["\']([^"\']+)["\']/i', $value, $matches)) {
            $value = html_entity_decode($matches[1], ENT_QUOTES);
        }

        if (! filter_var($value, FILTER_VALIDATE_URL)) {
            return null;
        }

        $host = parse_url($value, PHP_URL_HOST);

        if (! is_string($host) || ! str_ends_with(strtolower($host), 'google.com')) {
            return null;
        }

        return $value;
    }
}
