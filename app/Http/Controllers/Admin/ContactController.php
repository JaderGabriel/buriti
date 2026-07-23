<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ContactSource;
use App\Enums\ContactStatus;
use App\Enums\CrmActivityType;
use App\Enums\OpportunityStage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ContactRequest;
use App\Http\Requests\Admin\CrmActivityRequest;
use App\Models\Contact;
use App\Models\CrmActivity;
use App\Models\Project;
use App\Models\Task;
use App\Services\AttachmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function __construct(private AttachmentService $attachments) {}

    public function index(Request $request): View
    {
        $contacts = Contact::query()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('name', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('company', 'like', $term)
                        ->orWhere('phone', 'like', $term);
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.contacts.index', [
            'contacts' => $contacts,
            'statuses' => ContactStatus::options(),
        ]);
    }

    public function create(): View
    {
        return view('admin.contacts.form', [
            'contact' => new Contact([
                'status' => ContactStatus::Lead,
                'source' => ContactSource::Manual,
            ]),
            'statuses' => ContactStatus::options(),
            'sources' => ContactSource::options(),
        ]);
    }

    public function store(ContactRequest $request): RedirectResponse
    {
        $contact = Contact::query()->create($request->validated());

        return redirect()
            ->route('admin.contacts.show', $contact)
            ->with('success', 'Contato criado.');
    }

    public function show(Contact $contact): View
    {
        $contact->load([
            'opportunities.project',
            'projects',
            'messages' => fn ($q) => $q->latest()->limit(20),
            'tasks.project',
            'activities' => fn ($q) => $q->with(['user', 'opportunity', 'task'])->latest('happened_at')->limit(40),
            'attachments',
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
        return view('admin.contacts.form', [
            'contact' => $contact,
            'statuses' => ContactStatus::options(),
            'sources' => ContactSource::options(),
        ]);
    }

    public function update(ContactRequest $request, Contact $contact): RedirectResponse
    {
        $contact->update($request->validated());

        return redirect()
            ->route('admin.contacts.show', $contact)
            ->with('success', 'Contato atualizado.');
    }

    public function destroy(Contact $contact): RedirectResponse
    {
        $this->attachments->deleteAllFor($contact);
        $contact->delete();

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

    public function attachProject(Request $request, Contact $contact): RedirectResponse
    {
        $validated = $request->validate([
            'project_id' => ['required', 'exists:projects,id'],
        ]);

        $contact->projects()->syncWithoutDetaching([$validated['project_id']]);

        return back()->with('success', 'Projeto vinculado ao contato.');
    }

    public function detachProject(Contact $contact, Project $project): RedirectResponse
    {
        $contact->projects()->detach($project->id);

        return back()->with('success', 'Projeto desvinculado.');
    }
}
