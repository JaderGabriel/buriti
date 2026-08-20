<?php

namespace App\Models;

use App\Enums\CompanyStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Company extends Model
{
    /** @use HasFactory<\Database\Factories\CompanyFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'trade_name',
        'document',
        'email',
        'phone',
        'website_url',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => CompanyStatus::class,
        ];
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class);
    }

    public function ideaNotes(): HasMany
    {
        return $this->hasMany(IdeaNote::class)->orderByDesc('sort_order')->orderByDesc('id');
    }

    public function contactOpportunities(): HasManyThrough
    {
        return $this->hasManyThrough(Opportunity::class, Contact::class);
    }

    /** Oportunidades com company_id ou via contato da empresa. */
    public function relatedOpportunities(): Builder
    {
        return Opportunity::query()->where(function (Builder $q) {
            $q->where('company_id', $this->id)
                ->orWhereHas('contact', fn (Builder $c) => $c->where('company_id', $this->id));
        });
    }

    public function displayName(): string
    {
        return $this->trade_name ?: $this->name;
    }

    public function initials(): string
    {
        return collect(preg_split('/\s+/', trim($this->displayName())) ?: [])
            ->filter()
            ->take(2)
            ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
    }
}
