<?php

namespace App\Support;

class JirauSafeLink
{
    /** @var list<string> */
    private const DRIVE_HOSTS = [
        'drive.google.com',
        'docs.google.com',
        'drive.usercontent.google.com',
    ];

    /** @var list<string> */
    private const PHOTOS_HOSTS = [
        'photos.google.com',
        'photos.app.goo.gl',
    ];

    /**
     * @return array{url: string, kind: string}|null
     */
    public static function parse(string $raw): ?array
    {
        $url = trim($raw);
        if ($url === '' || mb_strlen($url) > 500) {
            return null;
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        $parts = parse_url($url);
        if (! is_array($parts)) {
            return null;
        }

        if (($parts['scheme'] ?? '') !== 'https') {
            return null;
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '' || str_contains($host, '..')) {
            return null;
        }

        $kind = match (true) {
            self::hostAllowed($host, self::DRIVE_HOSTS) => 'drive',
            self::hostAllowed($host, self::PHOTOS_HOSTS) => 'photos',
            default => null,
        };

        if ($kind === null) {
            return null;
        }

        return [
            'url' => $url,
            'kind' => $kind,
        ];
    }

    /**
     * @return list<array{url: string, kind: string}>
     */
    public static function parseMany(string $blob): array
    {
        $lines = preg_split('/\R+/', $blob) ?: [];
        $out = [];

        foreach ($lines as $line) {
            $parsed = self::parse((string) $line);
            if ($parsed) {
                $out[] = $parsed;
            }
        }

        return $out;
    }

    /** @param  list<string>  $hosts */
    private static function hostAllowed(string $host, array $hosts): bool
    {
        foreach ($hosts as $allowed) {
            if ($host === $allowed || str_ends_with($host, '.'.$allowed)) {
                return true;
            }
        }

        return false;
    }
}
