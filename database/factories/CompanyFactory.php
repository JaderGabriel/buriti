<?php

namespace Database\Factories;

use App\Enums\CompanyStatus;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'trade_name' => fake()->optional()->company(),
            'document' => fake()->optional()->numerify('##.###.###/####-##'),
            'email' => fake()->optional()->companyEmail(),
            'phone' => fake()->optional()->numerify('+55###########'),
            'website_url' => fake()->optional()->url(),
            'status' => CompanyStatus::Active,
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
