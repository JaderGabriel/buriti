<?php

namespace Tests\Unit;

use App\Support\WhatsAppLink;
use Tests\TestCase;

class WhatsAppLinkTest extends TestCase
{
    public function test_builds_href_from_username(): void
    {
        $this->assertSame('https://wa.me/jadergabriel', WhatsAppLink::href('jadergabriel'));
        $this->assertSame('https://wa.me/jadergabriel', WhatsAppLink::href('@JaderGabriel'));
        $this->assertSame('https://wa.me/jadergabriel', WhatsAppLink::href('https://wa.me/jadergabriel'));
    }

    public function test_builds_href_from_phone(): void
    {
        $this->assertSame('https://wa.me/5538991758416', WhatsAppLink::href('+55 38 99175-8416'));
        $this->assertSame('https://wa.me/5538991758416', WhatsAppLink::href('+55 (38) 99175-8416'));
    }

    public function test_normalize_keeps_username_and_formats_phone(): void
    {
        $this->assertSame('jadergabriel', WhatsAppLink::normalize('@JaderGabriel'));
        $this->assertSame('+55 (38) 99175-8416', WhatsAppLink::normalize('+55 38991758416'));
        $this->assertNull(WhatsAppLink::normalize(''));
    }

    public function test_kind_detects_phone_or_username(): void
    {
        $this->assertSame('username', WhatsAppLink::kind('jadergabriel'));
        $this->assertSame('phone', WhatsAppLink::kind('+55 38 99175-8416'));
        $this->assertNull(WhatsAppLink::kind(''));
    }
}
