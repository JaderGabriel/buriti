<?php

namespace App\Support;

final class RuntimeVersions
{
    /**
     * @return array{laravel: string, php: string, server: string, release: string}
     */
    public static function labels(): array
    {
        return [
            'laravel' => app()->version(),
            'php' => PHP_VERSION,
            'server' => self::server(),
            'release' => self::release(),
        ];
    }

    public static function release(): string
    {
        $version = trim((string) config('buriti.version', '1.5'));
        $codename = trim((string) config('buriti.codename', 'Vereda'));

        return trim('CRM '.$version.' '.$codename);
    }

    private static function server(): string
    {
        $software = trim((string) ($_SERVER['SERVER_SOFTWARE'] ?? ''));
        $lower = strtolower($software);

        if ($software !== '') {
            if (str_contains($lower, 'development server')) {
                return 'PHP built-in';
            }
            if (str_contains($lower, 'nginx') && preg_match('/nginx\/[\d.]+/i', $software, $match)) {
                return $match[0];
            }
            if (str_contains($lower, 'nginx')) {
                return 'nginx';
            }
            if ((str_contains($lower, 'apache') || str_contains($lower, 'httpd'))
                && preg_match('/Apache\/[\d.]+/i', $software, $match)) {
                return $match[0];
            }
            if (str_contains($lower, 'apache') || str_contains($lower, 'httpd')) {
                return 'Apache';
            }
            if (str_contains($lower, 'caddy')) {
                return 'Caddy';
            }

            return strtok($software, ' ') ?: $software;
        }

        return match (true) {
            PHP_SAPI === 'cli-server' => 'PHP built-in',
            str_contains(PHP_SAPI, 'fpm') => 'PHP-FPM',
            PHP_SAPI === 'apache2handler' => 'Apache',
            PHP_SAPI === 'cli' => 'CLI',
            PHP_SAPI !== '' => PHP_SAPI,
            default => 'n/d',
        };
    }
}
