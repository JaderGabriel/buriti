<?php

namespace App\Models;

use App\Enums\OpportunityStage;
use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Opportunity extends Model
{
    /** @use HasFactory<\Database\Factories\OpportunityFactory> */
    use HasAttachments, HasFactory;

    protected $fillable = [
        'contact_id',
        'company_id',
        'owner_id',
        'project_id',
        'title',
        'description',
        'stage',
        'value',
        'expected_close_at',
        'sort_order',
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

    public function clientCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(CrmActivity::class);
    }

    public function stageEvents(): HasMany
    {
        return $this->hasMany(OpportunityStageEvent::class)->orderByDesc('changed_at')->orderByDesc('id');
    }

    public function companyLabel(): ?string
    {
        $label = $this->clientCompany?->displayName()
            ?: $this->contact?->companyLabel();

        return filled($label) ? (string) $label : null;
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('stage', [
            OpportunityStage::Won->value,
            OpportunityStage::Lost->value,
        ]);
    }
}
