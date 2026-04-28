<?php

namespace Database\Factories;

use App\Enums\AssetType;
use App\Enums\PortStatus;
use App\Models\Submission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\SubmissionPort>
 */
class SubmissionPortFactory extends Factory
{
    public function definition(): array
    {
        return [
            'submission_id' => Submission::factory(),
            'asset_type' => AssetType::Odc,
            'port_number' => fake()->numberBetween(1, 8),
            'status' => PortStatus::Available,
        ];
    }
}
