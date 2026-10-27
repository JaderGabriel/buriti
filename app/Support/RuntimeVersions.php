<?php

namespace App\Support;

final class RuntimeVersions
{
    /**
     * Identidade de produto (muda em config/buriti.php e reflecte-se em todo o UI).
     *
     * @return array{name: string, version: string, codename: string, tagline: string, short: string, release: string}
     */
    public static function product(): array
    {
        return [
            'name' => self::name(),
            'version' => self::version(),
            'codename' => self::codename(),
            'tagline' => self::tagline(),
            'short' => self::short(),
            'release' => self::release(),
        ];
    }

    /**
     * @return array{laravel: string, php: string, server: string, name: string, version: string, codename: string, tagline: string, short: string, release: string}
     */
    public static function labels(): array
    {
        return array_merge(self::product(), [
            'laravel' => app()->version(),
            'php' => PHP_VERSION,
            'server' => self::server(),
        ]);
    }

    public static function name(): string
    {
        return trim((string) config('buriti.name', 'BURI-TI'));
    }

    public static function version(): string
    {
        return trim((string) config('buriti.version', '1.7'));
    }

    public static function codename(): string
    {
        return trim((string) config('buriti.codename', 'Igarapé'));
    }

    public static function tagline(): string
    {
        return trim((string) config('buriti.tagline', 'Tecnologia para Pessoas'));
    }

    public static function short(): string
    {
        return trim(self::version().' '.self::codename());
    }

    public static function release(): string
    {
        return trim(self::name().' '.self::short());
    }

    public static function format(string $as = 'release'): string
    {
        return match ($as) {
            'name' => self::name(),
            'version' => self::version(),
            'codename' => self::codename(),
            'tagline' => self::tagline(),
            'short' => self::short(),
            default => self::release(),
        };
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
