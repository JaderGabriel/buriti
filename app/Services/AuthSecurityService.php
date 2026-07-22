<?php

namespace App\Services;

use App\Models\LoginActivity;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuthSecurityService
{
    public function recordLoginAttempt(Request $request, ?User $user, bool $successful): void
    {
        LoginActivity::query()->create([
            'user_id' => $user?->id,
            'email' => $user?->email ?? $request->input('login', $request->input('email')),
            'successful' => $successful,
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
            'created_at' => now(),
        ]);
    }

    public function markSuccessfulLogin(User $user, Request $request): void
    {
        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        $this->recordLoginAttempt($request, $user, true);
    }

    /** @return list<object{id: string, user_id: int|null, ip_address: string|null, user_agent: string|null, last_activity: int, is_current: bool}> */
    public function sessionsFor(User $user, ?string $currentSessionId = null): array
    {
        if (config('session.driver') !== 'database') {
            return [];
        }

        return DB::table('sessions')
            ->where('user_id', $user->id)
            ->orderByDesc('last_activity')
            ->get()
            ->map(fn ($row) => (object) [
                'id' => $row->id,
                'user_id' => $row->user_id,
                'ip_address' => $row->ip_address,
                'user_agent' => $row->user_agent,
                'last_activity' => (int) $row->last_activity,
                'is_current' => $currentSessionId !== null && hash_equals((string) $row->id, (string) $currentSessionId),
            ])
            ->all();
    }

    public function destroySession(User $user, string $sessionId): bool
    {
        if (config('session.driver') !== 'database') {
            return false;
        }

        return DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('id', $sessionId)
            ->delete() > 0;
    }

    public function destroyOtherSessions(User $user, string $currentSessionId): int
    {
        if (config('session.driver') !== 'database') {
            return 0;
        }

        return DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('id', '!=', $currentSessionId)
            ->delete();
    }

    public function adminCount(): int
    {
        return User::query()
            ->where('is_admin', true)
            ->where('is_active', true)
            ->count();
    }
}
