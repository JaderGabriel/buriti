<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ContactSource;
use App\Enums\ContactStatus;
use App\Enums\CrmActivityType;
use App\Enums\OpportunityStage;
use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AttachProjectToContactRequest;
use App\Http\Requests\Admin\BulkCrmActivityRequest;
use App\Http\Requests\Admin\ContactRequest;
use App\Http\Requests\Admin\CrmActivityRequest;
use App\Models\Company;
use App\Models\Contact;
use App\Models\CrmActivity;
use App\Models\Project;
use App\Models\Task;
use App\Services\AttachmentService;
use App\Services\AuditLogger;
use App\Services\CompanyResolver;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function __construct(
        private AttachmentService $attachments,
        private AuditLogger $audit,
        private CompanyResolver $companies,
    ) {}

    public function index(Request $request): View
    {
        $view = $this->resolveContactsView($request->query('view'));
        $letter = $this->resolveAlphabetLetter($request->query('letter'));

        $baseQuery = Contact::query()
            ->with('clientCompany')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('company_id'), fn ($q) => $q->where('company_id', $request->integer('company_id')))
            ->when($request->filled('channel'), fn ($q) => $q->where('preferred_channel', $request->string('channel')))
            ->when($request->string('phone') === 'with', fn ($q) => $q->whereNotNull('phone')->where('phone', '!=', ''))
            ->when($request->string('phone') === 'without', fn ($q) => $q->where(fn ($inner) => $inner->whereNull('phone')->orWhere('phone', '')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('name', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('company', 'like', $term)
                        ->orWhere('phone', 'like', $term)
                        ->orWhereHas('clientCompany', fn ($company) => $company->where('name', 'like', $term));
                });
            });

        $letterCounts = (clone $baseQuery)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Contact $contact) => $contact->alphabetLetter())
            ->countBy()
            ->all();

        if ($view === 'cards' && $letter === null) {
            $contacts = (clone $baseQuery)
                ->orderBy('name')
                ->paginate(24)
                ->withQueryString();
            $groups = collect();
        } else {
            $contacts = (clone $baseQuery)
                ->orderBy('name')
                ->get()
                ->when($letter !== null, fn ($items) => $items->filter(
                    fn (Contact $contact) => $contact->alphabetLetter() === $letter
                )->values());
            $groups = $contacts->groupBy(fn (Contact $contact) => $contact->alphabetLetter());
        }

        return view('admin.contacts.index', [
            'view' => $view,
            'letter' => $letter,
            'contacts' => $contacts,
            'groups' => $groups,
            'letterCounts' => $letterCounts,
            'alphabet' => array_merge(range('A', 'Z'), ['#']),
            'statuses' => ContactStatus::options(),
            'statusCounts' => Contact::query()
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
            'companies' => Company::query()->orderBy('name')->get(),
            'channels' => [
                'email' => 'E-mail',
                'phone' => 'Telefone',
                'whatsapp' => 'WhatsApp',
            ],
            'activityTypes' => CrmActivityType::options(),
            'activityTypeMeta' => collect(CrmActivityType::cases())
                ->mapWithKeys(fn (CrmActivityType $type) => [$type->value => [
                    'label' => $type->label(),
                    'icon' => $type->icon(),
                    'tone' => $type->tone(),
                ]])
                ->all(),
            'openTasks' => Task::query()
                ->where(function ($query) {
                    $query->open()->orWhere(function ($done) {
                        $done->where('status', TaskStatus::Done)->whereNotNull('contact_id');
                    });
                })
                ->orderBy('title')
                ->limit(120)
                ->get(),
            'pickerContacts' => Contact::query()
                ->with('clientCompany:id,name')
                ->orderBy('name')
                ->get(['id', 'name', 'company', 'company_id', 'phone', 'email', 'status']),
        ]);
    }

    private function resolveContactsView(?string $view): string
    {
        return in_array($view, ['phonebook', 'cards'], true) ? $view : 'phonebook';
    }

    private function resolveAlphabetLetter(mixed $letter): ?string
    {
        $letter = strtoupper(trim((string) $letter));

        if ($letter === '' || $letter === 'ALL') {
            return null;
        }

        if ($letter === '#') {
            return '#';
        }

        return preg_match('/^[A-Z]$/', $letter) === 1 ? $letter : null;
    }

    public function create(Request $request): View
    {
        $companyId = $request->integer('company_id') ?: null;

        return view('admin.contacts.form', [
            'contact' => new Contact([
                'status' => ContactStatus::Lead,
                'source' => ContactSource::Manual,
                'company_id' => $companyId,
                'company' => $companyId
                    ? Company::query()->find($companyId)?->name
                    : null,
            ]),
            'statuses' => ContactStatus::options(),
            'sources' => ContactSource::options(),
            'companies' => Company::query()->orderBy('name')->get(),
        ]);
    }

    public function store(ContactRequest $request): RedirectResponse
    {
        $contact = Contact::query()->create($request->contactData($this->companies));

        $this->audit->record('contact.created', $contact, [
            'summary' => $contact->name,
            'email' => $contact->email,
            'company_id' => $contact->company_id,
        ]);

        return redirect()
            ->route('admin.contacts.show', $contact)
            ->with('success', 'Contato criado.');
    }

    public function show(Request $request, Contact $contact): View
    {
        $contact->load([
            'clientCompany',
            'opportunities.project',
            'projects.clientCompany',
            'messages' => fn ($q) => $q->latest()->limit(20),
            'tasks.project',
            'activities' => fn ($q) => $q->with(['user', 'opportunity', 'task'])->latest('happened_at')->limit(40),
            'attachments',
            'trashedAttachments.deleter',
        ]);

        $agenda = $this->buildContactAgenda($contact, $request->query('month'));

        return view('admin.contacts.show', [
            'contact' => $contact,
            'statuses' => ContactStatus::options(),
            'sources' => ContactSource::options(),
            'stages' => OpportunityStage::options(),
            'activityTypes' => CrmActivityType::options(),
            'activityTypeMeta' => collect(CrmActivityType::cases())
                ->mapWithKeys(fn (CrmActivityType $type) => [$type->value => [
                    'label' => $type->label(),
                    'icon' => $type->icon(),
                    'tone' => $type->tone(),
                ]])
                ->all(),
            'allProjects' => Project::query()->orderBy('name')->get(),
            'linkableTasks' => $this->linkableTasksForContact($contact),
            'pickerContacts' => Contact::query()
                ->with('clientCompany:id,name')
                ->orderBy('name')
                ->get(['id', 'name', 'company', 'company_id', 'phone', 'email', 'status']),
            ...$agenda,
        ]);
    }

    /**
     * Tarefas abertas + tarefas deste contato (incl. concluídas), para notas posteriores.
     *
     * @return \Illuminate\Support\Collection<int, Task>
     */
    private function linkableTasksForContact(Contact $contact, ?Task $include = null): \Illuminate\Support\Collection
    {
        $tasks = Task::query()
            ->where(function ($query) use ($contact) {
                $query->open()->orWhere('contact_id', $contact->id);
            })
            ->orderBy('title')
            ->get();

        if ($include && ! $tasks->contains('id', $include->id)) {
            $tasks = $tasks->prepend($include)->unique('id')->values();
        }

        return $tasks->sortBy(fn (Task $task) => mb_strtolower($task->title))->values();
    }

    /**
     * @return array{
     *     agendaMonth: string,
     *     agendaMonthLabel: string,
     *     agendaPrevMonth: string,
     *     agendaNextMonth: string,
     *     agendaCalendarDays: \Illuminate\Support\Collection<int, array<string, mixed>>,
     *     agendaUndatedTasks: \Illuminate\Support\Collection<int, Task>
     * }
     */
    private function buildContactAgenda(Contact $contact, mixed $monthQuery): array
    {
        $cursor = $this->resolveAgendaMonth($monthQuery);
        $tasks = $contact->tasks;

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
                'tasks' => $byDate->get($key, collect()),
            ]);
        }

        return [
            'agendaMonth' => $cursor->format('Y-m'),
            'agendaMonthLabel' => Str::ucfirst($cursor->translatedFormat('F Y')),
            'agendaPrevMonth' => $cursor->copy()->subMonth()->format('Y-m'),
            'agendaNextMonth' => $cursor->copy()->addMonth()->format('Y-m'),
            'agendaCalendarDays' => $calendarDays,
            'agendaUndatedTasks' => $tasks->filter(fn (Task $task) => $task->due_at === null)->values(),
        ];
    }

    private function resolveAgendaMonth(mixed $monthQuery): Carbon
    {
        $raw = is_string($monthQuery) ? $monthQuery : '';

        if (preg_match('/^\d{4}-\d{2}$/', $raw) === 1) {
            try {
                return Carbon::createFromFormat('Y-m', $raw)->startOfMonth();
            } catch (\Throwable) {
                // fall through
            }
        }

        return now()->startOfMonth();
    }

    public function edit(Contact $contact): View
    {
        $contact->load('clientCompany');

        return view('admin.contacts.form', [
            'contact' => $contact,
            'statuses' => ContactStatus::options(),
            'sources' => ContactSource::options(),
            'companies' => Company::query()->orderBy('name')->get(),
        ]);
    }

    public function update(ContactRequest $request, Contact $contact): RedirectResponse
    {
        $contact->update($request->contactData($this->companies));

        $this->audit->record('contact.updated', $contact, [
            'summary' => $contact->name,
            'email' => $contact->email,
            'company_id' => $contact->company_id,
        ]);

        return redirect()
            ->route('admin.contacts.show', $contact)
            ->with('success', 'Contato atualizado.');
    }

    public function destroy(Contact $contact): RedirectResponse
    {
        $summary = $contact->name;
        $this->attachments->deleteAllFor($contact, auth()->id());
        $contact->delete();

        $this->audit->record('contact.deleted', null, [
            'summary' => $summary,
            'contact_id' => $contact->id,
        ]);

        return redirect()
            ->route('admin.contacts.index')
            ->with('success', 'Contato removido.');
    }

    public function storeActivity(CrmActivityRequest $request, Contact $contact): RedirectResponse
    {
        $data = $request->validated();
        $completeTask = (bool) ($data['complete_task'] ?? false);
        unset($data['complete_task']);

        if (! empty($data['opportunity_id'])) {
            $owns = $contact->opportunities()->whereKey($data['opportunity_id'])->exists();
            if (! $owns) {
                return back()->withErrors(['opportunity_id' => 'Oportunidade inválida para este contato.']);
            }
        }

        $task = null;
        if (! empty($data['task_id'])) {
            $task = Task::query()->find($data['task_id']);
            if (! $task) {
                return back()->withErrors(['task_id' => 'Tarefa inválida.']);
            }
        }

        CrmActivity::query()->create([
            ...$data,
            'contact_id' => $contact->id,
            'user_id' => $request->user()->id,
        ]);

        $message = 'Atividade registada.';
        if ($task && $completeTask) {
            $task->forceFill([
                'status' => TaskStatus::Done,
            ])->save();
            $message = 'Atividade registada e tarefa marcada como concluída.';
        }

        return back()->with('success', $message);
    }

    public function storeBulkActivity(BulkCrmActivityRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $contactIds = $data['contact_ids'];
        $completeTask = (bool) ($data['complete_task'] ?? false);
        unset($data['contact_ids'], $data['complete_task']);

        $task = null;
        if (! empty($data['task_id'])) {
            $task = Task::query()->find($data['task_id']);
            if (! $task) {
                return back()->withErrors(['task_id' => 'Tarefa inválida.']);
            }
        }

        $created = 0;
        foreach ($contactIds as $contactId) {
            CrmActivity::query()->create([
                ...$data,
                'contact_id' => $contactId,
                'opportunity_id' => null,
                'user_id' => $request->user()->id,
            ]);
            $created++;
        }

        if ($task && $completeTask) {
            $task->forceFill([
                'status' => TaskStatus::Done,
            ])->save();
        }

        $message = $created === 1
            ? 'Atividade registada em 1 contato.'
            : "Atividade registada em {$created} contatos.";

        if ($task && $completeTask) {
            $message .= ' Tarefa marcada como concluída.';
        }

        return back()->with('success', $message);
    }

    public function editActivity(Contact $contact, CrmActivity $activity): View
    {
        abort_unless($activity->contact_id === $contact->id, 404);

        $contact->load(['opportunities', 'clientCompany']);
        $activity->load(['task', 'opportunity', 'user']);

        $relatedActivities = collect();
        if ($activity->task_id) {
            $relatedActivities = CrmActivity::query()
                ->with(['user', 'contact'])
                ->where('task_id', $activity->task_id)
                ->orderBy('happened_at')
                ->orderBy('id')
                ->get();
        }

        return view('admin.contacts.activities.edit', [
            'contact' => $contact,
            'activity' => $activity,
            'activityTypes' => CrmActivityType::options(),
            'linkableTasks' => $this->linkableTasksForContact($contact, $activity->task),
            'relatedActivities' => $relatedActivities,
        ]);
    }

    public function updateActivity(CrmActivityRequest $request, Contact $contact, CrmActivity $activity): RedirectResponse
    {
        abort_unless($activity->contact_id === $contact->id, 404);

        $data = $request->validated();
        $completeTask = (bool) ($data['complete_task'] ?? false);
        unset($data['complete_task']);

        if (! empty($data['opportunity_id'])) {
            $owns = $contact->opportunities()->whereKey($data['opportunity_id'])->exists();
            if (! $owns) {
                return back()->withErrors(['opportunity_id' => 'Oportunidade inválida para este contato.']);
            }
        }

        $task = null;
        if (! empty($data['task_id'])) {
            $task = Task::query()->find($data['task_id']);
            if (! $task) {
                return back()->withErrors(['task_id' => 'Tarefa inválida.']);
            }
        }

        $activity->update($data);

        $message = 'Atividade actualizada.';
        if ($task && $completeTask) {
            $task->forceFill([
                'status' => TaskStatus::Done,
            ])->save();
            $message = 'Atividade actualizada e tarefa marcada como concluída.';
        }

        return redirect()
            ->route('admin.contacts.show', $contact)
            ->with('success', $message);
    }

    public function destroyActivity(Contact $contact, CrmActivity $activity): RedirectResponse
    {
        abort_unless($activity->contact_id === $contact->id, 404);

        $activity->delete();

        return back()->with('success', 'Atividade removida.');
    }

    public function attachProject(AttachProjectToContactRequest $request, Contact $contact): RedirectResponse
    {
        $projectId = $request->validated('project_id');

        $contact->projects()->syncWithoutDetaching([$projectId]);

        if ($contact->company_id) {
            Project::query()
                ->whereKey($projectId)
                ->whereNull('company_id')
                ->update(['company_id' => $contact->company_id]);
        }

        return back()->with('success', 'Projeto vinculado ao contato.');
    }

    public function detachProject(Contact $contact, Project $project): RedirectResponse
    {
        $contact->projects()->detach($project->id);

        return back()->with('success', 'Projeto desvinculado.');
    }
}
