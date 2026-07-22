<?php

namespace Database\Factories;

use App\Enums\CrmActivityType;
use App\Models\Contact;
use App\Models\CrmActivity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CrmActivity>
 */
class CrmActivityFactory extends Factory
{
    protected $model = CrmActivity::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'contact_id' => Contact::factory(),
            'opportunity_id' => null,
            'task_id' => null,
            'user_id' => null,
            'type' => CrmActivityType::Note,
            'subject' => fake()->sentence(5),
            'body' => fake()->optional()->paragraph(),
            'happened_at' => now(),
        ];
    }
}
