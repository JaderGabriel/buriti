<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QaRun extends Model
{
    public const STATUS_RUNNING = 'running';

    public const STATUS_PASSED = 'passed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_ERROR = 'error';

    protected $fillable = [
        'user_id',
        'kind',
        'command',
        'status',
        'summary',
        'output',
        'meta',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isRunning(): bool
    {
        return $this->status === self::STATUS_RUNNING;
    }

    public function isPassed(): bool
    {
        return $this->status === self::STATUS_PASSED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED || $this->status === self::STATUS_ERROR;
    }

    public function durationSeconds(): ?int
    {
        if ($this->started_at === null || $this->finished_at === null) {
            return null;
        }

        return (int) max(0, $this->started_at->diffInSeconds($this->finished_at));
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_RUNNING => 'A correr',
            self::STATUS_PASSED => 'OK',
            self::STATUS_FAILED => 'Falhou',
            self::STATUS_ERROR => 'Erro',
            default => $this->status,
        };
    }

    public function durationLabel(): string
    {
        $seconds = $this->durationSeconds();
        if ($seconds === null) {
            return '—';
        }

        if ($seconds < 60) {
            return $seconds.' s';
        }

        return intdiv($seconds, 60).' min '.($seconds % 60).' s';
    }
}
