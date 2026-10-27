<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Support\ProductRoadmap;
use App\Support\RuntimeVersions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductRoadmapTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_scores_ideas_and_covers_waves(): void
    {
        $ideas = ProductRoadmap::ideas();

        $this->assertGreaterThanOrEqual(12, count($ideas));

        $waves = [];
        $kinds = [];
        foreach ($ideas as $idea) {
            $this->assertNotSame('', $idea['title']);
            $this->assertContains($idea['kind'], ['modulo', 'submodulo', 'recurso']);
            $this->assertContains($idea['wave'], ['agora', 'proximo', 'escala']);
            $this->assertGreaterThan(0, $idea['indice']);
            $this->assertContains($idea['prioridade'], ['alta', 'média', 'baixa']);
            $waves[$idea['wave']] = true;
            $kinds[$idea['kind']] = true;
        }

        $this->assertCount(3, $waves);
        $this->assertCount(3, $kinds);
        $this->assertSame(
            (int) round((5 * 20 - 2 * 4) / 3),
            collect($ideas)->firstWhere('id', 'propostas')['indice']
        );
        $this->assertSame('shipped', collect($ideas)->firstWhere('id', 'inbox-app')['status']);
        $this->assertSame('partial', collect($ideas)->firstWhere('id', 'apontamento')['status']);
    }

    public function test_admin_can_read_roadmap_page(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)
            ->get(route('admin.roadmap.show'))
            ->assertOk()
            ->assertSee('Roadmap de produto', false)
            ->assertSee('>Roadmap</span>', false)
            ->assertSee('Pipedrive', false)
            ->assertSee('Inbox WhatsApp', false)
            ->assertSee('Índice de impacto', false)
            ->assertSee('data-roadmap', false)
            ->assertSee('Como cada onda mexe no sistema', false)
            ->assertSee('Accelo', false)
            ->assertSee(RuntimeVersions::short(), false)
            ->assertSee('Nesta versão', false)
            ->assertSee('Inbox in-app', false);
    }

    public function test_search_finds_roadmap_entry(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)
            ->getJson(route('admin.search', ['q' => 'roadmap']))
            ->assertOk()
            ->assertJsonFragment([
                'title' => 'Roadmap',
                'type' => 'página',
                'subtitle' => 'Sistema',
            ]);
    }

    public function test_guest_is_redirected_from_roadmap(): void
    {
        $this->get(route('admin.roadmap.show'))
            ->assertRedirect(route('login'));
    }

    public function test_staff_cannot_read_roadmap(): void
    {
        $staff = User::factory()->withoutAdminAccess()->create();

        $this->actingAs($staff)
            ->get(route('admin.roadmap.show'))
            ->assertRedirect(route('jirau.home'));
    }
}
