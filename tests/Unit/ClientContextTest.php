<?php

namespace Tests\Unit;

use App\Support\ClientContext;
use PHPUnit\Framework\TestCase;

class ClientContextTest extends TestCase
{
    public function test_parses_location_device_and_application(): void
    {
        $this->assertSame('Localhost', ClientContext::locationLabel('127.0.0.1'));
        $this->assertSame('Rede privada / LAN', ClientContext::locationLabel('192.168.1.10'));
        $this->assertSame('IP público', ClientContext::locationLabel('8.8.8.8'));

        $chrome = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
        $this->assertSame('Desktop', ClientContext::deviceType($chrome));
        $this->assertSame('Chrome · Linux', ClientContext::application($chrome));

        $iphone = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';
        $this->assertSame('Mobile', ClientContext::deviceType($iphone));
        $this->assertSame('Safari · iOS', ClientContext::application($iphone));
    }
}
