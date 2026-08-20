<?php

namespace App\Support;

class BrazilianDocument
{
    public static function digits(?string $value): string
    {
        return preg_replace('/\D+/', '', (string) $value) ?: '';
    }

    public static function type(?string $value): ?string
    {
        $digits = self::digits($value);

        return match (strlen($digits)) {
            11 => 'cpf',
            14 => 'cnpj',
            default => null,
        };
    }

    public static function format(?string $value): ?string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        // Documentos com letras (estrangeiros / outros) não são mascarados.
        if (preg_match('/[A-Za-z]/', $raw)) {
            return $raw;
        }

        $digits = self::digits($raw);
        if ($digits === '') {
            return $raw;
        }

        if (strlen($digits) <= 11) {
            return self::formatCpfProgressive($digits);
        }

        return self::formatCnpjProgressive(substr($digits, 0, 14));
    }

    public static function normalize(?string $value): ?string
    {
        $formatted = self::format($value);
        if ($formatted === null || $formatted === '') {
            return null;
        }

        return $formatted;
    }

    private static function formatCpfProgressive(string $digits): string
    {
        $digits = substr($digits, 0, 11);
        $len = strlen($digits);

        if ($len <= 3) {
            return $digits;
        }
        if ($len <= 6) {
            return substr($digits, 0, 3).'.'.substr($digits, 3);
        }
        if ($len <= 9) {
            return substr($digits, 0, 3).'.'.substr($digits, 3, 3).'.'.substr($digits, 6);
        }

        return substr($digits, 0, 3).'.'.substr($digits, 3, 3).'.'.substr($digits, 6, 3).'-'.substr($digits, 9);
    }

    private static function formatCnpjProgressive(string $digits): string
    {
        $digits = substr($digits, 0, 14);
        $len = strlen($digits);

        if ($len <= 2) {
            return $digits;
        }
        if ($len <= 5) {
            return substr($digits, 0, 2).'.'.substr($digits, 2);
        }
        if ($len <= 8) {
            return substr($digits, 0, 2).'.'.substr($digits, 2, 3).'.'.substr($digits, 5);
        }
        if ($len <= 12) {
            return substr($digits, 0, 2).'.'.substr($digits, 2, 3).'.'.substr($digits, 5, 3).'/'.substr($digits, 8);
        }

        return substr($digits, 0, 2).'.'
            .substr($digits, 2, 3).'.'
            .substr($digits, 5, 3).'/'
            .substr($digits, 8, 4).'-'
            .substr($digits, 12);
    }
}
