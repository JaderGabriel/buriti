<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->is_admin === true;
    }

    public function create(User $actor): bool
    {
        return $actor->is_admin === true;
    }

    public function update(User $actor, User $user): bool
    {
        return $actor->is_admin === true;
    }
}
