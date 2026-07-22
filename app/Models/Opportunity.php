<?php

namespace App\Models;

use App\Enums\OpportunityStage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Opportunity extends Model
{
    /** @use HasFactory<\Database\Factories\OpportunityFactory> */
    use HasFactory;

    protected $fillable = [
        'contact_id',
        'project_id',
        'title',
        'description',
        'stage',
        'value',
        'expected_close_at',
    ];

    protected function casts(): array
    {
        return [
            'stage' => OpportunityStage::class,
            'value' => 'decimal:2',
            'expected_close_at' => 'date',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(CrmActivity::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('stage', [
            OpportunityStage::Won->value,
            OpportunityStage::Lost->value,
        ]);
    }
}
