<?php

namespace Database\Factories;

use App\Enums\JirauTemplate;
use App\Models\Jirau;
use App\Models\Project;
use App\Models\User;
use App\Services\Jirau\JirauBoardService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Jirau>
 */
class JirauFactory extends Factory
{
    protected $model = Jirau::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'company_id' => null,
            'contract_name' => null,
            'name' => 'Jirau '.fake()->unique()->word(),
            'template' => JirauTemplate::Development,
            'is_active' => true,
            'sort_order' => 0,
            'created_by' => User::factory(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Jirau $jirau): void {
            if ($jirau->company_id === null) {
                $companyId = $jirau->project()->value('company_id');
                if ($companyId) {
                    $jirau->forceFill(['company_id' => $companyId])->saveQuietly();
                }
            }

            if ($jirau->columns()->exists()) {
                return;
            }

            app(JirauBoardService::class)->provisionDefaults($jirau);
        });
    }
}
