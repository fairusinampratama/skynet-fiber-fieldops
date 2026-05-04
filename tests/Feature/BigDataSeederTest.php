<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\BigDataSeeder;
use App\Services\DashboardMetricsService;
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

    public function test_demo_profile_creates_realistic_dashboard_story(): void
    {
        $this->artisan('fieldops:seed-big-data', [
            '--profile' => 'demo',
            '--scenario' => 'balanced',
            '--seed' => 2026,
            '--reset' => true,
            '--with-submissions' => true,
            '--chunk' => 500,
        ])->assertSuccessful();

        $seeder = app(BigDataSeeder::class);
        $counts = $seeder->counts();
        $profile = BigDataSeeder::profile('demo');

        $this->assertSame($profile['projects'], $counts['projects']);
        $this->assertSame($profile['projects'] * $profile['areas_per_project'], $counts['areas']);
        $this->assertSame($profile['projects'] * $profile['olts_per_project'], $counts['olts']);
        $this->assertSame($profile['projects'] * $profile['odcs_per_project'], $counts['odcs']);
        $this->assertSame($profile['projects'] * $profile['odps_per_project'], $counts['odps']);
        $this->assertSame($profile['projects'] * $profile['submissions_per_project'], $counts['submissions']);
        $this->assertGreaterThan(0, $counts['unmapped_odcs']);
        $this->assertGreaterThan(0, $counts['unlinked_odps']);

        $this->assertDatabaseHas('projects', ['name' => 'Malang Timur FTTH']);
        $this->assertDatabaseHas('projects', ['name' => 'Surabaya Barat Expansion']);
        $this->assertDatabaseHas('users', ['name' => 'Andi Pratama 01']);

        $summary = collect(app(DashboardMetricsService::class)->utilizationSummary())->pluck('count', 'category');
        $alerts = collect(app(DashboardMetricsService::class)->alerts())->pluck('type');

        $this->assertGreaterThan(0, $summary['Aman']);
        $this->assertGreaterThan(0, $summary['Hampir Penuh']);
        $this->assertGreaterThan(0, $summary['Penuh']);
        $this->assertTrue($alerts->contains('ODC Belum Mapping'));
        $this->assertTrue($alerts->contains('ODP Belum Mapping'));
        $this->assertTrue($alerts->contains('ODP Kritis'));

        foreach (['assigned', 'submitted', 'approved', 'correction_needed', 'resubmitted', 'rejected'] as $status) {
            $this->assertGreaterThan(
                0,
                DB::table('submissions')->where('status', $status)->where('box_id', 'like', 'BIG-%')->count(),
                "Expected demo submissions with status [{$status}].",
            );
        }

        $this->artisan('fieldops:seed-big-data', [
            '--profile' => 'demo',
            '--scenario' => 'balanced',
            '--seed' => 2026,
            '--with-submissions' => true,
            '--chunk' => 500,
        ])->assertSuccessful();

        $this->assertSame($counts, $seeder->counts());
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
        $this->assertSame($counts['submissions'] * $profile['ports_per_asset'], $counts['submission_ports']);
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

    public function test_generated_coordinates_are_scattered_by_area_and_follow_hierarchy(): void
    {
        $this->artisan('fieldops:seed-big-data', [
            '--profile' => 'tiny',
            '--seed' => 111,
            '--reset' => true,
            '--chunk' => 250,
        ])->assertSuccessful();

        $areaCenters = DB::table('areas')
            ->join('odp_assets', 'odp_assets.area_id', '=', 'areas.id')
            ->where('areas.code', 'like', 'BIG-A-P001-%')
            ->select('areas.code')
            ->selectRaw('avg(odp_assets.latitude) as latitude, avg(odp_assets.longitude) as longitude')
            ->groupBy('areas.code')
            ->orderBy('areas.code')
            ->get();

        $this->assertCount(2, $areaCenters);
        $this->assertGreaterThan(
            0.015,
            $this->coordinateDistance($areaCenters[0], $areaCenters[1]),
            'Expected generated areas to be visually separated on the map.',
        );

        $mappedOdc = DB::table('odc_assets')
            ->join('olt_pon_ports', 'olt_pon_ports.id', '=', 'odc_assets.olt_pon_port_id')
            ->join('olt_assets', 'olt_assets.id', '=', 'olt_pon_ports.olt_asset_id')
            ->where('odc_assets.box_id', 'like', 'BIG-ODC-P%')
            ->whereNotNull('odc_assets.olt_pon_port_id')
            ->select([
                'odc_assets.latitude as odc_latitude',
                'odc_assets.longitude as odc_longitude',
                'olt_assets.latitude as olt_latitude',
                'olt_assets.longitude as olt_longitude',
            ])
            ->first();

        $this->assertNotNull($mappedOdc);
        $this->assertLessThan(
            0.010,
            $this->coordinateDistance(
                (object) ['latitude' => $mappedOdc->odc_latitude, 'longitude' => $mappedOdc->odc_longitude],
                (object) ['latitude' => $mappedOdc->olt_latitude, 'longitude' => $mappedOdc->olt_longitude],
            ),
            'Expected mapped ODCs to stay near their OLT/area cluster.',
        );

        $linkedOdp = DB::table('odp_assets')
            ->join('odc_assets', 'odc_assets.id', '=', 'odp_assets.odc_asset_id')
            ->where('odp_assets.box_id', 'like', 'BIG-ODP-P%')
            ->whereNotNull('odp_assets.odc_asset_id')
            ->select([
                'odp_assets.latitude as odp_latitude',
                'odp_assets.longitude as odp_longitude',
                'odc_assets.latitude as odc_latitude',
                'odc_assets.longitude as odc_longitude',
            ])
            ->first();

        $this->assertNotNull($linkedOdp);
        $this->assertLessThan(
            0.002,
            $this->coordinateDistance(
                (object) ['latitude' => $linkedOdp->odp_latitude, 'longitude' => $linkedOdp->odp_longitude],
                (object) ['latitude' => $linkedOdp->odc_latitude, 'longitude' => $linkedOdp->odc_longitude],
            ),
            'Expected linked ODPs to stay near their parent ODC.',
        );
    }

    public function test_generated_coordinates_are_seeded_repeatably_with_jitter(): void
    {
        $this->artisan('fieldops:seed-big-data', [
            '--profile' => 'tiny',
            '--seed' => 111,
            '--reset' => true,
            '--chunk' => 250,
        ])->assertSuccessful();

        $firstCoordinate = $this->coordinateFor('BIG-ODP-P001-00001');

        $this->artisan('fieldops:seed-big-data', [
            '--profile' => 'tiny',
            '--seed' => 111,
            '--reset' => true,
            '--chunk' => 250,
        ])->assertSuccessful();

        $this->assertSame($firstCoordinate, $this->coordinateFor('BIG-ODP-P001-00001'));

        $this->artisan('fieldops:seed-big-data', [
            '--profile' => 'tiny',
            '--seed' => 222,
            '--reset' => true,
            '--chunk' => 250,
        ])->assertSuccessful();

        $this->assertNotSame($firstCoordinate, $this->coordinateFor('BIG-ODP-P001-00001'));
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

        $service = app(DashboardMetricsService::class);
        $summary = collect($service->utilizationSummary())->pluck('count', 'category');
        $alerts = collect($service->alerts())->pluck('type');

        $this->assertGreaterThan(0, $summary['Aman']);
        $this->assertGreaterThan(0, $summary['Hampir Penuh']);
        $this->assertGreaterThan(0, $summary['Penuh']);
        $this->assertTrue($alerts->contains('ODC Belum Mapping'));
        $this->assertTrue($alerts->contains('ODP Belum Mapping'));
        $this->assertTrue($alerts->contains('ODP Kritis'));
    }

    private function coordinateDistance(object $a, object $b): float
    {
        return sqrt(
            (((float) $a->latitude - (float) $b->latitude) ** 2)
            + (((float) $a->longitude - (float) $b->longitude) ** 2),
        );
    }

    /** @return array{latitude: string, longitude: string} */
    private function coordinateFor(string $boxId): array
    {
        $row = DB::table('odp_assets')->where('box_id', $boxId)->first(['latitude', 'longitude']);

        $this->assertNotNull($row);

        return ['latitude' => (string) $row->latitude, 'longitude' => (string) $row->longitude];
    }
}
