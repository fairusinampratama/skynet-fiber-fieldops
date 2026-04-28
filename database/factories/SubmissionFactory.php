<?php

namespace Database\Factories;

use App\Enums\OdpCoreColor;
use App\Enums\SubmissionStatus;
use App\Models\Area;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Submission>
 */
class SubmissionFactory extends Factory
{
    public function definition(): array
    {
        $project = Project::factory()->create();

        return [
            'project_id' => $project->id,
            'technician_id' => User::factory()->technician(),
            'team_id' => Team::factory()->for($project),
            'area_id' => Area::factory()->for($project),
            'work_date' => now()->toDateString(),
            'odc_box_id' => 'ODC-' . fake()->unique()->bothify('####'),
            'odc_photo_path' => 'submissions/odc/example.png',
            'odc_latitude' => '-7.96662000',
            'odc_longitude' => '112.63263200',
            'odp_box_id' => 'ODP-' . fake()->unique()->bothify('####'),
            'odp_photo_path' => 'submissions/odp/example.png',
            'odp_latitude' => '-7.96700000',
            'odp_longitude' => '112.63300000',
            'odp_core_color' => OdpCoreColor::Biru,
            'notes' => fake()->sentence(),
            'status' => SubmissionStatus::Draft,
        ];
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
