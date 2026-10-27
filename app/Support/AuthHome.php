<?php

namespace App\Support;

use App\Models\User;

class AuthHome
{
    public static function routeName(?User $user): string
    {
        return $user?->is_admin ? 'admin.dashboard' : 'jirau.home';
    }

    public static function url(?User $user): string
    {
        return route(self::routeName($user));
    }
}
