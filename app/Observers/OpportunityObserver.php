<?php

namespace App\Observers;

use App\Enums\OpportunityStage;
use App\Models\Opportunity;
use App\Models\OpportunityStageEvent;
use Illuminate\Support\Facades\Auth;

class OpportunityObserver
{
    public function created(Opportunity $opportunity): void
    {
        $this->record($opportunity, null, $this->stageValue($opportunity->stage));
    }

    public function updating(Opportunity $opportunity): void
    {
        if (! $opportunity->isDirty('stage')) {
            return;
        }

        $this->record(
            $opportunity,
            $this->stageValue($opportunity->getOriginal('stage')),
            $this->stageValue($opportunity->stage),
        );
    }

    private function record(Opportunity $opportunity, ?string $fromStage, ?string $toStage): void
    {
        if ($toStage === null || $toStage === '' || $fromStage === $toStage) {
            return;
        }

        OpportunityStageEvent::query()->create([
            'opportunity_id' => $opportunity->id,
            'user_id' => Auth::id(),
            'from_stage' => $fromStage,
            'to_stage' => $toStage,
            'changed_at' => now(),
        ]);
    }

    private function stageValue(mixed $stage): ?string
    {
        if ($stage instanceof OpportunityStage) {
            return $stage->value;
        }

        if (is_string($stage) && $stage !== '') {
            return $stage;
        }

        return null;
    }
}
