<?php

namespace App\Listeners;

use App\Services\MailboxStore;
use Illuminate\Mail\Events\MessageSent;
use Throwable;

class StoreSentMailCopy
{
    public function __construct(private MailboxStore $mailbox) {}

    public function handle(MessageSent $event): void
    {
        if (! $this->mailbox->shouldArchiveSent()) {
            return;
        }

        try {
            $this->mailbox->storeSent($event->message->toString());
        } catch (Throwable $e) {
            report($e);
        }
    }
}
