<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TaskRequest;
use App\Models\Project;
use App\Models\Task;
use App\Services\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TaskController extends Controller
{
    public function __construct(private SettingService $settings) {}

    public function index(): View
    {
        $tasks = Task::query()
            ->with('project')
            ->boardOrdered()
            ->get()
            ->groupBy(fn (Task $task) => $task->status->value);

        $columns = [];
        foreach (TaskStatus::boardOrder() as $status) {
            $columns[$status] = $tasks->get($status, collect());
        }

        return view('admin.tasks.index', [
            'columns' => $columns,
            'statusLabels' => TaskStatus::options(),
            'priorityLabels' => TaskPriority::options(),
            'projects' => Project::query()->orderBy('name')->get(),
            'googleCalendarSrc' => $this->settings->calendarSrc(),
            'googleCalendarUrl' => $this->settings->get('google_calendar_url'),
        ]);
    }

    public function store(TaskRequest $request): RedirectResponse
    {
        Task::query()->create($request->validated());

        return redirect()
            ->route('admin.tasks.index')
            ->with('success', 'Tarefa criada.');
    }

    public function update(TaskRequest $request, Task $task): RedirectResponse
    {
        $task->update($request->validated());

        return redirect()
            ->route('admin.tasks.index')
            ->with('success', 'Tarefa atualizada.');
    }

    public function destroy(Task $task): RedirectResponse
    {
        $task->delete();

        return redirect()
            ->route('admin.tasks.index')
            ->with('success', 'Tarefa removida.');
    }
}
