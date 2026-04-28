<?php

namespace Database\Factories;

use App\Models\Area;
use App\Models\OltPonPort;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\OdcAsset>
 */
class OdcAssetFactory extends Factory
{
    public function definition(): array
    {
        $project = Project::factory()->create();

        return [
            'project_id' => $project->id,
            'area_id' => Area::factory()->for($project),
            'olt_pon_port_id' => null,
            'box_id' => 'ODC-' . fake()->unique()->bothify('####'),
            'photo_path' => 'assets/odc/example.png',
            'latitude' => '-7.96662000',
            'longitude' => '112.63263200',
            'status' => 'unmapped',
        ];
    }

    public function mapped(?OltPonPort $ponPort = null): static
    {
        return $this->state(function () use ($ponPort): array {
            $ponPort ??= OltPonPort::factory()->create();

            return [
                'project_id' => $ponPort->oltAsset->project_id,
                'area_id' => $ponPort->oltAsset->area_id,
                'olt_pon_port_id' => $ponPort->id,
                'status' => 'active',
            ];
        });
    }
}
