<?php

namespace Database\Factories;

use App\Enums\OpportunityStage;
use App\Models\Contact;
use App\Models\Opportunity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Opportunity>
 */
class OpportunityFactory extends Factory
{
    protected $model = Opportunity::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'contact_id' => Contact::factory(),
            'project_id' => null,
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'stage' => OpportunityStage::Lead,
            'value' => fake()->optional()->randomFloat(2, 500, 50000),
            'expected_close_at' => fake()->optional()->dateTimeBetween('now', '+6 months'),
        ];
    }

    public function won(): static
    {
        return $this->state(fn () => ['stage' => OpportunityStage::Won]);
    }
}
