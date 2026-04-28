<?php

namespace Tests;

use App\Enums\AssetType;
use App\Enums\PortStatus;
use App\Models\Area;
use App\Models\Project;
use App\Models\Submission;
use App\Models\SubmissionPort;
use App\Models\Team;
use App\Models\User;

trait CreatesFieldopsData
{
    protected function fieldopsBundle(?User $technician = null): array
    {
        $technician ??= User::factory()->technician()->create();
        $project = Project::factory()->create();
        $team = Team::factory()->for($project)->for($technician, 'leader')->create();
        $area = Area::factory()->for($project)->create();

        return compact('technician', 'project', 'team', 'area');
    }

    protected function submissionWithPorts(array $attributes = []): Submission
    {
        $bundle = $this->fieldopsBundle($attributes['technician'] ?? null);

        unset($attributes['technician']);

        $submission = Submission::factory()
            ->for($bundle['project'])
            ->for($bundle['technician'], 'technician')
            ->for($bundle['team'])
            ->for($bundle['area'])
            ->create($attributes);

        foreach ([AssetType::Odc, AssetType::Odp] as $assetType) {
            foreach (range(1, 8) as $portNumber) {
                SubmissionPort::factory()->for($submission)->create([
                    'asset_type' => $assetType,
                    'port_number' => $portNumber,
                    'status' => $portNumber % 2 === 0 ? PortStatus::Used : PortStatus::Available,
                ]);
            }
        }

        return $submission;
    }
}
