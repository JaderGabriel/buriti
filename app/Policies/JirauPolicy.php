<?php

namespace App\Policies;

use App\Models\Jirau;
use App\Models\User;
use App\Services\Jirau\JirauAccess;

class JirauPolicy
{
    public function __construct(private JirauAccess $access) {}

    public function viewAny(User $user): bool
    {
        return $user->canEnterJirau();
    }

    public function view(User $user, Jirau $jirau): bool
    {
        return $this->access->canView($user, $jirau);
    }

    public function create(User $user): bool
    {
        return $this->access->canManage($user);
    }

    public function update(User $user, Jirau $jirau): bool
    {
        return $this->access->canManage($user) && $this->access->canView($user, $jirau);
    }

    public function delete(User $user, Jirau $jirau): bool
    {
        return $this->update($user, $jirau);
    }

    public function manage(User $user): bool
    {
        return $this->access->canManage($user);
    }

    public function write(User $user, Jirau $jirau): bool
    {
        return $this->access->canWrite($user, $jirau);
    }

    public function trackTime(User $user, Jirau $jirau): bool
    {
        return $this->access->canTrackTime($user, $jirau);
    }
}
