<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\BigDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BigDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_medium_profile_documents_expected_big_data_targets(): void
    {
        $profile = BigDataSeeder::profile('medium');

        $this->assertSame(10, $profile['projects']);
        $this->assertSame(100, $profile['projects'] * $profile['areas_per_project']);
        $this->assertSame(100, $profile['projects'] * $profile['olts_per_project']);
        $this->assertSame(800, $profile['projects'] * $profile['olts_per_project'] * $profile['pons_per_olt']);
        $this->assertSame(2000, $profile['projects'] * $profile['odcs_per_project']);
        $this->assertSame(10000, $profile['projects'] * $profile['odps_per_project']);
        $this->assertSame(16000, $profile['projects'] * $profile['odcs_per_project'] * $profile['ports_per_asset']);
        $this->assertSame(80000, $profile['projects'] * $profile['odps_per_project'] * $profile['ports_per_asset']);
        $this->assertSame(2000, $profile['projects'] * $profile['submissions_per_project']);
    }

    public function test_big_data_command_creates_repeatable_hierarchy_and_submissions(): void
    {
        $this->artisan('fieldops:seed-big-data', [
            '--profile' => 'tiny',
            '--reset' => true,
            '--with-submissions' => true,
            '--chunk' => 250,
        ])->assertSuccessful();

        $seeder = app(BigDataSeeder::class);
        $counts = $seeder->counts();
        $profile = BigDataSeeder::profile('tiny');

        $this->assertSame($profile['projects'], $counts['projects']);
        $this->assertSame($profile['projects'] * $profile['areas_per_project'], $counts['areas']);
        $this->assertSame($profile['projects'] * $profile['olts_per_project'], $counts['olts']);
        $this->assertSame($profile['projects'] * $profile['olts_per_project'] * $profile['pons_per_olt'], $counts['pons']);
        $this->assertSame($profile['projects'] * $profile['odcs_per_project'], $counts['odcs']);
        $this->assertSame($profile['projects'] * $profile['odps_per_project'], $counts['odps']);
        $this->assertSame($counts['odcs'] * $profile['ports_per_asset'], $counts['odc_ports']);
        $this->assertSame($counts['odps'] * $profile['ports_per_asset'], $counts['odp_ports']);
        $this->assertSame($profile['projects'] * $profile['submissions_per_project'], $counts['submissions']);
        $this->assertSame($counts['submissions'] * $profile['ports_per_asset'] * 2, $counts['submission_ports']);
        $this->assertGreaterThan(0, $counts['unmapped_odcs']);
        $this->assertGreaterThan(0, $counts['unlinked_odps']);

        $this->assertSame(0, DB::table('odc_assets')
            ->leftJoin('olt_pon_ports', 'olt_pon_ports.id', '=', 'odc_assets.olt_pon_port_id')
            ->where('odc_assets.box_id', 'like', 'BIG-ODC-P%')
            ->whereNotNull('odc_assets.olt_pon_port_id')
            ->whereNull('olt_pon_ports.id')
            ->count());

        $this->assertSame(0, DB::table('odp_assets')
            ->leftJoin('odc_assets', 'odc_assets.id', '=', 'odp_assets.odc_asset_id')
            ->where('odp_assets.box_id', 'like', 'BIG-ODP-P%')
            ->whereNotNull('odp_assets.odc_asset_id')
            ->whereNull('odc_assets.id')
            ->count());

        $this->artisan('fieldops:seed-big-data', [
            '--profile' => 'tiny',
            '--with-submissions' => true,
            '--chunk' => 250,
        ])->assertSuccessful();

        $this->assertSame($counts, $seeder->counts());
    }

    public function test_big_data_reset_removes_only_generated_data(): void
    {
        $user = User::factory()->create(['email' => 'normal-user@example.com']);

        $this->artisan('fieldops:seed-big-data', ['--profile' => 'tiny'])->assertSuccessful();
        $this->assertGreaterThan(0, app(BigDataSeeder::class)->counts()['projects']);

        $this->artisan('fieldops:seed-big-data', ['--profile' => 'tiny', '--reset' => true])->assertSuccessful();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => 'normal-user@example.com']);
        $this->assertSame(BigDataSeeder::profile('tiny')['projects'], app(BigDataSeeder::class)->counts()['projects']);
    }

    public function test_generated_ports_produce_dashboard_utilization_bands_and_alerts(): void
    {
        $this->artisan('fieldops:seed-big-data', ['--profile' => 'tiny', '--reset' => true])->assertSuccessful();

        $service = app(\App\Services\DashboardMetricsService::class);
        $summary = collect($service->utilizationSummary())->pluck('count', 'category');
        $alerts = collect($service->alerts())->pluck('type');

        $this->assertGreaterThan(0, $summary['Aman']);
        $this->assertGreaterThan(0, $summary['Hampir Penuh']);
        $this->assertGreaterThan(0, $summary['Penuh']);
        $this->assertTrue($alerts->contains('ODC Belum Mapping'));
        $this->assertTrue($alerts->contains('ODP Belum Mapping'));
        $this->assertTrue($alerts->contains('ODP Kritis'));
    }
}
