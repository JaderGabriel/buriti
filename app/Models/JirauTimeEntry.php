<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class JirauTimeEntry extends Model
{
    protected $fillable = [
        'jirau_id',
        'card_id',
        'user_id',
        'column_id',
        'column_name',
        'started_at',
        'ended_at',
        'seconds',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'seconds' => 'integer',
        ];
    }

    public function jirau(): BelongsTo
    {
        return $this->belongsTo(Jirau::class);
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(JirauCard::class, 'card_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function column(): BelongsTo
    {
        return $this->belongsTo(JirauColumn::class, 'column_id');
    }

    public function scopeRunning(Builder $query): Builder
    {
        return $query->whereNull('ended_at');
    }

    public function isRunning(): bool
    {
        return $this->ended_at === null;
    }

    public function elapsedSeconds(?Carbon $at = null): int
    {
        if (! $this->isRunning()) {
            return max(0, (int) $this->seconds);
        }

        $from = $this->started_at?->getTimestamp() ?? time();
        $to = ($at ?? now())->getTimestamp();

        return max(0, $to - $from);
    }

    public function elapsedLabel(?Carbon $at = null): string
    {
        return self::formatSeconds($this->elapsedSeconds($at));
    }

    public static function formatSeconds(int $seconds): string
    {
        $seconds = max(0, $seconds);
        if ($seconds < 60) {
            return $seconds.'s';
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        if ($hours > 0 && $minutes > 0) {
            return $hours.'h '.$minutes.'min';
        }

        if ($hours > 0) {
            return $hours.'h';
        }

        return $minutes.'min';
    }

    public function rangeLabel(): string
    {
        $zone = (string) config('app.timezone');
        $start = $this->started_at?->timezone($zone)?->format('d/m H:i');
        if ($this->isRunning()) {
            return 'desde '.$start;
        }

        $end = $this->ended_at?->timezone($zone)?->format('H:i');

        return $start.'–'.$end;
    }
}
