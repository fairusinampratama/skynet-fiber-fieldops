<?php

namespace Tests\Feature;

use App\Enums\PortStatus;
use App\Models\OdcAsset;
use App\Models\OdpAsset;
use App\Models\OdpPort;
use App\Models\OltPonPort;
use App\Services\DashboardMetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardMetricsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_metrics_classify_empty_safe_warning_and_full_odps(): void
    {
        $service = app(DashboardMetricsService::class);

        $empty = OdpAsset::factory()->create(['box_id' => 'ODP-EMPTY']);
        $safe = $this->odpWithPorts('ODP-SAFE', [
            PortStatus::Used,
            PortStatus::Used,
            PortStatus::Available,
            PortStatus::Available,
        ]);
        $warning = $this->odpWithPorts('ODP-WARN', [
            PortStatus::Used,
            PortStatus::Used,
            PortStatus::Used,
            PortStatus::Available,
        ]);
        $full = $this->odpWithPorts('ODP-FULL', [
            PortStatus::Used,
            PortStatus::Used,
            PortStatus::Used,
            PortStatus::Reserved,
        ]);

        $rows = $service->odpUtilizationRows()->keyBy('box_id');

        $this->assertSame(0.0, $rows[$empty->box_id]['utilization']);
        $this->assertSame('safe', $rows[$empty->box_id]['category']);
        $this->assertSame('safe', $rows[$safe->box_id]['category']);
        $this->assertSame(50.0, $rows[$safe->box_id]['utilization']);
        $this->assertSame('warning', $rows[$warning->box_id]['category']);
        $this->assertSame(75.0, $rows[$warning->box_id]['utilization']);
        $this->assertSame('full', $rows[$full->box_id]['category']);
        $this->assertSame(100.0, $rows[$full->box_id]['utilization']);
    }

    public function test_dashboard_kpis_and_alerts_use_real_odp_port_data(): void
    {
        $this->odpWithPorts('ODP-WARN', [
            PortStatus::Used,
            PortStatus::Used,
            PortStatus::Used,
            PortStatus::Available,
        ]);
        $this->odpWithPorts('ODP-FULL', [
            PortStatus::Used,
            PortStatus::Used,
            PortStatus::Used,
            PortStatus::Reserved,
        ]);
        $this->odpWithPorts('ODP-MAINT', [
            PortStatus::Broken,
            PortStatus::Unknown,
            PortStatus::Available,
            PortStatus::Available,
        ]);

        $service = app(DashboardMetricsService::class);

        $this->assertSame([
            'total_olt' => 0,
            'total_odc' => 0,
            'total_odp' => 3,
            'total_capacity' => 12,
            'active_customers' => 6,
            'empty_ports' => 3,
            'critical_odp' => 1,
            'near_full_odp' => 1,
            'pressured_pon' => 0,
            'full_odp' => 1,
            'pon_overload' => 0,
        ], $service->kpis());

        $cards = collect($service->kpiCards())->keyBy('label');

        $this->assertSame('Port ODP terpakai', $cards['Pelanggan Aktif']['description']);
        $this->assertStringContainsString('/admin/odp-assets', $cards['Pelanggan Aktif']['url']);
        $this->assertStringContainsString('/admin/olt-pon-ports', $cards['PON Bermasalah']['url']);

        $alerts = collect($service->alerts())->pluck('type')->all();

        $this->assertContains('ODP Kritis', $alerts);
        $this->assertContains('ODP Hampir Penuh', $alerts);
        $this->assertContains('ODP Belum Mapping', $alerts);
    }

    public function test_pon_monitoring_uses_downstream_used_odp_ports_pon_capacity_and_status_labels(): void
    {
        $safePon = $this->ponWithDownstreamUsedPorts(10, 7);
        $warningPon = $this->ponWithDownstreamUsedPorts(100, 75);
        $fullPon = $this->ponWithDownstreamUsedPorts(100, 90);
        $overloadPon = $this->ponWithDownstreamUsedPorts(2, 3);

        $rows = app(DashboardMetricsService::class)->ponMonitoringRows()->keyBy('id');

        $this->assertSame('Aman', $rows[$safePon->id]['status']);
        $this->assertSame('safe', $rows[$safePon->id]['category']);
        $this->assertSame('Hampir Penuh', $rows[$warningPon->id]['status']);
        $this->assertSame('warning', $rows[$warningPon->id]['category']);
        $this->assertSame('Penuh', $rows[$fullPon->id]['status']);
        $this->assertSame('full', $rows[$fullPon->id]['category']);
        $this->assertSame('Overload', $rows[$overloadPon->id]['status']);
        $this->assertSame('overload', $rows[$overloadPon->id]['category']);
        $this->assertSame(150.0, $rows[$overloadPon->id]['utilization']);
        $this->assertStringContainsString("/admin/olt-pon-ports/{$overloadPon->id}/edit", $rows[$overloadPon->id]['url']);
        $this->assertContains('PON Bermasalah', collect(app(DashboardMetricsService::class)->alerts())->pluck('type')->all());
    }

    public function test_critical_odp_rows_include_hierarchy_context_and_links(): void
    {
        $ponPort = OltPonPort::factory()->create(['pon_number' => 4, 'capacity' => 128]);
        $odc = OdcAsset::factory()->mapped($ponPort)->create(['box_id' => 'ODC-CRIT']);
        $odp = OdpAsset::factory()->for($odc->project)->for($odc->area)->for($odc, 'odcAsset')->create(['box_id' => 'ODP-CRIT']);

        foreach ([PortStatus::Used, PortStatus::Used, PortStatus::Used, PortStatus::Reserved] as $index => $status) {
            OdpPort::factory()->create(['odp_asset_id' => $odp->id, 'port_number' => $index + 1, 'status' => $status]);
        }

        $row = app(DashboardMetricsService::class)->criticalOdps()->first();

        $this->assertSame($ponPort->oltAsset->code, $row['olt']);
        $this->assertSame(4, $row['pon']);
        $this->assertSame('ODC-CRIT', $row['odc']);
        $this->assertSame('ODP-CRIT', $row['box_id']);
        $this->assertSame('Penuh', $row['status']);
        $this->assertStringContainsString("/admin/odp-assets/{$odp->id}/edit", $row['url']);
        $this->assertStringContainsString("/admin/odc-assets/{$odc->id}/edit", $row['odc_url']);
        $this->assertStringContainsString("/admin/olt-pon-ports/{$ponPort->id}/edit", $row['pon_url']);
    }

    public function test_dashboard_metrics_handle_empty_datasets(): void
    {
        $service = app(DashboardMetricsService::class);

        $this->assertSame([
            'total_olt' => 0,
            'total_odc' => 0,
            'total_odp' => 0,
            'total_capacity' => 0,
            'active_customers' => 0,
            'empty_ports' => 0,
            'critical_odp' => 0,
            'near_full_odp' => 0,
            'pressured_pon' => 0,
            'full_odp' => 0,
            'pon_overload' => 0,
        ], $service->kpis());
        $this->assertSame([], $service->alerts());
        $this->assertTrue($service->ponMonitoring()->isEmpty());
        $this->assertTrue($service->criticalOdps()->isEmpty());
    }

    public function test_top_critical_odps_are_ranked_by_utilization(): void
    {
        $this->odpWithPorts('ODP-WARN', [
            PortStatus::Used,
            PortStatus::Used,
            PortStatus::Used,
            PortStatus::Available,
        ]);
        $this->odpWithPorts('ODP-FULL', [
            PortStatus::Used,
            PortStatus::Used,
            PortStatus::Used,
            PortStatus::Reserved,
        ]);

        $rows = app(DashboardMetricsService::class)->topCriticalOdps();

        $this->assertSame(['ODP-FULL', 'ODP-WARN'], $rows->pluck('box_id')->all());
    }

    /**
     * @param  list<PortStatus>  $statuses
     */
    private function odpWithPorts(string $boxId, array $statuses): OdpAsset
    {
        $odp = OdpAsset::factory()->create(['box_id' => $boxId]);

        foreach ($statuses as $index => $status) {
            OdpPort::factory()->create([
                'odp_asset_id' => $odp->id,
                'port_number' => $index + 1,
                'status' => $status,
            ]);
        }

        return $odp;
    }

    private function ponWithDownstreamUsedPorts(int $capacity, int $usedPorts): OltPonPort
    {
        $ponPort = OltPonPort::factory()->create(['capacity' => $capacity]);
        $odc = OdcAsset::factory()->mapped($ponPort)->create();
        $odp = OdpAsset::factory()->for($odc->project)->for($odc->area)->for($odc, 'odcAsset')->create();

        foreach (range(1, $usedPorts) as $portNumber) {
            OdpPort::factory()->create([
                'odp_asset_id' => $odp->id,
                'port_number' => $portNumber,
                'status' => PortStatus::Used,
            ]);
        }

        return $ponPort;
    }
}
