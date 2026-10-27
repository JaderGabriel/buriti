<?php

namespace App\Services\Jirau;

use App\Enums\JirauCardPriority;
use App\Enums\JirauCardType;
use App\Enums\JirauTemplate;
use App\Models\Jirau;
use App\Models\JirauCard;
use App\Models\JirauColumn;
use App\Models\ProjectStep;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class JirauMetricsService
{
    public function __construct(private JirauAccess $access) {}

    /**
     * @return array{
     *     boards: int,
     *     open: int,
     *     inbox: int,
     *     wip: int,
     *     done: int,
     *     archived: int,
     *     overdue: int,
     *     unassigned: int,
     *     blocked: int,
     *     demands: int,
     *     flow_percent: int|null,
     *     estimate_open: int,
     *     estimate_done: int,
     *     created_7d: int,
     *     done_7d: int,
     *     lead_time_days: float|null,
     *     inbox_oldest_days: int|null,
     *     by_type: array<string, int>,
     *     by_priority: array<string, int>,
     *     by_template: array<string, int>,
     *     columns: list<array{id: int, name: string, color: string, count: int, wip_limit: ?int, over: bool, is_inbox: bool, is_done: bool}>,
     *     wip_breaches: list<array{jirau_id: int, jirau_name: string, column: string, count: int, limit: int}>,
     *     aging: list<array{id: int, jirau_id: int, title: string, column: string, days: int}>,
     *     overdue_cards: list<array{id: int, jirau_id: int, title: string, due_at: string, days: int}>,
     *     assignees: list<array{id: ?int, name: string, open: int, done: int}>,
     *     board_rows: list<array{id: int, name: string, project: ?string, template: ?string, open: int, inbox: int, done: int, overdue: int, flow_percent: int|null}>,
     *     project_rows: list<array{id: ?int, name: string, boards: int, open: int, inbox: int, done: int, overdue: int, flow_percent: int|null}>,
     *     steps: array{total: int, done: int, linked: int, unlinked: int, percent: int|null}
     * }
     */
    public function snapshot(User $actor, array $filters = []): array
    {
        $filters = $this->normalize($filters);
        $boards = $this->boards($actor, $filters);
        $ids = $boards->modelKeys();

        if ($ids === []) {
            return $this->empty();
        }

        $cards = JirauCard::query()
            ->whereIn('jirau_id', $ids)
            ->with([
                'column:id,jirau_id,name,slug,is_inbox,is_done,wip_limit,color,sort_order',
                'assignee:id,name',
                'jirau:id,name',
                'labels:id,name',
            ])
            ->get();

        $cards = $this->filterCards($cards, $filters);
        $active = $cards->whereNull('archived_at');
        $inbox = $active->filter(fn (JirauCard $card) => (bool) $card->column?->is_inbox);
        $done = $active->filter(fn (JirauCard $card) => (bool) $card->column?->is_done);
        $wip = $active->filter(fn (JirauCard $card) => $card->column && ! $card->column->is_inbox && ! $card->column->is_done);
        $overdue = $active
            ->filter(fn (JirauCard $card) => $card->due_at && $card->due_at->isPast() && ! $card->column?->is_done);
        $since = now()->subDays(7);

        $openCount = $active->count();
        $doneCount = $done->count();

        return [
            'boards' => $boards->count(),
            'open' => $openCount,
            'inbox' => $inbox->count(),
            'wip' => $wip->count(),
            'done' => $doneCount,
            'archived' => $cards->whereNotNull('archived_at')->count(),
            'overdue' => $overdue->count(),
            'unassigned' => $wip->merge($inbox)->whereNull('assigned_to')->count(),
            'blocked' => $active->filter(fn (JirauCard $card) => $card->labels->contains(
                fn ($label) => mb_strtolower((string) $label->name) === 'bloqueado'
            ))->count(),
            'demands' => $active->filter(fn (JirauCard $card) => $card->type === JirauCardType::Demand)->count(),
            'flow_percent' => $openCount > 0 ? (int) round(($doneCount / $openCount) * 100) : null,
            'estimate_open' => (int) $active->reject(fn (JirauCard $card) => (bool) $card->column?->is_done)->sum('estimate'),
            'estimate_done' => (int) $done->sum('estimate'),
            'created_7d' => $cards->filter(fn (JirauCard $card) => $card->created_at?->gte($since))->count(),
            'done_7d' => $done->filter(fn (JirauCard $card) => $card->updated_at?->gte($since))->count(),
            'lead_time_days' => $this->averageLeadTime($done),
            'inbox_oldest_days' => $this->oldestDays($inbox),
            'by_type' => $this->countByType($active),
            'by_priority' => $this->countByPriority($active),
            'by_template' => $this->countByTemplate($boards),
            'columns' => $this->columnBars($boards, $active, $filters['quadro'] !== null),
            'wip_breaches' => $this->wipBreaches($boards, $active),
            'aging' => $this->aging($wip->merge($inbox)),
            'overdue_cards' => $this->overdueRows($overdue),
            'assignees' => $this->assigneeRows($active),
            'board_rows' => $this->boardRows($boards, $active, $overdue),
            'project_rows' => $this->projectRows($boards, $active, $overdue),
            'steps' => $this->stepStats($boards),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{
     *     projeto: ?int,
     *     quadro: ?int,
     *     usuario: ?string,
     *     modelo: ?string,
     *     estado: string,
     *     tipo: ?string,
     *     prioridade: ?string,
     *     risco: ?string
     * }
     */
    public function normalize(array $input): array
    {
        $templates = array_map(fn (JirauTemplate $case) => $case->value, JirauTemplate::cases());
        $types = array_map(fn (JirauCardType $case) => $case->value, JirauCardType::cases());
        $priorities = array_map(fn (JirauCardPriority $case) => $case->value, JirauCardPriority::cases());
        $estado = (string) ($input['estado'] ?? 'ativos');
        $usuario = $input['usuario'] ?? null;
        $usuario = is_string($usuario) || is_int($usuario) ? (string) $usuario : null;

        return [
            'projeto' => is_numeric($input['projeto'] ?? null) ? (int) $input['projeto'] : null,
            'quadro' => is_numeric($input['quadro'] ?? null) ? (int) $input['quadro'] : null,
            'usuario' => in_array($usuario, ['none', '0'], true) || (is_string($usuario) && ctype_digit($usuario))
                ? ($usuario === '0' ? 'none' : $usuario)
                : null,
            'modelo' => in_array($input['modelo'] ?? null, $templates, true) ? (string) $input['modelo'] : null,
            'estado' => in_array($estado, ['ativos', 'inativos', 'todos'], true) ? $estado : 'ativos',
            'tipo' => in_array($input['tipo'] ?? null, $types, true) ? (string) $input['tipo'] : null,
            'prioridade' => in_array($input['prioridade'] ?? null, $priorities, true) ? (string) $input['prioridade'] : null,
            'risco' => in_array($input['risco'] ?? null, ['atraso', 'sem_responsavel', 'bloqueado'], true)
                ? (string) $input['risco']
                : null,
        ];
    }

    /** @return array<string, mixed> */
    public function filtersFrom(Request $request): array
    {
        return $this->normalize($request->query());
    }

    /** @return Collection<int, Jirau> */
    private function boards(User $actor, array $filters): Collection
    {
        $query = $this->access->visibleQuery($actor)
            ->withoutDemo()
            ->with(['project:id,name', 'columns'])
            ->orderBy('name')
            ->orderBy('id');

        if ($filters['projeto']) {
            $query->where('project_id', $filters['projeto']);
        }
        if ($filters['quadro']) {
            $query->whereKey($filters['quadro']);
        }
        if ($filters['modelo']) {
            $query->where('template', $filters['modelo']);
        }
        if ($filters['estado'] === 'ativos') {
            $query->where('is_active', true);
        } elseif ($filters['estado'] === 'inativos') {
            $query->where('is_active', false);
        }

        return $query->get();
    }

    /**
     * @param  Collection<int, JirauCard>  $cards
     * @param  array<string, mixed>  $filters
     * @return Collection<int, JirauCard>
     */
    private function filterCards(Collection $cards, array $filters): Collection
    {
        return $cards->filter(function (JirauCard $card) use ($filters) {
            if ($filters['usuario'] === 'none' && $card->assigned_to !== null) {
                return false;
            }
            if (is_string($filters['usuario']) && $filters['usuario'] !== 'none'
                && (int) $card->assigned_to !== (int) $filters['usuario']) {
                return false;
            }
            if ($filters['tipo'] && $card->type?->value !== $filters['tipo']) {
                return false;
            }
            if ($filters['prioridade'] && $card->priority?->value !== $filters['prioridade']) {
                return false;
            }
            if ($filters['risco'] === 'atraso') {
                return $card->archived_at === null
                    && $card->due_at
                    && $card->due_at->isPast()
                    && ! $card->column?->is_done;
            }
            if ($filters['risco'] === 'sem_responsavel') {
                return $card->archived_at === null && $card->assigned_to === null;
            }
            if ($filters['risco'] === 'bloqueado') {
                return $card->labels->contains(
                    fn ($label) => mb_strtolower((string) $label->name) === 'bloqueado'
                );
            }

            return true;
        })->values();
    }

    /**
     * @param  Collection<int, JirauCard>  $done
     */
    private function averageLeadTime(Collection $done): ?float
    {
        $days = $done
            ->filter(fn (JirauCard $card) => $card->created_at && $card->updated_at)
            ->map(fn (JirauCard $card) => max(0, (int) $card->created_at->diffInDays($card->updated_at)))
            ->values();

        if ($days->isEmpty()) {
            return null;
        }

        return round((float) $days->avg(), 1);
    }

    /**
     * @param  Collection<int, JirauCard>  $cards
     */
    private function oldestDays(Collection $cards): ?int
    {
        $oldest = $cards->sortBy('created_at')->first();
        if (! $oldest?->created_at) {
            return null;
        }

        return max(0, (int) $oldest->created_at->diffInDays(now()));
    }

    /**
     * @param  Collection<int, JirauCard>  $active
     * @return array<string, int>
     */
    private function countByType(Collection $active): array
    {
        $counts = [];
        foreach (JirauCardType::cases() as $type) {
            $counts[$type->value] = $active->filter(fn (JirauCard $card) => $card->type === $type)->count();
        }

        return $counts;
    }

    /**
     * @param  Collection<int, JirauCard>  $active
     * @return array<string, int>
     */
    private function countByPriority(Collection $active): array
    {
        $counts = [];
        foreach (JirauCardPriority::cases() as $priority) {
            $counts[$priority->value] = $active->filter(fn (JirauCard $card) => $card->priority === $priority)->count();
        }

        return $counts;
    }

    /**
     * @param  Collection<int, Jirau>  $boards
     * @return array<string, int>
     */
    private function countByTemplate(Collection $boards): array
    {
        $counts = [];
        foreach (JirauTemplate::cases() as $template) {
            $counts[$template->value] = $boards->filter(fn (Jirau $board) => $board->template === $template)->count();
        }

        return $counts;
    }

    /**
     * @param  Collection<int, Jirau>  $boards
     * @param  Collection<int, JirauCard>  $active
     * @return list<array{id: int, name: string, color: string, count: int, wip_limit: ?int, over: bool, is_inbox: bool, is_done: bool}>
     */
    private function columnBars(Collection $boards, Collection $active, bool $detail): array
    {
        if ($detail && $boards->count() === 1) {
            $board = $boards->first();

            return $board->columns
                ->sortBy('sort_order')
                ->values()
                ->map(function (JirauColumn $column) use ($active) {
                    $count = $active->where('column_id', $column->id)->count();
                    $limit = $column->wip_limit;

                    return [
                        'id' => $column->id,
                        'name' => $column->name,
                        'color' => $column->color ?: '#2f6bff',
                        'count' => $count,
                        'wip_limit' => $limit,
                        'over' => $limit !== null && $count > $limit,
                        'is_inbox' => (bool) $column->is_inbox,
                        'is_done' => (bool) $column->is_done,
                    ];
                })
                ->all();
        }

        $groups = [
            ['id' => 0, 'name' => 'Inbox', 'slug' => 'inbox', 'color' => '#c74634', 'is_inbox' => true, 'is_done' => false],
            ['id' => 0, 'name' => 'Em curso', 'slug' => 'wip', 'color' => '#2f6bff', 'is_inbox' => false, 'is_done' => false],
            ['id' => 0, 'name' => 'Feito', 'slug' => 'done', 'color' => '#0f9d58', 'is_inbox' => false, 'is_done' => true],
        ];

        return collect($groups)->map(function (array $group) use ($active) {
            $count = $active->filter(function (JirauCard $card) use ($group) {
                if ($group['is_inbox']) {
                    return (bool) $card->column?->is_inbox;
                }
                if ($group['is_done']) {
                    return (bool) $card->column?->is_done;
                }

                return $card->column && ! $card->column->is_inbox && ! $card->column->is_done;
            })->count();

            return [
                'id' => 0,
                'name' => $group['name'],
                'color' => $group['color'],
                'count' => $count,
                'wip_limit' => null,
                'over' => false,
                'is_inbox' => $group['is_inbox'],
                'is_done' => $group['is_done'],
            ];
        })->all();
    }

    /**
     * @param  Collection<int, Jirau>  $boards
     * @param  Collection<int, JirauCard>  $active
     * @return list<array{jirau_id: int, jirau_name: string, column: string, count: int, limit: int}>
     */
    private function wipBreaches(Collection $boards, Collection $active): array
    {
        $rows = [];
        foreach ($boards as $board) {
            foreach ($board->columns as $column) {
                if ($column->wip_limit === null) {
                    continue;
                }
                $count = $active->where('column_id', $column->id)->count();
                if ($count > $column->wip_limit) {
                    $rows[] = [
                        'jirau_id' => $board->id,
                        'jirau_name' => $board->name,
                        'column' => $column->name,
                        'count' => $count,
                        'limit' => (int) $column->wip_limit,
                    ];
                }
            }
        }

        return $rows;
    }

    /**
     * @param  Collection<int, JirauCard>  $cards
     * @return list<array{id: int, jirau_id: int, title: string, column: string, days: int}>
     */
    private function aging(Collection $cards): array
    {
        return $cards
            ->sortBy('created_at')
            ->take(8)
            ->values()
            ->map(fn (JirauCard $card) => [
                'id' => $card->id,
                'jirau_id' => (int) $card->jirau_id,
                'title' => $card->title,
                'column' => $card->column?->name ?? '—',
                'days' => $card->created_at ? max(0, (int) $card->created_at->diffInDays(now())) : 0,
            ])
            ->all();
    }

    /**
     * @param  Collection<int, JirauCard>  $overdue
     * @return list<array{id: int, jirau_id: int, title: string, due_at: string, days: int}>
     */
    private function overdueRows(Collection $overdue): array
    {
        return $overdue
            ->sortBy('due_at')
            ->take(8)
            ->values()
            ->map(fn (JirauCard $card) => [
                'id' => $card->id,
                'jirau_id' => (int) $card->jirau_id,
                'title' => $card->title,
                'due_at' => $card->due_at?->format('d/m') ?? '—',
                'days' => $card->due_at ? max(1, (int) $card->due_at->diffInDays(now())) : 0,
            ])
            ->all();
    }

    /**
     * @param  Collection<int, JirauCard>  $active
     * @return list<array{name: string, open: int, done: int}>
     */
    private function assigneeRows(Collection $active): array
    {
        return $active
            ->groupBy(fn (JirauCard $card) => $card->assigned_to ?: 0)
            ->map(function (Collection $group) {
                $first = $group->first();

                return [
                    'id' => $first?->assigned_to ? (int) $first->assigned_to : null,
                    'name' => $first?->assignee?->name ?? 'Sem responsável',
                    'open' => $group->reject(fn (JirauCard $card) => (bool) $card->column?->is_done)->count(),
                    'done' => $group->filter(fn (JirauCard $card) => (bool) $card->column?->is_done)->count(),
                ];
            })
            ->sortByDesc('open')
            ->values()
            ->take(8)
            ->all();
    }

    /**
     * @param  Collection<int, Jirau>  $boards
     * @param  Collection<int, JirauCard>  $active
     * @param  Collection<int, JirauCard>  $overdue
     * @return list<array{id: int, name: string, project: ?string, template: ?string, open: int, inbox: int, done: int, overdue: int, flow_percent: int|null}>
     */
    private function boardRows(Collection $boards, Collection $active, Collection $overdue): array
    {
        return $boards->map(function (Jirau $board) use ($active, $overdue) {
            $cards = $active->where('jirau_id', $board->id);
            $open = $cards->count();
            $done = $cards->filter(fn (JirauCard $card) => (bool) $card->column?->is_done)->count();

            return [
                'id' => $board->id,
                'name' => $board->name,
                'project' => $board->project?->name,
                'template' => $board->template?->label(),
                'open' => $open,
                'inbox' => $cards->filter(fn (JirauCard $card) => (bool) $card->column?->is_inbox)->count(),
                'done' => $done,
                'overdue' => $overdue->where('jirau_id', $board->id)->count(),
                'flow_percent' => $open > 0 ? (int) round(($done / $open) * 100) : null,
            ];
        })->all();
    }

    /**
     * @param  Collection<int, Jirau>  $boards
     * @param  Collection<int, JirauCard>  $active
     * @param  Collection<int, JirauCard>  $overdue
     * @return list<array{id: ?int, name: string, boards: int, open: int, inbox: int, done: int, overdue: int, flow_percent: int|null}>
     */
    private function projectRows(Collection $boards, Collection $active, Collection $overdue): array
    {
        return $boards
            ->groupBy(fn (Jirau $board) => $board->project_id ?: 0)
            ->map(function (Collection $group) use ($active, $overdue) {
                $ids = $group->modelKeys();
                $cards = $active->whereIn('jirau_id', $ids);
                $open = $cards->count();
                $done = $cards->filter(fn (JirauCard $card) => (bool) $card->column?->is_done)->count();
                $project = $group->first()?->project;

                return [
                    'id' => $project?->id,
                    'name' => $project?->name ?? 'Sem projeto',
                    'boards' => $group->count(),
                    'open' => $open,
                    'inbox' => $cards->filter(fn (JirauCard $card) => (bool) $card->column?->is_inbox)->count(),
                    'done' => $done,
                    'overdue' => $overdue->whereIn('jirau_id', $ids)->count(),
                    'flow_percent' => $open > 0 ? (int) round(($done / $open) * 100) : null,
                ];
            })
            ->sortBy('name')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Jirau>  $boards
     * @return array{total: int, done: int, linked: int, unlinked: int, percent: int|null}
     */
    private function stepStats(Collection $boards): array
    {
        $projectIds = $boards->pluck('project_id')->filter()->unique()->values();
        if ($projectIds->isEmpty()) {
            return ['total' => 0, 'done' => 0, 'linked' => 0, 'unlinked' => 0, 'percent' => null];
        }

        $steps = ProjectStep::query()
            ->whereIn('project_id', $projectIds)
            ->with('jirauCard:id,project_step_id')
            ->get(['id', 'project_id', 'is_completed']);

        $total = $steps->count();
        $done = $steps->where('is_completed', true)->count();
        $linked = $steps->filter(fn (ProjectStep $step) => $step->jirauCard !== null)->count();

        return [
            'total' => $total,
            'done' => $done,
            'linked' => $linked,
            'unlinked' => $total - $linked,
            'percent' => $total > 0 ? (int) round(($done / $total) * 100) : null,
        ];
    }

    /** @return array<string, mixed> */
    private function empty(): array
    {
        return [
            'boards' => 0,
            'open' => 0,
            'inbox' => 0,
            'wip' => 0,
            'done' => 0,
            'archived' => 0,
            'overdue' => 0,
            'unassigned' => 0,
            'blocked' => 0,
            'demands' => 0,
            'flow_percent' => null,
            'estimate_open' => 0,
            'estimate_done' => 0,
            'created_7d' => 0,
            'done_7d' => 0,
            'lead_time_days' => null,
            'inbox_oldest_days' => null,
            'by_type' => [],
            'by_priority' => [],
            'by_template' => [],
            'columns' => [],
            'wip_breaches' => [],
            'aging' => [],
            'overdue_cards' => [],
            'assignees' => [],
            'board_rows' => [],
            'project_rows' => [],
            'steps' => ['total' => 0, 'done' => 0, 'linked' => 0, 'unlinked' => 0, 'percent' => null],
        ];
    }
}
