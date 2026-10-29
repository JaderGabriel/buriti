<?php

namespace Tests\Unit;

use App\Mail\ImapClient;
use Tests\Support\ScriptedImapTransport;
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

        $this->assertSame('[Gmail]/Sent Mail', ImapClient::pickSentFolder([
            ['name' => 'INBOX', 'attrs' => '\\HasNoChildren'],
            ['name' => '[Gmail]/Sent Mail', 'attrs' => '\\HasNoChildren'],
        ]));
        $this->assertSame('sent', ImapClient::classifyFolder('[Gmail]/Sent Mail', ''));
        $this->assertSame('sent', ImapClient::classifyFolder('[Gmail]/E-mails enviados', ''));
    }

    public function test_append_writes_exact_octet_literal(): void
    {
        $transport = new ScriptedImapTransport([
            '+ Ready for literal',
            'A1 OK [APPENDUID 1 2] APPEND completed',
        ]);

        (new ImapClient($transport))->append('Sent', "Subject: Hi\n\nOi");

        $this->assertCount(2, $transport->writes);
        $this->assertMatchesRegularExpression('/^A1 APPEND "Sent" \(\\\\Seen\) \{\d+\}\r\n$/', $transport->writes[0]);
        preg_match('/\{(\d+)\}/', $transport->writes[0], $match);
        $this->assertSame((int) $match[1], strlen($transport->writes[1]));
        $this->assertSame("Subject: Hi\r\n\r\nOi\r\n", $transport->writes[1]);
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

    public function test_list_messages_blank_query_does_not_search(): void
    {
        $transport = new ScriptedImapTransport([
            '* 0 EXISTS',
            'A1 OK SELECT completed',
        ]);

        $listing = (new ImapClient($transport))->listMessages('INBOX', 1, 40, '   ');

        $this->assertSame(0, $listing['exists']);
        $this->assertFalse(str_contains(implode('', $transport->writes), 'SEARCH'));
    }

    public function test_list_messages_query_uses_uid_search(): void
    {
        $transport = new ScriptedImapTransport([
            '* 4 EXISTS',
            'A1 OK SELECT completed',
            '* SEARCH',
            'A2 OK SEARCH completed',
        ]);

        $listing = (new ImapClient($transport))->listMessages('INBOX', 1, 40, 'proposta');

        $this->assertSame(0, $listing['exists']);
        $this->assertTrue(str_contains(implode('', $transport->writes), 'UID SEARCH'));
        $this->assertTrue(str_contains(implode('', $transport->writes), 'FROM "proposta"'));
    }
}
