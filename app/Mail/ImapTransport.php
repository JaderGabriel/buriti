<?php

namespace App\Mail;

interface ImapTransport
{
    public function readLine(): string;

    public function readBytes(int $length): string;

    public function write(string $data): void;

    public function enableCrypto(): void;

    public function close(): void;
}
