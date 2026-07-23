<?php

namespace App\Support;

use Illuminate\Support\Collection;

class PhoneNumber
{
    /** @return Collection<int, array{iso: string, name: string, dial: string, flag: string}> */
    public static function countries(): Collection
    {
        return collect(config('countries', []));
    }

    /** @return array{iso: string, name: string, dial: string, flag: string}|null */
    public static function country(?string $iso): ?array
    {
        $iso = strtoupper(trim((string) $iso));

        /** @var array{iso: string, name: string, dial: string, flag: string}|null $match */
        $match = self::countries()->firstWhere('iso', $iso);

        return $match;
    }

    /**
     * @return array{iso: string, dial: string, national: string, flag: string}
     */
    public static function parse(?string $phone, string $fallbackIso = 'BR'): array
    {
        $fallback = self::country($fallbackIso) ?? self::country('BR') ?? [
            'iso' => 'BR',
            'name' => 'Brasil',
            'dial' => '55',
            'flag' => '🇧🇷',
        ];

        $raw = trim((string) $phone);
        if ($raw === '') {
            return [
                'iso' => $fallback['iso'],
                'dial' => $fallback['dial'],
                'national' => '',
                'flag' => $fallback['flag'],
            ];
        }

        $digits = preg_replace('/\D+/', '', $raw) ?: '';
        $countries = self::countries()
            ->sortByDesc(fn (array $country) => strlen($country['dial']))
            ->values();

        foreach ($countries as $country) {
            $dial = (string) $country['dial'];
            if ($dial !== '' && str_starts_with($digits, $dial) && strlen($digits) > strlen($dial) + 6) {
                return [
                    'iso' => $country['iso'],
                    'dial' => $dial,
                    'national' => substr($digits, strlen($dial)),
                    'flag' => $country['flag'],
                ];
            }
        }

        return [
            'iso' => $fallback['iso'],
            'dial' => $fallback['dial'],
            'national' => ltrim($digits, '0'),
            'flag' => $fallback['flag'],
        ];
    }

    public static function compose(?string $iso, ?string $national): ?string
    {
        $country = self::country($iso) ?? self::country('BR');
        if (! $country) {
            return null;
        }

        $local = preg_replace('/\D+/', '', (string) $national) ?: '';
        $local = ltrim($local, '0');
        $dial = (string) $country['dial'];

        if ($local === '') {
            return null;
        }

        if (str_starts_with($local, $dial) && strlen($local) > strlen($dial) + 7) {
            $local = substr($local, strlen($dial));
        }

        return '+'.$dial.' '.self::formatNational($local, $country['iso']);
    }

    public static function format(?string $phone): ?string
    {
        $raw = trim((string) $phone);
        if ($raw === '') {
            return null;
        }

        $parsed = self::parse($raw);
        if ($parsed['national'] === '') {
            return $raw;
        }

        return '+'.$parsed['dial'].' '.self::formatNational($parsed['national'], $parsed['iso']);
    }

    public static function digits(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?: '';

        return $digits !== '' ? $digits : null;
    }

    public static function formatNational(string $national, string $iso = 'BR'): string
    {
        $digits = preg_replace('/\D+/', '', $national) ?: '';
        if ($digits === '') {
            return '';
        }

        if (strtoupper($iso) !== 'BR') {
            return $digits;
        }

        // Celular: AA 9XXXX-XXXX
        if (preg_match('/^(\d{2})(9\d{4})(\d{4})$/', $digits, $m)) {
            return $m[1].' '.$m[2].'-'.$m[3];
        }

        // Fixo: AA XXXX-XXXX
        if (preg_match('/^(\d{2})(\d{4})(\d{4})$/', $digits, $m)) {
            return $m[1].' '.$m[2].'-'.$m[3];
        }

        return $digits;
    }

    /**
     * Normaliza phone_country + phone_number (ou phone legado) no request.
     *
     * @param  array<string, mixed>  $input
     * @return array{phone_country: string, phone_number: ?string, phone: ?string}
     */
    public static function normalizeInput(array $input, string $fallbackIso = 'BR'): array
    {
        $hasSplit = array_key_exists('phone_country', $input) || array_key_exists('phone_number', $input);

        if ($hasSplit) {
            $iso = strtoupper((string) ($input['phone_country'] ?? $fallbackIso));
            $national = (string) ($input['phone_number'] ?? '');
        } else {
            $parsed = self::parse(isset($input['phone']) ? (string) $input['phone'] : null, $fallbackIso);
            $iso = $parsed['iso'];
            $national = $parsed['national'];
        }

        $country = self::country($iso) ?? self::country($fallbackIso) ?? self::country('BR');
        $iso = $country['iso'] ?? 'BR';
        $local = preg_replace('/\D+/', '', $national) ?: '';
        $local = ltrim($local, '0');
        $dial = (string) ($country['dial'] ?? '55');

        if (str_starts_with($local, $dial) && strlen($local) > strlen($dial) + 7) {
            $local = substr($local, strlen($dial));
        }

        $phone = $local !== '' ? self::compose($iso, $local) : null;

        return [
            'phone_country' => $iso,
            'phone_number' => $local !== '' ? $local : null,
            'phone' => $phone,
        ];
    }
}
