<?php

namespace Tests\Feature;

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
            ->assertSee('Todos os direitos reservados', false);

        $this->get(route('cookies'))
            ->assertOk()
            ->assertSee('Política de Cookies', false)
            ->assertSee('buriti-cookie-consent', false)
            ->assertSee('estritamente necessários', false);
    }

    public function test_home_footer_shows_copyright_and_legal_links(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Todos os direitos reservados', false)
            ->assertSee('Desenvolvido e mantido por', false)
            ->assertSee('Política de Privacidade', false)
            ->assertSee('Política de Cookies', false)
            ->assertSee('Cookies e privacidade', false)
            ->assertSee('cookieConsent', false);
    }
}
