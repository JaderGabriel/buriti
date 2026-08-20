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

        if (self::isUsername($raw)) {
            return strtolower($raw);
        }

        $digits = preg_replace('/\D+/', '', $raw) ?: '';

        return $digits !== '' ? $digits : null;
    }

    public static function kind(?string $value): ?string
    {
        $handle = self::handle($value);
        if ($handle === null) {
            return null;
        }

        return preg_match('/^\d+$/', $handle) === 1 ? 'phone' : 'username';
    }

    public static function isUsername(string $value): bool
    {
        $value = ltrim(trim($value), '@');

        return preg_match('/^[A-Za-z][A-Za-z0-9._]{2,29}$/', $value) === 1;
    }

    public static function normalize(?string $value): ?string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        $kind = self::kind($raw);
        $handle = self::handle($raw);
        if ($kind === null || $handle === null) {
            return null;
        }

        if ($kind === 'username') {
            return $handle;
        }

        return PhoneNumber::format('+'.$handle) ?? '+'.$handle;
    }

    public static function label(?string $value): ?string
    {
        $normalized = self::normalize($value);
        if ($normalized === null) {
            return null;
        }

        if (self::kind($normalized) === 'username') {
            return '@'.$normalized;
        }

        return $normalized;
    }
}
