<?php

namespace App\Http\Controllers\Jirau;

use App\Enums\JirauCardPriority;
use App\Enums\JirauCardType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Jirau\DeleteBoardRequest;
use App\Http\Requests\Jirau\ReorderBoardsRequest;
use App\Http\Requests\Jirau\StoreBoardRequest;
use App\Http\Requests\Jirau\UpdateBoardRequest;
use App\Models\Company;
use App\Models\Jirau;
use App\Models\JirauCard;
use App\Models\Project;
use App\Models\User;
use App\Services\Jirau\JirauAccess;
use App\Services\Jirau\JirauBoardService;
use App\Services\Jirau\JirauEvidenceService;
use App\Services\Jirau\JirauTimeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BoardController extends Controller
{
    public function __construct(
        private JirauAccess $access,
        private JirauBoardService $boards,
        private JirauEvidenceService $evidence,
        private JirauTimeService $time,
    ) {}

    public function home(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        $query = Jirau::query()
            ->with(['project', 'company', 'columns'])
            ->withCount([
                'cards as cards_count',
                'cards as open_cards_count' => fn ($q) => $q->whereNull('archived_at'),
                'cards as inbox_cards_count' => fn ($q) => $q
                    ->whereNull('archived_at')
                    ->whereHas('column', fn ($column) => $column->where('is_inbox', true)),
            ]);

        if (! $user->is_admin && $user->jirau_role === null) {
            $query->whereHas('members', fn ($q) => $q->whereKey($user->id));
        }

        $jiraus = $query
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        if ($jiraus->count() === 1 && ! $user->is_admin) {
            return redirect()->route('jirau.show', $jiraus->first());
        }

        return view('jirau.home', [
            'jiraus' => $jiraus,
            'activeBoards' => $jiraus->where('is_active', true)->values(),
            'inactiveBoards' => $jiraus->where('is_active', false)->values(),
            'jirau' => null,
            'isAdmin' => (bool) $user->is_admin,
        ]);
    }

    public function destroy(DeleteBoardRequest $request, Jirau $jirau): RedirectResponse
    {
        $this->access->assertManage($request->user());
        $this->access->assertView($request->user(), $jirau);

        $this->access->assertWritable($request->user(), $jirau);

        $name = $jirau->name;
        $this->boards->deleteEmpty($jirau);

        return redirect()
            ->route('jirau.home')
            ->with('success', 'Quadro «'.$name.'» removido.');
    }

    public function store(StoreBoardRequest $request): RedirectResponse
    {
        $project = Project::query()->findOrFail($request->projectId());

        $company = $request->companyId()
            ? Company::query()->find($request->companyId())
            : $project->clientCompany;

        $jirau = $this->boards->createForProject(
            $project,
            $request->user(),
            $request->boardName(),
            $request->template(),
            $company,
            $request->contractName(),
        );

        return redirect()
            ->route('jirau.show', $jirau)
            ->with('success', 'Jirau «'.$jirau->name.'» criado com o modelo '.$jirau->template->label().'.');
    }

    public function reorder(ReorderBoardsRequest $request): JsonResponse
    {
        $this->access->assertManage($request->user());
        $this->boards->reorder($request->validated('ordered_ids'));

        return response()->json(['ok' => true]);
    }

    public function update(UpdateBoardRequest $request, Jirau $jirau): RedirectResponse
    {
        $this->access->assertView($request->user(), $jirau);
        $this->access->assertWritable($request->user(), $jirau);

        $this->boards->rename($jirau, $request->boardName());

        return redirect()
            ->back(fallback: route('jirau.show', $jirau))
            ->with('success', 'Quadro renomeado para «'.$jirau->name.'».');
    }

    public function show(Request $request, Jirau $jirau): View
    {
        $this->access->assertView($request->user(), $jirau);

        $jirau->load([
            'company',
            'project.steps.jirauCard',
            'project.jiraus.company',
            'labels',
            'members',
            'columns' => fn ($q) => $q->with(['watchers:id,name,jirau_role,is_admin'])->withCount(['cards as cards_count']),
        ]);

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'type' => $request->query('type'),
            'priority' => $request->query('priority'),
            'label' => $request->query('label'),
        ];

        $cardQuery = $jirau->cards()
            ->active()
            ->with([
                'labels',
                'assignee',
                'responsibles:id,name,jirau_role,is_admin',
                'actors:id,name,jirau_role,is_admin',
                'creator',
                'checklistItems',
                'projectStep',
                'runningTimeEntries.user:id,name,jirau_role,is_admin',
                'latestClosedTimeEntry',
            ])
            ->withSum('timeEntries as closed_seconds', 'seconds')
            ->withCount([
                'checklistItems',
                'checklistItems as done_checklist_items_count' => fn ($q) => $q->where('is_done', true),
                'comments',
                'attachments',
                'links',
            ]);

        if ($filters['q'] !== '') {
            $cardQuery->where(function ($q) use ($filters) {
                $q->where('title', 'like', '%'.$filters['q'].'%')
                    ->orWhere('description', 'like', '%'.$filters['q'].'%');
            });
        }
        if (in_array($filters['type'], array_keys(JirauCardType::options()), true)) {
            $cardQuery->where('type', $filters['type']);
        }
        if (in_array($filters['priority'], array_keys(JirauCardPriority::options()), true)) {
            $cardQuery->where('priority', $filters['priority']);
        }
        if (is_numeric($filters['label'])) {
            $cardQuery->whereHas('labels', fn ($q) => $q->whereKey((int) $filters['label']));
        }

        $cards = $cardQuery->get()->groupBy('column_id');

        foreach ($jirau->columns as $column) {
            $column->setRelation('cards', $cards->get($column->id, collect()));
        }

        $openCard = null;
        $cardId = (int) $request->query('cartao', 0);
        if ($cardId > 0) {
            $openCard = JirauCard::query()
                ->where('jirau_id', $jirau->id)
                ->with([
                    'labels',
                    'assignee',
                    'responsibles:id,name,jirau_role,is_admin',
                    'actors:id,name,jirau_role,is_admin',
                    'creator',
                    'column',
                    'checklistItems',
                    'comments.author',
                    'projectStep',
                    'attachments',
                    'links',
                    'runningTimeEntries.user:id,name,jirau_role,is_admin',
                    'latestClosedTimeEntry',
                    'timeEntries.user:id,name,jirau_role,is_admin',
                    'timeEntries.column:id,name,color,sort_order',
                ])
                ->withSum('timeEntries as closed_seconds', 'seconds')
                ->find($cardId);
        }

        $isAdmin = $this->access->canManage($request->user());
        $actor = $request->user();

        return view('jirau.board', [
            'jirau' => $jirau,
            'openCard' => $openCard,
            'filters' => $filters,
            'isAdmin' => $isAdmin,
            'canOperate' => $isAdmin && $jirau->is_active,
            'canDemand' => ! $isAdmin && $jirau->is_active,
            'canTrackTime' => $this->access->canTrackTime($actor, $jirau),
            'canSeeTimeHistory' => $this->access->canSeeTimeHistory($actor),
            'timeSnapshot' => $openCard && $this->access->canSeeTimeHistory($actor)
                ? $this->time->snapshot($openCard)
                : null,
            'types' => JirauCardType::options(),
            'adminTypes' => JirauCardType::adminOptions(),
            'priorities' => JirauCardPriority::options(),
            'estimates' => JirauBoardService::estimateOptions(),
            'assignees' => User::assignableOnJirau()->get(['id', 'name', 'jirau_role', 'is_admin']),
            'moveUrlTemplate' => url('/jirau/'.$jirau->id.'/cartoes/__ID__/mover'),
            'unlinkedSteps' => $jirau->project
                ? $jirau->project->steps->filter(fn ($step) => $step->jirauCard === null)->values()
                : collect(),
            'canManageEvidence' => $openCard && $jirau->is_active
                ? $this->evidence->canManage($request->user(), $openCard)
                : false,
        ]);
    }
}
