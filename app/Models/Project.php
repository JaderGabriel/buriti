<?php

namespace App\Models;

use App\Enums\ProjectStatus;
use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Project extends Model
{
    /** @use HasFactory<\Database\Factories\ProjectFactory> */
    use HasAttachments, HasFactory;
    protected $fillable = [
        'name',
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
        return $this->contract_path ? Storage::disk('public')->url($this->contract_path) : null;
    }
}
