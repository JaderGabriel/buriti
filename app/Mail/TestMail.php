<?php

namespace App\Mail;

use App\Services\SettingService;
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
        $reply = app(SettingService::class)->get('contact_email');

        return new Envelope(
            from: $from !== '' ? new Address($from, $name !== '' ? $name : $from) : null,
            subject: 'BURI-TI — e-mail de teste',
            replyTo: ($reply && strcasecmp($reply, $from) !== 0) ? [$reply] : [],
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
