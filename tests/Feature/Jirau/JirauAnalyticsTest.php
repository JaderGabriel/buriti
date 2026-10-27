<?php

namespace Tests\Feature\Jirau;

use App\Enums\JirauCardPriority;
use App\Enums\JirauStaffRole;
use App\Models\Jirau;
use App\Models\JirauCard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JirauAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_overview_counts_wip_breach_and_overdue(): void
    {
        $admin = User::factory()->create();
        $jirau = Jirau::factory()->create(['name' => 'Portal fiscal', 'created_by' => $admin->id]);
        $doing = $jirau->columns()->where('slug', 'doing')->first();
        $inbox = $jirau->inboxColumn();

        JirauCard::factory()->count(4)->create([
            'jirau_id' => $jirau->id,
            'column_id' => $doing->id,
            'created_by' => $admin->id,
        ]);
        JirauCard::factory()->create([
            'jirau_id' => $jirau->id,
            'column_id' => $inbox->id,
            'created_by' => $admin->id,
            'title' => 'Entrega atrasada',
            'priority' => JirauCardPriority::Urgent,
            'due_at' => now()->subDays(4),
        ]);

        $this->actingAs($admin)
            ->get(route('jirau.analytics'))
            ->assertOk()
            ->assertSee('Analítica', false)
            ->assertSee('Portal fiscal', false)
            ->assertSee('WIP estourado', false)
            ->assertSee('Entrega atrasada', false)
            ->assertSee('Prazo vencido', false)
            ->assertSee('Por projeto', false)
            ->assertSee('Responsável', false);
    }

    public function test_analytics_excludes_demo_board_from_kpis_and_lists(): void
    {
        $admin = User::factory()->create();
        $real = Jirau::factory()->create(['name' => 'Portal cliente', 'created_by' => $admin->id]);
        $demo = Jirau::factory()->create([
            'name' => Jirau::DEMO_NAME,
            'created_by' => $admin->id,
        ]);

        JirauCard::factory()->create([
            'jirau_id' => $real->id,
            'column_id' => $real->inboxColumn()->id,
            'title' => 'Demanda real',
        ]);
        JirauCard::factory()->create([
            'jirau_id' => $demo->id,
            'column_id' => $demo->inboxColumn()->id,
            'title' => 'Cartão do Demo',
        ]);

        $this->actingAs($admin)
            ->get(route('jirau.analytics'))
            ->assertOk()
            ->assertSee('Portal cliente', false)
            ->assertSee('Demanda real', false)
            ->assertDontSee('Cartão do Demo', false)
            ->assertDontSee(Jirau::DEMO_NAME, false);
    }

    public function test_board_analytics_redirects_to_filtered_index(): void
    {
        $admin = User::factory()->create();
        $jirau = Jirau::factory()->create(['created_by' => $admin->id]);

        $this->actingAs($admin)
            ->get(route('jirau.analytics.show', $jirau))
            ->assertRedirect(route('jirau.analytics', ['quadro' => $jirau->id]));

        $this->actingAs($admin)
            ->get(route('jirau.analytics', ['quadro' => $jirau->id]))
            ->assertOk()
            ->assertSee('Faixas deste quadro', false)
            ->assertSee('Demandas', false)
            ->assertSee('Em curso', false)
            ->assertSee('Feito', false);
    }

    public function test_admin_filters_analytics_by_project_and_assignee(): void
    {
        $admin = User::factory()->create();
        $dev = User::factory()->staff(JirauStaffRole::Developer)->create(['name' => 'Ana Dev']);
        $alfa = Jirau::factory()->create(['name' => 'Quadro Alfa', 'created_by' => $admin->id]);
        $beta = Jirau::factory()->create(['name' => 'Quadro Beta', 'created_by' => $admin->id]);

        JirauCard::factory()->create([
            'jirau_id' => $alfa->id,
            'column_id' => $alfa->inboxColumn()->id,
            'title' => 'Só Alfa',
            'assigned_to' => $dev->id,
        ]);
        JirauCard::factory()->create([
            'jirau_id' => $beta->id,
            'column_id' => $beta->inboxColumn()->id,
            'title' => 'Só Beta',
        ]);

        $this->actingAs($admin)
            ->get(route('jirau.analytics', ['projeto' => $alfa->project_id]))
            ->assertOk()
            ->assertSee('Só Alfa', false)
            ->assertDontSee('Só Beta', false)
            ->assertSee('Quadro Alfa', false)
            ->assertDontSee('Quadro Beta', false);

        $this->actingAs($admin)
            ->get(route('jirau.analytics', ['usuario' => $dev->id]))
            ->assertOk()
            ->assertSee('Só Alfa', false)
            ->assertDontSee('Só Beta', false)
            ->assertSee('Ana Dev', false);

        $this->actingAs($admin)
            ->get(route('jirau.analytics', ['usuario' => 'none']))
            ->assertOk()
            ->assertSee('Só Beta', false)
            ->assertDontSee('Só Alfa', false);
    }

    public function test_admin_filters_overdue_risk(): void
    {
        $admin = User::factory()->create();
        $jirau = Jirau::factory()->create(['created_by' => $admin->id]);
        $inbox = $jirau->inboxColumn();

        JirauCard::factory()->create([
            'jirau_id' => $jirau->id,
            'column_id' => $inbox->id,
            'title' => 'No prazo',
            'due_at' => now()->addDays(3),
        ]);
        JirauCard::factory()->create([
            'jirau_id' => $jirau->id,
            'column_id' => $inbox->id,
            'title' => 'Já venceu',
            'due_at' => now()->subDay(),
        ]);

        $this->actingAs($admin)
            ->get(route('jirau.analytics', ['risco' => 'atraso']))
            ->assertOk()
            ->assertSee('Já venceu', false)
            ->assertDontSee('No prazo', false);
    }

    public function test_member_and_staff_cannot_open_analytics(): void
    {
        $admin = User::factory()->create();
        $own = Jirau::factory()->create(['name' => 'Quadro do cliente', 'created_by' => $admin->id]);
        $member = User::factory()->withoutAdminAccess()->create();
        $staff = User::factory()->staff(JirauStaffRole::Tester)->create();
        $own->members()->attach($member->id);

        $this->actingAs($member)
            ->get(route('jirau.analytics'))
            ->assertForbidden();

        $this->actingAs($member)
            ->get(route('jirau.analytics.show', $own))
            ->assertForbidden();

        $this->actingAs($member)
            ->get(route('jirau.show', $own))
            ->assertOk()
            ->assertDontSee(route('jirau.analytics.show', $own), false)
            ->assertDontSee('Analítica', false);

        $this->actingAs($staff)
            ->get(route('jirau.analytics'))
            ->assertForbidden();
    }

    public function test_guest_is_sent_to_login(): void
    {
        $this->get(route('jirau.analytics'))->assertRedirect(route('login'));
    }

    public function test_board_page_links_to_analytics(): void
    {
        $admin = User::factory()->create();
        $jirau = Jirau::factory()->create(['created_by' => $admin->id]);

        $this->actingAs($admin)
            ->get(route('jirau.show', $jirau))
            ->assertOk()
            ->assertSee(route('jirau.analytics.show', $jirau), false);
    }
}
