<?php

namespace Database\Seeders;

use App\Models\Project;
use Illuminate\Database\Seeder;

class PortfolioSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('buriti.portfolio', []) as $item) {
            Project::query()->updateOrCreate(
                ['name' => $item['name']],
                [
                    'information' => $item['information'] ?? null,
                    'stack' => $item['stack'] ?? null,
                    'category' => $item['category'] ?? null,
                    'website_url' => $item['website_url'] ?? null,
                    'github_url' => $item['github_url'] ?? null,
                    'status' => $item['status'] ?? 'active',
                    'is_public' => (bool) ($item['is_public'] ?? true),
                    'repo_is_private' => (bool) ($item['repo_is_private'] ?? false),
                    'sort_order' => (int) ($item['sort_order'] ?? 0),
                ]
            );
        }

        $this->command?->info('Portfólio sincronizado: '.count(config('buriti.portfolio', [])).' projetos.');
    }
}
