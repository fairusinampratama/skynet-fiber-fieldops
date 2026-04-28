<?php

namespace Database\Factories;

use App\Enums\OdpCoreColor;
use App\Models\Area;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\OdpAsset>
 */
class OdpAssetFactory extends Factory
{
    public function definition(): array
    {
        $project = Project::factory()->create();
        $area = Area::factory()->for($project)->create();
        return [
            'project_id' => $project->id,
            'area_id' => $area->id,
            'odc_asset_id' => null,
            'box_id' => 'ODP-' . fake()->unique()->bothify('####'),
            'photo_path' => 'assets/odp/example.png',
            'latitude' => '-7.96700000',
            'longitude' => '112.63300000',
            'core_color' => OdpCoreColor::Biru,
            'status' => 'active',
        ];
    }
}
