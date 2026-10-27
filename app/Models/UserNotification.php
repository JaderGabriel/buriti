<?php

namespace App\Models;

use App\Enums\NotificationType;
use Database\Factories\UserNotificationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserNotification extends Model
{
    /** @use HasFactory<UserNotificationFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'body',
        'url',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => NotificationType::class,
            'read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
        if (! $this->isUnread()) {
            return;
        }

        $this->forceFill(['read_at' => now()])->save();
    }

    public function destinationUrl(): ?string
    {
        $url = trim((string) $this->url);
        if ($url === '') {
            return null;
        }

        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return $url;
        }

        $app = rtrim((string) config('app.url'), '/');
        if ($app !== '' && str_starts_with($url, $app)) {
            $rest = substr($url, strlen($app));
            if ($rest === '' || str_starts_with($rest, '/')) {
                return $url;
            }
        }

        return null;
    }
}
