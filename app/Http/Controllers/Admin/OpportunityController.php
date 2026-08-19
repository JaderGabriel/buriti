<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OpportunityStage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OpportunityRequest;
use App\Http\Requests\Admin\UpdateOpportunityStageRequest;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\Project;
use App\Services\AttachmentService;
use App\Services\AuditLogger;
use App\Services\FunnelMetricsService;
use App\Services\GoogleDriveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OpportunityController extends Controller
{
    /** @var list<string> */
    private const VIEWS = ['board', 'list'];

    public function __construct(
        private AttachmentService $attachments,
        private AuditLogger $audit,
        private FunnelMetricsService $funnel,
        private GoogleDriveService $drive,
    ) {}

    public function index(Request $request): View
    {
        $view = in_array($request->query('view'), self::VIEWS, true)
            ? (string) $request->query('view')
            : 'board';

        $stageFilter = in_array($request->query('stage'), OpportunityStage::boardOrder(), true)
            ? (string) $request->query('stage')
            : null;

        $companyId = $request->integer('company_id') ?: null;

        $counts = Opportunity::query()
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->selectRaw('stage, count(*) as total')
            ->groupBy('stage')
            ->pluck('total', 'stage');

        $query = Opportunity::query()
            ->with(['contact.clientCompany', 'clientCompany', 'project'])
            ->when($stageFilter, fn ($q) => $q->where('stage', $stageFilter))
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('title', 'like', $term)
                        ->orWhereHas('contact', fn ($c) => $c->where('name', 'like', $term));
                });
            })
            ->orderBy('sort_order')
            ->latest();

        $columns = [];
        $opportunities = null;

        if ($view === 'board') {
            $grouped = (clone $query)->get()->groupBy(fn (Opportunity $item) => $item->stage->value);
            foreach (OpportunityStage::boardOrder() as $stage) {
                $columns[$stage] = $grouped->get($stage, collect());
            }
        } else {
            $opportunities = (clone $query)->paginate(20)->withQueryString();
        }

        $stats = [
            'total' => (int) $counts->sum(),
            'open' => (int) collect(OpportunityStage::cases())
                ->filter(fn (OpportunityStage $stage) => $stage->isOpen())
                ->sum(fn (OpportunityStage $stage) => (int) ($counts[$stage->value] ?? 0)),
            'won' => (int) ($counts[OpportunityStage::Won->value] ?? 0),
            'lost' => (int) ($counts[OpportunityStage::Lost->value] ?? 0),
            'pipeline_value' => (float) Opportunity::query()
                ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
                ->open()
                ->sum('value'),
            'won_value' => (float) Opportunity::query()
                ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
                ->where('stage', OpportunityStage::Won)
                ->sum('value'),
        ];

        $metrics = $this->funnel->snapshot($companyId);

        return view('admin.opportunities.index', [
            'view' => $view,
            'stageFilter' => $stageFilter,
            'companyId' => $companyId,
            'companies' => Company::query()->orderBy('name')->get(),
            'q' => (string) $request->query('q', ''),
            'stages' => OpportunityStage::options(),
            'stageMeta' => OpportunityStage::pipelineMeta(),
            'counts' => $counts,
            'columns' => $columns,
            'opportunities' => $opportunities,
            'stats' => $stats,
            'funnel' => $metrics,
            'stageMoveUrlTemplate' => url('/admin/oportunidades/__ID__/stage'),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $companyId = $request->integer('company_id') ?: null;
        $rows = $this->funnel->exportRows($companyId);

        $filename = 'oportunidades-'.now()->format('Y-m').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'titulo', 'estagio', 'valor', 'contato', 'empresa', 'projeto', 'previsao', 'ultima_atividade']);
            foreach ($rows as $opportunity) {
                $last = $opportunity->activities->first();
                fputcsv($out, [
                    $opportunity->id,
                    $opportunity->title,
                    $opportunity->stage?->value,
                    $opportunity->value,
                    $opportunity->contact?->name,
                    $opportunity->companyLabel(),
                    $opportunity->project?->name,
                    optional($opportunity->expected_close_at)?->format('Y-m-d'),
                    optional($last?->happened_at)?->format('Y-m-d H:i'),
                ]);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function create(Request $request): View
    {
        $contactId = $request->integer('contact_id') ?: null;
        $contact = $contactId ? Contact::query()->find($contactId) : null;

        return view('admin.opportunities.form', [
            'opportunity' => new Opportunity([
                'contact_id' => $contact?->id,
                'company_id' => $request->integer('company_id') ?: $contact?->company_id,
                'stage' => OpportunityStage::Lead,
            ]),
            'contacts' => Contact::query()->with('clientCompany')->orderBy('name')->get(),
            'companies' => Company::query()->orderBy('name')->get(),
            'projects' => Project::query()->orderBy('name')->get(),
            'stages' => OpportunityStage::options(),
            'stageMeta' => OpportunityStage::pipelineMeta(),
            'stageEvents' => collect(),
            'driveTemplates' => [],
        ]);
    }

    public function store(OpportunityRequest $request): RedirectResponse
    {
        $opportunity = Opportunity::query()->create($request->validated());

        $this->audit->record('opportunity.created', $opportunity, [
            'summary' => $opportunity->title,
            'contact_id' => $opportunity->contact_id,
            'company_id' => $opportunity->company_id,
        ]);

        return redirect()
            ->route('admin.contacts.show', $opportunity->contact_id)
            ->with('success', 'Oportunidade criada.');
    }

    public function edit(Opportunity $opportunity): View
    {
        $opportunity->load(['attachments', 'trashedAttachments.deleter', 'stageEvents.user', 'clientCompany', 'contact']);

        return view('admin.opportunities.form', [
            'opportunity' => $opportunity,
            'contacts' => Contact::query()->with('clientCompany')->orderBy('name')->get(),
            'companies' => Company::query()->orderBy('name')->get(),
            'projects' => Project::query()->orderBy('name')->get(),
            'stages' => OpportunityStage::options(),
            'stageMeta' => OpportunityStage::pipelineMeta(),
            'stageEvents' => $opportunity->stageEvents,
            'driveTemplates' => $this->drive->listFolder($this->drive->templatesFolderId()),
        ]);
    }

    public function update(OpportunityRequest $request, Opportunity $opportunity): RedirectResponse
    {
        $opportunity->update($request->validated());

        $this->audit->record('opportunity.updated', $opportunity, [
            'summary' => $opportunity->title,
        ]);

        return redirect()
            ->route('admin.opportunities.index', ['view' => 'board'])
            ->with('success', 'Oportunidade atualizada.');
    }

    public function updateStage(UpdateOpportunityStageRequest $request, Opportunity $opportunity): JsonResponse
    {
        $stage = OpportunityStage::from($request->validated('stage'));
        $from = $opportunity->stage?->value;
        $orderedIds = array_values(array_unique(array_map('intval', $request->validated('ordered_ids') ?? [])));

        $opportunity->update(['stage' => $stage]);

        if ($orderedIds !== []) {
            if (! in_array((int) $opportunity->id, $orderedIds, true)) {
                $orderedIds[] = (int) $opportunity->id;
            }

            foreach ($orderedIds as $index => $id) {
                Opportunity::query()
                    ->whereKey($id)
                    ->where('stage', $stage)
                    ->update(['sort_order' => ($index + 1) * 10]);
            }
        }

        if ($from !== $stage->value) {
            $this->audit->record('opportunity.stage_moved', $opportunity, [
                'summary' => $opportunity->title,
                'from' => $from,
                'to' => $stage->value,
            ]);
        }

        return response()->json([
            'ok' => true,
            'id' => $opportunity->id,
            'stage' => $opportunity->stage->value,
            'label' => $opportunity->stage->label(),
            'tone' => $opportunity->stage->tone(),
            'icon' => $opportunity->stage->icon(),
            'ordered_ids' => $orderedIds,
        ]);
    }

    public function copyDriveTemplate(Request $request, Opportunity $opportunity): RedirectResponse
    {
        $fileId = trim((string) $request->input('drive_file_id', ''));
        if ($fileId === '') {
            return back()->with('error', 'Escolha um modelo no Drive.');
        }

        $name = 'Contrato — '.$opportunity->title.' — '.now()->format('Y-m-d');
        $result = $this->drive->copyTemplateToContracts($fileId, $name);

        $this->audit->record('opportunity.drive_copy', $opportunity, [
            'summary' => $opportunity->title,
            'drive_file_id' => $result['id'] ?? $fileId,
        ]);

        return back()->with($result['ok'] ? 'success' : 'error', $result['message']
            .(isset($result['webViewLink']) ? ' '.$result['webViewLink'] : ''));
    }

    public function destroy(Opportunity $opportunity): RedirectResponse
    {
        $contactId = $opportunity->contact_id;
        $summary = $opportunity->title;
        $this->attachments->deleteAllFor($opportunity, auth()->id());
        $opportunity->delete();

        $this->audit->record('opportunity.deleted', null, [
            'summary' => $summary,
            'opportunity_id' => $opportunity->id,
            'contact_id' => $contactId,
        ]);

        return redirect()
            ->route('admin.contacts.show', $contactId)
            ->with('success', 'Oportunidade removida.');
    }
}
