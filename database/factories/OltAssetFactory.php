<?php

namespace Database\Factories;

use App\Models\Area;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\OltAsset>
 */
class OltAssetFactory extends Factory
{
    public function definition(): array
    {
        $project = Project::factory()->create();

        return [
            'project_id' => $project->id,
            'area_id' => Area::factory()->for($project),
            'name' => 'OLT ' . fake()->unique()->bothify('??-##'),
            'code' => 'OLT-' . fake()->unique()->bothify('####'),
            'location' => fake()->streetAddress(),
            'latitude' => '-7.96500000',
            'longitude' => '112.63000000',
            'status' => 'active',
            'notes' => fake()->sentence(),
        ];
    }
}
