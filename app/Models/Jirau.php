<?php

namespace App\Models;

use App\Enums\JirauTemplate;
use Database\Factories\JirauFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Jirau extends Model
{
    /** @use HasFactory<JirauFactory> */
    use HasFactory;

    public const DEMO_NAME = 'Jirau DEMO';

    public const DEMO_PROJECT_NAME = 'Jirau demonstração';

    protected $table = 'jiraus';

    protected $fillable = [
        'project_id',
        'company_id',
        'contract_name',
        'name',
        'template',
        'is_active',
        'sort_order',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'template' => JirauTemplate::class,
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function contractLabel(): string
    {
        $named = trim((string) $this->contract_name);
        if ($named !== '') {
            return $named;
        }

        return $this->company?->displayName() ?? 'Contrato';
    }

    public function allocationLabel(): string
    {
        $project = $this->project?->name ?? 'Projeto';
        $company = $this->company?->displayName();
        $contract = trim((string) $this->contract_name);

        if ($company && $contract !== '' && strcasecmp($contract, $company) !== 0) {
            return $project.' · '.$company.' · '.$contract;
        }

        if ($company) {
            return $project.' · '.$company;
        }

        return $project;
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function columns(): HasMany
    {
        return $this->hasMany(JirauColumn::class)->orderBy('sort_order')->orderBy('id');
    }

    public function cards(): HasMany
    {
        return $this->hasMany(JirauCard::class);
    }

    public function labels(): HasMany
    {
        return $this->hasMany(JirauLabel::class)->orderBy('name');
    }

    public function mailLinks(): MorphMany
    {
        return $this->morphMany(MailLink::class, 'linkable');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'jirau_members')
            ->withPivot('invited_by')
            ->withTimestamps();
    }

    public function scopeWithoutDemo(Builder $query): Builder
    {
        return $query
            ->where('name', '!=', self::DEMO_NAME)
            ->whereDoesntHave('project', fn (Builder $project) => $project->where('name', self::DEMO_PROJECT_NAME));
    }

    public function isDemo(): bool
    {
        return $this->name === self::DEMO_NAME
            || $this->project?->name === self::DEMO_PROJECT_NAME;
    }

    public function isEmpty(): bool
    {
        if (array_key_exists('cards_count', $this->attributes)) {
            return (int) $this->getAttribute('cards_count') === 0;
        }

        return ! $this->cards()->exists();
    }

    public function inboxColumn(): ?JirauColumn
    {
        return $this->columns->firstWhere('is_inbox', true)
            ?? $this->columns()->where('is_inbox', true)->first();
    }

    public function doneColumn(): ?JirauColumn
    {
        return $this->columns->firstWhere('is_done', true)
            ?? $this->columns()->where('is_done', true)->first();
    }

    public function inboxCount(): int
    {
        $inbox = $this->inboxColumn();
        if (! $inbox) {
            return 0;
        }

        return (int) ($inbox->cards_count ?? $this->cards()
            ->where('column_id', $inbox->id)
            ->whereNull('archived_at')
            ->count());
    }
}
