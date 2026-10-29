<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

class CrmOutgoingMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $subjectLine,
        public string $body,
        public string $signature = '',
        public ?string $inReplyTo = null,
    ) {}

    public function envelope(): Envelope
    {
        $from = (string) config('mail.from.address');
        $name = (string) config('mail.from.name');

        return new Envelope(
            from: $from !== '' ? new Address($from, $name !== '' ? $name : $from) : null,
            subject: $this->subjectLine,
        );
    }

    public function headers(): Headers
    {
        $inReplyTo = is_string($this->inReplyTo) ? trim($this->inReplyTo) : '';

        return new Headers(
            text: $inReplyTo !== '' ? ['In-Reply-To' => $inReplyTo, 'References' => $inReplyTo] : [],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.crm',
            with: [
                'body' => $this->body,
                'signature' => $this->signature,
            ],
        );
    }
}
