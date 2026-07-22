<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

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
        ];
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
