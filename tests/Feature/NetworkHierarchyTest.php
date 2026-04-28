<?php

namespace Tests\Feature;

use App\Models\OdcAsset;
use App\Models\OdpAsset;
use App\Models\OltAsset;
use App\Models\OltPonPort;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NetworkHierarchyTest extends TestCase
{
    use RefreshDatabase;

    public function test_olt_pon_odc_odp_relationships_work(): void
    {
        $olt = OltAsset::factory()->create();
        $pon = OltPonPort::factory()->for($olt)->create(['pon_number' => 1]);
        $odc = OdcAsset::factory()->mapped($pon)->create(['box_id' => 'ODC-HIER']);
        $odp = OdpAsset::factory()->for($odc->project)->for($odc->area)->for($odc, 'odcAsset')->create(['box_id' => 'ODP-HIER']);

        $this->assertTrue($olt->ponPorts->contains($pon));
        $this->assertTrue($pon->odcAssets->contains($odc));
        $this->assertSame($pon->id, $odc->oltPonPort->id);
        $this->assertTrue($odc->odpAssets->contains($odp));
        $this->assertSame($odc->id, $odp->odcAsset->id);
        $this->assertTrue($olt->odcAssets->contains($odc));
    }

    public function test_odc_can_remain_unmapped_for_admin_assignment_later(): void
    {
        $odc = OdcAsset::factory()->create();

        $this->assertNull($odc->olt_pon_port_id);
        $this->assertSame('unmapped', $odc->status);
    }
}
