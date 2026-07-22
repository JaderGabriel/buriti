<?php

namespace Database\Factories;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'status' => TaskStatus::Todo,
            'priority' => TaskPriority::Medium,
            'due_at' => fake()->optional()->dateTimeBetween('now', '+2 weeks'),
            'google_event_id' => null,
            'meet_url' => null,
            'want_meet' => true,
        ];
    }

    public function doing(): static
    {
        return $this->state(fn () => ['status' => TaskStatus::Doing]);
    }

    public function done(): static
    {
        return $this->state(fn () => ['status' => TaskStatus::Done]);
    }
}
