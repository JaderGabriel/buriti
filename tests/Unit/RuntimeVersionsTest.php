<?php

namespace Tests\Unit;

use App\Support\RuntimeVersions;
use Tests\TestCase;

class RuntimeVersionsTest extends TestCase
{
    public function test_labels_include_laravel_php_server_and_release(): void
    {
        $labels = RuntimeVersions::labels();

        $this->assertSame(app()->version(), $labels['laravel']);
        $this->assertSame(PHP_VERSION, $labels['php']);
        $this->assertNotSame('', $labels['server']);
        $this->assertSame('BURI-TI 1.6 Vereda', $labels['release']);
        $this->assertSame('BURI-TI 1.6 Vereda', RuntimeVersions::release());
    }
}
