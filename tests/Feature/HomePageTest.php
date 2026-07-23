<?php

namespace Tests\Feature;

use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_loads_successfully(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('BURI-TI', false);
        $response->assertSee('Tecnologia para Pessoas', false);
        $response->assertSee('Consultoria em TI', false);
        $response->assertSee('Do contato à operação', false);
        $response->assertSee('Área admin', false);
        $response->assertSee('Quem é quem', false);
        $response->assertSee('Jader Gabriel', false);
        $response->assertSee('images/team/jader-gabriel.jpg', false);
        $response->assertSee('Fundador', false);
        $response->assertSee('/admin"', false);
        $response->assertSee('Pronto para o próximo passo digital?', false);
        $response->assertSee('Construa o futuro digital com a BURI-TI', false);
        $response->assertSee('mobile-proposal-fab', false);
        $response->assertSee('Método comercial', false);
        $response->assertSee('method-flow', false);
        $response->assertSee('O que chama atenção ao contratante', false);
        $response->assertSee('Como o resultado é produzido', false);
        $response->assertSee('Processo de desenvolvimento', false);
        $response->assertSee('Moodle', false);
        $response->assertSee('WordPress', false);
        $response->assertSee('Tecnologias, sistemas e operação', false);
        $response->assertSee('cPanel', false);
        $response->assertSee('Experiência direta', false);
        $response->assertSee('cdn.jsdelivr.net/npm/simple-icons@14/icons/laravel.svg', false);
        $response->assertSee('images/tech/ieducar-icon.png', false);
        $response->assertSee('images/tech/power-bi.svg', false);
        $response->assertSee('Operacional', false);
    }

    public function test_career_modal_is_closed_by_default(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString('Ver trajetória completa', $html);
        $this->assertStringContainsString('data-dialog-open="career-modal-0"', $html);
        $this->assertStringContainsString('class="career-dialog"', $html);
        $this->assertStringContainsString('id="career-modal-0"', $html);
        $this->assertStringContainsString('role="dialog"', $html);
        $this->assertStringContainsString('aria-label="Fechar trajetória"', $html);
        $this->assertStringContainsString('data-dialog-close', $html);
        $this->assertStringContainsString(' hidden', $html);
        // Botão "Fechar" de texto no rodapé do modal removido; só permanece o X no topo.
        $this->assertDoesNotMatchRegularExpression(
            '/border-t border-line pt-5[\s\S]*?>\s*Fechar\s*</',
            $html
        );
    }

    public function test_home_page_shows_public_projects_only(): void
    {
        $public = Project::factory()->public()->create(['name' => 'Projeto Público BURITI']);
        Project::factory()->create(['name' => 'Projeto Privado BURITI', 'is_public' => false]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee($public->name, false);
        $response->assertDontSee('Projeto Privado BURITI', false);
    }

    public function test_home_page_shows_private_repo_cases_without_links(): void
    {
        $private = Project::factory()->privateRepo()->create([
            'name' => 'Case NDA Municipal',
            'github_url' => 'https://github.com/JaderGabriel/secret-repo',
            'website_url' => 'https://internal.example/secret',
            'information' => 'Entrega confidencial de BI municipal.',
        ]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('Case NDA Municipal', false);
        $response->assertSee('Repositórios privados', false);
        $response->assertSee('Código sob NDA', false);
        $response->assertDontSee('https://github.com/JaderGabriel/secret-repo', false);
        $response->assertDontSee('https://internal.example/secret', false);
        $this->assertTrue($private->fresh()->repo_is_private);
    }

    public function test_home_page_shows_configured_contact_channels(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('mailto:jadergabriel8@gmail.com', false);
        $response->assertSee('aria-label="Enviar e-mail"', false);
        $response->assertSee('aria-label="Ligar"', false);
        $response->assertSee('aria-label="Abrir WhatsApp"', false);
        $response->assertSee('@JaderGabriel', false);
        $response->assertDontSee('+55 38 99175-8416', false);
        $response->assertDontSee('WhatsApp direto', false);
    }

    public function test_home_page_shows_expertise_and_portfolio_signals(): void
    {
        Project::factory()->public()->create([
            'name' => 'Servlitcys Destaque',
            'category' => 'BI & Painéis',
            'stack' => ['Laravel', 'Power BI'],
        ]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('Modelagem gerencial e técnica', false);
        $response->assertSee('Power BI', false);
        $response->assertSee('Servlitcys Destaque', false);
        $response->assertSee('BI &amp; Painéis', false);
        $response->assertSee('LMS, CMS e conteúdo', false);
    }

    public function test_home_page_lists_odin_as_private_portfolio_without_github_link(): void
    {
        Project::factory()->privateRepo()->create([
            'name' => 'Odin',
            'category' => 'LMS / Moodle',
            'information' => 'Sistema de gestão para ambiente Moodle.',
            'github_url' => 'https://github.com/JaderGabriel/Odin',
            'stack' => ['PHP', 'Moodle'],
        ]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('Odin', false);
        $response->assertSee('Moodle', false);
        $response->assertDontSee('https://github.com/JaderGabriel/Odin', false);
    }
}
