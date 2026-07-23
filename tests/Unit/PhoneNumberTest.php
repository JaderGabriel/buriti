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

    public function test_compose_strips_leading_dial_code_from_national(): void
    {
        $this->assertSame('+55 11 98888-7777', PhoneNumber::compose('BR', '5511988887777'));
    }

    public function test_format_normalizes_stored_values(): void
    {
        $this->assertSame('+55 38 99175-8416', PhoneNumber::format('+55 38991758416'));
        $this->assertSame('+351 912345678', PhoneNumber::format('+351 912345678'));
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
