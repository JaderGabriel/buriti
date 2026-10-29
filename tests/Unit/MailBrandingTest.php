<?php

namespace Tests\Unit;

use App\Support\MailBranding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MailBrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_block_uses_logo_and_icon_channels_without_exposing_numbers(): void
    {
        $block = MailBranding::block("Jader Gabriel\nFundador", 'BURI-TI');

        $this->assertSame('Jader Gabriel', $block['name']);
        $this->assertSame('Fundador', $block['role']);
        $this->assertSame('BURI-TI', $block['company']);
        $this->assertStringContainsString('logo-buriti.png', $block['logo']);

        $ids = array_column($block['channels'], 'id');
        $this->assertSame(['phone', 'whatsapp', 'telegram'], $ids);

        $hrefs = array_column($block['channels'], 'href');
        $this->assertContains('tel:+5538991758416', $hrefs);
        $this->assertTrue(collect($hrefs)->contains(fn (string $href) => str_contains($href, 'wa.me/')));
        $this->assertTrue(collect($hrefs)->contains(fn (string $href) => str_contains($href, 't.me/')));

        foreach ($block['channels'] as $channel) {
            $this->assertContains($channel['label'], ['Telefone', 'WhatsApp', 'Telegram']);
            $this->assertStringNotContainsString('99175', $channel['label']);
            $this->assertStringNotContainsString('@', $channel['label']);
            $this->assertStringContainsString('/images/mail/'.$channel['id'].'.png', $channel['icon']);
        }
    }

    public function test_telegram_href_accepts_url_or_handle(): void
    {
        $this->assertSame('https://t.me/JaderGabriel', MailBranding::telegramHref('https://t.me/JaderGabriel', null));
        $this->assertSame('https://t.me/JaderGabriel', MailBranding::telegramHref(null, '@JaderGabriel'));
        $this->assertNull(MailBranding::telegramHref('https://example.com', 'ab'));
    }

    public function test_personal_text_keeps_name_without_phone_or_email(): void
    {
        $text = MailBranding::personal('Jader', 'contato@exemplo.com', '+55 38 99175-8416');

        $this->assertStringContainsString('Jader', $text);
        $this->assertStringContainsString('BURI-TI · consultoria e software', $text);
        $this->assertStringNotContainsString('99175', $text);
        $this->assertStringNotContainsString('contato@exemplo.com', $text);
    }

    public function test_legal_notice_uses_mailbox_not_null_mx_vanity(): void
    {
        config(['mail.from.address' => 'jadergabriel8@gmail.com']);

        $notice = MailBranding::legalNotice();

        $this->assertStringContainsString('jadergabriel8@gmail.com', $notice);
        $this->assertStringNotContainsString('contato@buriti.dev.br', $notice);
    }
}
