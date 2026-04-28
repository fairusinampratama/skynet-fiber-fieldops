<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\Project>
 */
class ProjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->city() . ' Deployment',
            'code' => strtoupper(Str::random(8)),
            'description' => fake()->sentence(),
            'status' => 'active',
            'start_date' => now()->subWeek()->toDateString(),
            'target_date' => now()->addMonth()->toDateString(),
        ];
    }
}
