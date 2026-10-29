<?php

namespace App\Mail;

final readonly class ImapConfig
{
    public function __construct(
        public string $host,
        public int $port,
        public string $encryption,
        public string $username,
        public string $password,
        public ?string $sentFolder = null,
        public int $timeout = 12,
    ) {}
}
