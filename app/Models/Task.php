<?php

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
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
        'title',
        'description',
        'status',
        'priority',
        'due_at',
        'google_event_id',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'status' => TaskStatus::class,
            'priority' => TaskPriority::class,
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
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

    public function googleCalendarCreateUrl(): string
    {
        $start = ($this->due_at ?? now()->addDay())->copy()->startOfHour();
        $end = $start->copy()->addHour();

        $params = http_build_query([
            'action' => 'TEMPLATE',
            'text' => $this->title,
            'details' => $this->description ?? '',
            'dates' => $start->utc()->format('Ymd\THis\Z').'/'.$end->utc()->format('Ymd\THis\Z'),
        ]);

        return 'https://calendar.google.com/calendar/render?'.$params;
    }
}
