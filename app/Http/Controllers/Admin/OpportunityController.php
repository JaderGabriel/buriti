<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OpportunityStage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OpportunityRequest;
use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OpportunityController extends Controller
{
    public function index(Request $request): View
    {
        $opportunities = Opportunity::query()
            ->with(['contact', 'project'])
            ->when($request->filled('stage'), fn ($q) => $q->where('stage', $request->string('stage')))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.opportunities.index', [
            'opportunities' => $opportunities,
            'stages' => OpportunityStage::options(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.opportunities.form', [
            'opportunity' => new Opportunity([
                'contact_id' => $request->integer('contact_id') ?: null,
                'stage' => OpportunityStage::Lead,
            ]),
            'contacts' => Contact::query()->orderBy('name')->get(),
            'projects' => Project::query()->orderBy('name')->get(),
            'stages' => OpportunityStage::options(),
        ]);
    }

    public function store(OpportunityRequest $request): RedirectResponse
    {
        $opportunity = Opportunity::query()->create($request->validated());

        return redirect()
            ->route('admin.contacts.show', $opportunity->contact_id)
            ->with('success', 'Oportunidade criada.');
    }

    public function edit(Opportunity $opportunity): View
    {
        return view('admin.opportunities.form', [
            'opportunity' => $opportunity,
            'contacts' => Contact::query()->orderBy('name')->get(),
            'projects' => Project::query()->orderBy('name')->get(),
            'stages' => OpportunityStage::options(),
        ]);
    }

    public function update(OpportunityRequest $request, Opportunity $opportunity): RedirectResponse
    {
        $opportunity->update($request->validated());

        return redirect()
            ->route('admin.opportunities.index')
            ->with('success', 'Oportunidade atualizada.');
    }

    public function destroy(Opportunity $opportunity): RedirectResponse
    {
        $contactId = $opportunity->contact_id;
        $opportunity->delete();

        return redirect()
            ->route('admin.contacts.show', $contactId)
            ->with('success', 'Oportunidade removida.');
    }
}
