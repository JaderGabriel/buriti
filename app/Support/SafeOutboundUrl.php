<?php

namespace App\Support;

final class SafeOutboundUrl
{
    /**
     * Aceita só http(s) para hosts públicos. Bloqueia loopback, link-local,
     * IPs privados e o metadata IMDSv1 (SSRF).
     */
    public static function validate(?string $url, ?string $base = null): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }

        if ($base !== null && $base !== '') {
            $url = self::absolutize($url, $base) ?? $url;
        }

        if (str_starts_with($url, '//')) {
            $url = 'https:'.$url;
        }

        if (! preg_match('#^https?://#i', $url)) {
            return null;
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        $parts = parse_url($url);
        if (! is_array($parts)) {
            return null;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '' || self::isBlockedHost($host)) {
            return null;
        }

        $port = isset($parts['port']) ? (int) $parts['port'] : ($scheme === 'https' ? 443 : 80);
        if (! in_array($port, [80, 443], true)) {
            return null;
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        return $url;
    }

    public static function isBlockedHost(string $host): bool
    {
        $host = strtolower(trim($host, '[]'));
        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.localhost')) {
            return true;
        }

        if (str_contains($host, 'metadata.google') || $host === 'metadata' || str_ends_with($host, '.internal')) {
            return true;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return ! self::isPublicIp($host);
        }

        if (app()->runningUnitTests()) {
            return false;
        }

        $resolved = gethostbyname($host);
        if ($resolved === $host) {
            return true;
        }

        return ! self::isPublicIp($resolved);
    }

    public static function isPublicIp(string $ip): bool
    {
        $flags = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | $flags) !== false) {
            return ! str_starts_with($ip, '169.254.');
        }

        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 | $flags) !== false;
    }

    public static function absolutize(string $href, string $baseUrl): ?string
    {
        $href = trim($href);
        if ($href === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $href)) {
            return $href;
        }

        if (str_starts_with($href, '//')) {
            $scheme = parse_url($baseUrl, PHP_URL_SCHEME) ?: 'https';

            return $scheme.':'.$href;
        }

        $parts = parse_url($baseUrl);
        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }

        $origin = $parts['scheme'].'://'.$parts['host'];
        if (! empty($parts['port'])) {
            $origin .= ':'.$parts['port'];
        }

        if (str_starts_with($href, '/')) {
            return rtrim($origin, '/').$href;
        }

        $basePath = $parts['path'] ?? '/';
        $dir = str_contains($basePath, '/') ? (string) preg_replace('#/[^/]*$#', '/', $basePath) : '/';

        return rtrim($origin, '/').$dir.ltrim($href, '/');
    }
}
