<?php

namespace Database\Seeders;

use App\Models\Project;
use Illuminate\Database\Seeder;

class PortfolioSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('buriti.portfolio', []) as $item) {
            $project = Project::query()->firstOrNew(['name' => $item['name']]);

            $attributes = [
                'information' => $item['information'] ?? null,
                'stack' => $item['stack'] ?? null,
                'category' => $item['category'] ?? null,
                'website_url' => $item['website_url'] ?? null,
                'github_url' => $item['github_url'] ?? null,
                'status' => $item['status'] ?? 'active',
                'is_public' => (bool) ($item['is_public'] ?? true),
                'repo_is_private' => (bool) ($item['repo_is_private'] ?? false),
                'sort_order' => (int) ($item['sort_order'] ?? 0),
            ];

            if (array_key_exists('featured_on_home', $item)) {
                $attributes['featured_on_home'] = (bool) $item['featured_on_home'];
            }
            if (array_key_exists('featured_sort', $item)) {
                $attributes['featured_sort'] = (int) $item['featured_sort'];
            }
            if (array_key_exists('logo_path', $item)) {
                $current = $project->logo_path;
                if (! $current || str_starts_with((string) $current, 'images/')) {
                    $attributes['logo_path'] = $item['logo_path'];
                }
            }

            $project->fill($attributes)->save();
        }

        $this->command?->info('Portfólio sincronizado: '.count(config('buriti.portfolio', [])).' projetos.');
    }
}
