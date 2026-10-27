<?php

namespace App\Services\Jirau;

use App\Models\Jirau;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class JirauAccess
{
    public function canView(?User $user, Jirau $jirau): bool
    {
        if (! $user || ! $user->is_active) {
            return false;
        }

        if ($user->is_admin || $user->jirau_role !== null) {
            return true;
        }

        return $jirau->members()->whereKey($user->id)->exists();
    }

    public function canManage(?User $user): bool
    {
        return (bool) ($user?->is_admin && $user->is_active);
    }

    public function assertView(?User $user, Jirau $jirau): void
    {
        abort_unless($this->canView($user, $jirau), 403, 'Sem acesso a este Jirau.');
    }

    public function assertManage(?User $user): void
    {
        abort_unless($this->canManage($user), 403, 'Só o admin do quadro pode fazer isso.');
    }

    /** @return Builder<Jirau> */
    public function visibleQuery(User $user): Builder
    {
        $query = Jirau::query();

        if (! $user->is_admin && $user->jirau_role === null) {
            $query->whereHas('members', fn ($q) => $q->whereKey($user->id));
        }

        return $query;
    }
}
