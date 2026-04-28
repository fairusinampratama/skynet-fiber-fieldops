<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Team>
 */
class TeamFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'leader_id' => User::factory()->technician(),
            'name' => 'Team ' . fake()->unique()->bothify('??-###'),
            'notes' => fake()->sentence(),
        ];
    }
}
