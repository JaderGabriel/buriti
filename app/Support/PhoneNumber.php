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

        $explicitPlus = str_starts_with($raw, '+');
        $digits = preg_replace('/\D+/', '', $raw) ?: '';
        $countries = self::countries()
            ->sortByDesc(fn (array $country) => strlen($country['dial']))
            ->values();
        $fallbackIsBr = strtoupper($fallback['iso']) === 'BR';
        $looksBrNational = self::looksLikeFullNational($digits, 'BR');

        foreach ($countries as $country) {
            $dial = (string) $country['dial'];
            if ($dial === '' || ! str_starts_with($digits, $dial)) {
                continue;
            }

            $national = substr($digits, strlen($dial));
            if (! self::looksLikeFullNational($national, (string) $country['iso'])) {
                continue;
            }

            // Sem "+", 10–11 dígitos no Brasil são DDD+número (ex.: 11 São Paulo),
            // não o DDI 1 dos EUA.
            if (
                $fallbackIsBr
                && $looksBrNational
                && strlen($digits) <= 11
                && ! $explicitPlus
                && strtoupper((string) $country['iso']) !== 'BR'
            ) {
                continue;
            }

            return [
                'iso' => $country['iso'],
                'dial' => $dial,
                'national' => $national,
                'flag' => $country['flag'],
            ];
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

        $local = self::nationalDigits((string) $national, (string) $country['dial'], (string) $country['iso']);
        $dial = (string) $country['dial'];

        if ($local === '') {
            return null;
        }

        return '+'.$dial.' '.self::formatNational($local, $country['iso']);
    }

    public static function format(?string $phone, string $fallbackIso = 'BR'): ?string
    {
        $raw = trim((string) $phone);
        if ($raw === '') {
            return null;
        }

        $parsed = self::parse($raw, $fallbackIso);
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

    /**
     * Dígitos internacionais (DDI + nacional), prontos para tel: e wa.me.
     */
    public static function e164Digits(?string $phone, string $fallbackIso = 'BR'): ?string
    {
        $raw = trim((string) $phone);
        if ($raw === '') {
            return null;
        }

        $parsed = self::parse($raw, $fallbackIso);
        if ($parsed['national'] !== '' && self::looksLikeFullNational($parsed['national'], $parsed['iso'])) {
            return $parsed['dial'].$parsed['national'];
        }

        $digits = self::digits($raw);

        return $digits !== null && strlen($digits) >= 8 ? $digits : null;
    }

    public static function telHref(?string $phone, string $fallbackIso = 'BR'): ?string
    {
        $e164 = self::e164Digits($phone, $fallbackIso);

        return $e164 ? 'tel:+'.$e164 : null;
    }

    public static function whatsappHref(?string $phone, ?string $prefill = null, string $fallbackIso = 'BR'): ?string
    {
        $e164 = self::e164Digits($phone, $fallbackIso);
        if ($e164 === null) {
            return null;
        }

        $url = 'https://wa.me/'.$e164;
        if (filled($prefill)) {
            $url .= '?text='.rawurlencode($prefill);
        }

        return $url;
    }

    /**
     * Número para o texto do Telegram: E.164 contínuo (+DDI DDD assinante).
     * Parênteses e hífen fazem o cliente linkar só os últimos dígitos — a
     * ligação sai sem DDI/DDD. WhatsApp vai em botão; sendContact guarda na agenda.
     */
    public static function telegramHtml(?string $phone, string $fallbackIso = 'BR'): ?string
    {
        $raw = trim((string) $phone);
        if ($raw === '') {
            return null;
        }

        $e164 = self::e164Digits($raw, $fallbackIso);
        $display = $e164 !== null ? '+'.$e164 : (self::format($raw, $fallbackIso) ?? $raw);

        return htmlspecialchars($display, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * vCard 3.0 para sendContact: guardar na agenda com DDI+DDD+número.
     */
    public static function vcard(
        string $fullName,
        ?string $phone,
        ?string $email = null,
        ?string $organization = null,
        string $fallbackIso = 'BR',
    ): ?string {
        $e164 = self::e164Digits($phone, $fallbackIso);
        if ($e164 === null) {
            return null;
        }

        $escape = static function (string $value): string {
            return str_replace(
                ['\\', ';', ',', "\n", "\r"],
                ['\\\\', '\\;', '\\,', '\\n', ''],
                $value,
            );
        };

        $name = trim($fullName) !== '' ? trim($fullName) : 'Contato';
        $lines = [
            'BEGIN:VCARD',
            'VERSION:3.0',
            'FN:'.$escape($name),
            'TEL;TYPE=CELL:+'.$e164,
        ];

        $email = trim((string) $email);
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $lines[] = 'EMAIL:'.$escape($email);
        }

        $organization = trim((string) $organization);
        if ($organization !== '') {
            $lines[] = 'ORG:'.$escape($organization);
        }

        $lines[] = 'END:VCARD';

        return implode("\r\n", $lines);
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

        // Celular: (AA) 9XXXX-XXXX
        if (preg_match('/^(\d{2})(9\d{4})(\d{4})$/', $digits, $m)) {
            return '('.$m[1].') '.$m[2].'-'.$m[3];
        }

        // Fixo: (AA) XXXX-XXXX
        if (preg_match('/^(\d{2})(\d{4})(\d{4})$/', $digits, $m)) {
            return '('.$m[1].') '.$m[2].'-'.$m[3];
        }

        // Progressivo enquanto digita
        $len = strlen($digits);
        if ($len <= 2) {
            return $digits;
        }
        if ($len <= 6) {
            return '('.$digits[0].$digits[1].') '.substr($digits, 2);
        }
        if ($len <= 10) {
            return '('.$digits[0].$digits[1].') '.substr($digits, 2, $len - 6).'-'.substr($digits, -4);
        }

        return '('.$digits[0].$digits[1].') '.substr($digits, 2, 5).'-'.substr($digits, 7, 4);
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
        $dial = (string) ($country['dial'] ?? '55');
        $local = self::nationalDigits($national, $dial, $iso);

        $phone = $local !== '' ? self::compose($iso, $local) : null;

        return [
            'phone_country' => $iso,
            'phone_number' => $local !== '' ? $local : null,
            'phone' => $phone,
        ];
    }

    /**
     * Remove DDI só quando o valor claramente inclui país + número nacional completo.
     * No Brasil o nacional tem 10–11 dígitos (DDD + assinante). Números como DDD 55
     * (ex.: 55998887777) NÃO devem perder o DDD por colisão com o DDI 55.
     */
    private static function nationalDigits(string $national, string $dial, string $iso): string
    {
        $local = preg_replace('/\D+/', '', $national) ?: '';
        $local = ltrim($local, '0');

        if ($dial === '' || ! str_starts_with($local, $dial)) {
            return $local;
        }

        $withoutDial = substr($local, strlen($dial));
        if (self::looksLikeFullNational($withoutDial, $iso)) {
            return $withoutDial;
        }

        return $local;
    }

    private static function looksLikeFullNational(string $national, string $iso): bool
    {
        $length = strlen($national);
        if ($length < 8) {
            return false;
        }

        if (strtoupper($iso) === 'BR') {
            // DDD (2) + fixo (8) ou celular (9) => 10 ou 11. Nunca tratar 8–9 dígitos
            // como nacional completo após “DDI”, senão DDD 55 vira lixo.
            return $length === 10 || $length === 11;
        }

        return $length >= 8;
    }
}
