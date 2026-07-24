<?php

namespace App\Support;

/**
 * Laravel route:cache is incompatible with APP_URL that includes a path
 * (e.g. https://example.com/public). Cached routes then answer GET / with 405
 * (only HEAD). This guard removes that cache and blocks recreating it.
 */
final class SubdirectoryRouteCacheGuard
{
    public static function appUrlHasPath(?string $url = null): bool
    {
        $url ??= (string) (config('app.url') ?: ($_ENV['APP_URL'] ?? $_SERVER['APP_URL'] ?? ''));
        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && trim($path, '/') !== '';
    }

    /** @return list<string> */
    public static function cachedRouteFiles(): array
    {
        $files = glob(base_path('bootstrap/cache/routes*.php'));

        return is_array($files) ? array_values(array_filter($files, 'is_file')) : [];
    }

    public static function clearIncompatibleCache(): bool
    {
        if (! self::appUrlHasPath()) {
            return false;
        }

        $removed = false;
        foreach (self::cachedRouteFiles() as $file) {
            if (@unlink($file)) {
                $removed = true;
            }
        }

        return $removed;
    }
}
