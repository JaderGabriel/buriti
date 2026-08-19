<?php

namespace App\Models;

use App\Enums\OpportunityStage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpportunityStageEvent extends Model
{
    protected $fillable = [
        'opportunity_id',
        'user_id',
        'from_stage',
        'to_stage',
        'changed_at',
    ];

    protected function casts(): array
    {
        return [
            'from_stage' => OpportunityStage::class,
            'to_stage' => OpportunityStage::class,
            'changed_at' => 'datetime',
        ];
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function daysInPreviousStage(): ?int
    {
        if ($this->from_stage === null) {
            return null;
        }

        $previous = self::query()
            ->where('opportunity_id', $this->opportunity_id)
            ->where('id', '<', $this->id)
            ->orderByDesc('id')
            ->first();

        $from = $previous?->changed_at ?? $this->opportunity?->created_at;
        if (! $from) {
            return null;
        }

        return (int) $from->diffInDays($this->changed_at);
    }
}
