<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CompanyStatus;
use App\Enums\ContactSource;
use App\Enums\ContactStatus;
use App\Enums\OpportunityStage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AttachProjectToCompanyRequest;
use App\Http\Requests\Admin\CompanyRequest;
use App\Http\Requests\Admin\StoreCompanyContactRequest;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Project;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CompanyController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function index(Request $request): View
    {
        $companies = Company::query()
            ->withCount(['contacts', 'projects', 'opportunities'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('name', 'like', $term)
                        ->orWhere('trade_name', 'like', $term)
                        ->orWhere('document', 'like', $term)
                        ->orWhere('email', 'like', $term);
                });
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.companies.index', [
            'companies' => $companies,
            'statuses' => CompanyStatus::options(),
            'statusCounts' => Company::query()
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
        ]);
    }

    public function create(): View
    {
        return view('admin.companies.form', [
            'company' => new Company(['status' => CompanyStatus::Active]),
            'statuses' => CompanyStatus::options(),
        ]);
    }

    public function store(CompanyRequest $request): RedirectResponse
    {
        $company = Company::query()->create($request->companyData());

        $this->audit->record('company.created', $company, [
            'summary' => $company->name,
        ]);

        return redirect()
            ->route('admin.companies.show', $company)
            ->with('success', 'Empresa criada.');
    }

    public function show(Company $company): View
    {
        $company->load([
            'contacts' => fn ($q) => $q->withCount(['opportunities', 'projects'])->latest(),
            'projects' => fn ($q) => $q->with('contacts')->ordered(),
            'opportunities' => fn ($q) => $q->with(['contact', 'project'])->latest(),
        ]);

        $company->loadCount(['contacts', 'projects', 'opportunities']);

        return view('admin.companies.show', [
            'company' => $company,
            'statuses' => CompanyStatus::options(),
            'contactStatuses' => ContactStatus::options(),
            'stages' => OpportunityStage::options(),
            'unlinkedProjects' => Project::query()
                ->whereNull('company_id')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function edit(Company $company): View
    {
        return view('admin.companies.form', [
            'company' => $company,
            'statuses' => CompanyStatus::options(),
        ]);
    }

    public function update(CompanyRequest $request, Company $company): RedirectResponse
    {
        $company->update($request->companyData());

        Contact::query()
            ->where('company_id', $company->id)
            ->update(['company' => $company->name]);

        $this->audit->record('company.updated', $company, [
            'summary' => $company->name,
        ]);

        return redirect()
            ->route('admin.companies.show', $company)
            ->with('success', 'Empresa atualizada.');
    }

    public function destroy(Company $company): RedirectResponse
    {
        $summary = $company->name;
        $company->delete();

        $this->audit->record('company.deleted', null, [
            'summary' => $summary,
            'company_id' => $company->id,
        ]);

        return redirect()
            ->route('admin.companies.index')
            ->with('success', 'Empresa removida. Contatos e projetos ficaram sem vínculo.');
    }

    public function attachProject(AttachProjectToCompanyRequest $request, Company $company): RedirectResponse
    {
        Project::query()
            ->whereKey($request->validated('project_id'))
            ->update(['company_id' => $company->id]);

        return back()->with('success', 'Projeto vinculado à empresa.');
    }

    public function detachProject(Company $company, Project $project): RedirectResponse
    {
        abort_unless($project->company_id === $company->id, 404);

        $project->update(['company_id' => null]);

        return back()->with('success', 'Projeto desvinculado da empresa.');
    }

    public function storeContact(StoreCompanyContactRequest $request, Company $company): RedirectResponse
    {
        $contact = Contact::query()->create([
            ...$request->contactPayload(),
            'company_id' => $company->id,
            'company' => $company->name,
            'source' => ContactSource::Manual,
        ]);

        $this->audit->record('contact.created', $contact, [
            'summary' => $contact->name,
            'company_id' => $company->id,
        ]);

        return redirect()
            ->route('admin.contacts.show', $contact)
            ->with('success', 'Contato criado na empresa.');
    }
}
