<?php

namespace Tests\Feature;

use App\Enums\OdpCoreColor;
use App\Enums\SubmissionStatus;
use App\Enums\UserRole;
use App\Models\Area;
use App\Models\OltAsset;
use App\Models\OltPonPort;
use App\Models\Project;
use App\Models\Submission;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesFieldopsData;
use Tests\TestCase;

class SeederAndModelTest extends TestCase
{
    use CreatesFieldopsData;
    use RefreshDatabase;

    public function test_database_seeder_creates_login_users_and_sample_work_structure(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseHas(User::class, ['email' => 'admin@skynet.local', 'role' => UserRole::Admin->value]);
        $this->assertDatabaseHas(User::class, ['email' => 'tech@skynet.local', 'role' => UserRole::Technician->value]);
        $this->assertDatabaseHas(Project::class, ['code' => 'MLG-DEPLOY']);
        $this->assertDatabaseHas(Team::class, ['name' => 'Team Alpha']);
        $this->assertDatabaseHas(Area::class, ['code' => 'MLG-01']);
        $this->assertDatabaseHas(OltAsset::class, ['code' => 'OLT-MLG-01']);
        $this->assertDatabaseHas(OltPonPort::class, ['pon_number' => 1, 'capacity' => 128]);
    }

    public function test_submission_enum_casts_are_applied(): void
    {
        $submission = $this->submissionWithPorts([
            'odp_core_color' => OdpCoreColor::Hijau,
            'status' => SubmissionStatus::Submitted,
        ])->refresh();

        $this->assertSame(OdpCoreColor::Hijau, $submission->odp_core_color);
        $this->assertSame(SubmissionStatus::Submitted, $submission->status);
    }
}
