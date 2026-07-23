<?php

namespace App\Models;

use App\Enums\ProjectStatus;
use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Project extends Model
{
    /** @use HasFactory<\Database\Factories\ProjectFactory> */
    use HasAttachments, HasFactory;
    protected $fillable = [
        'name',
        'company_id',
        'information',
        'stack',
        'category',
        'website_url',
        'github_url',
        'logo_path',
        'contract_path',
        'status',
        'is_public',
        'repo_is_private',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'repo_is_private' => 'boolean',
            'status' => ProjectStatus::class,
            'stack' => 'array',
        ];
    }

    public function clientCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class)->withTimestamps();
    }

    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(ProjectStep::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Progresso preferencialmente pelas etapas do projeto; se não houver, usa atividades da agenda.
     *
     * @return array{total: int, done: int, open: int, percent: ?int, source: 'steps'|'tasks'|'none'}
     */
    public function progressStats(): array
    {
        $stepsTotal = (int) ($this->steps_count ?? $this->steps()->count());
        if ($stepsTotal > 0) {
            $done = (int) ($this->done_steps_count ?? $this->steps()->where('is_completed', true)->count());

            return [
                'total' => $stepsTotal,
                'done' => $done,
                'open' => max(0, $stepsTotal - $done),
                'percent' => (int) round(($done / $stepsTotal) * 100),
                'source' => 'steps',
            ];
        }

        $tasksTotal = (int) ($this->tasks_count ?? $this->tasks()->count());
        if ($tasksTotal > 0) {
            $done = (int) ($this->done_tasks_count ?? $this->tasks()->where('status', \App\Enums\TaskStatus::Done)->count());

            return [
                'total' => $tasksTotal,
                'done' => $done,
                'open' => (int) ($this->open_tasks_count ?? max(0, $tasksTotal - $done)),
                'percent' => (int) round(($done / $tasksTotal) * 100),
                'source' => 'tasks',
            ];
        }

        return [
            'total' => 0,
            'done' => 0,
            'open' => 0,
            'percent' => null,
            'source' => 'none',
        ];
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderByDesc('id');
    }

    public function scopeOpenSource(Builder $query): Builder
    {
        return $query->where('repo_is_private', false);
    }

    public function scopePrivateRepo(Builder $query): Builder
    {
        return $query->where('repo_is_private', true);
    }

    public function exposesPublicLinks(): bool
    {
        return ! $this->repo_is_private;
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }

    public function contractUrl(): ?string
    {
        if (! $this->contract_path) {
            return null;
        }

        return route('admin.projects.contract', $this);
    }
}
