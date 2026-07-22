<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Cria ou atualiza o usuário admin master.
     */
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => env('ADMIN_EMAIL', 'jadergabriel8@gmail.com')],
            [
                'name' => env('ADMIN_NAME', 'Jader Gabriel'),
                'username' => env('ADMIN_USERNAME', 'jadergabriel'),
                'password' => Hash::make(env('ADMIN_PASSWORD', 'buriti2026')),
                'is_admin' => true,
            ]
        );
    }
}
