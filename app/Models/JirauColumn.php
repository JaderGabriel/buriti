<?php

namespace App\Models;

use App\Enums\JirauOverviewLane;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JirauColumn extends Model
{
    protected $fillable = [
        'jirau_id',
        'name',
        'slug',
        'sort_order',
        'wip_limit',
        'is_inbox',
        'is_done',
        'color',
    ];

    protected function casts(): array
    {
        return [
            'is_inbox' => 'boolean',
            'is_done' => 'boolean',
            'wip_limit' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function jirau(): BelongsTo
    {
        return $this->belongsTo(Jirau::class);
    }

    public function cards(): HasMany
    {
        return $this->hasMany(JirauCard::class, 'column_id')
            ->whereNull('archived_at')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function isOverWip(): bool
    {
        if ($this->wip_limit === null) {
            return false;
        }

        $count = (int) ($this->cards_count ?? $this->cards()->count());

        return $count > $this->wip_limit;
    }

    public function overviewLane(): JirauOverviewLane
    {
        if ($this->is_inbox) {
            return JirauOverviewLane::Demand;
        }
        if ($this->is_done) {
            return JirauOverviewLane::Done;
        }

        return match ($this->slug) {
            'review', 'waiting' => JirauOverviewLane::Testing,
            'doing', 'class' => JirauOverviewLane::Doing,
            default => JirauOverviewLane::Backlog,
        };
    }
}
