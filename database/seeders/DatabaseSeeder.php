<?php

namespace Database\Seeders;

use App\Models\Task;
use App\Services\SettingService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            AdminSeeder::class,
            PortfolioSeeder::class,
        ]);

        app(SettingService::class)->putMany(app(SettingService::class)->defaults());

        $project = \App\Models\Project::query()->where('name', 'Servlitcys')->first();

        if ($project) {
            Task::query()->updateOrCreate(
                ['title' => 'Revisar proposta comercial', 'project_id' => $project->id],
                [
                    'description' => 'Preparar escopo e estimativa para o próximo cliente.',
                    'status' => 'todo',
                    'priority' => 'high',
                    'due_at' => now()->addDays(3),
                ]
            );
        }
    }
}
