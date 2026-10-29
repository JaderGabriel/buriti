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
        $rfc822 = str_replace("\n", "\r\n", str_replace("\r\n", "\n", $rfc822));
        if (! str_ends_with($rfc822, "\r\n")) {
            $rfc822 .= "\r\n";
        }

        $this->tag++;
        $tag = 'A'.$this->tag;
        $size = strlen($rfc822);
        $this->transport->write($tag.' APPEND '.self::quote($folder).' ('.$flags.') {'.$size."}\r\n");

        $line = $this->readPossiblyLiteralLine();
        if (! str_starts_with($line, '+')) {
            $lines = str_starts_with($line, $tag.' ') ? [$line] : $this->readUntilTag($tag, [$line]);
            throw new RuntimeException('IMAP APPEND recusado: '.self::sanitize($lines[array_key_last($lines)] ?? ''));
        }

        $this->transport->write($rfc822);
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

    /**
     * @return array{exists: int}
     */
    public function select(string $folder): array
    {
        $lines = $this->command('SELECT '.self::quote(self::safeFolder($folder)));
        $exists = 0;
        foreach ($lines as $line) {
            if (preg_match('/^\* (\d+) EXISTS\b/', $line, $match) === 1) {
                $exists = (int) $match[1];
            }
        }

        return ['exists' => $exists];
    }

    /**
     * @return array{messages: int, unseen: int}
     */
    public function status(string $folder): array
    {
        $lines = $this->command('STATUS '.self::quote(self::safeFolder($folder)).' (MESSAGES UNSEEN)');
        $messages = 0;
        $unseen = 0;
        foreach ($lines as $line) {
            if (preg_match('/MESSAGES (\d+)/i', $line, $match) === 1) {
                $messages = (int) $match[1];
            }
            if (preg_match('/UNSEEN (\d+)/i', $line, $match) === 1) {
                $unseen = (int) $match[1];
            }
        }

        return ['messages' => $messages, 'unseen' => $unseen];
    }

    /**
     * @return array{messages: list<array<string, mixed>>, exists: int, page: int, pages: int}
     */
    public function listMessages(string $folder, int $page = 1, int $perPage = 40, ?string $query = null): array
    {
        $meta = $this->select($folder);
        $exists = $meta['exists'];
        $perPage = max(1, min(50, $perPage));
        $query = is_string($query) ? trim($query) : '';

        if ($query !== '') {
            $uids = $this->searchUids($query);
            $total = count($uids);
            $pages = max(1, (int) ceil($total / $perPage));
            $page = min(max(1, $page), $pages);
            $slice = array_slice(array_reverse($uids), ($page - 1) * $perPage, $perPage);
            $messages = $slice === [] ? [] : $this->fetchListedByUids($slice);

            return ['messages' => $messages, 'exists' => $total, 'page' => $page, 'pages' => $pages];
        }

        $pages = max(1, (int) ceil(max($exists, 1) / $perPage));
        $page = min(max(1, $page), $pages);
        if ($exists < 1) {
            return ['messages' => [], 'exists' => 0, 'page' => 1, 'pages' => 1];
        }

        $end = $exists - (($page - 1) * $perPage);
        $start = max(1, $end - $perPage + 1);
        $fetch = $this->command(
            'FETCH '.$start.':'.$end.' (UID FLAGS BODY.PEEK[HEADER.FIELDS (FROM TO SUBJECT DATE)])'
        );

        return [
            'messages' => array_reverse(self::parseListedMessages($fetch)),
            'exists' => $exists,
            'page' => $page,
            'pages' => $pages,
        ];
    }

    /**
     * @return array{uid: int, flags: list<string>, seen: bool, raw: string, parsed: Rfc822Message}
     */
    public function fetchMessage(string $folder, int $uid): array
    {
        $this->select($folder);
        $uid = self::safeUid($uid);
        $lines = $this->command('UID FETCH '.$uid.' (UID FLAGS BODY.PEEK[])');
        $parsed = self::parseFullMessage($lines);
        if ($parsed === null) {
            throw new RuntimeException('IMAP: mensagem '.$uid.' não encontrada.');
        }

        return $parsed;
    }

    public function addFlags(string $folder, int $uid, string $flag): void
    {
        $this->select($folder);
        $this->command('UID STORE '.self::safeUid($uid).' +FLAGS ('.$flag.')');
    }

    public function removeFlags(string $folder, int $uid, string $flag): void
    {
        $this->select($folder);
        $this->command('UID STORE '.self::safeUid($uid).' -FLAGS ('.$flag.')');
    }

    public function copyUid(string $folder, int $uid, string $destination): void
    {
        $this->select($folder);
        $this->command('UID COPY '.self::safeUid($uid).' '.self::quote(self::safeFolder($destination)));
    }

    public function createFolder(string $name): void
    {
        $this->command('CREATE '.self::quote(self::safeFolder($name)));
    }

    public function expunge(): void
    {
        $this->command('EXPUNGE');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function parseListedMessages(array $lines): array
    {
        $blocks = preg_split('/^\* \d+ FETCH /m', implode("\n", $lines)) ?: [];
        $messages = [];

        foreach ($blocks as $block) {
            if (preg_match('/UID\s+(\d+)/i', $block, $uid) !== 1) {
                continue;
            }

            $flags = [];
            if (preg_match('/FLAGS \(([^)]*)\)/i', $block, $flagMatch) === 1) {
                $flags = preg_split('/\s+/', trim($flagMatch[1])) ?: [];
            }

            $flagBlob = strtolower(implode(' ', $flags).' '.$block);

            $messages[] = [
                'uid' => (int) $uid[1],
                'from' => self::headerValue($block, 'From'),
                'to' => self::headerValue($block, 'To'),
                'subject' => self::headerValue($block, 'Subject'),
                'date' => self::headerValue($block, 'Date'),
                'seen' => str_contains($flagBlob, '\\seen'),
                'flagged' => str_contains($flagBlob, '\\flagged'),
                'draft' => str_contains($flagBlob, '\\draft'),
                'answered' => str_contains($flagBlob, '\\answered'),
            ];
        }

        return $messages;
    }

    /**
     * @param  list<string>  $lines
     * @return array{uid: int, flags: list<string>, seen: bool, raw: string, parsed: Rfc822Message}|null
     */
    public static function parseFullMessage(array $lines): ?array
    {
        $text = implode("\n", $lines);
        if (preg_match('/UID\s+(\d+)/i', $text, $uid) !== 1) {
            return null;
        }

        $raw = '';
        if (preg_match('/BODY(?:\.PEEK)?\[\] \{\d+\}\n(.*)$/s', $text, $match) === 1) {
            $raw = preg_replace('/\)\s*(?:\nA\d+ OK.*)?$/s', '', $match[1]) ?? $match[1];
            $raw = rtrim($raw, ")\n ");
        }

        $seen = str_contains(strtolower($text), '\\seen');

        return [
            'uid' => (int) $uid[1],
            'flags' => [],
            'seen' => $seen,
            'raw' => $raw,
            'parsed' => Rfc822Message::parse($raw),
        ];
    }

    public static function classifyFolder(string $name, string $attrs): string
    {
        $attrs = strtolower($attrs);
        $base = strtolower($name);
        $leaf = strtolower((string) preg_replace('#^.*[./]#', '', $name));

        return match (true) {
            $base === 'inbox' || str_contains($attrs, '\\inbox') => 'inbox',
            str_contains($attrs, '\\sent')
                || str_contains($base, '[gmail]/sent')
                || str_contains($leaf, 'enviados')
                || in_array($leaf, ['sent', 'sent items', 'sent mail', 'sent messages'], true) => 'sent',
            str_contains($attrs, '\\drafts') || in_array($leaf, ['drafts', 'rascunhos'], true) => 'drafts',
            str_contains($attrs, '\\trash') || in_array($leaf, ['trash', 'deleted', 'lixo', 'deleted items'], true) => 'trash',
            str_contains($attrs, '\\junk') || in_array($leaf, ['junk', 'spam'], true) => 'junk',
            str_contains($attrs, '\\archive') || $leaf === 'archive' => 'archive',
            default => 'other',
        };
    }

    public static function folderLabel(string $name, string $role): string
    {
        return match ($role) {
            'inbox' => 'Caixa de entrada',
            'sent' => 'Enviados',
            'drafts' => 'Rascunhos',
            'trash' => 'Lixo',
            'junk' => 'Spam',
            'archive' => 'Arquivo',
            default => $name,
        };
    }

    public static function safeFolder(string $folder): string
    {
        $folder = trim(str_replace(["\r", "\n", "\0"], '', $folder));
        if ($folder === '' || strlen($folder) > 200) {
            throw new RuntimeException('IMAP: pasta inválida.');
        }

        return $folder;
    }

    public static function safeUid(int $uid): int
    {
        if ($uid < 1) {
            throw new RuntimeException('IMAP: uid inválido.');
        }

        return $uid;
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

        $candidates = [
            'Sent',
            'Sent Items',
            'Sent Messages',
            'Sent Mail',
            'INBOX.Sent',
            'Enviados',
            'INBOX.Enviados',
            '[Gmail]/Sent Mail',
            '[Gmail]/E-mails enviados',
            '[Google Mail]/Sent Mail',
        ];
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

    /**
     * @return list<int>
     */
    private function searchUids(string $query): array
    {
        $needle = trim(str_replace(['"', "\r", "\n"], '', $query));
        if ($needle === '') {
            return [];
        }

        $quoted = self::quote($needle);
        $lines = $this->command('UID SEARCH OR FROM '.$quoted.' OR TO '.$quoted.' SUBJECT '.$quoted);
        $uids = [];
        foreach ($lines as $line) {
            if (preg_match('/^\* SEARCH\b(.*)$/i', $line, $match) !== 1) {
                continue;
            }
            foreach (preg_split('/\s+/', trim($match[1])) ?: [] as $token) {
                if (ctype_digit($token)) {
                    $uids[] = (int) $token;
                }
            }
        }

        return $uids;
    }

    /**
     * @param  list<int>  $uids
     * @return list<array<string, mixed>>
     */
    private function fetchListedByUids(array $uids): array
    {
        $set = implode(',', array_map(fn (int $uid) => (string) $uid, $uids));
        $fetch = $this->command(
            'UID FETCH '.$set.' (UID FLAGS BODY.PEEK[HEADER.FIELDS (FROM TO SUBJECT DATE)])'
        );

        return self::parseListedMessages($fetch);
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
