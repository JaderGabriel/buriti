<?php

namespace Tests\Unit;

use App\Mail\ImapClient;
use App\Mail\ImapConfig;
use App\Services\ImapMailboxStore;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ScriptedImapTransport;
use Tests\TestCase;

class ImapMailboxStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_derived_imap_host_maps_known_providers_and_smtp_prefix(): void
    {
        $this->assertSame('imap.gmail.com', ImapMailboxStore::derivedImapHost('smtp.gmail.com'));
        $this->assertSame('outlook.office365.com', ImapMailboxStore::derivedImapHost('smtp.office365.com'));
        $this->assertSame('imap.mail.buriti.dev.br', ImapMailboxStore::derivedImapHost('smtp.mail.buriti.dev.br'));
        $this->assertSame('mail.buriti.dev.br', ImapMailboxStore::derivedImapHost('mail.buriti.dev.br'));
        $this->assertTrue(ImapMailboxStore::hostSavesSentAutomatically('smtp.gmail.com'));
        $this->assertFalse(ImapMailboxStore::hostSavesSentAutomatically('mail.buriti.dev.br'));
    }

    public function test_store_sent_appends_rfc822_to_special_use_folder(): void
    {
        $settings = app(SettingService::class);
        $settings->putMany([
            'mail_mailer' => 'smtp',
            'mail_host' => 'mail.exemplo.com',
            'mail_username' => 'contato@exemplo.com',
        ]);
        $settings->putSecret('mail_password', 'segredo-imap');

        $transport = new ScriptedImapTransport([
            '* LIST (\\HasNoChildren \\Sent) "/" Sent',
            'A1 OK LIST completed',
            '+',
            'A2 OK APPEND completed',
            '* BYE',
            'A3 OK LOGOUT completed',
        ]);

        $store = app(ImapMailboxStore::class);
        $store->connectUsing(fn (ImapConfig $config) => new ImapClient($transport));

        $raw = "From: contato@exemplo.com\r\nTo: qa@exemplo.com\r\nSubject: Teste\r\n\r\ncorpo";
        $folder = $store->storeSent($raw);

        $this->assertSame('Sent', $folder);
        $this->assertTrue($store->lastArchiveStatus()['stored']);
        $this->assertTrue(str_contains(implode('', $transport->writes), 'APPEND "Sent"'));
        $this->assertTrue(str_contains(implode('', $transport->writes), $raw));
    }

    public function test_should_archive_sent_skips_gmail_because_it_already_saves(): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.gmail.com',
        ]);
        app(SettingService::class)->putMany([
            'mail_username' => 'jadergabriel8@gmail.com',
            'mail_host' => 'smtp.gmail.com',
        ]);
        app(SettingService::class)->putSecret('mail_password', 'app-pass');

        $store = app(ImapMailboxStore::class);

        $this->assertTrue($store->providerSavesSentAutomatically());
        $this->assertFalse($store->shouldArchiveSent());
    }
}
