<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Area;
use App\Models\OltAsset;
use App\Models\OltPonPort;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@skynet.local'],
            ['name' => 'Skynet Admin', 'password' => Hash::make('password'), 'role' => UserRole::Admin, 'is_active' => true],
        );

        $tech = User::query()->firstOrCreate(
            ['email' => 'tech@skynet.local'],
            ['name' => 'Field Technician', 'password' => Hash::make('password'), 'role' => UserRole::Technician, 'is_active' => true],
        );

        $project = Project::query()->firstOrCreate(
            ['code' => 'MLG-DEPLOY'],
            ['name' => 'Malang Deployment', 'description' => 'Initial deployment sample project.', 'status' => 'active'],
        );

        Team::query()->firstOrCreate(['project_id' => $project->id, 'name' => 'Team Alpha'], ['leader_id' => $tech->id]);
        $area = Area::query()->firstOrCreate(['project_id' => $project->id, 'code' => 'MLG-01'], ['name' => 'Malang Area 01']);

        $olt = OltAsset::query()->firstOrCreate(
            ['project_id' => $project->id, 'code' => 'OLT-MLG-01'],
            ['area_id' => $area->id, 'name' => 'OLT Malang 01', 'location' => 'Malang POP', 'latitude' => '-7.96500000', 'longitude' => '112.63000000', 'status' => 'active'],
        );

        foreach (range(1, 8) as $ponNumber) {
            OltPonPort::query()->firstOrCreate(
                ['olt_asset_id' => $olt->id, 'pon_number' => $ponNumber],
                ['label' => 'PON ' . $ponNumber, 'capacity' => 128, 'status' => 'active'],
            );
        }

        $admin->markEmailAsVerified();
        $tech->markEmailAsVerified();
    }
}
