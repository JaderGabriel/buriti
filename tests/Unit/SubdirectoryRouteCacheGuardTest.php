<?php

namespace Tests\Unit;

use App\Support\SubdirectoryRouteCacheGuard;
use Tests\TestCase;

class SubdirectoryRouteCacheGuardTest extends TestCase
{
    public function test_detects_app_url_with_public_path(): void
    {
        $this->assertTrue(SubdirectoryRouteCacheGuard::appUrlHasPath('https://buriti.dev.br/public'));
        $this->assertTrue(SubdirectoryRouteCacheGuard::appUrlHasPath('https://buriti.dev.br/public/'));
        $this->assertFalse(SubdirectoryRouteCacheGuard::appUrlHasPath('https://buriti.dev.br'));
        $this->assertFalse(SubdirectoryRouteCacheGuard::appUrlHasPath('https://buriti.dev.br/'));
        $this->assertFalse(SubdirectoryRouteCacheGuard::appUrlHasPath(''));
    }

    public function test_clear_is_noop_without_subdirectory(): void
    {
        config(['app.url' => 'https://buriti.dev.br']);

        $this->assertFalse(SubdirectoryRouteCacheGuard::clearIncompatibleCache());
    }
}
