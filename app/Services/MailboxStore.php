<?php

namespace App\Services;

interface MailboxStore
{
    public function shouldArchiveSent(): bool;

    public function providerSavesSentAutomatically(): bool;

    public function storeSent(string $rfc822): string;

    /**
     * @return list<array{from: string, subject: string, date: string}>
     */
    public function recentInbox(int $limit = 8): array;

    /**
     * @return array{stored: bool, folder: ?string, error: ?string}
     */
    public function lastArchiveStatus(): array;
}
