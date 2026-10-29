<?php

namespace Tests\Unit;

use App\Support\WhatsAppLink;
use Tests\TestCase;

class WhatsAppLinkTest extends TestCase
{
    public function test_builds_href_from_username(): void
    {
        $this->assertSame('https://wa.me/acme_contato', WhatsAppLink::href('acme_contato'));
        $this->assertSame('https://wa.me/acme_contato', WhatsAppLink::href('@Acme_contato'));
        $this->assertSame('https://wa.me/acme_contato', WhatsAppLink::href('https://wa.me/acme_contato'));
    }

    public function test_chat_href_prefers_phone_over_username(): void
    {
        $this->assertSame('https://wa.me/5511999990000', WhatsAppLink::chatHref('buriti_demo', '+55 11 99999-0000'));
        $this->assertSame('https://wa.me/5511988887777', WhatsAppLink::chatHref('+55 11 98888-7777', '+55 11 99999-0000'));
        $this->assertSame('https://wa.me/buriti_demo', WhatsAppLink::chatHref('buriti_demo', null));
    }

    public function test_builds_href_from_phone(): void
    {
        $this->assertSame('https://wa.me/5511999990000', WhatsAppLink::href('+55 11 99999-0000'));
        $this->assertSame('https://wa.me/5511999990000', WhatsAppLink::href('+55 (11) 99999-0000'));
        $this->assertSame('https://wa.me/5511999990000', WhatsAppLink::href('11999990000'));
        $this->assertSame('https://wa.me/5511999990000', WhatsAppLink::href('11 99999-0000'));
    }

    public function test_normalize_keeps_username_and_formats_phone(): void
    {
        $this->assertSame('acme_contato', WhatsAppLink::normalize('@Acme_contato'));
        $this->assertSame('+55 (11) 99999-0000', WhatsAppLink::normalize('+55 11999990000'));
        $this->assertNull(WhatsAppLink::normalize(''));
    }

    public function test_kind_detects_phone_or_username(): void
    {
        $this->assertSame('username', WhatsAppLink::kind('acme_contato'));
        $this->assertSame('phone', WhatsAppLink::kind('+55 11 99999-0000'));
        $this->assertNull(WhatsAppLink::kind(''));
    }
}
