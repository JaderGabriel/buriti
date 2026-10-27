<?php

namespace App\Models;

use Database\Factories\ProjectStepFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ProjectStep extends Model
{
    /** @use HasFactory<ProjectStepFactory> */
    use HasFactory;

    protected $fillable = [
        'title',
        'notes',
        'is_completed',
        'completed_at',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_completed' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function jirauCard(): HasOne
    {
        return $this->hasOne(JirauCard::class);
    }

    public function markCompleted(bool $completed = true): void
    {
        $this->forceFill([
            'is_completed' => $completed,
            'completed_at' => $completed ? ($this->completed_at ?? now()) : null,
        ])->save();
    }
}
