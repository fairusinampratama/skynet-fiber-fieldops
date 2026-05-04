<?php

namespace Tests\Feature;

use App\Enums\OdpCoreColor;
use App\Enums\PortStatus;
use App\Models\Area;
use App\Models\OdcAsset;
use App\Models\OdpAsset;
use App\Models\OdpPort;
use App\Models\OltAsset;
use App\Models\OltPonPort;
use App\Models\Project;
use App\Services\AssetMapDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetMapDataServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_payload_contains_assets_metadata_without_map_links(): void
    {
        [$project, $area] = $this->projectAndArea();
        $olt = OltAsset::factory()->for($project)->for($area)->create([
            'code' => 'OLT-MAP',
            'name' => 'OLT Map',
            'latitude' => '-7.96500000',
            'longitude' => '112.63000000',
        ]);
        $pon = OltPonPort::factory()->for($olt, 'oltAsset')->create(['pon_number' => 3]);
        $odc = OdcAsset::factory()->for($project)->for($area)->create([
            'olt_pon_port_id' => $pon->id,
            'box_id' => 'ODC-MAP',
            'latitude' => '-7.96600000',
            'longitude' => '112.63100000',
            'status' => 'active',
        ]);
        $odp = OdpAsset::factory()->for($project)->for($area)->for($odc, 'odcAsset')->create([
            'box_id' => 'ODP-MAP',
            'latitude' => '-7.96700000',
            'longitude' => '112.63200000',
            'core_color' => OdpCoreColor::Biru,
        ]);

        foreach (range(1, 4) as $portNumber) {
            OdpPort::factory()->for($odp, 'asset')->create([
                'port_number' => $portNumber,
                'status' => $portNumber <= 2 ? PortStatus::Used : PortStatus::Available,
            ]);
        }

        $payload = app(AssetMapDataService::class)->payload();

        $this->assertCount(3, $payload['assets']);
        $this->assertSame([], $payload['links']);

        $odpPayload = collect($payload['assets'])->firstWhere('type', 'odp');
        $this->assertSame('ODP-MAP', $odpPayload['label']);
        $this->assertSame('ODC-MAP', $odpPayload['metadata']['odc']);
        $this->assertSame('OLT-MAP', $odpPayload['metadata']['olt']);
        $this->assertSame(3, $odpPayload['metadata']['pon']);
        $this->assertSame(4, $odpPayload['metadata']['capacity']);
        $this->assertSame(2, $odpPayload['metadata']['used']);
        $this->assertSame(50.0, $odpPayload['metadata']['utilization']);
        $this->assertStringEndsWith("/admin/odp-assets/{$odp->id}/edit", $odpPayload['url']);
    }

    public function test_payload_excludes_assets_with_invalid_coordinates(): void
    {
        [$project, $area] = $this->projectAndArea();

        OltAsset::factory()->for($project)->for($area)->create([
            'code' => 'VALID-OLT',
            'latitude' => '-7.96500000',
            'longitude' => '112.63000000',
        ]);
        OdcAsset::factory()->for($project)->for($area)->create([
            'box_id' => 'INVALID-ODC',
            'latitude' => '91.00000000',
            'longitude' => '112.63000000',
        ]);
        OdpAsset::factory()->for($project)->for($area)->create([
            'box_id' => 'ZERO-ODP',
            'latitude' => '0.00000000',
            'longitude' => '0.00000000',
        ]);

        $payload = app(AssetMapDataService::class)->payload();

        $this->assertSame(['VALID-OLT'], collect($payload['assets'])->pluck('label')->all());
        $this->assertSame([], $payload['links']);
    }

    public function test_filters_by_project_area_status_and_mapping_state(): void
    {
        [$project, $area] = $this->projectAndArea();
        [$otherProject, $otherArea] = $this->projectAndArea();
        $olt = OltAsset::factory()->for($project)->for($area)->create();
        $pon = OltPonPort::factory()->for($olt, 'oltAsset')->create();
        $mappedOdc = OdcAsset::factory()->for($project)->for($area)->create([
            'box_id' => 'ODC-MAPPED',
            'olt_pon_port_id' => $pon->id,
            'status' => 'active',
        ]);
        OdcAsset::factory()->for($project)->for($area)->create([
            'box_id' => 'ODC-UNMAPPED',
            'olt_pon_port_id' => null,
            'status' => 'unmapped',
        ]);
        OdpAsset::factory()->for($project)->for($area)->for($mappedOdc, 'odcAsset')->create([
            'box_id' => 'ODP-MAPPED',
            'status' => 'active',
        ]);
        OdpAsset::factory()->for($otherProject)->for($otherArea)->create([
            'box_id' => 'ODP-OTHER',
            'status' => 'active',
        ]);

        $payload = app(AssetMapDataService::class)->payload([
            'project_id' => $project->id,
            'area_id' => $area->id,
            'status' => 'active',
            'mapping_state' => 'mapped',
        ]);

        $this->assertEqualsCanonicalizing(
            ['ODC-MAPPED', 'ODP-MAPPED'],
            collect($payload['assets'])->pluck('label')->all(),
        );
        $this->assertSame([], $payload['links']);
    }

    /**
     * @return array{Project, Area}
     */
    private function projectAndArea(): array
    {
        $project = Project::factory()->create();
        $area = Area::factory()->for($project)->create();

        return [$project, $area];
    }
}
