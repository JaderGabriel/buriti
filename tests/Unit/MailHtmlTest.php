<?php

namespace Tests\Unit;

use App\Support\MailHtml;
use Tests\TestCase;

class MailHtmlTest extends TestCase
{
    public function test_plain_text_becomes_paragraphs_and_quotes(): void
    {
        $html = MailHtml::fromPlain("Olá\n\n> citado");

        $this->assertStringContainsString('<p>Olá</p>', $html);
        $this->assertStringContainsString('<blockquote>citado</blockquote>', $html);
    }

    public function test_prepare_wraps_plain_and_sanitizes_html(): void
    {
        $this->assertStringContainsString('<p>Segue o texto.</p>', MailHtml::prepare('Segue o texto.'));
        $this->assertStringContainsString('<strong>x</strong>', MailHtml::prepare('<p><strong>x</strong><script>alert(1)</script></p>'));
    }

    public function test_sanitize_strips_scripts_and_keeps_cid_images(): void
    {
        $html = MailHtml::sanitize('<script>alert(1)</script><p>Oi <strong>Carla</strong></p><img src="cid:img-1" alt="x"><img src="javascript:alert(1)">');

        $this->assertStringContainsString('Oi', $html);
        $this->assertStringContainsString('<strong>Carla</strong>', $html);
        $this->assertStringContainsString('cid:img-1', $html);
        $this->assertStringNotContainsString('<script', strtolower($html));
        $this->assertStringNotContainsString('javascript:', $html);
    }

    public function test_blank_html_is_detected(): void
    {
        $this->assertTrue(MailHtml::isBlank('<p><br></p>'));
        $this->assertTrue(MailHtml::isBlank('   '));
        $this->assertFalse(MailHtml::isBlank('<p>Olá</p>'));
    }

    public function test_to_text_keeps_readable_content(): void
    {
        $this->assertSame("Olá\n\nCarla", MailHtml::toText('<p>Olá</p><p>Carla</p>'));
    }

    public function test_quote_block_is_muted_and_survives_sanitize(): void
    {
        $quote = MailHtml::quoteBlock('Em 01/01/2026, Ana escreveu:', "Olá\nSegue o briefing.");
        $html = MailHtml::sanitize($quote);

        $this->assertStringContainsString('mail-quote', $html);
        $this->assertStringContainsString('Ana escreveu', $html);
        $this->assertStringContainsString('Segue o briefing.', $html);
        $this->assertStringContainsString('font-size:13px', $html);
        $this->assertStringContainsString('Arial', $html);
        $this->assertStringNotContainsString('<script', $html);
    }

    public function test_with_quote_keeps_reply_apart_from_original(): void
    {
        $quote = MailHtml::quoteBlock('Em 01/01/2026, Ana escreveu:', 'Texto antigo.');
        $html = MailHtml::withQuote('<p>Resposta nova.</p>', $quote);

        $this->assertStringContainsString('<p>Resposta nova.</p>', $html);
        $this->assertTrue(strpos($html, 'Resposta nova.') < strpos($html, 'Texto antigo.'));
        $this->assertStringContainsString('class="mail-quote"', $html);
    }
}
