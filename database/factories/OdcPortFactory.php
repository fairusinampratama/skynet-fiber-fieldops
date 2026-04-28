<?php

namespace Database\Factories;

use App\Enums\PortStatus;
use App\Models\OdcAsset;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\OdcPort>
 */
class OdcPortFactory extends Factory
{
    public function definition(): array
    {
        return [
            'odc_asset_id' => OdcAsset::factory(),
            'port_number' => fake()->numberBetween(1, 8),
            'status' => PortStatus::Available,
        ];
    }
}
