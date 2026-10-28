<?php

namespace Tests\Unit;

use App\Support\SafeOutboundUrl;
use Tests\TestCase;

class SafeOutboundUrlTest extends TestCase
{
    public function test_allows_public_https(): void
    {
        $this->assertSame(
            'https://exemplo.com/icon.png',
            SafeOutboundUrl::validate('https://exemplo.com/icon.png')
        );
    }

    public function test_blocks_loopback_and_metadata(): void
    {
        $this->assertNull(SafeOutboundUrl::validate('http://127.0.0.1/favicon.ico'));
        $this->assertNull(SafeOutboundUrl::validate('http://localhost/favicon.ico'));
        $this->assertNull(SafeOutboundUrl::validate('http://169.254.169.254/latest/meta-data'));
        $this->assertNull(SafeOutboundUrl::validate('http://10.0.0.8/logo.png'));
    }

    public function test_blocks_non_http_schemes_and_odd_ports(): void
    {
        $this->assertNull(SafeOutboundUrl::validate('file:///etc/passwd'));
        $this->assertNull(SafeOutboundUrl::validate('https://exemplo.com:8080/icon.png'));
    }
}
