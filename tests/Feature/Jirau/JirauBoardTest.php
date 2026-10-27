<?php

namespace Tests\Feature\Jirau;

use App\Enums\JirauCardPriority;
use App\Enums\JirauCardType;
use App\Models\Jirau;
use App\Models\JirauCard;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\AdminSeeder;
use Database\Seeders\JirauDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class JirauBoardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_jirau_for_project(): void
    {
        $admin = User::factory()->create();
        $project = Project::factory()->create(['name' => 'Portal fiscal']);

        $this->actingAs($admin)
            ->post(route('admin.projects.jirau.store', $project), [
                'template' => 'development',
            ])
            ->assertRedirect();

        $jirau = Jirau::query()->where('project_id', $project->id)->first();
        $this->assertNotNull($jirau);
        $this->assertSame(6, $jirau->columns()->count());
        $this->assertTrue($jirau->columns()->where('slug', 'inbox')->where('is_inbox', true)->exists());
        $this->assertTrue($jirau->labels()->exists());
    }

    public function test_member_logs_in_without_two_factor_and_sees_only_jirau(): void
    {
        $admin = User::factory()->create();
        $jirau = Jirau::factory()->create(['created_by' => $admin->id]);
        $member = User::factory()->withoutAdminAccess()->create([
            'username' => 'cliente.alpha',
            'email' => 'cliente@example.com',
            'password' => 'secret123',
        ]);
        $jirau->members()->attach($member->id, ['invited_by' => $admin->id]);

        $this->post(route('login'), [
            'login' => 'cliente.alpha',
            'password' => 'secret123',
        ])->assertRedirect(route('jirau.home'));

        $this->assertAuthenticatedAs($member);

        $html = $this->get(route('jirau.show', $jirau))
            ->assertOk()
            ->assertSee('Nova demanda', false)
            ->assertSee('jirau-demand-dialog', false)
            ->assertSee('role="dialog"', false)
            ->assertSee('data-admin-nav-toggle', false)
            ->assertSee('admin-modulebar', false)
            ->assertDontSee('Novo cartão', false)
            ->assertDontSee('Novo quadro', false)
            ->assertDontSee('jirau-create-board-dialog', false)
            ->assertDontSee('data-jirau-rename="board"', false)
            ->assertDontSee('Renomear', false)
            ->getContent();

        $this->assertMatchesRegularExpression('/id="jirau-demand-dialog"[^>]*\bhidden\b/', $html);

        $this->get(route('admin.dashboard'))->assertRedirect(route('jirau.home'));
        $this->assertAuthenticatedAs($member);
    }

    public function test_member_without_jirau_cannot_login(): void
    {
        User::factory()->withoutAdminAccess()->create([
            'email' => 'semquadro@example.com',
            'password' => 'secret123',
        ]);

        $this->from(route('login'))->post(route('login'), [
            'login' => 'semquadro@example.com',
            'password' => 'secret123',
        ])->assertRedirect(route('login'))
            ->assertSessionHasErrors('login');

        $this->assertGuest();
    }

    public function test_member_creates_demand_in_inbox_without_estimate_and_notifies_admin(): void
    {
        $this->fakeTelegramApi();

        $admin = User::factory()->telegramLinked('555001')->create();
        $jirau = Jirau::factory()->create(['created_by' => $admin->id]);
        $member = User::factory()->withoutAdminAccess()->create();
        $jirau->members()->attach($member->id);

        $this->actingAs($member)
            ->post(route('jirau.demands.store', $jirau), [
                'title' => 'Ajuste no boleto',
                'description' => 'O vencimento está um dia errado.',
                'priority' => JirauCardPriority::High->value,
            ])
            ->assertRedirect();

        $card = JirauCard::query()->first();
        $this->assertNotNull($card);
        $this->assertSame(JirauCardType::Demand, $card->type);
        $this->assertNull($card->estimate);
        $this->assertNull($card->assigned_to);
        $this->assertTrue($card->column->is_inbox);
        $this->assertSame($member->id, $card->created_by);

        Http::assertSent(function ($request) {
            $text = (string) ($request->data()['text'] ?? '');

            return str_contains($text, 'Nova demanda no Jirau')
                && str_contains($text, 'Ajuste no boleto');
        });
    }

    public function test_member_cannot_move_card_or_set_estimate(): void
    {
        $admin = User::factory()->create();
        $jirau = Jirau::factory()->create(['created_by' => $admin->id]);
        $member = User::factory()->withoutAdminAccess()->create();
        $jirau->members()->attach($member->id);

        $inbox = $jirau->columns()->where('slug', 'inbox')->first();
        $doing = $jirau->columns()->where('slug', 'doing')->first();
        $card = JirauCard::factory()->demand()->create([
            'jirau_id' => $jirau->id,
            'column_id' => $inbox->id,
            'created_by' => $member->id,
            'title' => 'Pedido do cliente',
        ]);

        $this->actingAs($member)
            ->postJson(route('jirau.cards.move', [$jirau, $card]), [
                'column_id' => $doing->id,
            ])
            ->assertForbidden();

        $this->actingAs($member)
            ->put(route('jirau.cards.update', [$jirau, $card]), [
                'title' => 'Pedido do cliente',
                'estimate' => 8,
            ]);

        $this->assertNull($card->fresh()->estimate);
        $this->assertSame($inbox->id, $card->fresh()->column_id);
    }

    public function test_admin_can_move_and_estimate(): void
    {
        $admin = User::factory()->create();
        $jirau = Jirau::factory()->create(['created_by' => $admin->id]);
        $inbox = $jirau->columns()->where('slug', 'inbox')->first();
        $backlog = $jirau->columns()->where('slug', 'backlog')->first();
        $card = JirauCard::factory()->demand()->create([
            'jirau_id' => $jirau->id,
            'column_id' => $inbox->id,
            'title' => 'Triagem',
        ]);

        $this->actingAs($admin)
            ->postJson(route('jirau.cards.move', [$jirau, $card]), [
                'column_id' => $backlog->id,
                'ordered_ids' => [$card->id],
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->actingAs($admin)
            ->put(route('jirau.cards.update', [$jirau, $card]), [
                'title' => 'Triagem',
                'type' => JirauCardType::Story->value,
                'priority' => JirauCardPriority::High->value,
                'estimate' => 5,
            ])
            ->assertRedirect();

        $fresh = $card->fresh();
        $this->assertSame($backlog->id, $fresh->column_id);
        $this->assertSame(5, $fresh->estimate);
        $this->assertSame(JirauCardType::Story, $fresh->type);
        $this->assertNotNull($fresh->triaged_at);
    }

    public function test_comment_also_saves_edited_card_fields(): void
    {
        $admin = User::factory()->create();
        $jirau = Jirau::factory()->create(['created_by' => $admin->id]);
        $card = JirauCard::factory()->create([
            'jirau_id' => $jirau->id,
            'column_id' => $jirau->columns()->where('slug', 'doing')->value('id'),
            'title' => 'Título antigo',
            'description' => 'Descrição antiga',
            'priority' => JirauCardPriority::Low,
        ]);

        $this->actingAs($admin)
            ->post(route('jirau.cards.comments.store', [$jirau, $card]), [
                'save_card' => '1',
                'title' => 'Título novo',
                'description' => 'Descrição nova',
                'priority' => JirauCardPriority::High->value,
                'type' => JirauCardType::Task->value,
                'body' => 'Vamos priorizar isso.',
            ])
            ->assertRedirect(route('jirau.show', ['jirau' => $jirau, 'cartao' => $card->id]));

        $fresh = $card->fresh();
        $this->assertSame('Título novo', $fresh->title);
        $this->assertSame('Descrição nova', $fresh->description);
        $this->assertSame(JirauCardPriority::High, $fresh->priority);
        $this->assertTrue($fresh->comments()->where('body', 'Vamos priorizar isso.')->exists());
    }

    public function test_checklist_also_saves_edited_card_fields(): void
    {
        $admin = User::factory()->create();
        $jirau = Jirau::factory()->create(['created_by' => $admin->id]);
        $card = JirauCard::factory()->create([
            'jirau_id' => $jirau->id,
            'column_id' => $jirau->columns()->where('slug', 'doing')->value('id'),
            'title' => 'Título antigo',
            'description' => 'Texto',
        ]);

        $this->actingAs($admin)
            ->post(route('jirau.cards.checklist.store', [$jirau, $card]), [
                'save_card' => '1',
                'title' => 'Título atualizado',
                'description' => 'Texto revisado',
                'priority' => JirauCardPriority::Medium->value,
                'type' => JirauCardType::Task->value,
                'item' => 'Escrever o teste',
            ])
            ->assertRedirect(route('jirau.show', ['jirau' => $jirau, 'cartao' => $card->id]));

        $fresh = $card->fresh();
        $this->assertSame('Título atualizado', $fresh->title);
        $this->assertSame('Texto revisado', $fresh->description);
        $this->assertTrue($fresh->checklistItems()->where('title', 'Escrever o teste')->exists());
    }

    public function test_member_comment_saves_edits_only_on_own_inbox_demand(): void
    {
        $admin = User::factory()->create();
        $jirau = Jirau::factory()->create(['created_by' => $admin->id]);
        $member = User::factory()->withoutAdminAccess()->create();
        $jirau->members()->attach($member->id);

        $inbox = $jirau->columns()->where('slug', 'inbox')->first();
        $doing = $jirau->columns()->where('slug', 'doing')->first();

        $own = JirauCard::factory()->demand()->create([
            'jirau_id' => $jirau->id,
            'column_id' => $inbox->id,
            'created_by' => $member->id,
            'title' => 'Pedido original',
        ]);
        $other = JirauCard::factory()->create([
            'jirau_id' => $jirau->id,
            'column_id' => $doing->id,
            'created_by' => $admin->id,
            'title' => 'Cartão da equipe',
        ]);

        $this->actingAs($member)
            ->post(route('jirau.cards.comments.store', [$jirau, $own]), [
                'save_card' => '1',
                'title' => 'Pedido revisado',
                'description' => 'Mais contexto',
                'priority' => JirauCardPriority::High->value,
                'body' => 'Incluí o print.',
            ])
            ->assertRedirect();

        $this->assertSame('Pedido revisado', $own->fresh()->title);
        $this->assertTrue($own->comments()->where('body', 'Incluí o print.')->exists());

        $this->actingAs($member)
            ->post(route('jirau.cards.comments.store', [$jirau, $other]), [
                'save_card' => '1',
                'title' => 'Não deveria gravar',
                'body' => 'Só um recado.',
            ])
            ->assertRedirect();

        $this->assertSame('Cartão da equipe', $other->fresh()->title);
        $this->assertTrue($other->comments()->where('body', 'Só um recado.')->exists());
    }

    public function test_admin_can_invite_project_user(): void
    {
        $admin = User::factory()->create();
        $jirau = Jirau::factory()->create(['created_by' => $admin->id]);

        $this->actingAs($admin)
            ->post(route('jirau.members.store', $jirau), [
                'mode' => 'create',
                'name' => 'Ana Cliente',
                'username' => 'ana_cliente',
                'email' => 'ana@cliente.test',
                'password' => 'cliente123',
                'password_confirmation' => 'cliente123',
            ])
            ->assertRedirect(route('jirau.show', $jirau));

        $this->assertDatabaseHas('users', [
            'username' => 'ana_cliente',
            'is_admin' => 0,
        ]);
        $this->assertDatabaseHas('jirau_members', [
            'jirau_id' => $jirau->id,
            'user_id' => User::query()->where('username', 'ana_cliente')->value('id'),
        ]);
    }

    public function test_member_cannot_see_another_jirau(): void
    {
        $admin = User::factory()->create();
        $own = Jirau::factory()->create(['created_by' => $admin->id]);
        $other = Jirau::factory()->create(['created_by' => $admin->id]);
        $member = User::factory()->withoutAdminAccess()->create();
        $own->members()->attach($member->id);

        $this->actingAs($member)
            ->get(route('jirau.show', $other))
            ->assertForbidden();
    }

    public function test_admin_home_lists_boards_by_sort_order_with_icon_actions(): void
    {
        $admin = User::factory()->create();
        $zebra = Jirau::factory()->create([
            'created_by' => $admin->id,
            'name' => 'Zebra',
            'sort_order' => 20,
        ]);
        $alpha = Jirau::factory()->create([
            'created_by' => $admin->id,
            'name' => 'Alpha',
            'sort_order' => 10,
        ]);

        $html = $this->actingAs($admin)
            ->get(route('jirau.home'))
            ->assertOk()
            ->assertSee('data-jirau-home', false)
            ->assertSee('data-can-drag="1"', false)
            ->assertSee(route('jirau.boards.reorder'), false)
            ->assertSee('data-jirau-home-handle', false)
            ->assertSee('jirau-action--board', false)
            ->assertSee('jirau-action--steps', false)
            ->assertSee('jirau-action--people', false)
            ->assertSee('jirau-action--analytics', false)
            ->assertSee('Novo quadro', false)
            ->assertSee('Quadro geral', false)
            ->assertSee('Abrir quadro', false)
            ->getContent();

        $this->assertLessThan(
            strpos($html, 'Zebra') ?: PHP_INT_MAX,
            strpos($html, 'Alpha') ?: PHP_INT_MAX,
        );
        $this->assertNotFalse(strpos($html, 'data-board-id="'.$alpha->id.'"'));
        $this->assertNotFalse(strpos($html, 'data-board-id="'.$zebra->id.'"'));
    }

    public function test_admin_can_reorder_home_boards(): void
    {
        $admin = User::factory()->create();
        $first = Jirau::factory()->create(['created_by' => $admin->id, 'sort_order' => 10]);
        $second = Jirau::factory()->create(['created_by' => $admin->id, 'sort_order' => 20]);
        $third = Jirau::factory()->create(['created_by' => $admin->id, 'sort_order' => 30]);

        $this->actingAs($admin)
            ->postJson(route('jirau.boards.reorder'), [
                'ordered_ids' => [$third->id, $first->id, $second->id],
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertSame(10, (int) $third->fresh()->sort_order);
        $this->assertSame(20, (int) $first->fresh()->sort_order);
        $this->assertSame(30, (int) $second->fresh()->sort_order);
    }

    public function test_member_cannot_reorder_home_boards(): void
    {
        $admin = User::factory()->create();
        $own = Jirau::factory()->create(['created_by' => $admin->id, 'sort_order' => 10]);
        $other = Jirau::factory()->create(['created_by' => $admin->id, 'sort_order' => 20]);
        $member = User::factory()->withoutAdminAccess()->create();
        $own->members()->attach($member->id);
        $other->members()->attach($member->id);

        $this->actingAs($member)
            ->get(route('jirau.home'))
            ->assertOk()
            ->assertDontSee('data-jirau-home-handle', false)
            ->assertDontSee('data-can-drag="1"', false)
            ->assertDontSee('Novo quadro', false);

        $this->actingAs($member)
            ->postJson(route('jirau.boards.reorder'), [
                'ordered_ids' => [$other->id, $own->id],
            ])
            ->assertForbidden();

        $this->assertSame(10, (int) $own->fresh()->sort_order);
        $this->assertSame(20, (int) $other->fresh()->sort_order);
    }

    public function test_admin_board_uses_icon_actions_and_rename_handles(): void
    {
        $admin = User::factory()->create();
        $jirau = Jirau::factory()->create(['created_by' => $admin->id]);
        $column = $jirau->columns()->where('slug', 'doing')->first();

        $this->actingAs($admin)
            ->get(route('jirau.show', $jirau))
            ->assertOk()
            ->assertSee('Novo cartão', false)
            ->assertSee('jirau-action--card', false)
            ->assertSee('jirau-action--board', false)
            ->assertSee('data-jirau-toggle="new-card"', false)
            ->assertSee('data-jirau-panel="new-card"', false)
            ->assertSee('data-jirau-panel="filters"', false)
            ->assertSee('data-jirau-panel="steps"', false)
            ->assertSee('data-jirau-panel="members"', false)
            ->assertSee('data-jirau-panel="new-column"', false)
            ->assertSee('data-jirau-rename="board"', false)
            ->assertSee('data-jirau-rename="column-'.$column->id.'"', false)
            ->assertSee('jirau-column__count', false)
            ->assertSee('jirau-action--icon', false)
            ->assertSee('Ficha do projeto', false)
            ->assertSee('Fases', false)
            ->assertSee('Etapa', false)
            ->assertSee('Novo quadro', false)
            ->assertDontSee('Renomear', false)
            ->assertDontSee('>Faixa</span>', false);
    }

    public function test_demo_seeder_creates_board_and_member_login(): void
    {
        $this->seed(AdminSeeder::class);
        $this->seed(JirauDemoSeeder::class);

        $this->assertDatabaseHas('users', [
            'username' => JirauDemoSeeder::DEMO_USERNAME,
            'is_admin' => 0,
        ]);
        $this->assertTrue(Jirau::query()->where('name', JirauDemoSeeder::DEMO_BOARD_NAME)->exists());

        $this->post(route('login'), [
            'login' => JirauDemoSeeder::DEMO_USERNAME,
            'password' => JirauDemoSeeder::DEMO_PASSWORD,
        ])->assertRedirect(route('jirau.home'));

        $this->followingRedirects()
            ->get(route('jirau.home'))
            ->assertOk()
            ->assertSee('Jirau', false)
            ->assertSee('data-admin-module-pin', false)
            ->assertSee('Módulo Jirau', false);
    }
}
