<?php

namespace App\Mail;

use App\Support\MailBranding;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function envelope(): Envelope
    {
        $from = (string) config('mail.from.address');
        $name = (string) config('mail.from.name');

        // Respostas vão para a conta SMTP/IMAP. contact_email do site (ex. contato@buriti.dev.br)
        // não pode ser Reply-To: buriti.dev.br anuncia Null MX e o Gmail recusa a entrega.
        return new Envelope(
            from: $from !== '' ? new Address($from, $name !== '' ? $name : $from) : null,
            subject: 'BURI-TI — e-mail de teste',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.test',
            with: [
                'legalNotice' => MailBranding::legalNotice(),
            ],
        );
    }
}
