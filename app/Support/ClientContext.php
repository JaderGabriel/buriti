<?php

namespace App\Support;

use Illuminate\Support\Str;

class ClientContext
{
    public static function locationLabel(?string $ip): string
    {
        $ip = trim((string) $ip);

        if ($ip === '') {
            return '—';
        }

        if ($ip === '127.0.0.1' || $ip === '::1') {
            return 'Localhost';
        }

        if (self::isPrivateIp($ip)) {
            return 'Rede privada / LAN';
        }

        return 'IP público';
    }

    public static function deviceType(?string $userAgent): string
    {
        $ua = strtolower((string) $userAgent);

        if ($ua === '') {
            return 'Desconhecido';
        }

        if (preg_match('/bot|crawl|spider|slurp|facebookexternalhit/i', $ua)) {
            return 'Bot';
        }

        if (preg_match('/ipad|tablet|kindle|playbook|silk|(android(?!.*mobile))/i', $ua)) {
            return 'Tablet';
        }

        if (preg_match('/mobi|iphone|ipod|android.*mobile|windows phone|opera mini/i', $ua)) {
            return 'Mobile';
        }

        return 'Desktop';
    }

    public static function application(?string $userAgent): string
    {
        $ua = (string) $userAgent;

        if (trim($ua) === '') {
            return '—';
        }

        $lower = strtolower($ua);

        if (str_contains($lower, 'telegram')) {
            return 'Telegram';
        }

        if (str_contains($lower, 'postman')) {
            return 'Postman';
        }

        if (str_contains($lower, 'curl/')) {
            return 'cURL';
        }

        if (str_contains($lower, 'phpunit') || str_contains($lower, 'symfony')) {
            return 'Testes / CLI';
        }

        $browser = match (true) {
            str_contains($lower, 'edg/') => 'Edge',
            str_contains($lower, 'opr/') || str_contains($lower, 'opera') => 'Opera',
            str_contains($lower, 'firefox/') => 'Firefox',
            str_contains($lower, 'chrome/') && ! str_contains($lower, 'edg/') => 'Chrome',
            str_contains($lower, 'safari/') && ! str_contains($lower, 'chrome/') => 'Safari',
            default => 'Navegador',
        };

        $os = match (true) {
            str_contains($lower, 'windows') => 'Windows',
            str_contains($lower, 'android') => 'Android',
            str_contains($lower, 'iphone') || str_contains($lower, 'ipad') || str_contains($lower, 'ios') => 'iOS',
            str_contains($lower, 'mac os') || str_contains($lower, 'macintosh') => 'macOS',
            str_contains($lower, 'linux') => 'Linux',
            default => null,
        };

        return $os ? "{$browser} · {$os}" : $browser;
    }

    public static function summarize(?string $userAgent, int $limit = 72): string
    {
        $ua = trim((string) $userAgent);

        return $ua === '' ? '—' : Str::limit($ua, $limit);
    }

    private static function isPrivateIp(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        }

        return false;
    }
}
