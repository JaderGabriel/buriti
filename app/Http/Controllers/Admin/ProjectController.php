<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProjectStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProjectRequest;
use App\Models\Project;
use App\Services\AttachmentService;
use App\Services\ProjectFileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function __construct(
        private ProjectFileService $files,
        private AttachmentService $attachments,
    ) {}

    public function index(): View
    {
        $projects = Project::query()->ordered()->paginate(12);

        return view('admin.projects.index', compact('projects'));
    }

    public function create(): View
    {
        return view('admin.projects.form', [
            'project' => new Project,
            'statuses' => ProjectStatus::options(),
        ]);
    }

    public function store(ProjectRequest $request): RedirectResponse
    {
        $data = $request->projectData();
        $data['logo_path'] = $this->files->store($request->file('logo'), 'projects/logos');
        $data['contract_path'] = $this->files->store($request->file('contract'), 'projects/contracts');

        Project::query()->create($data);

        return redirect()
            ->route('admin.projects.index')
            ->with('success', 'Projeto cadastrado.');
    }

    public function edit(Project $project): View
    {
        $project->load('attachments');

        return view('admin.projects.form', [
            'project' => $project,
            'statuses' => ProjectStatus::options(),
        ]);
    }

    public function update(ProjectRequest $request, Project $project): RedirectResponse
    {
        $data = $request->projectData();
        $data['logo_path'] = $this->files->replace($project->logo_path, $request->file('logo'), 'projects/logos');
        $data['contract_path'] = $this->files->replace($project->contract_path, $request->file('contract'), 'projects/contracts');

        $project->update($data);

        return redirect()
            ->route('admin.projects.index')
            ->with('success', 'Projeto atualizado.');
    }

    public function destroy(Project $project): RedirectResponse
    {
        $this->files->delete($project->logo_path);
        $this->files->delete($project->contract_path);
        $this->attachments->deleteAllFor($project);
        $project->delete();

        return redirect()
            ->route('admin.projects.index')
            ->with('success', 'Projeto removido.');
    }
}
