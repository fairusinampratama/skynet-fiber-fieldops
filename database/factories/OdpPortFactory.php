<?php

namespace Database\Factories;

use App\Enums\PortStatus;
use App\Models\OdpAsset;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\OdpPort>
 */
class OdpPortFactory extends Factory
{
    public function definition(): array
    {
        return [
            'odp_asset_id' => OdpAsset::factory(),
            'port_number' => fake()->numberBetween(1, 8),
            'status' => PortStatus::Available,
        ];
    }
}
