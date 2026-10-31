<?php

namespace App\Mail;

class Rfc822Message
{
    /**
     * @param  array<string, string>  $headers
     * @param  list<array{name: string, type: string, size: int, content: string}>  $attachments
     */
    public function __construct(
        public array $headers,
        public string $text,
        public string $html,
        public array $attachments,
        public string $raw,
    ) {}

    public static function parse(string $raw): self
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $raw);
        [$headerBlock, $body] = self::splitHeaders($normalized);
        $headers = self::parseHeaders($headerBlock);
        $parsed = self::parseBody($headers, $body);

        return new self(
            headers: $headers,
            text: $parsed['text'],
            html: $parsed['html'],
            attachments: $parsed['attachments'],
            raw: $raw,
        );
    }

    public function header(string $name): string
    {
        return $this->headers[strtolower($name)] ?? '';
    }

    public function from(): string
    {
        return $this->header('from');
    }

    public function to(): string
    {
        return $this->header('to');
    }

    public function cc(): string
    {
        return $this->header('cc');
    }

    public function bcc(): string
    {
        return $this->header('bcc');
    }

    public function subject(): string
    {
        return $this->decode($this->header('subject'));
    }

    public function date(): string
    {
        return $this->header('date');
    }

    public function messageId(): string
    {
        return $this->header('message-id');
    }

    public function preview(int $length = 140): string
    {
        $source = $this->text !== '' ? $this->text : trim(html_entity_decode(strip_tags($this->html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $source = preg_replace('/\s+/', ' ', trim($source)) ?? '';

        return mb_strlen($source) > $length ? mb_substr($source, 0, $length - 1).'…' : $source;
    }

    public static function safeHtml(string $html): string
    {
        $html = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $html) ?? $html;
        $html = preg_replace('#<style\b[^>]*>.*?</style>#is', '', $html) ?? $html;
        $html = preg_replace('#</?(iframe|object|embed|link|meta|form|input|button|base)\b[^>]*>#is', '', $html) ?? $html;
        $html = preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? $html;
        $html = preg_replace('/javascript\s*:/i', '', $html) ?? $html;

        return $html;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function splitHeaders(string $raw): array
    {
        $parts = preg_split("/\n\n/", $raw, 2);

        return [$parts[0] ?? '', $parts[1] ?? ''];
    }

    /**
     * @return array<string, string>
     */
    private static function parseHeaders(string $block): array
    {
        $block = preg_replace("/\n[ \t]+/", ' ', $block) ?? $block;
        $headers = [];

        foreach (preg_split("/\n/", $block) ?: [] as $line) {
            if (! str_contains($line, ':')) {
                continue;
            }
            [$name, $value] = explode(':', $line, 2);
            $key = strtolower(trim($name));
            $decoded = self::decode(trim($value));
            $headers[$key] = isset($headers[$key]) ? $headers[$key].', '.$decoded : $decoded;
        }

        return $headers;
    }

    /**
     * @param  array<string, string>  $headers
     * @return array{text: string, html: string, attachments: list<array{name: string, type: string, size: int, content: string}>}
     */
    private static function parseBody(array $headers, string $body): array
    {
        $type = strtolower($headers['content-type'] ?? 'text/plain');
        $encoding = strtolower($headers['content-transfer-encoding'] ?? '');
        $boundary = self::boundary($headers['content-type'] ?? '');

        if (str_starts_with($type, 'multipart/') && $boundary !== null) {
            return self::parseMultipart($body, $boundary);
        }

        $decoded = self::decodeBody($body, $encoding);
        $charset = self::charset($headers['content-type'] ?? '');
        $decoded = self::toUtf8($decoded, $charset);

        if (str_starts_with($type, 'text/html')) {
            return ['text' => '', 'html' => $decoded, 'attachments' => []];
        }

        if (str_starts_with($type, 'text/')) {
            return ['text' => $decoded, 'html' => '', 'attachments' => []];
        }

        $name = self::filename($headers) ?: 'anexo';

        return [
            'text' => '',
            'html' => '',
            'attachments' => [[
                'name' => $name,
                'type' => strtok($type, ';') ?: 'application/octet-stream',
                'size' => strlen($decoded),
                'content' => $decoded,
            ]],
        ];
    }

    /**
     * @return array{text: string, html: string, attachments: list<array{name: string, type: string, size: int, content: string}>}
     */
    private static function parseMultipart(string $body, string $boundary): array
    {
        $text = '';
        $html = '';
        $attachments = [];
        $chunks = explode('--'.$boundary, $body);

        foreach ($chunks as $chunk) {
            $chunk = trim($chunk);
            if ($chunk === '' || $chunk === '--') {
                continue;
            }

            $part = self::parse($chunk);
            if ($part->html !== '') {
                $html = $html === '' ? $part->html : $html;
            }
            if ($part->text !== '') {
                $text = $text === '' ? $part->text : $text."\n\n".$part->text;
            }
            foreach ($part->attachments as $attachment) {
                $attachments[] = $attachment;
            }

            $disposition = strtolower($part->header('content-disposition'));
            $partType = strtolower($part->header('content-type'));
            if (str_starts_with($disposition, 'attachment') || (str_starts_with($partType, 'application/') && $part->html === '' && $part->text === '')) {
                if ($part->attachments === [] && $part->raw !== '') {
                    $name = self::filename($part->headers) ?: 'anexo';
                    $attachments[] = [
                        'name' => $name,
                        'type' => strtok($partType, ';') ?: 'application/octet-stream',
                        'size' => strlen($part->text),
                        'content' => $part->text,
                    ];
                }
            }
        }

        return ['text' => trim($text), 'html' => $html, 'attachments' => $attachments];
    }

    private static function boundary(string $contentType): ?string
    {
        if (preg_match('/boundary=(?:"([^"]+)"|([^;\s]+))/i', $contentType, $match) !== 1) {
            return null;
        }

        return $match[1] !== '' ? $match[1] : $match[2];
    }

    private static function charset(string $contentType): string
    {
        if (preg_match('/charset=(?:"([^"]+)"|([^;\s]+))/i', $contentType, $match) === 1) {
            return strtoupper($match[1] !== '' ? $match[1] : $match[2]);
        }

        return 'UTF-8';
    }

    /**
     * @param  array<string, string>  $headers
     */
    private static function filename(array $headers): ?string
    {
        $disposition = $headers['content-disposition'] ?? '';
        if (preg_match('/filename\*?=(?:UTF-8\'\')?"?([^";]+)"?/i', $disposition, $match) === 1) {
            return self::decode(urldecode(trim($match[1], '"')));
        }

        $type = $headers['content-type'] ?? '';
        if (preg_match('/name=(?:"([^"]+)"|([^;\s]+))/i', $type, $match) === 1) {
            return self::decode($match[1] !== '' ? $match[1] : $match[2]);
        }

        return null;
    }

    private static function decodeBody(string $body, string $encoding): string
    {
        $body = trim($body);

        return match (true) {
            str_contains($encoding, 'base64') => (string) base64_decode($body, true),
            str_contains($encoding, 'quoted-printable') => quoted_printable_decode($body),
            default => $body,
        };
    }

    private static function toUtf8(string $value, string $charset): string
    {
        if ($value === '' || in_array($charset, ['UTF-8', 'UTF8', 'US-ASCII', ''], true)) {
            return $value;
        }

        $converted = @mb_convert_encoding($value, 'UTF-8', $charset);

        return is_string($converted) ? $converted : $value;
    }

    private static function decode(string $value): string
    {
        if ($value === '') {
            return '';
        }

        return function_exists('mb_decode_mimeheader') ? mb_decode_mimeheader($value) : $value;
    }
}
