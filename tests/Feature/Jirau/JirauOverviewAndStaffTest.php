<?php

namespace Tests\Feature\Jirau;

use App\Enums\JirauStaffRole;
use App\Enums\JirauTemplate;
use App\Models\Jirau;
use App\Models\JirauCard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JirauOverviewAndStaffTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_cards_mapped_into_overview_lanes(): void
    {
        $admin = User::factory()->create();
        $dev = Jirau::factory()->create(['name' => 'Portal', 'created_by' => $admin->id]);
        $consulting = Jirau::factory()->create([
            'name' => 'Diagnóstico',
            'template' => JirauTemplate::Consulting,
            'created_by' => $admin->id,
        ]);

        JirauCard::factory()->create([
            'jirau_id' => $dev->id,
            'column_id' => $dev->inboxColumn()->id,
            'title' => 'Pedido no inbox',
        ]);
        JirauCard::factory()->create([
            'jirau_id' => $dev->id,
            'column_id' => $dev->columns()->where('slug', 'backlog')->value('id'),
            'title' => 'Item de backlog',
        ]);
        JirauCard::factory()->create([
            'jirau_id' => $dev->id,
            'column_id' => $dev->columns()->where('slug', 'doing')->value('id'),
            'title' => 'Em curso agora',
        ]);
        JirauCard::factory()->create([
            'jirau_id' => $consulting->id,
            'column_id' => $consulting->columns()->where('slug', 'review')->value('id'),
            'title' => 'Homologação do cliente',
        ]);
        JirauCard::factory()->create([
            'jirau_id' => $dev->id,
            'column_id' => $dev->doneColumn()->id,
            'title' => 'Já entregue',
        ]);

        $html = $this->actingAs($admin)
            ->get(route('jirau.overview'))
            ->assertOk()
            ->assertSee('Quadro geral', false)
            ->assertSee('Pedido no inbox', false)
            ->assertSee('Item de backlog', false)
            ->assertSee('Em curso agora', false)
            ->assertSee('Homologação do cliente', false)
            ->assertSee('Já entregue', false)
            ->assertDontSee('Novo cartão', false)
            ->getContent();

        $this->assertStringContainsString('Demandas', $html);
        $this->assertStringContainsString('Em andamento', $html);
        $this->assertStringContainsString('Em teste', $html);
        $this->assertStringContainsString('Concluídos', $html);
        $this->assertStringNotContainsString('data-jirau-board', $html);
        $this->assertStringContainsString('jirau-card__hit', $html);
    }

    public function test_inactive_boards_are_hidden_from_overview_until_filtered(): void
    {
        $admin = User::factory()->create();
        $active = Jirau::factory()->create(['name' => 'Ativo Alfa', 'created_by' => $admin->id]);
        $inactive = Jirau::factory()->create([
            'name' => 'Arquivo Beta',
            'is_active' => false,
            'created_by' => $admin->id,
        ]);

        JirauCard::factory()->create([
            'jirau_id' => $active->id,
            'column_id' => $active->inboxColumn()->id,
            'title' => 'Cartão vivo',
        ]);
        JirauCard::factory()->create([
            'jirau_id' => $inactive->id,
            'column_id' => $inactive->inboxColumn()->id,
            'title' => 'Cartão arquivado',
        ]);

        $this->actingAs($admin)
            ->get(route('jirau.overview'))
            ->assertOk()
            ->assertSee('Cartão vivo', false)
            ->assertDontSee('Cartão arquivado', false);

        $this->actingAs($admin)
            ->get(route('jirau.overview', ['estado' => 'inativos']))
            ->assertOk()
            ->assertSee('Cartão arquivado', false)
            ->assertDontSee('Cartão vivo', false);

        $this->actingAs($admin)
            ->get(route('jirau.overview', ['estado' => 'todos']))
            ->assertOk()
            ->assertSee('Cartão vivo', false)
            ->assertSee('Cartão arquivado', false);
    }

    public function test_admin_can_deactivate_a_board(): void
    {
        $admin = User::factory()->create();
        $jirau = Jirau::factory()->create(['created_by' => $admin->id]);

        $this->actingAs($admin)
            ->from(route('jirau.show', $jirau))
            ->post(route('jirau.status', $jirau), ['is_active' => 0])
            ->assertRedirect();

        $this->assertFalse($jirau->fresh()->is_active);
    }

    public function test_admin_can_rename_a_board(): void
    {
        $admin = User::factory()->create();
        $jirau = Jirau::factory()->create([
            'created_by' => $admin->id,
            'name' => 'Quadro antigo',
        ]);

        $this->actingAs($admin)
            ->get(route('jirau.show', $jirau))
            ->assertOk()
            ->assertSee('Renomear', false)
            ->assertSee('jirau-rename-board-dialog', false);

        $this->actingAs($admin)
            ->from(route('jirau.show', $jirau))
            ->put(route('jirau.update', $jirau), [
                'board_name' => '  Jirau DEMO  ',
                '_board_rename' => '1',
            ])
            ->assertRedirect();

        $this->assertSame('Jirau DEMO', $jirau->fresh()->name);
    }

    public function test_rename_rejects_blank_name_and_members_cannot_rename(): void
    {
        $admin = User::factory()->create();
        $jirau = Jirau::factory()->create([
            'created_by' => $admin->id,
            'name' => 'Quadro original',
        ]);
        $member = User::factory()->withoutAdminAccess()->create();
        $jirau->members()->attach($member->id);

        $this->actingAs($admin)
            ->from(route('jirau.show', $jirau))
            ->put(route('jirau.update', $jirau), [
                'board_name' => '   ',
                '_board_rename' => '1',
            ])
            ->assertSessionHasErrors('board_name');

        $this->actingAs($member)
            ->put(route('jirau.update', $jirau), [
                'board_name' => 'Hack',
            ])
            ->assertForbidden();

        $this->actingAs($member)
            ->get(route('jirau.show', $jirau))
            ->assertOk()
            ->assertDontSee('Renomear', false)
            ->assertDontSee('jirau-rename-board-dialog', false);

        $this->assertSame('Quadro original', $jirau->fresh()->name);
    }

    public function test_admin_creates_staff_and_assigns_card(): void
    {
        $admin = User::factory()->create();
        $jirau = Jirau::factory()->create(['created_by' => $admin->id]);
        $column = $jirau->columns()->where('slug', 'doing')->first();

        $this->actingAs($admin)
            ->get(route('jirau.staff'))
            ->assertOk()
            ->assertSee('Equipe de TI', false)
            ->assertSee('Novo da equipe', false)
            ->assertSee($admin->name, false)
            ->assertSee('Membro Admin', false)
            ->assertSee('@'.$admin->username, false);

        $this->actingAs($admin)
            ->from(route('jirau.staff'))
            ->post(route('jirau.staff.store'), [
                'name' => 'Ana Dev',
                'username' => 'ana.dev',
                'email' => 'ana.dev@buriti.dev.br',
                'password' => 'secret123',
                'password_confirmation' => 'secret123',
                'jirau_role' => JirauStaffRole::Developer->value,
            ])
            ->assertRedirect(route('jirau.staff'))
            ->assertSessionHasNoErrors();

        $dev = User::query()->where('username', 'ana.dev')->first();
        $this->assertNotNull($dev);
        $this->assertFalse($dev->is_admin);
        $this->assertSame(JirauStaffRole::Developer, $dev->jirau_role);

        $card = JirauCard::factory()->create([
            'jirau_id' => $jirau->id,
            'column_id' => $column->id,
            'title' => 'API de boletos',
        ]);

        $this->actingAs($admin)
            ->put(route('jirau.cards.update', [$jirau, $card]), [
                'title' => 'API de boletos',
                'assigned_to' => $dev->id,
            ])
            ->assertRedirect();

        $this->assertSame($dev->id, $card->fresh()->assigned_to);
    }

    public function test_cannot_assign_card_to_project_member(): void
    {
        $admin = User::factory()->create();
        $jirau = Jirau::factory()->create(['created_by' => $admin->id]);
        $member = User::factory()->withoutAdminAccess()->create();
        $jirau->members()->attach($member->id);
        $card = JirauCard::factory()->create([
            'jirau_id' => $jirau->id,
            'column_id' => $jirau->inboxColumn()->id,
            'title' => 'Só TI',
        ]);

        $this->actingAs($admin)
            ->from(route('jirau.show', ['jirau' => $jirau, 'cartao' => $card->id]))
            ->put(route('jirau.cards.update', [$jirau, $card]), [
                'title' => 'Só TI',
                'assigned_to' => $member->id,
            ])
            ->assertSessionHasErrors('assigned_to');

        $this->assertNull($card->fresh()->assigned_to);
    }

    public function test_system_admin_appears_on_jirau_team_as_admin_member(): void
    {
        $admin = User::factory()->create([
            'name' => 'Jader Gabriel',
            'username' => 'jadergabriel',
        ]);
        $dev = User::factory()->staff(JirauStaffRole::Developer)->create([
            'name' => 'Ana Dev',
            'username' => 'ana.dev',
        ]);

        $html = $this->actingAs($admin)
            ->get(route('jirau.staff'))
            ->assertOk()
            ->assertSee('Jader Gabriel', false)
            ->assertSee('Membro Admin', false)
            ->assertSee('Ana Dev', false)
            ->assertSee('Dev', false)
            ->getContent();

        $adminPos = strpos($html, 'Jader Gabriel');
        $devPos = strpos($html, 'Ana Dev');
        $this->assertNotFalse($adminPos);
        $this->assertNotFalse($devPos);
        $this->assertLessThan($devPos, $adminPos);
        $this->assertSame('Membro Admin', $admin->jirauTeamLabel());
        $this->assertSame('Dev', $dev->jirauTeamLabel());
    }

    public function test_staff_can_login_without_board_membership(): void
    {
        User::factory()->staff(JirauStaffRole::Tester)->create([
            'username' => 'qa.beta',
            'password' => 'secret123',
        ]);

        $this->post(route('login'), [
            'login' => 'qa.beta',
            'password' => 'secret123',
        ])->assertRedirect(route('jirau.home'));
    }

    public function test_member_cannot_open_overview_or_create_staff(): void
    {
        $admin = User::factory()->create();
        $jirau = Jirau::factory()->create(['created_by' => $admin->id]);
        $member = User::factory()->withoutAdminAccess()->create();
        $jirau->members()->attach($member->id);

        $this->actingAs($member)
            ->get(route('jirau.overview'))
            ->assertForbidden();

        $this->actingAs($member)
            ->get(route('jirau.staff'))
            ->assertForbidden();

        $this->actingAs($member)
            ->post(route('jirau.staff.store'), [
                'name' => 'Hack',
                'username' => 'hack.user',
                'email' => 'hack@example.com',
                'password' => 'secret123',
                'password_confirmation' => 'secret123',
                'jirau_role' => JirauStaffRole::Po->value,
            ])
            ->assertForbidden();
    }
}
