<?php

namespace App\Services\Jirau;

use App\Enums\JirauOverviewLane;
use App\Models\Jirau;
use App\Models\JirauCard;
use Illuminate\Support\Collection;

class JirauOverviewService
{
    /**
     * @return array{
     *     estado: string,
     *     boards: int,
     *     lanes: array<string, Collection<int, JirauCard>>
     * }
     */
    public function snapshot(string $estado = 'ativos'): array
    {
        $estado = in_array($estado, ['ativos', 'inativos', 'todos'], true) ? $estado : 'ativos';

        $boards = Jirau::query()
            ->when($estado === 'ativos', fn ($q) => $q->where('is_active', true))
            ->when($estado === 'inativos', fn ($q) => $q->where('is_active', false))
            ->orderBy('name')
            ->get(['id', 'name', 'is_active', 'project_id']);

        $ids = $boards->modelKeys();
        $cards = $ids === []
            ? collect()
            : JirauCard::query()
                ->whereIn('jirau_id', $ids)
                ->active()
                ->with([
                    'column',
                    'labels',
                    'assignee',
                    'jirau.project',
                    'creator',
                ])
                ->withCount([
                    'checklistItems',
                    'checklistItems as done_checklist_items_count' => fn ($q) => $q->where('is_done', true),
                    'comments',
                ])
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();

        $lanes = [];
        foreach (JirauOverviewLane::cases() as $lane) {
            $lanes[$lane->value] = $cards
                ->filter(fn (JirauCard $card) => $card->column?->overviewLane() === $lane)
                ->values();
        }

        return [
            'estado' => $estado,
            'boards' => $boards->count(),
            'lanes' => $lanes,
        ];
    }
}
