<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Firewall;
use App\Models\User;
use App\Services\OpnSenseApiService;
use App\Services\PfSenseApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HighAvailabilityCarpOpnSenseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:' . base64_encode(str_repeat('x', 32))]);
        $this->withoutVite();
    }

    public function test_opnsense_high_availability_and_carp_lifecycle()
    {
        $company = Company::create(['name' => 'Acme Corp']);
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@central.test',
            'password' => 'password',
            'role' => 'admin',
        ]);
        $fw = Firewall::create([
            'name' => 'OPNsense HA FW',
            'netgate_id' => 'fw-ha-test',
            'url' => 'https://192.168.240.11',
            'api_key' => 'test-key',
            'api_secret' => 'test-secret',
            'os_type' => 'opnsense',
            'company_id' => $company->id,
            'is_online' => true,
        ]);

        Http::fake([
            '*api/core/hasync/get*' => Http::response([
                'hasync' => [
                    'disablepreempt' => '0',
                    'disconnectppps' => '0',
                    'pfsyncinterface' => [
                        '' => ['value' => 'Disabled', 'selected' => 1],
                        'lan' => ['value' => 'LAN', 'selected' => 0],
                        'wan' => ['value' => 'WAN', 'selected' => 0],
                    ],
                    'pfsyncpeerip' => '192.168.240.12',
                    'pfsyncversion' => [
                        '1400' => ['value' => 'OPNsense 24.7 or above', 'selected' => 1],
                    ],
                    'pfsyncdefer' => '0',
                    'synchronizetoip' => '192.168.240.12',
                    'verifypeer' => '0',
                    'username' => 'root',
                    'password' => 'secret',
                    'syncitems' => [
                        'rules' => ['value' => 'Firewall Rules', 'selected' => 1],
                        'aliases' => ['value' => 'Aliases', 'selected' => 1],
                        'nat' => ['value' => 'NAT', 'selected' => 0],
                    ],
                ],
            ], 200),
            '*api/core/hasync/set*' => Http::response([
                'result' => 'saved',
            ], 200),
            '*api/diagnostics/interface/getVipStatus*' => Http::response([
                'total' => 1,
                'rowCount' => 1,
                'current' => 1,
                'rows' => [
                    [
                        'interface' => 'vtnet0',
                        'status' => 'MASTER',
                        'vhid' => '1',
                        'advskew' => '0',
                        'advbase' => '1',
                        'subnet' => '192.168.240.10/24',
                    ],
                ],
                'carp' => [
                    'demotion' => '0',
                    'allow' => '1',
                    'maintenancemode' => false,
                    'status_msg' => 'CARP is active and operational.',
                ],
            ], 200),
            '*api/interfaces/vip_settings/searchItem*' => Http::response([
                'rows' => [],
                'rowCount' => 0,
                'total' => 0,
            ], 200),
            '*api/core/system/status*' => Http::response([
                'system' => ['status' => 'ok'],
            ], 200),
            '*api/diagnostics/system/systemInformation*' => Http::response([
                'versions' => ['OPNsense 26.7.3_11-amd64', 'FreeBSD 15.1-RELEASE-p3'],
            ], 200),
        ]);

        // 1. Direct OpnSenseApiService tests
        $opnApi = new OpnSenseApiService($fw);
        $haData = $opnApi->getHighAvailabilitySync();
        $this->assertArrayHasKey('hasync', $haData);
        $this->assertEquals('192.168.240.12', $haData['hasync']['pfsyncpeerip']);

        $updateHaRes = $opnApi->updateHighAvailabilitySync([
            'disablepreempt' => '1',
            'syncitems' => 'rules,aliases',
        ]);
        $this->assertEquals('saved', $updateHaRes['result']);

        $vipStatus = $opnApi->getVipStatus();
        $this->assertEquals(1, $vipStatus['total']);
        $this->assertEquals('MASTER', $vipStatus['rows'][0]['status']);

        $carpStatus = $opnApi->getCarpStatus();
        $this->assertEquals(200, $carpStatus['status']);
        $this->assertTrue($carpStatus['data']['enable']);
        $this->assertFalse($carpStatus['data']['maintenance_mode']);
        $this->assertEquals('0', $carpStatus['data']['demotion']);
        $this->assertEquals('CARP is active and operational.', $carpStatus['data']['status_msg']);

        // 2. PfSenseApiService compatibility translation tests
        $pfApi = new PfSenseApiService($fw);
        $pfHaData = $pfApi->getHighAvailabilitySync();
        $this->assertArrayHasKey('hasync', $pfHaData);

        $pfUpdateHa = $pfApi->updateHighAvailabilitySync(['disablepreempt' => '0']);
        $this->assertEquals('saved', $pfUpdateHa['result']);

        $pfCarpStatus = $pfApi->getCarpStatus();
        $this->assertEquals(200, $pfCarpStatus['status']);
        $this->assertTrue($pfCarpStatus['data']['enable']);

        $pfUpdateCarp = $pfApi->updateCarpStatus(['enable' => true]);
        $this->assertEquals(200, $pfUpdateCarp['status']);

        // 3. Web UI Controllers & Routes tests
        $this->actingAs($admin);

        // Status CARP view
        $carpResp = $this->get(route('status.carp', $fw));
        $carpResp->assertStatus(200);
        $carpResp->assertSee('Global CARP Settings');
        $carpResp->assertSee('CARP is active and operational.');
        $carpResp->assertSee('Configure HA Sync');

        // Status CARP update action
        $updateCarpResp = $this->post(route('status.carp.update', $fw), [
            'enable' => '1',
        ]);
        $updateCarpResp->assertRedirect();
        $updateCarpResp->assertSessionHas('success');

        // System High Availability Sync view
        $haResp = $this->get(route('system.high-avail-sync', $fw));
        $haResp->assertStatus(200);
        $haResp->assertSee('High Availability Sync');
        $haResp->assertSee('State Synchronization (pfsync)');
        $haResp->assertSee('Configuration Synchronization (XMLRPC Sync)');
        $haResp->assertSee('Firewall Rules');
        $haResp->assertSee('Save HA Settings');

        // System High Availability Sync update action
        $haUpdateResp = $this->post(route('system.high-avail-sync.update', $fw), [
            'disablepreempt' => '1',
            'pfsyncinterface' => 'lan',
            'pfsyncpeerip' => '192.168.240.13',
            'synchronizetoip' => '192.168.240.13',
            'username' => 'root',
            'password' => 'secret123',
            'syncitems' => ['rules', 'aliases'],
        ]);
        $haUpdateResp->assertRedirect();
        $haUpdateResp->assertSessionHas('success');
    }
}
