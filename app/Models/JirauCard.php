<?php

namespace App\Models;

use App\Enums\JirauCardParticipantRole;
use App\Enums\JirauCardPriority;
use App\Enums\JirauCardType;
use App\Models\Concerns\HasAttachments;
use Database\Factories\JirauCardFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

class JirauCard extends Model
{
    /** @use HasFactory<JirauCardFactory> */
    use HasAttachments, HasFactory;

    protected $fillable = [
        'jirau_id',
        'column_id',
        'created_by',
        'assigned_to',
        'title',
        'description',
        'type',
        'priority',
        'estimate',
        'due_at',
        'sort_order',
        'triaged_at',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => JirauCardType::class,
            'priority' => JirauCardPriority::class,
            'due_at' => 'datetime',
            'triaged_at' => 'datetime',
            'archived_at' => 'datetime',
            'telegram_reminder_sent_at' => 'datetime',
            'estimate' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function jirau(): BelongsTo
    {
        return $this->belongsTo(Jirau::class);
    }

    public function column(): BelongsTo
    {
        return $this->belongsTo(JirauColumn::class, 'column_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'jirau_card_participants', 'card_id', 'user_id')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function responsibles(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'jirau_card_participants', 'card_id', 'user_id')
            ->withPivot('role')
            ->withTimestamps()
            ->wherePivot('role', JirauCardParticipantRole::Responsible->value);
    }

    public function actors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'jirau_card_participants', 'card_id', 'user_id')
            ->withPivot('role')
            ->withTimestamps()
            ->wherePivot('role', JirauCardParticipantRole::Actor->value);
    }

    public function demander(): ?User
    {
        $this->loadMissing('creator');
        $creator = $this->creator;
        if ($creator && ! $creator->isAssignableOnJirau()) {
            return $creator;
        }

        return null;
    }

    public function demanderId(): ?int
    {
        return $this->demander()?->id;
    }

    /** @return Collection<int, User> */
    public function responsiblePeople(): Collection
    {
        $this->loadMissing(['responsibles', 'assignee']);
        if ($this->responsibles->isNotEmpty()) {
            return $this->responsibles;
        }

        return collect($this->assignee ? [$this->assignee] : []);
    }

    /** @return Collection<int, User> */
    public function actorPeople(): Collection
    {
        $this->loadMissing(['actors']);
        $responsibleIds = $this->responsiblePeople()->pluck('id')->all();

        return $this->actors
            ->reject(fn (User $user) => in_array($user->id, $responsibleIds, true))
            ->values();
    }

    public function hasResponsible(?int $userId): bool
    {
        if (! $userId) {
            return false;
        }

        if ((int) $this->assigned_to === $userId) {
            return true;
        }

        return $this->responsiblePeople()->contains(fn (User $user) => (int) $user->id === $userId);
    }

    public function isUnassigned(): bool
    {
        return $this->responsiblePeople()->isEmpty();
    }

    public function projectStep(): BelongsTo
    {
        return $this->belongsTo(ProjectStep::class);
    }

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(JirauLabel::class, 'jirau_card_label', 'card_id', 'label_id');
    }

    public function checklistItems(): HasMany
    {
        return $this->hasMany(JirauChecklistItem::class, 'card_id')->orderBy('sort_order')->orderBy('id');
    }

    public function items(): HasMany
    {
        return $this->checklistItems();
    }

    public function comments(): HasMany
    {
        return $this->hasMany(JirauComment::class, 'card_id')->orderBy('created_at');
    }

    public function links(): HasMany
    {
        return $this->hasMany(JirauCardLink::class, 'card_id')->latest();
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(JirauTimeEntry::class, 'card_id')->orderByDesc('started_at')->orderByDesc('id');
    }

    public function runningTimeEntries(): HasMany
    {
        return $this->hasMany(JirauTimeEntry::class, 'card_id')->whereNull('ended_at');
    }

    public function latestClosedTimeEntry(): HasOne
    {
        return $this->hasOne(JirauTimeEntry::class, 'card_id')->ofMany(
            ['ended_at' => 'max', 'id' => 'max'],
            fn ($query) => $query->whereNotNull('ended_at'),
        );
    }

    public function spentSeconds(): int
    {
        if ($this->relationLoaded('timeEntries')) {
            return (int) $this->timeEntries->sum(fn (JirauTimeEntry $entry) => $entry->elapsedSeconds());
        }

        $closed = (int) ($this->closed_seconds ?? 0);
        $running = $this->relationLoaded('runningTimeEntries')
            ? $this->runningTimeEntries
            : collect();

        return $closed + (int) $running->sum(fn (JirauTimeEntry $entry) => $entry->elapsedSeconds());
    }

    public function spentLabel(): string
    {
        return JirauTimeEntry::formatSeconds($this->spentSeconds());
    }

    public function isTrackedBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        $running = $this->relationLoaded('runningTimeEntries')
            ? $this->runningTimeEntries
            : $this->runningTimeEntries()->where('user_id', $user->id)->get();

        return $running->contains(fn (JirauTimeEntry $entry) => (int) $entry->user_id === (int) $user->id);
    }

    public function runningEntryFor(?User $user): ?JirauTimeEntry
    {
        if (! $user) {
            return null;
        }

        $running = $this->relationLoaded('runningTimeEntries')
            ? $this->runningTimeEntries
            : $this->runningTimeEntries()->where('user_id', $user->id)->get();

        return $running->first(fn (JirauTimeEntry $entry) => (int) $entry->user_id === (int) $user->id);
    }

    public function key(): string
    {
        return 'JR-'.$this->id;
    }

    public function isInbox(): bool
    {
        return (bool) $this->column?->is_inbox;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    public function checklistProgress(): array
    {
        $total = (int) ($this->checklist_items_count ?? $this->checklistItems()->count());
        $done = (int) ($this->done_checklist_items_count ?? $this->checklistItems()->where('is_done', true)->count());

        return [
            'total' => $total,
            'done' => $done,
            'percent' => $total > 0 ? (int) round(($done / $total) * 100) : null,
        ];
    }
}
