<?php

namespace Tests\Feature\Admin;

use App\Models\Project;
use App\Models\ProjectStep;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectBoardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
    }

    public function test_admin_can_move_project_status_via_patch(): void
    {
        $project = Project::factory()->create(['status' => 'active', 'name' => 'Board Move']);

        $this->actingAs($this->admin)
            ->patchJson(route('admin.projects.status', $project), ['status' => 'paused'])
            ->assertOk()
            ->assertJsonPath('status', 'paused')
            ->assertJsonPath('ok', true);

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'status' => 'paused',
        ]);
    }

    public function test_admin_can_reorder_projects_within_column(): void
    {
        $first = Project::factory()->create([
            'status' => 'active',
            'name' => 'Primeiro',
            'sort_order' => 10,
        ]);
        $second = Project::factory()->create([
            'status' => 'active',
            'name' => 'Segundo',
            'sort_order' => 20,
        ]);
        $third = Project::factory()->create([
            'status' => 'active',
            'name' => 'Terceiro',
            'sort_order' => 30,
        ]);

        $this->actingAs($this->admin)
            ->patchJson(route('admin.projects.status', $third), [
                'status' => 'active',
                'ordered_ids' => [$third->id, $first->id, $second->id],
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('ordered_ids.0', $third->id);

        $this->assertSame(10, (int) $third->fresh()->sort_order);
        $this->assertSame(20, (int) $first->fresh()->sort_order);
        $this->assertSame(30, (int) $second->fresh()->sort_order);

        $ordered = Project::query()->where('status', 'active')->ordered()->pluck('id')->all();
        $this->assertSame([$third->id, $first->id, $second->id], $ordered);
    }

    public function test_admin_can_move_project_and_place_in_column_order(): void
    {
        $activeA = Project::factory()->create([
            'status' => 'active',
            'name' => 'Ativo A',
            'sort_order' => 10,
        ]);
        $activeB = Project::factory()->create([
            'status' => 'active',
            'name' => 'Ativo B',
            'sort_order' => 20,
        ]);
        $paused = Project::factory()->create([
            'status' => 'paused',
            'name' => 'Pausado',
            'sort_order' => 10,
        ]);

        $this->actingAs($this->admin)
            ->patchJson(route('admin.projects.status', $paused), [
                'status' => 'active',
                'ordered_ids' => [$activeA->id, $paused->id, $activeB->id],
            ])
            ->assertOk()
            ->assertJsonPath('status', 'active');

        $this->assertDatabaseHas('projects', [
            'id' => $paused->id,
            'status' => 'active',
            'sort_order' => 20,
        ]);
        $this->assertSame(10, (int) $activeA->fresh()->sort_order);
        $this->assertSame(30, (int) $activeB->fresh()->sort_order);
    }

    public function test_project_steps_drive_completion_percent(): void
    {
        $project = Project::factory()->create(['name' => 'Com Etapas']);

        $this->actingAs($this->admin)->post(route('admin.projects.steps.store', $project), [
            'title' => 'Discovery',
            'notes' => 'Kickoff feito',
        ])->assertRedirect();

        $this->actingAs($this->admin)->post(route('admin.projects.steps.store', $project), [
            'title' => 'Entrega',
            'notes' => null,
        ])->assertRedirect();

        $project->refresh()->loadCount(['steps', 'steps as done_steps_count' => fn ($q) => $q->where('is_completed', true)]);
        $this->assertSame(0, $project->progressStats()['percent']);
        $this->assertSame('steps', $project->progressStats()['source']);

        $step = ProjectStep::query()->where('project_id', $project->id)->where('title', 'Discovery')->first();
        $this->assertNotNull($step);

        $this->actingAs($this->admin)->put(route('admin.projects.steps.update', [$project, $step]), [
            'title' => 'Discovery',
            'notes' => 'OK',
            'is_completed' => '1',
        ])->assertRedirect();

        $project->refresh()->loadCount([
            'steps',
            'steps as done_steps_count' => fn ($q) => $q->where('is_completed', true),
        ]);

        $this->assertSame(50, $project->progressStats()['percent']);

        $this->actingAs($this->admin)
            ->get(route('admin.projects.edit', $project))
            ->assertOk()
            ->assertSee('Etapas / to-do', false)
            ->assertSee('Discovery', false)
            ->assertSee('50%', false);

        $this->actingAs($this->admin)
            ->get(route('admin.projects.index'))
            ->assertOk()
            ->assertSee('data-project-board', false)
            ->assertSee('data-status-url-template', false)
            ->assertSee('1/2 · 50%', false);
    }
}
