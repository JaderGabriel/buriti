<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OpportunityStage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OpportunityRequest;
use App\Http\Requests\Admin\UpdateOpportunityStageRequest;
use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\Project;
use App\Services\AttachmentService;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OpportunityController extends Controller
{
    /** @var list<string> */
    private const VIEWS = ['board', 'list'];

    public function __construct(
        private AttachmentService $attachments,
        private AuditLogger $audit,
    ) {}

    public function index(Request $request): View
    {
        $view = in_array($request->query('view'), self::VIEWS, true)
            ? (string) $request->query('view')
            : 'board';

        $stageFilter = in_array($request->query('stage'), OpportunityStage::boardOrder(), true)
            ? (string) $request->query('stage')
            : null;

        $counts = Opportunity::query()
            ->selectRaw('stage, count(*) as total')
            ->groupBy('stage')
            ->pluck('total', 'stage');

        $query = Opportunity::query()
            ->with(['contact', 'project'])
            ->when($stageFilter, fn ($q) => $q->where('stage', $stageFilter))
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
            'pipeline_value' => (float) Opportunity::query()->open()->sum('value'),
            'won_value' => (float) Opportunity::query()->where('stage', OpportunityStage::Won)->sum('value'),
        ];

        return view('admin.opportunities.index', [
            'view' => $view,
            'stageFilter' => $stageFilter,
            'stages' => OpportunityStage::options(),
            'stageMeta' => OpportunityStage::pipelineMeta(),
            'counts' => $counts,
            'columns' => $columns,
            'opportunities' => $opportunities,
            'stats' => $stats,
            'stageMoveUrlTemplate' => url('/admin/oportunidades/__ID__/stage'),
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
            'stageMeta' => OpportunityStage::pipelineMeta(),
        ]);
    }

    public function store(OpportunityRequest $request): RedirectResponse
    {
        $opportunity = Opportunity::query()->create($request->validated());

        $this->audit->record('opportunity.created', $opportunity, [
            'summary' => $opportunity->title,
            'contact_id' => $opportunity->contact_id,
        ]);

        return redirect()
            ->route('admin.contacts.show', $opportunity->contact_id)
            ->with('success', 'Oportunidade criada.');
    }

    public function edit(Opportunity $opportunity): View
    {
        $opportunity->load(['attachments', 'trashedAttachments.deleter']);

        return view('admin.opportunities.form', [
            'opportunity' => $opportunity,
            'contacts' => Contact::query()->orderBy('name')->get(),
            'projects' => Project::query()->orderBy('name')->get(),
            'stages' => OpportunityStage::options(),
            'stageMeta' => OpportunityStage::pipelineMeta(),
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
        $opportunity->update(['stage' => $stage]);

        $this->audit->record('opportunity.stage_moved', $opportunity, [
            'summary' => $opportunity->title,
            'from' => $from,
            'to' => $stage->value,
        ]);

        return response()->json([
            'ok' => true,
            'id' => $opportunity->id,
            'stage' => $opportunity->stage->value,
            'label' => $opportunity->stage->label(),
            'tone' => $opportunity->stage->tone(),
            'icon' => $opportunity->stage->icon(),
        ]);
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
