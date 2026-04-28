<?php

namespace Database\Factories;

use App\Models\OltAsset;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\OltPonPort>
 */
class OltPonPortFactory extends Factory
{
    public function definition(): array
    {
        $ponNumber = fake()->numberBetween(1, 8);

        return [
            'olt_asset_id' => OltAsset::factory(),
            'pon_number' => $ponNumber,
            'label' => 'PON ' . $ponNumber,
            'capacity' => 128,
            'status' => 'active',
        ];
    }
}
