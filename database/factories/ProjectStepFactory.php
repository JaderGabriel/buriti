<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectStep>
 */
class ProjectStepFactory extends Factory
{
    protected $model = ProjectStep::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'title' => fake()->sentence(3),
            'notes' => fake()->optional()->sentence(),
            'is_completed' => false,
            'completed_at' => null,
            'sort_order' => 0,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'is_completed' => true,
            'completed_at' => now(),
        ]);
    }
}
