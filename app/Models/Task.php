<?php

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Services\GoogleCalendarService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    /** @use HasFactory<\Database\Factories\TaskFactory> */
    use HasFactory;

    protected $fillable = [
        'project_id',
        'contact_id',
        'title',
        'description',
        'status',
        'priority',
        'due_at',
        'google_event_id',
        'meet_url',
        'want_meet',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'status' => TaskStatus::class,
            'priority' => TaskPriority::class,
            'want_meet' => 'boolean',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [TaskStatus::Todo, TaskStatus::Doing]);
    }

    public function scopeBoardOrdered(Builder $query): Builder
    {
        return $query
            ->orderByRaw("case status when 'doing' then 0 when 'todo' then 1 else 2 end")
            ->orderByRaw('due_at is null')
            ->orderBy('due_at');
    }

    public function hasMeet(): bool
    {
        return filled($this->meet_url);
    }

    public function isSyncedWithGoogle(): bool
    {
        return filled($this->google_event_id);
    }

    public function googleCalendarCreateUrl(bool $withMeet = true): string
    {
        return app(GoogleCalendarService::class)->createEventUrl($this, $withMeet && $this->want_meet);
    }

    public function meetActionUrl(): string
    {
        if ($this->hasMeet()) {
            return $this->meet_url;
        }

        return app(GoogleCalendarService::class)->instantMeetUrl();
    }
}
