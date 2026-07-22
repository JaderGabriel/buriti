<?php

namespace Database\Factories;

use App\Enums\ProjectStatus;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'information' => fake()->paragraph(),
            'stack' => ['Laravel', 'PHP'],
            'category' => 'Desenvolvimento',
            'website_url' => fake()->url(),
            'github_url' => 'https://github.com/'.fake()->userName().'/'.fake()->slug(2),
            'logo_path' => null,
            'contract_path' => null,
            'status' => ProjectStatus::Active,
            'is_public' => false,
            'sort_order' => 0,
        ];
    }

    public function public(): static
    {
        return $this->state(fn () => ['is_public' => true]);
    }
}
