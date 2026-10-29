<?php

namespace Tests\Unit;

use App\Mail\Rfc822Message;
use Tests\TestCase;

class Rfc822MessageTest extends TestCase
{
    public function test_parses_plain_text_and_headers(): void
    {
        $raw = "From: Ada <ada@exemplo.com>\r\nTo: contato@buriti.dev.br\r\nSubject: Ola\r\n\r\nCorpo da mensagem.";
        $parsed = Rfc822Message::parse($raw);

        $this->assertSame('Ada <ada@exemplo.com>', $parsed->from());
        $this->assertSame('Ola', $parsed->subject());
        $this->assertSame('Corpo da mensagem.', $parsed->text);
        $this->assertSame('', $parsed->html);
    }

    public function test_prefers_html_in_multipart_alternative(): void
    {
        $raw = <<<'RAW'
From: Ada <ada@exemplo.com>
Subject: Html
Content-Type: multipart/alternative; boundary="b1"

--b1
Content-Type: text/plain; charset=UTF-8

Texto simples
--b1
Content-Type: text/html; charset=UTF-8

<p>Ola <strong>mundo</strong></p>
--b1--
RAW;
        $parsed = Rfc822Message::parse($raw);

        $this->assertSame('Texto simples', $parsed->text);
        $this->assertStringContainsString('<strong>mundo</strong>', $parsed->html);
    }

    public function test_safe_html_strips_script_and_handlers(): void
    {
        $html = Rfc822Message::safeHtml('<p onclick="alert(1)">Oi</p><script>alert(2)</script><a href="javascript:alert(3)">x</a>');

        $this->assertStringContainsString('<p>Oi</p>', $html);
        $this->assertStringNotContainsString('script', $html);
        $this->assertStringNotContainsString('onclick', $html);
        $this->assertStringNotContainsString('javascript:', $html);
    }
}
