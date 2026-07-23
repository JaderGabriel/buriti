<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProjectRequest;
use App\Http\Requests\Admin\UpdateProjectStatusRequest;
use App\Models\Company;
use App\Models\Project;
use App\Services\AttachmentService;
use App\Services\AuditLogger;
use App\Services\ProjectFileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectController extends Controller
{
    /** @var list<string> */
    private const VIEWS = ['board', 'list'];

    public function __construct(
        private ProjectFileService $files,
        private AttachmentService $attachments,
        private AuditLogger $audit,
    ) {}

    public function index(Request $request): View
    {
        $view = $this->resolveView($request->query('view'));
        $statusFilter = $this->resolveStatusFilter($request->query('status'));

        $baseQuery = Project::query()
            ->withCount([
                'tasks',
                'tasks as open_tasks_count' => fn ($query) => $query->whereIn('status', [
                    TaskStatus::Todo->value,
                    TaskStatus::Doing->value,
                ]),
                'tasks as done_tasks_count' => fn ($query) => $query->where('status', TaskStatus::Done->value),
                'steps',
                'steps as done_steps_count' => fn ($query) => $query->where('is_completed', true),
            ])
            ->ordered();

        if ($statusFilter !== null) {
            $baseQuery->where('status', $statusFilter);
        }

        $allForStats = Project::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $stats = [
            'total' => (int) $allForStats->sum(),
            'active' => (int) ($allForStats[ProjectStatus::Active->value] ?? 0),
            'paused' => (int) ($allForStats[ProjectStatus::Paused->value] ?? 0),
            'done' => (int) ($allForStats[ProjectStatus::Done->value] ?? 0),
            'public' => Project::query()->where('is_public', true)->count(),
        ];

        $columns = [];
        $projects = null;

        if ($view === 'board') {
            $grouped = (clone $baseQuery)->get()->groupBy(fn (Project $project) => $project->status->value);
            foreach (ProjectStatus::boardOrder() as $status) {
                $columns[$status] = $grouped->get($status, collect());
            }
        } else {
            $projects = (clone $baseQuery)->paginate(16)->withQueryString();
        }

        return view('admin.projects.index', [
            'view' => $view,
            'statusFilter' => $statusFilter,
            'statusLabels' => ProjectStatus::options(),
            'columns' => $columns,
            'projects' => $projects,
            'stats' => $stats,
            'statusMoveUrlTemplate' => url('/admin/projetos/__ID__/status'),
        ]);
    }

    public function create(): View
    {
        return view('admin.projects.form', [
            'project' => new Project,
            'statuses' => ProjectStatus::options(),
            'companies' => Company::query()->orderBy('name')->get(),
        ]);
    }

    public function store(ProjectRequest $request): RedirectResponse
    {
        $data = $request->projectData();
        $data['logo_path'] = $this->files->store($request->file('logo'), 'projects/logos');
        $data['contract_path'] = $this->files->store($request->file('contract'), 'projects/contracts', 'local');

        $project = Project::query()->create($data);

        $this->audit->record('project.created', $project, ['summary' => $project->name]);

        return redirect()
            ->route('admin.projects.index')
            ->with('success', 'Projeto cadastrado.');
    }

    public function edit(Project $project): View
    {
        $project->load([
            'attachments',
            'trashedAttachments.deleter',
            'steps',
        ]);
        $project->loadCount([
            'tasks',
            'tasks as open_tasks_count' => fn ($query) => $query->whereIn('status', [
                TaskStatus::Todo->value,
                TaskStatus::Doing->value,
            ]),
            'tasks as done_tasks_count' => fn ($query) => $query->where('status', TaskStatus::Done->value),
            'steps',
            'steps as done_steps_count' => fn ($query) => $query->where('is_completed', true),
        ]);

        return view('admin.projects.form', [
            'project' => $project,
            'statuses' => ProjectStatus::options(),
            'companies' => Company::query()->orderBy('name')->get(),
        ]);
    }

    public function update(ProjectRequest $request, Project $project): RedirectResponse
    {
        $data = $request->projectData();
        $data['logo_path'] = $this->files->replace($project->logo_path, $request->file('logo'), 'projects/logos');
        $data['contract_path'] = $this->files->replace(
            $project->contract_path,
            $request->file('contract'),
            'projects/contracts',
            'local'
        );

        $project->update($data);

        $this->audit->record('project.updated', $project, ['summary' => $project->name]);

        return redirect()
            ->route('admin.projects.index')
            ->with('success', 'Projeto atualizado.');
    }

    public function updateStatus(UpdateProjectStatusRequest $request, Project $project): JsonResponse
    {
        $data = $request->validated();
        $status = ProjectStatus::from($data['status']);
        $from = $project->status?->value;
        $orderedIds = array_values(array_unique(array_map('intval', $data['ordered_ids'] ?? [])));

        $project->update(['status' => $status]);

        if ($orderedIds !== []) {
            if (! in_array((int) $project->id, $orderedIds, true)) {
                $orderedIds[] = (int) $project->id;
            }

            foreach ($orderedIds as $index => $id) {
                Project::query()
                    ->whereKey($id)
                    ->where('status', $status)
                    ->update(['sort_order' => ($index + 1) * 10]);
            }
        }

        if ($from !== $status->value) {
            $this->audit->record('project.status_moved', $project, [
                'summary' => $project->name,
                'from' => $from,
                'to' => $status->value,
            ]);
        } elseif ($orderedIds !== []) {
            $this->audit->record('project.reordered', $project, [
                'summary' => $project->name,
                'status' => $status->value,
                'ordered_ids' => $orderedIds,
            ]);
        }

        $project->loadCount([
            'steps',
            'steps as done_steps_count' => fn ($query) => $query->where('is_completed', true),
            'tasks',
            'tasks as done_tasks_count' => fn ($query) => $query->where('status', TaskStatus::Done->value),
            'tasks as open_tasks_count' => fn ($query) => $query->whereIn('status', [
                TaskStatus::Todo->value,
                TaskStatus::Doing->value,
            ]),
        ]);

        $progress = $project->progressStats();

        return response()->json([
            'ok' => true,
            'id' => $project->id,
            'status' => $project->status->value,
            'label' => $project->status->label(),
            'sort_order' => (int) $project->fresh()->sort_order,
            'ordered_ids' => $orderedIds,
            'progress' => $progress,
        ]);
    }

    public function destroy(Project $project): RedirectResponse
    {
        $summary = $project->name;
        $this->files->delete($project->logo_path);
        $this->files->delete($project->contract_path, 'local');
        $this->files->delete($project->contract_path, 'public'); // contratos antigos no disco público
        $this->attachments->deleteAllFor($project, auth()->id());
        $project->delete();

        $this->audit->record('project.deleted', null, [
            'summary' => $summary,
            'project_id' => $project->id,
        ]);

        return redirect()
            ->route('admin.projects.index')
            ->with('success', 'Projeto removido.');
    }

    public function downloadContract(Project $project): StreamedResponse
    {
        abort_unless(filled($project->contract_path), 404);

        foreach (['local', 'public'] as $disk) {
            if (Storage::disk($disk)->exists($project->contract_path)) {
                return Storage::disk($disk)->download(
                    $project->contract_path,
                    basename($project->contract_path),
                );
            }
        }

        abort(404);
    }

    private function resolveView(?string $view): string
    {
        return in_array($view, self::VIEWS, true) ? $view : 'board';
    }

    private function resolveStatusFilter(?string $status): ?string
    {
        return in_array($status, ProjectStatus::boardOrder(), true) ? $status : null;
    }
}
