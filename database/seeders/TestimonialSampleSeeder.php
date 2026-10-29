<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\TestimonialService;
use Illuminate\Database\Seeder;

class TestimonialSampleSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('is_admin', true)->orderBy('id')->first()
            ?? User::factory()->create(['name' => 'Admin amostras']);

        $created = app(TestimonialService::class)->plantSamples($admin);

        $this->command?->info('Depoimentos de teste (is_sample): '.$created->count());
    }
}
