<?php

namespace App\Mail;

use RuntimeException;

class ImapClient
{
    private int $tag = 0;

    public function __construct(private ImapTransport $transport) {}

    public static function connect(ImapConfig $config): self
    {
        $client = new self(StreamImapTransport::connect($config));
        $client->readGreeting();

        if ($config->encryption === 'tls') {
            $client->command('STARTTLS');
            $client->transport->enableCrypto();
        }

        $client->login($config->username, $config->password);

        return $client;
    }

    public function login(string $username, string $password): void
    {
        $this->command('LOGIN '.self::quote($username).' '.self::quote($password));
    }

    /**
     * @return list<array{name: string, attrs: string}>
     */
    public function listMailboxes(): array
    {
        $lines = $this->command('LIST "" "*"');
        $boxes = [];

        foreach ($lines as $line) {
            $parsed = self::parseListLine($line);
            if ($parsed !== null) {
                $boxes[] = $parsed;
            }
        }

        return $boxes;
    }

    public function resolveSentFolder(?string $preferred = null): string
    {
        $boxes = $this->listMailboxes();
        $picked = self::pickSentFolder($boxes, $preferred);

        if ($picked !== null) {
            return $picked;
        }

        $create = $preferred !== null && $preferred !== '' ? $preferred : 'Sent';
        $this->command('CREATE '.self::quote($create));

        return $create;
    }

    public function append(string $folder, string $rfc822, string $flags = '\\Seen'): void
    {
        $this->tag++;
        $tag = 'A'.$this->tag;
        $size = strlen($rfc822);
        $this->transport->write($tag.' APPEND '.self::quote($folder).' ('.$flags.') {'.$size."}\r\n");

        $line = $this->readPossiblyLiteralLine();
        if (! str_starts_with($line, '+')) {
            $lines = str_starts_with($line, $tag.' ') ? [$line] : $this->readUntilTag($tag, [$line]);
            throw new RuntimeException('IMAP APPEND recusado: '.self::sanitize($lines[array_key_last($lines)] ?? ''));
        }

        $this->transport->write($rfc822."\r\n");
        $done = $this->readUntilTag($tag);
        $last = $done[array_key_last($done)] ?? '';
        if (! str_contains($last, ' OK ')) {
            throw new RuntimeException('IMAP APPEND falhou: '.self::sanitize($last));
        }
    }

    /**
     * @return list<array{from: string, subject: string, date: string}>
     */
    public function recentInbox(int $limit = 8): array
    {
        $lines = $this->command('SELECT INBOX');
        $exists = 0;
        foreach ($lines as $line) {
            if (preg_match('/^\* (\d+) EXISTS\b/', $line, $match) === 1) {
                $exists = (int) $match[1];
            }
        }

        if ($exists < 1) {
            return [];
        }

        $limit = max(1, min(20, $limit));
        $start = max(1, $exists - $limit + 1);
        $fetch = $this->command(
            'FETCH '.$start.':'.$exists.' (BODY.PEEK[HEADER.FIELDS (FROM SUBJECT DATE)])'
        );

        $messages = self::parseFetchHeaders($fetch);

        return array_reverse($messages);
    }

    public function logout(): void
    {
        try {
            $this->command('LOGOUT');
        } catch (RuntimeException) {
            // ignore
        }

        $this->transport->close();
    }

    /**
     * @param  list<array{name: string, attrs: string}>  $boxes
     */
    public static function pickSentFolder(array $boxes, ?string $preferred = null): ?string
    {
        $preferred = is_string($preferred) ? trim($preferred) : '';
        if ($preferred !== '') {
            foreach ($boxes as $box) {
                if (strcasecmp($box['name'], $preferred) === 0) {
                    return $box['name'];
                }
            }

            return $preferred;
        }

        foreach ($boxes as $box) {
            if (str_contains(strtolower($box['attrs']), '\\sent')) {
                return $box['name'];
            }
        }

        $candidates = ['Sent', 'Sent Items', 'Sent Messages', 'INBOX.Sent', 'Enviados', 'INBOX.Enviados'];
        foreach ($candidates as $candidate) {
            foreach ($boxes as $box) {
                if (strcasecmp($box['name'], $candidate) === 0) {
                    return $box['name'];
                }
            }
        }

        return null;
    }

    public static function quote(string $value): string
    {
        return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
    }

    public static function parseListLine(string $line): ?array
    {
        if (preg_match('/^\* LIST \((.*)\) (?:"[^"]*"|NIL) (.+)$/i', $line, $match) !== 1) {
            return null;
        }

        return [
            'attrs' => $match[1],
            'name' => self::unquote(trim($match[2])),
        ];
    }

    /**
     * @param  list<string>  $lines
     * @return list<array{from: string, subject: string, date: string}>
     */
    public static function parseFetchHeaders(array $lines): array
    {
        $blocks = preg_split('/^\* \d+ FETCH /mi', implode("\n", $lines)) ?: [];
        $messages = [];

        foreach ($blocks as $block) {
            if (! str_contains($block, 'From:') && ! str_contains($block, 'FROM:')) {
                continue;
            }

            $messages[] = [
                'from' => self::headerValue($block, 'From'),
                'subject' => self::headerValue($block, 'Subject'),
                'date' => self::headerValue($block, 'Date'),
            ];
        }

        return $messages;
    }

    public static function unquote(string $value): string
    {
        $value = trim($value);
        if (str_starts_with($value, '"') && str_ends_with($value, '"')) {
            $value = substr($value, 1, -1);
        }

        return str_replace(['\\"', '\\\\'], ['"', '\\'], $value);
    }

    private function readGreeting(): void
    {
        $line = $this->readPossiblyLiteralLine();
        if (! str_starts_with($line, '* OK') && ! str_starts_with($line, '* PREAUTH')) {
            throw new RuntimeException('IMAP: saudação inesperada.');
        }
    }

    /**
     * @return list<string>
     */
    private function command(string $command): array
    {
        $this->tag++;
        $tag = 'A'.$this->tag;
        $this->transport->write($tag.' '.$command."\r\n");
        $lines = $this->readUntilTag($tag);
        $last = $lines[array_key_last($lines)] ?? '';
        if (! str_contains($last, ' OK ')) {
            throw new RuntimeException('IMAP '.$this->verb($command).' falhou: '.self::sanitize($last));
        }

        return $lines;
    }

    /**
     * @param  list<string>  $seed
     * @return list<string>
     */
    private function readUntilTag(string $tag, array $seed = []): array
    {
        $lines = $seed;
        if ($lines === []) {
            $lines[] = $this->readPossiblyLiteralLine();
        }

        while (! str_starts_with($lines[array_key_last($lines)], $tag.' ')) {
            $lines[] = $this->readPossiblyLiteralLine();
        }

        return $lines;
    }

    private function readPossiblyLiteralLine(): string
    {
        $line = $this->transport->readLine();
        if (preg_match('/\{(\d+)\}\s*$/', $line, $match) !== 1) {
            return $line;
        }

        $body = $this->transport->readBytes((int) $match[1]);
        $rest = $this->transport->readLine();

        return $line."\n".$body."\n".$rest;
    }

    private function verb(string $command): string
    {
        $space = strpos($command, ' ');

        return strtoupper($space === false ? $command : substr($command, 0, $space));
    }

    private static function headerValue(string $block, string $name): string
    {
        if (preg_match('/^'.$name.':\s*(.+)$/im', $block, $match) !== 1) {
            return '';
        }

        $value = trim($match[1]);
        if (function_exists('mb_decode_mimeheader')) {
            $value = mb_decode_mimeheader($value);
        }

        return $value;
    }

    private static function sanitize(string $line): string
    {
        return mb_substr(trim($line), 0, 180);
    }
}
