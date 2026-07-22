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

    public function test_home_page_shows_configured_contact_channels(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('jadergabriel8@gmail.com', false);
        $response->assertSee('@JaderGabriel', false);
        $response->assertSee('+55 38991758416', false);
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
    }
}
