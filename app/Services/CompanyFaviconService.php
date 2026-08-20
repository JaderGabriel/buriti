<?php

namespace App\Services;

use App\Models\Company;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class CompanyFaviconService
{
    public function __construct(private ProjectFileService $files) {}

    public function syncFromWebsite(Company $company, ?string $previousWebsiteUrl = null): bool
    {
        $website = trim((string) $company->website_url);
        if ($website === '') {
            return false;
        }

        $normalized = $this->normalizeUrl($website);
        if ($normalized === null) {
            return false;
        }

        $previous = $previousWebsiteUrl ? $this->normalizeUrl($previousWebsiteUrl) : null;
        if (
            $previous !== null
            && $this->origin($previous) === $this->origin($normalized)
            && filled($company->logo_path)
        ) {
            return false;
        }

        $icon = $this->downloadFavicon($normalized);
        if ($icon === null) {
            return false;
        }

        $directory = 'companies/logos';
        $filename = $company->id.'-'.Str::lower(Str::random(10)).'.'.$icon['extension'];
        $path = $directory.'/'.$filename;

        Storage::disk('public')->put($path, $icon['body']);

        $this->files->delete($company->logo_path);
        $company->forceFill(['logo_path' => $path])->save();

        return true;
    }

    /** @return array{body: string, extension: string}|null */
    private function downloadFavicon(string $websiteUrl): ?array
    {
        $candidates = $this->candidateIconUrls($websiteUrl);

        foreach ($candidates as $iconUrl) {
            $icon = $this->fetchIcon($iconUrl);
            if ($icon !== null) {
                return $icon;
            }
        }

        return null;
    }

    /** @return list<string> */
    private function candidateIconUrls(string $websiteUrl): array
    {
        $origin = $this->origin($websiteUrl);
        $candidates = [];

        try {
            $response = Http::timeout(8)
                ->withHeaders($this->browserHeaders())
                ->withOptions(['allow_redirects' => true])
                ->get($websiteUrl);

            if ($response->successful()) {
                $html = $response->body();
                $finalUrl = $websiteUrl;
                $finalOrigin = $origin;

                foreach ($this->iconsFromHtml($html, $finalUrl) as $href) {
                    $candidates[] = $href;
                }

                if ($finalOrigin) {
                    $candidates[] = rtrim($finalOrigin, '/').'/favicon.ico';
                    $candidates[] = rtrim($finalOrigin, '/').'/apple-touch-icon.png';
                }
            }
        } catch (Throwable) {
            // continua com fallbacks
        }

        if ($origin) {
            $candidates[] = rtrim($origin, '/').'/favicon.ico';
            $candidates[] = rtrim($origin, '/').'/apple-touch-icon.png';
            $candidates[] = rtrim($origin, '/').'/apple-touch-icon-precomposed.png';
        }

        return array_values(array_unique(array_filter($candidates)));
    }

    /** @return list<string> */
    private function iconsFromHtml(string $html, string $baseUrl): array
    {
        if ($html === '') {
            return [];
        }

        $previous = libxml_use_internal_errors(true);
        $document = new \DOMDocument();
        $loaded = $document->loadHTML($html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            return [];
        }

        $icons = [];
        foreach ($document->getElementsByTagName('link') as $link) {
            if (! $link instanceof \DOMElement) {
                continue;
            }

            $rel = Str::lower(trim($link->getAttribute('rel')));
            if ($rel === '') {
                continue;
            }

            $isIcon = str_contains($rel, 'icon')
                || $rel === 'shortcut icon'
                || str_contains($rel, 'apple-touch-icon');

            if (! $isIcon) {
                continue;
            }

            $href = trim($link->getAttribute('href'));
            if ($href === '' || str_starts_with(Str::lower($href), 'data:')) {
                continue;
            }

            $absolute = $this->absolutize($href, $baseUrl);
            if ($absolute !== null) {
                $icons[] = $absolute;
            }
        }

        return $icons;
    }

    /** @return array{body: string, extension: string}|null */
    private function fetchIcon(string $url): ?array
    {
        try {
            $response = Http::timeout(8)
                ->withHeaders($this->browserHeaders())
                ->withOptions(['allow_redirects' => true])
                ->get($url);

            if (! $response->successful()) {
                return null;
            }

            $body = $response->body();
            if (strlen($body) < 32 || strlen($body) > 2_000_000) {
                return null;
            }

            $mime = Str::lower((string) ($response->header('Content-Type') ?: ''));
            $mime = explode(';', $mime)[0];

            $extension = $this->extensionFromMimeOrBytes($mime, $body, $url);
            if ($extension === null) {
                return null;
            }

            return [
                'body' => $body,
                'extension' => $extension,
            ];
        } catch (Throwable) {
            return null;
        }
    }

    private function extensionFromMimeOrBytes(string $mime, string $body, string $url): ?string
    {
        $map = [
            'image/png' => 'png',
            'image/x-png' => 'png',
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
            'image/x-icon' => 'ico',
            'image/vnd.microsoft.icon' => 'ico',
            'image/ico' => 'ico',
        ];

        if (isset($map[$mime])) {
            return $map[$mime];
        }

        if (str_starts_with($body, "\x89PNG")) {
            return 'png';
        }
        if (str_starts_with($body, "\xFF\xD8\xFF")) {
            return 'jpg';
        }
        if (str_starts_with($body, 'GIF8')) {
            return 'gif';
        }
        if (str_starts_with($body, 'RIFF') && str_contains(substr($body, 0, 16), 'WEBP')) {
            return 'webp';
        }
        if (str_starts_with(ltrim($body), '<svg') || str_contains(substr($body, 0, 200), '<svg')) {
            return 'svg';
        }
        // ICO begins with reserved 0 + type 1
        if (strlen($body) >= 4 && $body[0] === "\x00" && $body[1] === "\x00" && ($body[2] === "\x01" || $body[2] === "\x02")) {
            return 'ico';
        }

        $pathExt = Str::lower(pathinfo(parse_url($url, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION));
        if (in_array($pathExt, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'ico'], true)) {
            return $pathExt === 'jpeg' ? 'jpg' : $pathExt;
        }

        return null;
    }

    private function normalizeUrl(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://'.$url;
        }

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        return $url;
    }

    private function origin(string $url): ?string
    {
        $parts = parse_url($url);
        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }

        $origin = $parts['scheme'].'://'.$parts['host'];
        if (! empty($parts['port'])) {
            $origin .= ':'.$parts['port'];
        }

        return $origin;
    }

    private function absolutize(string $href, string $baseUrl): ?string
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

        $origin = $this->origin($baseUrl);
        if ($origin === null) {
            return null;
        }

        if (str_starts_with($href, '/')) {
            return rtrim($origin, '/').$href;
        }

        $basePath = parse_url($baseUrl, PHP_URL_PATH) ?: '/';
        $dir = str_contains($basePath, '/') ? preg_replace('#/[^/]*$#', '/', $basePath) : '/';

        return rtrim($origin, '/').$dir.ltrim($href, '/');
    }

    /** @return array<string, string> */
    private function browserHeaders(): array
    {
        return [
            'User-Agent' => 'Mozilla/5.0 (compatible; BuritiBot/1.0; +https://buriti.dev.br)',
            'Accept' => 'text/html,application/xhtml+xml,image/avif,image/webp,image/apng,image/*,*/*;q=0.8',
        ];
    }
}
