<?php

namespace App\Http\Controllers\Jirau;

use App\Enums\JirauCardPriority;
use App\Enums\JirauCardType;
use App\Enums\JirauTemplate;
use App\Http\Controllers\Controller;
use App\Models\Jirau;
use App\Models\JirauCard;
use App\Models\User;
use App\Services\Jirau\JirauAccess;
use App\Services\Jirau\JirauBoardService;
use App\Services\Jirau\JirauEvidenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BoardController extends Controller
{
    public function __construct(
        private JirauAccess $access,
        private JirauBoardService $boards,
        private JirauEvidenceService $evidence,
    ) {}

    public function home(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        $query = Jirau::query()
            ->with(['project', 'columns'])
            ->withCount([
                'cards as open_cards_count' => fn ($q) => $q->whereNull('archived_at'),
            ]);

        if (! $user->is_admin && $user->jirau_role === null) {
            $query->whereHas('members', fn ($q) => $q->whereKey($user->id));
        }

        $jiraus = $query->orderBy('name')->get();

        if ($jiraus->count() === 1 && ! $user->is_admin) {
            return redirect()->route('jirau.show', $jiraus->first());
        }

        return view('jirau.home', [
            'jiraus' => $jiraus,
            'jirau' => null,
            'isAdmin' => (bool) $user->is_admin,
        ]);
    }

    public function show(Request $request, Jirau $jirau): View
    {
        $this->access->assertView($request->user(), $jirau);

        $jirau->load([
            'project.steps.jirauCard',
            'project.jiraus',
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
            ->with(['labels', 'assignee', 'creator', 'checklistItems', 'projectStep'])
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
                    'creator',
                    'column',
                    'checklistItems',
                    'comments.author',
                    'projectStep',
                    'attachments',
                    'links',
                ])
                ->find($cardId);
        }

        return view('jirau.board', [
            'jirau' => $jirau,
            'openCard' => $openCard,
            'filters' => $filters,
            'isAdmin' => $this->access->canManage($request->user()),
            'types' => JirauCardType::options(),
            'adminTypes' => JirauCardType::adminOptions(),
            'priorities' => JirauCardPriority::options(),
            'estimates' => JirauBoardService::estimateOptions(),
            'assignees' => User::assignableOnJirau()->get(['id', 'name', 'jirau_role', 'is_admin']),
            'moveUrlTemplate' => url('/jirau/'.$jirau->id.'/cartoes/__ID__/mover'),
            'templates' => JirauTemplate::cases(),
            'unlinkedSteps' => $jirau->project
                ? $jirau->project->steps->filter(fn ($step) => $step->jirauCard === null)->values()
                : collect(),
            'canManageEvidence' => $openCard
                ? $this->evidence->canManage($request->user(), $openCard)
                : false,
        ]);
    }
}
