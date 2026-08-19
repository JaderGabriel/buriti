<?php

namespace App\Support;

class WhatsAppLink
{
    public static function href(?string $value, ?string $prefill = null): ?string
    {
        $handle = self::handle($value);
        if ($handle === null) {
            return null;
        }

        $url = 'https://wa.me/'.$handle;
        if (filled($prefill)) {
            $url .= '?text='.rawurlencode($prefill);
        }

        return $url;
    }

    public static function handle(?string $value): ?string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        $raw = (string) preg_replace('#^https?://(?:www\.)?wa\.me/#i', '', $raw);
        $raw = ltrim($raw, '@');
        $raw = explode('?', $raw, 2)[0];
        $raw = trim($raw, '/');

        if (preg_match('/^[A-Za-z][A-Za-z0-9._]{2,29}$/', $raw) === 1) {
            return $raw;
        }

        $digits = preg_replace('/\D+/', '', $raw) ?: '';

        return $digits !== '' ? $digits : null;
    }
}
