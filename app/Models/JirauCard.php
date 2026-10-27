<?php

namespace App\Models;

use App\Enums\JirauCardPriority;
use App\Enums\JirauCardType;
use Database\Factories\JirauCardFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JirauCard extends Model
{
    /** @use HasFactory<JirauCardFactory> */
    use HasFactory;

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

    public function comments(): HasMany
    {
        return $this->hasMany(JirauComment::class, 'card_id')->orderBy('created_at');
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
