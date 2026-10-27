<?php

namespace App\Models;

use App\Enums\JirauOverviewLane;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JirauColumn extends Model
{
    protected $fillable = [
        'jirau_id',
        'name',
        'slug',
        'sort_order',
        'wip_limit',
        'is_inbox',
        'is_done',
        'overview_lane',
        'color',
        'default_due_hours',
    ];

    protected function casts(): array
    {
        return [
            'is_inbox' => 'boolean',
            'is_done' => 'boolean',
            'overview_lane' => JirauOverviewLane::class,
            'wip_limit' => 'integer',
            'sort_order' => 'integer',
            'default_due_hours' => 'integer',
        ];
    }

    /** @return array<int, string> */
    public static function defaultDueOptions(): array
    {
        return [
            2 => '2 horas',
            4 => '4 horas',
            8 => '8 horas',
            24 => '1 dia',
            48 => '2 dias',
            72 => '3 dias',
            120 => '5 dias',
            168 => '1 semana',
        ];
    }

    /** @return list<string> */
    public static function colorOptions(): array
    {
        return [
            '#c74634',
            '#e24b35',
            '#c4a035',
            '#0f9d58',
            '#2f6bff',
            '#2f7ec4',
            '#1a5f9e',
            '#7c3aed',
            '#5b6b82',
        ];
    }

    public function defaultDueLabel(): ?string
    {
        if ($this->default_due_hours === null) {
            return null;
        }

        return self::defaultDueOptions()[$this->default_due_hours] ?? $this->default_due_hours.'h';
    }

    public function dueFromDefault(?CarbonInterface $from = null): ?CarbonInterface
    {
        if ($this->default_due_hours === null || $this->default_due_hours < 1) {
            return null;
        }

        return ($from ?? now())->addHours($this->default_due_hours);
    }

    public function applyDefaultDueTo(JirauCard $card): bool
    {
        if ($card->due_at !== null) {
            return false;
        }

        $due = $this->dueFromDefault();
        if (! $due) {
            return false;
        }

        $card->due_at = $due;
        $card->telegram_reminder_sent_at = null;

        return true;
    }

    public function jirau(): BelongsTo
    {
        return $this->belongsTo(Jirau::class);
    }

    public function cards(): HasMany
    {
        return $this->hasMany(JirauCard::class, 'column_id')
            ->whereNull('archived_at')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function watchers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'jirau_column_user', 'column_id', 'user_id')
            ->withTimestamps()
            ->orderBy('name');
    }

    public function isOverWip(): bool
    {
        if ($this->wip_limit === null) {
            return false;
        }

        $count = (int) ($this->cards_count ?? $this->cards()->count());

        return $count > $this->wip_limit;
    }

    public static function inferOverviewLane(bool $inbox, bool $done, ?string $slug): JirauOverviewLane
    {
        if ($inbox) {
            return JirauOverviewLane::Demand;
        }
        if ($done) {
            return JirauOverviewLane::Done;
        }

        return match ($slug) {
            'review', 'waiting' => JirauOverviewLane::Testing,
            'doing', 'class' => JirauOverviewLane::Doing,
            default => JirauOverviewLane::Backlog,
        };
    }

    public function overviewLane(): JirauOverviewLane
    {
        if ($this->is_inbox) {
            return JirauOverviewLane::Demand;
        }
        if ($this->is_done) {
            return JirauOverviewLane::Done;
        }

        return $this->overview_lane
            ?? self::inferOverviewLane(false, false, $this->slug);
    }

    public function overviewLaneIsLocked(): bool
    {
        return $this->is_inbox || $this->is_done;
    }
}
