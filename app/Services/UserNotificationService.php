<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class UserNotificationService
{
    /**
     * @param  iterable<int, User|null>  $users
     */
    public function notify(
        iterable $users,
        NotificationType $type,
        string $title,
        ?string $body = null,
        ?string $url = null,
    ): int {
        $now = now();
        $rows = collect($users)
            ->filter(fn ($user) => $user instanceof User && $user->is_active)
            ->unique(fn (User $user) => $user->id)
            ->map(fn (User $user) => [
                'user_id' => $user->id,
                'type' => $type->value,
                'title' => Str::limit($title, 180, ''),
                'body' => $body !== null ? Str::limit($body, 500, '') : null,
                'url' => $url !== null ? Str::limit($url, 2048, '') : null,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->values()
            ->all();

        if ($rows === []) {
            return 0;
        }

        UserNotification::query()->insert($rows);

        return count($rows);
    }

    public function notifyAdmins(
        NotificationType $type,
        string $title,
        ?string $body = null,
        ?string $url = null,
    ): int {
        return $this->notify(
            User::query()->where('is_admin', true)->where('is_active', true)->get(),
            $type,
            $title,
            $body,
            $url,
        );
    }

    /** @return Collection<int, UserNotification> */
    public function recentFor(User $user, int $limit = 8): Collection
    {
        return $user->inboxNotifications()
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function unreadCountFor(User $user): int
    {
        return $user->inboxNotifications()->unread()->count();
    }

    public function markAllRead(User $user): int
    {
        return $user->inboxNotifications()
            ->unread()
            ->update(['read_at' => now()]);
    }
}
