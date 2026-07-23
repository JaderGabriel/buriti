<?php

namespace App\Services;

use App\Models\LoginActivity;
use App\Models\User;
use App\Support\ClientContext;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuthSecurityService
{
    public function __construct(private AuditLogger $audit) {}

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

        $this->audit->record(
            $successful ? 'auth.login.success' : 'auth.login.failed',
            $user,
            [
                'summary' => $user?->email ?? $request->input('login', $request->input('email')),
                'email' => $user?->email ?? $request->input('login', $request->input('email')),
            ],
            $request,
            $user?->id,
        );
    }

    public function markSuccessfulLogin(User $user, Request $request): void
    {
        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        $this->recordLoginAttempt($request, $user, true);
    }

    public function sessionsEnabled(): bool
    {
        return config('session.driver') === 'database';
    }

    /** @return list<object> */
    public function sessionsFor(User $user, ?string $currentSessionId = null): array
    {
        if (! $this->sessionsEnabled()) {
            return [];
        }

        return DB::table('sessions')
            ->where('user_id', $user->id)
            ->orderByDesc('last_activity')
            ->get()
            ->map(fn ($row) => $this->presentSession($row, $currentSessionId, $user))
            ->all();
    }

    /** @return list<object> */
    public function activeSessions(?string $viewerSessionId = null, int $limit = 50): array
    {
        if (! $this->sessionsEnabled()) {
            return [];
        }

        $rows = DB::table('sessions')
            ->whereNotNull('user_id')
            ->orderByDesc('last_activity')
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $users = User::query()
            ->whereIn('id', $rows->pluck('user_id')->unique()->filter()->all())
            ->get(['id', 'name', 'email', 'username', 'avatar_path', 'is_active'])
            ->keyBy('id');

        return $rows
            ->map(fn ($row) => $this->presentSession(
                $row,
                $viewerSessionId,
                $users->get((int) $row->user_id),
            ))
            ->all();
    }

    public function destroySession(User $user, string $sessionId): bool
    {
        if (! $this->sessionsEnabled()) {
            return false;
        }

        $deleted = DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('id', $sessionId)
            ->delete() > 0;

        if ($deleted) {
            $this->audit->record('auth.session.destroy', $user, [
                'summary' => 'Sessão '.$sessionId,
                'session_id' => $sessionId,
            ]);
        }

        return $deleted;
    }

    public function destroyOtherSessions(User $user, string $currentSessionId): int
    {
        if (! $this->sessionsEnabled()) {
            return 0;
        }

        $count = DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('id', '!=', $currentSessionId)
            ->delete();

        if ($count > 0) {
            $this->audit->record('auth.session.destroy_others', $user, [
                'summary' => "{$count} sessão(ões)",
                'count' => $count,
            ]);
        }

        return $count;
    }

    public function destroyAllSessions(User $user): int
    {
        if (! $this->sessionsEnabled()) {
            return 0;
        }

        $count = DB::table('sessions')
            ->where('user_id', $user->id)
            ->delete();

        if ($count > 0) {
            $this->audit->record('auth.session.destroy_all', $user, [
                'summary' => "{$count} sessão(ões)",
                'count' => $count,
            ]);
        }

        return $count;
    }

    public function adminCount(): int
    {
        return User::query()
            ->where('is_admin', true)
            ->where('is_active', true)
            ->count();
    }

    public function recordLogout(User $user, Request $request): void
    {
        $this->audit->record('auth.logout', $user, [
            'summary' => $user->email,
            'email' => $user->email,
        ], $request, $user->id);
    }

    private function presentSession(object $row, ?string $currentSessionId, ?User $user): object
    {
        $ua = $row->user_agent !== null ? (string) $row->user_agent : null;
        $lastActivity = (int) $row->last_activity;

        return (object) [
            'id' => (string) $row->id,
            'user_id' => $row->user_id !== null ? (int) $row->user_id : null,
            'user' => $user,
            'ip_address' => $row->ip_address,
            'location' => ClientContext::locationLabel($row->ip_address !== null ? (string) $row->ip_address : null),
            'device_type' => ClientContext::deviceType($ua),
            'application' => ClientContext::application($ua),
            'user_agent' => $ua,
            'last_activity' => $lastActivity,
            'last_activity_at' => Carbon::createFromTimestamp($lastActivity, config('app.timezone')),
            'is_current' => $currentSessionId !== null && hash_equals((string) $row->id, (string) $currentSessionId),
        ];
    }
}
