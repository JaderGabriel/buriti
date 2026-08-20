<?php

namespace Tests\Unit;

use App\Support\BrazilianDocument;
use Tests\TestCase;

class BrazilianDocumentTest extends TestCase
{
    public function test_formats_cpf(): void
    {
        $this->assertSame('529.982.247-25', BrazilianDocument::format('52998224725'));
        $this->assertSame('529.982.247-25', BrazilianDocument::format('529.982.247-25'));
        $this->assertSame('529.982', BrazilianDocument::format('529982'));
    }

    public function test_formats_cnpj(): void
    {
        $this->assertSame('11.222.333/0001-81', BrazilianDocument::format('11222333000181'));
        $this->assertSame('11.222.333/0001-81', BrazilianDocument::format('11.222.333/0001-81'));
        $this->assertSame('11.222.333/0001', BrazilianDocument::format('112223330001'));
    }

    public function test_detects_type(): void
    {
        $this->assertSame('cpf', BrazilianDocument::type('529.982.247-25'));
        $this->assertSame('cnpj', BrazilianDocument::type('11222333000181'));
        $this->assertNull(BrazilianDocument::type('123'));
    }

    public function test_keeps_alphanumeric_documents(): void
    {
        $this->assertSame('AB-12345', BrazilianDocument::format('AB-12345'));
    }

    public function test_normalize_empty_returns_null(): void
    {
        $this->assertNull(BrazilianDocument::normalize(''));
        $this->assertNull(BrazilianDocument::normalize(null));
    }
}
