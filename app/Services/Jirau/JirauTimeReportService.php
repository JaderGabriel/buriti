<?php

namespace App\Services\Jirau;

use App\Models\Jirau;
use App\Models\JirauTimeEntry;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class JirauTimeReportService
{
    public function __construct(private JirauAccess $access) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function snapshot(User $actor, array $filters): array
    {
        $boards = $this->boards($actor, $filters);
        $ids = $boards->modelKeys();

        if ($ids === []) {
            return $this->empty();
        }

        $entries = JirauTimeEntry::query()
            ->whereIn('jirau_id', $ids)
            ->with([
                'user:id,name,jirau_role,is_admin',
                'card:id,title,jirau_id',
                'jirau:id,name,project_id,company_id',
                'jirau.project:id,name',
                'jirau.company:id,name,trade_name',
            ])
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->get();

        $entries = $this->filterEntries($entries, $filters);
        $total = (int) $entries->sum(fn (JirauTimeEntry $entry) => $entry->elapsedSeconds());
        $running = $entries->filter(fn (JirauTimeEntry $entry) => $entry->isRunning());
        $people = $entries->pluck('user_id')->unique()->count();
        $sessions = $entries->count();
        $avgSession = $sessions > 0 ? (int) round($total / $sessions) : 0;
        $avgPerson = $people > 0 ? (int) round($total / $people) : 0;

        return [
            'total' => $total,
            'total_label' => JirauTimeEntry::formatSeconds($total),
            'sessions' => $sessions,
            'running' => $running->count(),
            'people' => $people,
            'cards' => $entries->pluck('card_id')->unique()->count(),
            'avg_session_label' => JirauTimeEntry::formatSeconds($avgSession),
            'avg_person_label' => JirauTimeEntry::formatSeconds($avgPerson),
            'by_user' => $this->groupRows($entries, function (JirauTimeEntry $entry) {
                return (string) ($entry->user_id ?: 0);
            }, function (Collection $group) {
                $first = $group->first();

                return [
                    'id' => $first?->user_id ? (int) $first->user_id : null,
                    'name' => $first?->user?->name ?? 'Alguém',
                ];
            }),
            'by_project' => $this->groupRows($entries, function (JirauTimeEntry $entry) {
                return (string) ($entry->jirau?->project_id ?: 0);
            }, function (Collection $group) {
                $project = $group->first()?->jirau?->project;

                return [
                    'id' => $project?->id,
                    'name' => $project?->name ?? 'Sem projeto',
                ];
            }),
            'by_company' => $this->groupRows($entries, function (JirauTimeEntry $entry) {
                return (string) ($entry->jirau?->company_id ?: 0);
            }, function (Collection $group) {
                $company = $group->first()?->jirau?->company;

                return [
                    'id' => $company?->id,
                    'name' => $company?->displayName() ?? 'Sem empresa',
                ];
            }),
            'by_column' => $this->groupRows($entries, function (JirauTimeEntry $entry) {
                return (string) ($entry->column_id ?: 'x-'.$entry->column_name);
            }, function (Collection $group) {
                $first = $group->first();

                return [
                    'id' => $first?->column_id,
                    'name' => $first?->column_name ?: 'Etapa',
                ];
            }),
            'entries' => $entries
                ->take(80)
                ->values()
                ->map(fn (JirauTimeEntry $entry) => [
                    'card_id' => (int) $entry->card_id,
                    'jirau_id' => (int) $entry->jirau_id,
                    'title' => $entry->card?->title ?? 'Cartão',
                    'user' => $entry->user?->name ?? 'Alguém',
                    'project' => $entry->jirau?->project?->name ?? '—',
                    'company' => $entry->jirau?->company?->displayName() ?? '—',
                    'column' => $entry->column_name,
                    'started' => $entry->startedLabel(),
                    'ended' => $entry->isRunning() ? 'Em curso' : $entry->endedLabel(),
                    'running' => $entry->isRunning(),
                    'label' => $entry->elapsedLabel(),
                    'seconds' => $entry->elapsedSeconds(),
                ])
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Jirau>
     */
    private function boards(User $actor, array $filters): Collection
    {
        $query = $this->access->visibleQuery($actor)
            ->withoutDemo()
            ->with(['project:id,name', 'company:id,name,trade_name'])
            ->orderBy('name')
            ->orderBy('id');

        if ($filters['projeto']) {
            $query->where('project_id', $filters['projeto']);
        }
        if ($filters['quadro']) {
            $query->whereKey($filters['quadro']);
        }
        if ($filters['empresa']) {
            $query->where('company_id', $filters['empresa']);
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
     * @param  Collection<int, JirauTimeEntry>  $entries
     * @param  array<string, mixed>  $filters
     * @return Collection<int, JirauTimeEntry>
     */
    private function filterEntries(Collection $entries, array $filters): Collection
    {
        $from = $this->dayStart($filters['de'] ?? null);
        $to = $this->dayEnd($filters['ate'] ?? null);
        $userId = is_string($filters['usuario'] ?? null) && ctype_digit((string) $filters['usuario'])
            ? (int) $filters['usuario']
            : null;

        return $entries->filter(function (JirauTimeEntry $entry) use ($from, $to, $userId) {
            if ($userId && (int) $entry->user_id !== $userId) {
                return false;
            }
            if ($from && $entry->started_at && $entry->started_at->lt($from)) {
                return false;
            }
            if ($to && $entry->started_at && $entry->started_at->gt($to)) {
                return false;
            }

            return true;
        })->values();
    }

    /**
     * @param  Collection<int, JirauTimeEntry>  $entries
     * @param  callable(JirauTimeEntry): string  $key
     * @param  callable(Collection<int, JirauTimeEntry>): array{id: int|string|null, name: string}  $meta
     * @return list<array{id: int|string|null, name: string, seconds: int, label: string, sessions: int, people: int, cards: int, running: int}>
     */
    private function groupRows(Collection $entries, callable $key, callable $meta): array
    {
        return $entries
            ->groupBy($key)
            ->map(function (Collection $group) use ($meta) {
                $seconds = (int) $group->sum(fn (JirauTimeEntry $entry) => $entry->elapsedSeconds());
                $info = $meta($group);

                return [
                    'id' => $info['id'],
                    'name' => $info['name'],
                    'seconds' => $seconds,
                    'label' => JirauTimeEntry::formatSeconds($seconds),
                    'sessions' => $group->count(),
                    'people' => $group->pluck('user_id')->unique()->count(),
                    'cards' => $group->pluck('card_id')->unique()->count(),
                    'running' => $group->filter(fn (JirauTimeEntry $entry) => $entry->isRunning())->count(),
                ];
            })
            ->sortByDesc('seconds')
            ->values()
            ->all();
    }

    private function dayStart(?string $date): ?Carbon
    {
        if (! is_string($date) || $date === '') {
            return null;
        }

        return Carbon::parse($date, (string) config('app.timezone'))->startOfDay();
    }

    private function dayEnd(?string $date): ?Carbon
    {
        if (! is_string($date) || $date === '') {
            return null;
        }

        return Carbon::parse($date, (string) config('app.timezone'))->endOfDay();
    }

    /** @return array<string, mixed> */
    private function empty(): array
    {
        return [
            'total' => 0,
            'total_label' => JirauTimeEntry::formatSeconds(0),
            'sessions' => 0,
            'running' => 0,
            'people' => 0,
            'cards' => 0,
            'avg_session_label' => JirauTimeEntry::formatSeconds(0),
            'avg_person_label' => JirauTimeEntry::formatSeconds(0),
            'by_user' => [],
            'by_project' => [],
            'by_company' => [],
            'by_column' => [],
            'entries' => [],
        ];
    }
}
