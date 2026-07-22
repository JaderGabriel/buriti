<?php

namespace Database\Factories;

use App\Enums\ContactSource;
use App\Enums\ContactStatus;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    protected $model = Contact::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->optional()->numerify('+55###########'),
            'company' => fake()->optional()->company(),
            'role' => fake()->optional()->jobTitle(),
            'preferred_channel' => fake()->randomElement(['email', 'phone', 'whatsapp']),
            'status' => ContactStatus::Lead,
            'source' => ContactSource::Manual,
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['status' => ContactStatus::Active]);
    }

    public function fromWebsite(): static
    {
        return $this->state(fn () => ['source' => ContactSource::Website]);
    }
}
