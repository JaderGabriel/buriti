<?php

namespace Tests\Feature;

use App\Support\RuntimeVersions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_privacy_and_cookies_pages_are_public(): void
    {
        $this->get(route('privacy'))
            ->assertOk()
            ->assertSee('Política de Privacidade', false)
            ->assertSee('LGPD', false)
            ->assertSee('©', false);

        $this->get(route('cookies'))
            ->assertOk()
            ->assertSee('Política de Cookies', false)
            ->assertSee('buriti-cookie-consent', false)
            ->assertSee('estritamente necessários', false);
    }

    public function test_home_footer_shows_copyright_and_legal_links(): void
    {
        $versions = RuntimeVersions::labels();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('site-versions', false)
            ->assertSee($versions['php'], false)
            ->assertSee($versions['laravel'], false)
            ->assertSee('1.6 Vereda', false)
            ->assertSee('©', false)
            ->assertSee('Desenvolvido e mantido por', false)
            ->assertSee('Política de Privacidade', false)
            ->assertSee('Política de Cookies', false)
            ->assertSee('Cookies e privacidade', false)
            ->assertSee('Concordo e Aceito', false)
            ->assertSee('cookie-banner', false);
    }
}
