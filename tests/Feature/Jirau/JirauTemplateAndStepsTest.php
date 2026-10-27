<?php

namespace Tests\Feature\Jirau;

use App\Enums\JirauTemplate;
use App\Models\Jirau;
use App\Models\JirauCard;
use App\Models\Project;
use App\Models\ProjectStep;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JirauTemplateAndStepsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_multiple_jiraus_with_templates(): void
    {
        $admin = User::factory()->create();
        $project = Project::factory()->create(['name' => 'Cliente Alfa']);

        $this->actingAs($admin)
            ->post(route('admin.projects.jirau.store', $project), [
                'template' => JirauTemplate::Development->value,
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->post(route('admin.projects.jirau.store', $project), [
                'name' => 'Consultoria Alfa',
                'template' => JirauTemplate::Consulting->value,
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->post(route('admin.projects.jirau.store', $project), [
                'template' => JirauTemplate::Training->value,
            ])
            ->assertRedirect();

        $this->assertSame(3, $project->jiraus()->count());

        $consulting = Jirau::query()->where('name', 'Consultoria Alfa')->first();
        $this->assertSame(JirauTemplate::Consulting, $consulting->template);
        $this->assertTrue($consulting->columns()->where('slug', 'discovery')->exists());
        $this->assertTrue($consulting->labels()->where('name', 'Contrato')->exists());

        $training = $project->jiraus()->where('template', JirauTemplate::Training)->first();
        $this->assertSame(7, $training->columns()->count());
        $this->assertTrue($training->columns()->where('slug', 'class')->exists());

        $this->actingAs($admin)
            ->get(route('admin.projects.edit', $project))
            ->assertOk()
            ->assertSee('Adicionar quadro', false)
            ->assertSee('Criar outro quadro', false)
            ->assertSee('Consultoria', false)
            ->assertSee('Treinamento', false)
            ->assertSee('Atividades', false);

        $this->actingAs($admin)
            ->get(route('jirau.show', $consulting))
            ->assertOk()
            ->assertSee('Consultoria Alfa', false)
            ->assertSee('Treinamento', false)
            ->assertSee('Desenvolvimento', false)
            ->assertSee('Fases', false)
            ->assertSee('Novo quadro', false)
            ->assertSee('Nova fase', false)
            ->assertSee('Juntar fases soltas', false);
    }

    public function test_admin_can_create_board_from_jirau_home_without_opening_project(): void
    {
        $admin = User::factory()->create();
        $alpha = Project::factory()->create(['name' => 'Cliente Alfa']);
        $beta = Project::factory()->create(['name' => 'Cliente Beta']);

        $this->actingAs($admin)
            ->get(route('jirau.home'))
            ->assertOk()
            ->assertSee('Novo quadro', false)
            ->assertSee('jirau-create-board-dialog', false)
            ->assertSee('Cliente Alfa', false)
            ->assertSee('Cliente Beta', false)
            ->assertSee('Consultoria', false);

        $this->actingAs($admin)
            ->from(route('jirau.home'))
            ->post(route('jirau.boards.store'), [
                'project_id' => $beta->id,
                'board_name' => 'Consultoria Beta',
                'template' => JirauTemplate::Consulting->value,
                '_board_create' => '1',
            ])
            ->assertRedirect();

        $board = Jirau::query()->where('name', 'Consultoria Beta')->first();
        $this->assertNotNull($board);
        $this->assertSame($beta->id, $board->project_id);
        $this->assertSame(JirauTemplate::Consulting, $board->template);
        $this->assertSame(0, $alpha->jiraus()->count());
        $this->assertSame(1, $beta->jiraus()->count());
    }

    public function test_create_board_from_jirau_requires_existing_project(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)
            ->from(route('jirau.home'))
            ->post(route('jirau.boards.store'), [
                'template' => JirauTemplate::Development->value,
                '_board_create' => '1',
            ])
            ->assertSessionHasErrors('project_id');

        $this->actingAs($admin)
            ->from(route('jirau.home'))
            ->post(route('jirau.boards.store'), [
                'project_id' => 99999,
                'template' => JirauTemplate::Development->value,
                '_board_create' => '1',
            ])
            ->assertSessionHasErrors('project_id');

        $this->assertSame(0, Jirau::query()->count());
    }

    public function test_unknown_template_is_rejected(): void
    {
        $admin = User::factory()->create();
        $project = Project::factory()->create();

        $this->actingAs($admin)
            ->from(route('admin.projects.edit', $project))
            ->post(route('admin.projects.jirau.store', $project), [
                'template' => 'scrum-pirate',
            ])
            ->assertSessionHasErrors('template');

        $this->assertSame(0, $project->jiraus()->count());
    }

    public function test_member_cannot_create_jirau_or_import_steps(): void
    {
        $admin = User::factory()->create();
        $project = Project::factory()->create();
        $jirau = Jirau::factory()->create(['project_id' => $project->id, 'created_by' => $admin->id]);
        $member = User::factory()->withoutAdminAccess()->create();
        $jirau->members()->attach($member->id);

        $this->actingAs($member)
            ->post(route('admin.projects.jirau.store', $project), [
                'template' => JirauTemplate::Activities->value,
            ])
            ->assertRedirect(route('jirau.home'));

        $this->actingAs($member)
            ->post(route('jirau.boards.store'), [
                'project_id' => $project->id,
                'template' => JirauTemplate::Activities->value,
            ])
            ->assertForbidden();

        $this->actingAs($member)
            ->post(route('jirau.steps.import', $jirau))
            ->assertForbidden();

        $this->actingAs($member)
            ->post(route('jirau.steps.store', $jirau), ['title' => 'Hack'])
            ->assertForbidden();

        $card = JirauCard::factory()->create([
            'jirau_id' => $jirau->id,
            'column_id' => $jirau->inboxColumn()->id,
        ]);

        $this->actingAs($member)
            ->post(route('jirau.cards.step.attach', [$jirau, $card]), ['title' => 'Hack'])
            ->assertForbidden();

        $this->actingAs($member)
            ->delete(route('jirau.cards.step.detach', [$jirau, $card]))
            ->assertForbidden();

        $this->assertSame(1, $project->jiraus()->count());
        $this->assertSame(0, $project->steps()->count());
    }

    public function test_admin_creates_step_from_board_and_links_card(): void
    {
        $admin = User::factory()->create();
        $jirau = Jirau::factory()->create();

        $this->actingAs($admin)
            ->post(route('jirau.steps.store', $jirau), [
                'title' => 'Levantamento',
                'notes' => 'Kickoff com o cliente',
                'create_card' => '1',
            ])
            ->assertRedirect(route('jirau.show', $jirau));

        $step = ProjectStep::query()->where('title', 'Levantamento')->first();
        $this->assertNotNull($step);
        $this->assertSame($jirau->project_id, $step->project_id);
        $this->assertNotNull($step->jirauCard);
        $this->assertSame('Levantamento', $step->jirauCard->title);
    }

    public function test_import_joins_unlinked_steps_and_rejects_foreign_project(): void
    {
        $admin = User::factory()->create();
        $jirau = Jirau::factory()->create();
        $own = $jirau->project->steps()->create([
            'title' => 'Homologação',
            'sort_order' => 1,
            'is_completed' => false,
        ]);
        $foreign = Project::factory()->create();
        $otherStep = $foreign->steps()->create([
            'title' => 'Segredo',
            'sort_order' => 1,
            'is_completed' => false,
        ]);

        $this->actingAs($admin)
            ->post(route('jirau.steps.import', $jirau))
            ->assertRedirect(route('jirau.show', $jirau));

        $this->assertNotNull($own->fresh()->jirauCard);
        $this->assertNull($otherStep->fresh()->jirauCard);

        $card = JirauCard::factory()->create([
            'jirau_id' => $jirau->id,
            'column_id' => $jirau->inboxColumn()->id,
            'title' => 'Cartão local',
        ]);

        $this->actingAs($admin)
            ->post(route('jirau.cards.step.attach', [$jirau, $card]), [
                'step_id' => $otherStep->id,
            ])
            ->assertNotFound();

        $foreignBoard = Jirau::factory()->create();
        $foreignCard = JirauCard::factory()->create([
            'jirau_id' => $foreignBoard->id,
            'column_id' => $foreignBoard->inboxColumn()->id,
        ]);

        $this->actingAs($admin)
            ->post(route('jirau.cards.step.attach', [$jirau, $foreignCard]))
            ->assertNotFound();
    }

    public function test_moving_card_to_done_completes_linked_step(): void
    {
        $admin = User::factory()->create();
        $jirau = Jirau::factory()->create();
        $step = $jirau->project->steps()->create([
            'title' => 'Go-live',
            'sort_order' => 1,
            'is_completed' => false,
        ]);
        $inbox = $jirau->inboxColumn();
        $done = $jirau->doneColumn();
        $card = JirauCard::factory()->create([
            'jirau_id' => $jirau->id,
            'column_id' => $inbox->id,
            'title' => 'Go-live',
        ]);
        $card->forceFill(['project_step_id' => $step->id])->save();

        $this->actingAs($admin)
            ->postJson(route('jirau.cards.move', [$jirau, $card]), [
                'column_id' => $done->id,
                'ordered_ids' => [$card->id],
            ])
            ->assertOk();

        $this->assertTrue($step->fresh()->is_completed);
    }

    public function test_export_card_creates_project_step(): void
    {
        $admin = User::factory()->create();
        $jirau = Jirau::factory()->create();
        $card = JirauCard::factory()->create([
            'jirau_id' => $jirau->id,
            'column_id' => $jirau->inboxColumn()->id,
            'title' => 'Publicar API',
        ]);

        $this->actingAs($admin)
            ->post(route('jirau.cards.step.attach', [$jirau, $card]))
            ->assertRedirect();

        $this->assertDatabaseHas('project_steps', [
            'project_id' => $jirau->project_id,
            'title' => 'Publicar API',
        ]);
        $this->assertNotNull($card->fresh()->project_step_id);
    }

    public function test_activities_template_has_waiting_lane(): void
    {
        $admin = User::factory()->create();
        $project = Project::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.projects.jirau.store', $project), [
                'name' => 'Rotina',
                'template' => JirauTemplate::Activities->value,
            ])
            ->assertRedirect();

        $jirau = $project->jiraus()->first();
        $this->assertSame(JirauTemplate::Activities, $jirau->template);
        $this->assertTrue($jirau->columns()->where('slug', 'waiting')->exists());
        $this->assertTrue($jirau->columns()->where('is_inbox', true)->exists());
        $this->assertTrue($jirau->labels()->where('name', 'Reunião')->exists());
    }
}
