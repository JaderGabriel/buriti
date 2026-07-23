<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TaskRequest;
use App\Models\Contact;
use App\Models\Project;
use App\Models\Task;
use App\Services\AttachmentService;
use App\Services\AuditLogger;
use App\Services\GoogleCalendarService;
use App\Services\SettingService;
use App\Services\TaskIcsExporter;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class TaskController extends Controller
{
    /** @var list<string> */
    private const VIEWS = ['calendar', 'agenda', 'board', 'list'];

    public function __construct(
        private SettingService $settings,
        private GoogleCalendarService $google,
        private AttachmentService $attachments,
        private AuditLogger $audit,
        private TaskIcsExporter $icsExporter,
    ) {}

    public function index(Request $request): View
    {
        $view = $this->resolveView($request->query('view'));
        $cursor = $this->resolveMonth($request->query('month'));

        $tasks = Task::query()
            ->with(['project', 'contact', 'attachments', 'trashedAttachments.deleter'])
            ->boardOrdered()
            ->get();

        $columns = [];
        $grouped = $tasks->groupBy(fn (Task $task) => $task->status->value);
        foreach (TaskStatus::boardOrder() as $status) {
            $columns[$status] = $grouped->get($status, collect());
        }

        $byDate = $tasks
            ->filter(fn (Task $task) => $task->due_at !== null)
            ->sortBy(fn (Task $task) => $task->due_at->timestamp)
            ->groupBy(fn (Task $task) => $task->due_at->format('Y-m-d'))
            ->map(fn ($dayTasks) => $dayTasks->values());

        $gridStart = $cursor->copy()->startOfMonth()->startOfWeek(Carbon::SUNDAY);
        $gridEnd = $cursor->copy()->endOfMonth()->endOfWeek(Carbon::SATURDAY);

        $calendarDays = collect();
        for ($day = $gridStart->copy(); $day->lte($gridEnd); $day->addDay()) {
            $key = $day->format('Y-m-d');
            $calendarDays->push([
                'date' => $key,
                'day' => $day->day,
                'in_month' => $day->month === $cursor->month,
                'is_today' => $day->isToday(),
                'weekday' => $day->dayOfWeek,
                'tasks' => $byDate->get($key, collect()),
            ]);
        }

        $undatedTasks = $tasks->filter(fn (Task $task) => $task->due_at === null)->values();

        $agendaGroups = $byDate;

        return view('admin.tasks.index', [
            'view' => $view,
            'month' => $cursor->format('Y-m'),
            'monthLabel' => $cursor->translatedFormat('F Y'),
            'prevMonth' => $cursor->copy()->subMonth()->format('Y-m'),
            'nextMonth' => $cursor->copy()->addMonth()->format('Y-m'),
            'calendarDays' => $calendarDays,
            'agendaGroups' => $agendaGroups,
            'undatedTasks' => $undatedTasks,
            'tasks' => $tasks,
            'columns' => $columns,
            'statusLabels' => TaskStatus::options(),
            'priorityLabels' => TaskPriority::options(),
            'projects' => Project::query()->orderBy('name')->get(),
            'contacts' => Contact::query()->orderBy('name')->get(),
            'googleCalendarSrc' => $this->settings->calendarSrc(),
            'googleCalendarUrl' => $this->google->calendarHomeUrl(),
            'googleIntegration' => $this->google->integrationStatus(),
            'instantMeetUrl' => $this->google->instantMeetUrl(),
            'stats' => [
                'total' => $tasks->count(),
                'open' => $tasks->filter(fn (Task $task) => in_array($task->status, [TaskStatus::Todo, TaskStatus::Doing], true))->count(),
                'due_month' => $tasks->filter(
                    fn (Task $task) => $task->due_at
                        && $task->due_at->isSameMonth($cursor)
                )->count(),
                'undated' => $undatedTasks->count(),
            ],
        ]);
    }

    public function export(Request $request): Response
    {
        $cursor = $this->resolveMonth($request->query('month'));

        $tasks = Task::query()
            ->whereNotNull('due_at')
            ->whereBetween('due_at', [
                $cursor->copy()->startOfMonth()->startOfDay(),
                $cursor->copy()->endOfMonth()->endOfDay(),
            ])
            ->orderBy('due_at')
            ->get();

        $ics = $this->icsExporter->export(
            $tasks,
            'BURI-TI Agenda '.$cursor->translatedFormat('F Y')
        );

        $filename = 'buriti-agenda-'.$cursor->format('Y-m').'.ics';

        return response($ics, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function store(TaskRequest $request): RedirectResponse
    {
        $task = Task::query()->create($request->validated());

        $this->audit->record('task.created', $task, ['summary' => $task->title]);

        if ($this->shouldAutoSync()) {
            $result = $this->google->syncTask($task);

            if (($result['mode'] ?? null) === 'redirect' && ! empty($result['url'])) {
                return redirect()->away($result['url']);
            }

            return $this->tasksRedirect()
                ->with('success', 'Tarefa criada. '.($result['message'] ?? ''));
        }

        return $this->tasksRedirect()->with('success', 'Tarefa criada.');
    }

    public function update(TaskRequest $request, Task $task): RedirectResponse
    {
        $task->update($request->validated());

        $this->audit->record('task.updated', $task, ['summary' => $task->title]);

        if ($this->shouldAutoSync() && $task->want_meet) {
            $result = $this->google->syncTask($task->fresh());

            return $this->tasksRedirect()
                ->with('success', 'Tarefa atualizada. '.($result['message'] ?? ''));
        }

        return $this->tasksRedirect()->with('success', 'Tarefa atualizada.');
    }

    public function destroy(Task $task): RedirectResponse
    {
        $summary = $task->title;
        $this->google->deleteRemoteEvent($task);
        $this->attachments->deleteAllFor($task, auth()->id());
        $task->delete();

        $this->audit->record('task.deleted', null, [
            'summary' => $summary,
            'task_id' => $task->id,
        ]);

        return $this->tasksRedirect()->with('success', 'Tarefa removida.');
    }

    public function syncGoogle(Task $task): RedirectResponse
    {
        $result = $this->google->syncTask($task);

        if (($result['mode'] ?? null) === 'redirect' && ! empty($result['url'])) {
            return redirect()->away($result['url']);
        }

        return $this->tasksRedirect()
            ->with('success', $result['message'] ?? 'Sincronizado com Google.');
    }

    private function shouldAutoSync(): bool
    {
        return $this->settings->autoSyncEnabled() && $this->google->apiConfigured();
    }

    private function resolveView(?string $view): string
    {
        return in_array($view, self::VIEWS, true) ? $view : 'calendar';
    }

    private function resolveMonth(?string $month): Carbon
    {
        try {
            return Carbon::createFromFormat('Y-m', (string) $month)->startOfMonth();
        } catch (\Throwable) {
            return now()->startOfMonth();
        }
    }

    private function tasksRedirect(): RedirectResponse
    {
        $params = [];

        if (request()->filled('return_view') || request()->filled('view')) {
            $params['view'] = $this->resolveView((string) request('return_view', request('view')));
        }

        if (request()->filled('return_month') || request()->filled('month')) {
            $params['month'] = (string) request('return_month', request('month'));
        }

        return redirect()->route('admin.tasks.index', $params);
    }
}