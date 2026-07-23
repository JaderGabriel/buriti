<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProjectStepRequest;
use App\Http\Requests\Admin\ToggleProjectStepRequest;
use App\Models\Project;
use App\Models\ProjectStep;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;

class ProjectStepController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function store(ProjectStepRequest $request, Project $project): RedirectResponse
    {
        $nextOrder = ((int) $project->steps()->max('sort_order')) + 1;
        $data = $request->stepData();

        $step = $project->steps()->create([
            ...$data,
            'sort_order' => $nextOrder,
            'completed_at' => $data['is_completed'] ? now() : null,
        ]);

        $this->audit->record('project_step.created', $step, [
            'summary' => $step->title,
            'project_id' => $project->id,
        ]);

        return back()->with('success', 'Etapa adicionada.');
    }

    public function update(ProjectStepRequest $request, Project $project, ProjectStep $step): RedirectResponse
    {
        abort_unless($step->project_id === $project->id, 404);

        $data = $request->stepData();
        if ($data['is_completed'] && ! $step->is_completed) {
            $data['completed_at'] = now();
        } elseif (! $data['is_completed']) {
            $data['completed_at'] = null;
        } else {
            $data['completed_at'] = $step->completed_at ?? now();
        }

        $step->update($data);

        $this->audit->record('project_step.updated', $step, [
            'summary' => $step->title,
            'project_id' => $project->id,
        ]);

        return back()->with('success', 'Etapa atualizada.');
    }

    public function toggle(ToggleProjectStepRequest $request, Project $project, ProjectStep $step): RedirectResponse
    {
        abort_unless($step->project_id === $project->id, 404);

        $completed = ! $step->is_completed;
        $step->markCompleted($completed);

        if ($request->filled('notes')) {
            $step->update([
                'notes' => $request->validated('notes'),
            ]);
        }

        return back()->with('success', $completed ? 'Etapa concluída.' : 'Etapa reaberta.');
    }

    public function destroy(Project $project, ProjectStep $step): RedirectResponse
    {
        abort_unless($step->project_id === $project->id, 404);

        $summary = $step->title;
        $step->delete();

        $this->audit->record('project_step.deleted', null, [
            'summary' => $summary,
            'project_id' => $project->id,
            'step_id' => $step->id,
        ]);

        return back()->with('success', 'Etapa removida.');
    }
}
