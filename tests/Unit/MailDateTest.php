<?php

namespace Tests\Unit;

use App\Support\MailDate;
use Tests\TestCase;

class MailDateTest extends TestCase
{
    public function test_compact_and_full_formats(): void
    {
        $this->assertSame('', MailDate::compact(''));
        $this->assertSame('Hoje não', MailDate::compact('Hoje não'));

        $today = now()->setTime(9, 5);
        $this->assertSame('09:05', MailDate::compact($today->toRfc2822String()));
        $this->assertStringContainsString(' às 09:05', MailDate::full($today->toRfc2822String()));
        $this->assertSame('Ontem', MailDate::compact(now()->subDay()->toRfc2822String()));
    }
}
