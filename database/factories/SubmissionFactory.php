<?php

namespace Database\Factories;

use App\Enums\AssetType;
use App\Enums\OdpCoreColor;
use App\Enums\SubmissionStatus;
use App\Models\Area;
use App\Models\Project;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Submission>
 */
class SubmissionFactory extends Factory
{
    public function definition(): array
    {
        $project = Project::factory()->create();

        return [
            'project_id' => $project->id,
            'technician_id' => User::factory()->technician(),
            'area_id' => Area::factory()->for($project),
            'work_date' => now()->toDateString(),
            'asset_type' => AssetType::Odc,
            'box_id' => 'ODC-'.fake()->unique()->bothify('####'),
            'photo_path' => 'submissions/odc/example.png',
            'planned_latitude' => '-7.96660000',
            'planned_longitude' => '112.63260000',
            'latitude' => '-7.96662000',
            'longitude' => '112.63263200',
            'core_color' => null,
            'notes' => fake()->sentence(),
            'status' => SubmissionStatus::Assigned,
            'assigned_at' => now(),
        ];
    }

    public function odc(): static
    {
        return $this->state(fn (): array => [
            'asset_type' => AssetType::Odc,
            'box_id' => 'ODC-'.fake()->unique()->bothify('####'),
            'photo_path' => 'submissions/odc/example.png',
            'core_color' => null,
            'parent_odc_asset_id' => null,
        ]);
    }

    public function odp(): static
    {
        return $this->state(fn (): array => [
            'asset_type' => AssetType::Odp,
            'box_id' => 'ODP-'.fake()->unique()->bothify('####'),
            'photo_path' => 'submissions/odp/example.png',
            'core_color' => OdpCoreColor::Biru,
        ]);
    }

    public function submitted(): static
    {
        return $this->state(fn (): array => [
            'status' => SubmissionStatus::Submitted,
            'submitted_at' => now(),
        ]);
    }

    public function correctionNeeded(): static
    {
        return $this->state(fn (): array => ['status' => SubmissionStatus::CorrectionNeeded]);
    }

}
