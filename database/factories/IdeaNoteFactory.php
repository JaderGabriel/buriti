<?php

namespace Database\Factories;

use App\Enums\IdeaNoteColor;
use App\Models\IdeaNote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IdeaNote>
 */
class IdeaNoteFactory extends Factory
{
    protected $model = IdeaNote::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'title' => fake()->optional()->sentence(3),
            'body' => fake()->optional()->paragraph(),
            'color' => fake()->randomElement(IdeaNoteColor::values()),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (IdeaNote $note) {
            if (! array_key_exists('user_id', $note->getAttributes())) {
                $note->setAttribute('user_id', User::factory()->create()->id);
            }
            if (! array_key_exists('sort_order', $note->getAttributes())) {
                $note->setAttribute('sort_order', 0);
            }
        });
    }
}
