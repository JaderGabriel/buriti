<?php

namespace App\Policies;

use App\Models\User;

class MailboxPolicy
{
    public function manage(User $user): bool
    {
        return $user->canManageMail();
    }
}
