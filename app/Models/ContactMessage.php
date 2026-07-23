<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactMessage extends Model
{
    /** @use HasFactory<\Database\Factories\ContactMessageFactory> */
    use HasFactory;
    protected $fillable = [
        'contact_id',
        'name',
        'email',
        'phone',
        'preferred_channel',
        'company',
        'subject',
        'message',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    public function isUnread(): bool
    {
        return $this->read_at === null;
    }

    public function markAsRead(): void
    {
        if ($this->isUnread()) {
            $this->update(['read_at' => now()]);
        }
    }

    public function initials(): string
    {
        return collect(preg_split('/\s+/', trim($this->name)) ?: [])
            ->filter()
            ->take(2)
            ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('') ?: '?';
    }

    public function preview(int $limit = 72): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $this->message) ?? '');

        return \Illuminate\Support\Str::limit($text !== '' ? $text : $this->subject, $limit);
    }

    public function relativeDay(): string
    {
        if (! $this->created_at) {
            return '';
        }

        if ($this->created_at->isToday()) {
            return $this->created_at->format('H:i');
        }

        if ($this->created_at->isYesterday()) {
            return 'Ontem';
        }

        if ($this->created_at->greaterThan(now()->subWeek())) {
            $days = ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb'];

            return $days[(int) $this->created_at->dayOfWeek] ?? $this->created_at->format('d/m');
        }

        return $this->created_at->format('d/m/Y');
    }
}
