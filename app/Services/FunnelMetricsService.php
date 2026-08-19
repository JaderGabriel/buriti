<?php

namespace App\Services;

use App\Enums\OpportunityStage;
use App\Models\Opportunity;
use App\Models\OpportunityStageEvent;
use Illuminate\Support\Collection;

class FunnelMetricsService
{
    /**
     * @return array{
     *     value_by_stage: array<string, float>,
     *     count_by_stage: array<string, int>,
     *     win_rate: float,
     *     avg_days_by_stage: array<string, float|null>,
     *     closed: int,
     *     won: int
     * }
     */
    public function snapshot(?int $companyId = null): array
    {
        $query = Opportunity::query()->when($companyId, fn ($q) => $q->where('company_id', $companyId));

        $valueByStage = [];
        $countByStage = [];
        foreach (OpportunityStage::boardOrder() as $stage) {
            $valueByStage[$stage] = (float) (clone $query)->where('stage', $stage)->sum('value');
            $countByStage[$stage] = (int) (clone $query)->where('stage', $stage)->count();
        }

        $won = $countByStage[OpportunityStage::Won->value] ?? 0;
        $lost = $countByStage[OpportunityStage::Lost->value] ?? 0;
        $closed = $won + $lost;

        return [
            'value_by_stage' => $valueByStage,
            'count_by_stage' => $countByStage,
            'win_rate' => $closed > 0 ? round(($won / $closed) * 100, 1) : 0.0,
            'avg_days_by_stage' => $this->averageDaysByStage($companyId),
            'closed' => $closed,
            'won' => $won,
        ];
    }

    /** @return array<string, float|null> */
    private function averageDaysByStage(?int $companyId): array
    {
        $events = OpportunityStageEvent::query()
            ->whereNotNull('from_stage')
            ->when($companyId, fn ($q) => $q->whereHas('opportunity', fn ($o) => $o->where('company_id', $companyId)))
            ->with('opportunity:id,created_at')
            ->orderBy('opportunity_id')
            ->orderBy('changed_at')
            ->get();

        $sums = [];
        $counts = [];
        $lastByOpp = [];

        foreach ($events as $event) {
            $prev = $lastByOpp[$event->opportunity_id] ?? null;
            $fromAt = $prev?->changed_at ?? $event->opportunity?->created_at;
            $stage = $event->from_stage instanceof OpportunityStage
                ? $event->from_stage->value
                : (string) $event->from_stage;

            if ($fromAt && $stage !== '') {
                $days = max(0, $fromAt->diffInDays($event->changed_at));
                $sums[$stage] = ($sums[$stage] ?? 0) + $days;
                $counts[$stage] = ($counts[$stage] ?? 0) + 1;
            }

            $lastByOpp[$event->opportunity_id] = $event;
        }

        $avg = [];
        foreach (OpportunityStage::boardOrder() as $stage) {
            $avg[$stage] = isset($counts[$stage]) && $counts[$stage] > 0
                ? round($sums[$stage] / $counts[$stage], 1)
                : null;
        }

        return $avg;
    }

    /** @return Collection<int, Opportunity> */
    public function exportRows(?int $companyId = null): Collection
    {
        return Opportunity::query()
            ->with(['contact.clientCompany', 'clientCompany', 'project', 'activities' => fn ($q) => $q->latest('happened_at')->limit(1)])
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->latest()
            ->limit(2000)
            ->get();
    }
}
