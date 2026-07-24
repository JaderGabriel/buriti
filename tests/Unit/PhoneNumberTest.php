<?php

namespace Tests\Unit;

use App\Support\PhoneNumber;
use Tests\TestCase;

class PhoneNumberTest extends TestCase
{
    public function test_compose_formats_brazilian_mobile(): void
    {
        $this->assertSame('+55 38 99175-8416', PhoneNumber::compose('BR', '38991758416'));
        $this->assertSame('+55 38 99175-8416', PhoneNumber::compose('BR', '38 99175-8416'));
    }

    public function test_compose_keeps_ddd_55_distinct_from_country_code(): void
    {
        $this->assertSame('+55 55 99888-7777', PhoneNumber::compose('BR', '55998887777'));
        $this->assertSame('+55 55 3333-4444', PhoneNumber::compose('BR', '5533334444'));
        $this->assertSame('+55 55 99888-7777', PhoneNumber::compose('BR', '55 99888-7777'));
    }

    public function test_compose_strips_leading_dial_code_from_national(): void
    {
        $this->assertSame('+55 11 98888-7777', PhoneNumber::compose('BR', '5511988887777'));
        $this->assertSame('+55 55 99888-7777', PhoneNumber::compose('BR', '5555998887777'));
    }

    public function test_format_normalizes_stored_values(): void
    {
        $this->assertSame('+55 38 99175-8416', PhoneNumber::format('+55 38991758416'));
        $this->assertSame('+55 55 99888-7777', PhoneNumber::format('+55 55 99888-7777'));
        $this->assertSame('+55 55 99888-7777', PhoneNumber::format('5555998887777'));
        $this->assertSame('+351 912345678', PhoneNumber::format('+351 912345678'));
    }

    public function test_parse_does_not_treat_ddd_55_as_country_code(): void
    {
        $parsed = PhoneNumber::parse('55998887777', 'BR');

        $this->assertSame('BR', $parsed['iso']);
        $this->assertSame('55', $parsed['dial']);
        $this->assertSame('55998887777', $parsed['national']);
    }

    public function test_normalize_input_from_split_fields(): void
    {
        $normalized = PhoneNumber::normalizeInput([
            'phone_country' => 'BR',
            'phone_number' => '38 99175-8416',
        ]);

        $this->assertSame('BR', $normalized['phone_country']);
        $this->assertSame('38991758416', $normalized['phone_number']);
        $this->assertSame('+55 38 99175-8416', $normalized['phone']);
    }

    public function test_normalize_input_preserves_ddd_55(): void
    {
        $normalized = PhoneNumber::normalizeInput([
            'phone_country' => 'BR',
            'phone_number' => '55 99888-7777',
        ]);

        $this->assertSame('55998887777', $normalized['phone_number']);
        $this->assertSame('+55 55 99888-7777', $normalized['phone']);
    }

    public function test_normalize_input_empty_number_clears_phone(): void
    {
        $normalized = PhoneNumber::normalizeInput([
            'phone_country' => 'BR',
            'phone_number' => '',
        ]);

        $this->assertNull($normalized['phone_number']);
        $this->assertNull($normalized['phone']);
    }
}
