<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ContactSource;
use App\Enums\ContactStatus;
use App\Enums\CrmActivityType;
use App\Enums\OpportunityStage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AttachProjectToContactRequest;
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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        $contacts = Contact::query()
            ->with('clientCompany')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('company_id'), fn ($q) => $q->where('company_id', $request->integer('company_id')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('name', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('company', 'like', $term)
                        ->orWhere('phone', 'like', $term)
                        ->orWhereHas('clientCompany', fn ($company) => $company->where('name', 'like', $term));
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.contacts.index', [
            'contacts' => $contacts,
            'statuses' => ContactStatus::options(),
            'statusCounts' => Contact::query()
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
        ]);
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

    public function show(Contact $contact): View
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

        return view('admin.contacts.show', [
            'contact' => $contact,
            'statuses' => ContactStatus::options(),
            'sources' => ContactSource::options(),
            'stages' => OpportunityStage::options(),
            'activityTypes' => CrmActivityType::options(),
            'allProjects' => Project::query()->orderBy('name')->get(),
            'openTasks' => Task::query()->open()->orderBy('title')->get(),
        ]);
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

        if (! empty($data['opportunity_id'])) {
            $owns = $contact->opportunities()->whereKey($data['opportunity_id'])->exists();
            if (! $owns) {
                return back()->withErrors(['opportunity_id' => 'Oportunidade inválida para este contato.']);
            }
        }

        CrmActivity::query()->create([
            ...$data,
            'contact_id' => $contact->id,
            'user_id' => $request->user()->id,
        ]);

        return back()->with('success', 'Atividade registada.');
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
