<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TaskRequest;
use App\Models\Contact;
use App\Models\Project;
use App\Models\Task;
use App\Services\GoogleCalendarService;
use App\Services\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TaskController extends Controller
{
    public function __construct(
        private SettingService $settings,
        private GoogleCalendarService $google,
    ) {}

    public function index(): View
    {
        $tasks = Task::query()
            ->with(['project', 'contact'])
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
            'contacts' => Contact::query()->orderBy('name')->get(),
            'googleCalendarSrc' => $this->settings->calendarSrc(),
            'googleCalendarUrl' => $this->google->calendarHomeUrl(),
            'googleIntegration' => $this->google->integrationStatus(),
            'instantMeetUrl' => $this->google->instantMeetUrl(),
        ]);
    }

    public function store(TaskRequest $request): RedirectResponse
    {
        $task = Task::query()->create($request->validated());

        if ($this->shouldAutoSync()) {
            $result = $this->google->syncTask($task);

            if (($result['mode'] ?? null) === 'redirect' && ! empty($result['url'])) {
                return redirect()->away($result['url']);
            }

            return redirect()
                ->route('admin.tasks.index')
                ->with('success', 'Tarefa criada. '.($result['message'] ?? ''));
        }

        return redirect()
            ->route('admin.tasks.index')
            ->with('success', 'Tarefa criada.');
    }

    public function update(TaskRequest $request, Task $task): RedirectResponse
    {
        $task->update($request->validated());

        if ($this->shouldAutoSync() && $task->want_meet) {
            $result = $this->google->syncTask($task->fresh());

            return redirect()
                ->route('admin.tasks.index')
                ->with('success', 'Tarefa atualizada. '.($result['message'] ?? ''));
        }

        return redirect()
            ->route('admin.tasks.index')
            ->with('success', 'Tarefa atualizada.');
    }

    public function destroy(Task $task): RedirectResponse
    {
        $this->google->deleteRemoteEvent($task);
        $task->delete();

        return redirect()
            ->route('admin.tasks.index')
            ->with('success', 'Tarefa removida.');
    }

    public function syncGoogle(Task $task): RedirectResponse
    {
        $result = $this->google->syncTask($task);

        if (($result['mode'] ?? null) === 'redirect' && ! empty($result['url'])) {
            return redirect()->away($result['url']);
        }

        return redirect()
            ->route('admin.tasks.index')
            ->with('success', $result['message'] ?? 'Sincronizado com Google.');
    }

    private function shouldAutoSync(): bool
    {
        return $this->settings->autoSyncEnabled() && $this->google->apiConfigured();
    }
}
