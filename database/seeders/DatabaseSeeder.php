<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Area;
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
        Area::query()->firstOrCreate(['project_id' => $project->id, 'code' => 'MLG-01'], ['name' => 'Malang Area 01']);

        $admin->markEmailAsVerified();
        $tech->markEmailAsVerified();
    }
}
