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
