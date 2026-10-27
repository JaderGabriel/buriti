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
        $this->assertSame(RuntimeVersions::release(), $labels['release']);
        $this->assertSame(RuntimeVersions::short(), $labels['short']);
        $this->assertSame(config('buriti.name'), $labels['name']);
        $this->assertSame(config('buriti.version'), $labels['version']);
        $this->assertSame(config('buriti.codename'), $labels['codename']);
        $this->assertSame(config('buriti.tagline'), $labels['tagline']);
    }

    public function test_helpers_and_format_follow_config(): void
    {
        config([
            'buriti.name' => 'BURITI-TEST',
            'buriti.version' => '9.9',
            'buriti.codename' => 'Teste',
            'buriti.tagline' => 'Tagline de teste',
        ]);

        $this->assertSame('BURITI-TEST', product_name());
        $this->assertSame('9.9', product_version());
        $this->assertSame('Teste', product_codename());
        $this->assertSame('Tagline de teste', product_tagline());
        $this->assertSame('9.9 Teste', product_short());
        $this->assertSame('BURITI-TEST 9.9 Teste', product_release());
        $this->assertSame('9.9 Teste', RuntimeVersions::format('short'));
        $this->assertSame('BURITI-TEST 9.9 Teste', RuntimeVersions::format('release'));
    }
}
