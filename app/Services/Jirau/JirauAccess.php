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

    public function canWrite(?User $user, Jirau $jirau): bool
    {
        return $this->canView($user, $jirau) && $jirau->is_active;
    }

    public function canTrackTime(?User $user, Jirau $jirau): bool
    {
        return $this->canView($user, $jirau)
            && $jirau->is_active
            && (bool) $user?->isAssignableOnJirau();
    }

    public function canSeeTimeHistory(?User $user): bool
    {
        return $this->canManage($user);
    }

    public function assertWritable(?User $user, Jirau $jirau): void
    {
        $this->assertView($user, $jirau);
        abort_unless($jirau->is_active, 403, 'Este quadro está inativo. Reative-o para alterar cartões.');
    }

    public function assertTrackTime(?User $user, Jirau $jirau): void
    {
        $this->assertView($user, $jirau);
        abort_unless($user?->isAssignableOnJirau(), 403, 'Quem demanda não aponta horas neste quadro.');
        abort_unless($jirau->is_active, 403, 'Este quadro está inativo. Reative-o para apontar horas.');
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
