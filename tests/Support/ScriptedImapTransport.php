<?php

namespace Tests\Support;

use App\Mail\ImapTransport;
use RuntimeException;

class ScriptedImapTransport implements ImapTransport
{
    /** @var list<string> */
    public array $writes = [];

    /**
     * @param  list<string>  $replies
     */
    public function __construct(private array $replies, private string $bytes = '') {}

    public function readLine(): string
    {
        if ($this->replies === []) {
            throw new RuntimeException('IMAP script esgotado.');
        }

        return array_shift($this->replies);
    }

    public function readBytes(int $length): string
    {
        $chunk = substr($this->bytes, 0, $length);
        $this->bytes = substr($this->bytes, $length);
        if (strlen($chunk) < $length) {
            throw new RuntimeException('IMAP script sem bytes suficientes.');
        }

        return $chunk;
    }

    public function write(string $data): void
    {
        $this->writes[] = $data;
    }

    public function enableCrypto(): void {}

    public function close(): void {}
}
