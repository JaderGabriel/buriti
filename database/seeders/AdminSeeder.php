<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class AdminSeeder extends Seeder
{
    /**
     * Cria ou atualiza o usuário admin master.
     */
    public function run(): void
    {
        $password = env('ADMIN_PASSWORD');

        if (! is_string($password) || $password === '') {
            if (app()->environment('production')) {
                throw new RuntimeException(
                    'ADMIN_PASSWORD é obrigatório para o AdminSeeder em produção.'
                );
            }

            // Apenas ambientes locais/dev — nunca usar como senha real em produção.
            $password = 'buriti-local-only';
        }

        $user = User::query()->firstOrNew([
            'email' => env('ADMIN_EMAIL', 'jadergabriel8@gmail.com'),
        ]);

        $user->fill([
            'name' => env('ADMIN_NAME', 'Jader Gabriel'),
            'username' => env('ADMIN_USERNAME', 'jadergabriel'),
            'password' => Hash::make($password),
        ]);
        $user->forceFill([
            'is_admin' => true,
            'is_active' => true,
        ])->save();
    }
}
