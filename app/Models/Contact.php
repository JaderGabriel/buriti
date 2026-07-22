<?php

namespace App\Models;

use App\Enums\ContactSource;
use App\Enums\ContactStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contact extends Model
{
    /** @use HasFactory<\Database\Factories\ContactFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'company',
        'role',
        'preferred_channel',
        'status',
        'source',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => ContactStatus::class,
            'source' => ContactSource::class,
        ];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ContactMessage::class);
    }

    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(CrmActivity::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class)->withTimestamps();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ContactStatus::Active);
    }

    public function scopeLeads(Builder $query): Builder
    {
        return $query->where('status', ContactStatus::Lead);
    }
}
