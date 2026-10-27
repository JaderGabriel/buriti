<?php

namespace App\Services\Auth;

use App\Models\User;

class TotpService
{
    public const SETUP_SESSION_KEY = 'totp_setup_secret';

    public const PERIOD = 30;

    public const DIGITS = 6;

    public const WINDOW = 1;

    public const ISSUER = 'BURI-TI';

    public function generateSecret(): string
    {
        return $this->base32Encode(random_bytes(20));
    }

    public function step(int $timestamp): int
    {
        return intdiv($timestamp, self::PERIOD);
    }

    public function at(string $secret, int $timestamp): string
    {
        return $this->codeForStep($secret, $this->step($timestamp));
    }

    public function verify(string $secret, string $code, ?int $timestamp = null): bool
    {
        return $this->matchingStep($secret, $code, $timestamp) !== null;
    }

    public function matchingStep(string $secret, string $code, ?int $timestamp = null): ?int
    {
        $normalized = preg_replace('/\D+/', '', $code) ?? '';
        if (strlen($normalized) !== self::DIGITS || $secret === '') {
            return null;
        }

        $timestamp ??= time();
        $current = $this->step($timestamp);

        for ($offset = -self::WINDOW; $offset <= self::WINDOW; $offset++) {
            $step = $current + $offset;
            if ($step < 0) {
                continue;
            }

            if (hash_equals($this->codeForStep($secret, $step), $normalized)) {
                return $step;
            }
        }

        return null;
    }

    public function otpauthUri(User $user, string $secret): string
    {
        $issuer = rawurlencode(self::ISSUER);
        $label = $issuer.':'.rawurlencode($user->email);

        return 'otpauth://totp/'.$label.'?secret='.$secret
            .'&issuer='.$issuer
            .'&algorithm=SHA1'
            .'&digits='.self::DIGITS
            .'&period='.self::PERIOD;
    }

    public function groupedSecret(string $secret): string
    {
        return trim(chunk_split($secret, 4, ' '));
    }

    private function codeForStep(string $secret, int $step): string
    {
        $key = $this->base32Decode($secret);
        $counter = pack('N*', 0).pack('N*', $step);
        $hash = hash_hmac('sha1', $counter, $key, true);
        $offset = ord($hash[19]) & 0x0F;
        $truncated = unpack('N', substr($hash, $offset, 4))[1] & 0x7FFFFFFF;

        return str_pad((string) ($truncated % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    private function base32Encode(string $bytes): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $buffer = 0;
        $bits = 0;
        $out = '';

        foreach (str_split($bytes) as $char) {
            $buffer = ($buffer << 8) | ord($char);
            $bits += 8;
            while ($bits >= 5) {
                $bits -= 5;
                $out .= $alphabet[($buffer >> $bits) & 31];
            }
        }

        if ($bits > 0) {
            $out .= $alphabet[($buffer << (5 - $bits)) & 31];
        }

        return $out;
    }

    private function base32Decode(string $secret): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = strtoupper(preg_replace('/[^A-Z2-7]/', '', $secret) ?? '');
        $buffer = 0;
        $bits = 0;
        $out = '';

        foreach (str_split($secret) as $char) {
            $value = strpos($alphabet, $char);
            if ($value === false) {
                continue;
            }
            $buffer = ($buffer << 5) | $value;
            $bits += 5;
            if ($bits >= 8) {
                $bits -= 8;
                $out .= chr(($buffer >> $bits) & 255);
            }
        }

        return $out;
    }
}
