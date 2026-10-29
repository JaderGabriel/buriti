<?php

namespace App\Mail;

use App\Support\MailBranding;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

class CrmOutgoingMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  list<array{path: string, name: string, mime: string}>  $files
     */
    public function __construct(
        public string $subjectLine,
        public string $body,
        public string $signature = '',
        public ?string $inReplyTo = null,
        public array $files = [],
    ) {}

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        return array_values(array_filter(array_map(function (array $file): ?Attachment {
            if (! is_file($file['path'] ?? '')) {
                return null;
            }

            return Attachment::fromPath($file['path'])
                ->as($file['name'] ?: basename($file['path']))
                ->withMime($file['mime'] ?: 'application/octet-stream');
        }, $this->files)));
    }

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
            html: 'mail.crm',
            text: 'mail.crm-text',
            with: [
                'body' => $this->body,
                'signature' => $this->signature,
                'signatureBlock' => filled($this->signature)
                    ? MailBranding::block($this->signature, (string) config('mail.from.name'))
                    : null,
                'fromName' => (string) config('mail.from.name'),
                'fromAddress' => (string) config('mail.from.address'),
                'siteUrl' => MailBranding::siteUrl(),
                'siteHost' => MailBranding::siteHost(),
                'privacyUrl' => MailBranding::privacyUrl(),
                'legalNotice' => MailBranding::legalNotice(),
            ],
        );
    }
}
