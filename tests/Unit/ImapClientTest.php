<?php

namespace Tests\Unit;

use App\Mail\ImapClient;
use Tests\TestCase;

class ImapClientTest extends TestCase
{
    public function test_quote_escapes_slash_and_quotes(): void
    {
        $this->assertSame('"conta@exemplo.com"', ImapClient::quote('conta@exemplo.com'));
        $this->assertSame('"a\\\\b\\"c"', ImapClient::quote('a\\b"c'));
    }

    public function test_parse_list_line_reads_sent_flag_and_quoted_name(): void
    {
        $box = ImapClient::parseListLine('* LIST (\\HasNoChildren \\Sent) "/" "Sent Items"');

        $this->assertNotNull($box);
        $this->assertSame('Sent Items', $box['name']);
        $this->assertStringContainsString('\\Sent', $box['attrs']);
    }

    public function test_pick_sent_folder_prefers_special_use_then_common_names(): void
    {
        $this->assertSame('INBOX.Sent', ImapClient::pickSentFolder([
            ['name' => 'INBOX', 'attrs' => '\\HasNoChildren'],
            ['name' => 'INBOX.Sent', 'attrs' => '\\Sent'],
        ]));

        $this->assertSame('Enviados', ImapClient::pickSentFolder([
            ['name' => 'INBOX', 'attrs' => ''],
            ['name' => 'Enviados', 'attrs' => ''],
        ]));

        $this->assertSame('Sent', ImapClient::pickSentFolder([
            ['name' => 'INBOX', 'attrs' => ''],
        ], 'Sent'));
    }

    public function test_parse_fetch_headers_reads_from_subject_date(): void
    {
        $messages = ImapClient::parseFetchHeaders([
            '* 1 FETCH (BODY[HEADER.FIELDS (FROM SUBJECT DATE)]',
            'From: Ana <ana@exemplo.com>',
            'Subject: Ola',
            'Date: Thu, 29 Oct 2026 01:00:00 -0300',
            ')',
            '* 2 FETCH (BODY[HEADER.FIELDS (FROM SUBJECT DATE)]',
            'From: Bob <bob@exemplo.com>',
            'Subject: Re: Ola',
            'Date: Thu, 29 Oct 2026 02:00:00 -0300',
            ')',
            'A2 OK Fetch completed',
        ]);

        $this->assertCount(2, $messages);
        $this->assertSame('Ana <ana@exemplo.com>', $messages[0]['from']);
        $this->assertSame('Ola', $messages[0]['subject']);
        $this->assertSame('Bob <bob@exemplo.com>', $messages[1]['from']);
    }

    public function test_parse_listed_messages_reads_uid_and_unseen(): void
    {
        $messages = ImapClient::parseListedMessages([
            '* 4 FETCH (UID 88 FLAGS (\\Recent) BODY[HEADER.FIELDS (FROM TO SUBJECT DATE)]',
            'From: Ada <ada@exemplo.com>',
            'Subject: Hello',
            'Date: Thu, 29 Oct 2026 10:00:00 -0300',
            ')',
        ]);

        $this->assertCount(1, $messages);
        $this->assertSame(88, $messages[0]['uid']);
        $this->assertFalse($messages[0]['seen']);
        $this->assertSame('Hello', $messages[0]['subject']);
        $this->assertSame('inbox', ImapClient::classifyFolder('INBOX', ''));
        $this->assertSame('Enviados', ImapClient::folderLabel('Sent', 'sent'));
    }
}
