<?php

namespace App\Support;

use App\Models\Testimonial;
use InvalidArgumentException;

final class TestimonialSeal
{
    public const VERSION = 1;

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function canonical(array $payload): string
    {
        $encoded = json_encode(
            self::ksortRecursive($payload),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );

        return $encoded;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function digest(array $payload): string
    {
        return hash('sha256', self::canonical($payload));
    }

    /**
     * @return array<string, mixed>
     */
    public static function sourcePayload(Testimonial $testimonial): array
    {
        return [
            'v' => self::VERSION,
            'kind' => 'testimonial.source',
            'uuid' => (string) $testimonial->uuid,
            'contact_id' => (int) $testimonial->contact_id,
            'stars' => (int) $testimonial->original_stars,
            'body' => (string) $testimonial->original_body,
            'display_name' => (string) $testimonial->original_display_name,
            'submitted_at' => $testimonial->submitted_at?->getTimestamp(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function resultPayload(Testimonial $testimonial): array
    {
        return [
            'v' => self::VERSION,
            'kind' => 'testimonial.result',
            'uuid' => (string) $testimonial->uuid,
            'source_digest' => (string) $testimonial->source_digest,
            'project_id' => $testimonial->project_id !== null ? (int) $testimonial->project_id : null,
            'stars' => $testimonial->published_stars !== null ? (int) $testimonial->published_stars : null,
            'body' => $testimonial->published_body,
            'display_name' => $testimonial->published_display_name,
            'status' => $testimonial->status->value,
            'moderated_at' => $testimonial->moderated_at?->getTimestamp(),
        ];
    }

    public static function mac(string $sourceDigest, string $resultDigest): string
    {
        return hash_hmac('sha256', $sourceDigest.'|'.$resultDigest, self::secret());
    }

    public static function hashInviteToken(string $plain): string
    {
        return hash('sha256', $plain);
    }

    public static function newInviteToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    public static function newVerificationCode(): string
    {
        $alphabet = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
        $raw = random_bytes(8);
        $chars = '';

        for ($i = 0; $i < 8; $i++) {
            $chars .= $alphabet[ord($raw[$i]) % 32];
        }

        return 'BURI-'.substr($chars, 0, 4).'-'.substr($chars, 4, 4);
    }

    public static function fingerprint(string $digest, int $length = 12): string
    {
        return strtoupper(substr($digest, 0, $length));
    }

    /**
     * @return array{
     *     source: bool,
     *     result: bool,
     *     seal: bool,
     *     ok: bool,
     *     source_digest: ?string,
     *     result_digest: ?string,
     *     expected_source: ?string,
     *     expected_result: ?string,
     *     expected_seal: ?string
     * }
     */
    public static function inspect(Testimonial $testimonial): array
    {
        if ($testimonial->submitted_at === null || $testimonial->source_digest === null) {
            return [
                'source' => false,
                'result' => false,
                'seal' => false,
                'ok' => false,
                'source_digest' => $testimonial->source_digest,
                'result_digest' => $testimonial->result_digest,
                'expected_source' => null,
                'expected_result' => null,
                'expected_seal' => null,
            ];
        }

        $expectedSource = self::digest(self::sourcePayload($testimonial));
        $expectedResult = self::digest(self::resultPayload($testimonial));
        $expectedSeal = self::mac($expectedSource, $expectedResult);

        $sourceOk = hash_equals($expectedSource, (string) $testimonial->source_digest);
        $resultOk = $testimonial->result_digest !== null
            && hash_equals($expectedResult, (string) $testimonial->result_digest);
        $sealOk = $testimonial->seal !== null
            && hash_equals($expectedSeal, (string) $testimonial->seal);

        return [
            'source' => $sourceOk,
            'result' => $resultOk,
            'seal' => $sealOk,
            'ok' => $sourceOk && $resultOk && $sealOk,
            'source_digest' => $testimonial->source_digest,
            'result_digest' => $testimonial->result_digest,
            'expected_source' => $expectedSource,
            'expected_result' => $expectedResult,
            'expected_seal' => $expectedSeal,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private static function ksortRecursive(array $payload): array
    {
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = self::ksortRecursive($value);
            }
        }

        ksort($payload);

        return $payload;
    }

    private static function secret(): string
    {
        $key = (string) config('app.key');
        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);
            if ($decoded !== false && $decoded !== '') {
                return $decoded;
            }
        }

        if ($key === '') {
            throw new InvalidArgumentException('APP_KEY is required to seal testimonials.');
        }

        return $key;
    }
}
