<?php

namespace Database\Factories;

use App\Enums\JirauCardPriority;
use App\Enums\JirauCardType;
use App\Models\Jirau;
use App\Models\JirauCard;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JirauCard>
 */
class JirauCardFactory extends Factory
{
    protected $model = JirauCard::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'jirau_id' => Jirau::factory(),
            'column_id' => fn (array $attributes) => Jirau::query()
                ->find($attributes['jirau_id'])
                ?->columns()
                ->where('slug', 'backlog')
                ->value('id'),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'type' => JirauCardType::Task,
            'priority' => JirauCardPriority::Medium,
            'sort_order' => 10,
        ];
    }

    public function demand(): static
    {
        return $this->state(fn () => [
            'type' => JirauCardType::Demand,
        ]);
    }
}
