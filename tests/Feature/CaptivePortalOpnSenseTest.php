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

class CaptivePortalOpnSenseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:' . base64_encode(str_repeat('x', 32))]);
        $this->withoutVite();
    }

    public function test_opnsense_captive_portal_lifecycle()
    {
        $company = Company::create(['name' => 'Acme Corp']);
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@central.test',
            'password' => 'password',
            'role' => 'admin',
        ]);
        $fw = Firewall::create([
            'name' => 'OPNsense Firewall',
            'netgate_id' => 'fw-cp-test',
            'url' => 'https://192.168.240.11',
            'api_key' => 'test-key',
            'api_secret' => 'test-secret',
            'os_type' => 'opnsense',
            'company_id' => $company->id,
            'is_online' => true,
        ]);

        Http::fake([
            '*api/captiveportal/settings/searchZones*' => Http::response([
                'rows' => [
                    [
                        'uuid' => 'cp-zone-1',
                        'zoneid' => '0',
                        'description' => 'Guest WiFi Zone',
                        'interfaces' => 'lan',
                        '%interfaces' => 'LAN',
                        'enabled' => '1',
                    ],
                ],
                'total' => 1,
            ], 200),
            '*api/captiveportal/settings/getZone/cp-zone-1*' => Http::response([
                'zone' => [
                    'description' => 'Guest WiFi Zone',
                    'interfaces' => 'lan',
                    'enabled' => '1',
                ],
            ], 200),
            '*api/captiveportal/settings/addZone*' => Http::response([
                'result' => 'saved',
                'uuid' => 'cp-zone-new',
            ], 200),
            '*api/captiveportal/settings/setZone/cp-zone-1*' => Http::response([
                'result' => 'saved',
            ], 200),
            '*api/captiveportal/settings/delZone/cp-zone-1*' => Http::response([
                'result' => 'deleted',
            ], 200),
            '*api/captiveportal/session/search*' => Http::response([
                'rows' => [
                    [
                        'sessionId' => 'sess-1',
                        'userName' => 'john.doe',
                        'ipAddress' => '192.168.240.55',
                        'macAddress' => '00:11:22:33:44:55',
                        'startTime' => time(),
                    ],
                ],
                'total' => 1,
            ], 200),
            '*api/captiveportal/service/status*' => Http::response([
                'status' => 'running',
            ], 200),
            '*api/captiveportal/service/reconfigure*' => Http::response([
                'status' => 'ok',
            ], 200),
        ]);

        // 1. Web UI: Captive Portal Zones View
        $resUi = $this->actingAs($admin)->get("/firewall/{$fw->netgate_id}/services/captive-portal");
        $resUi->assertStatus(200);
        $resUi->assertSee('Guest WiFi Zone');
        $resUi->assertSee('LAN');

        // 2. Direct API Testing via OpnSenseApiService and PfSenseApiService delegation
        $pfApi = new PfSenseApiService($fw);
        $zones = $pfApi->getCaptivePortalZones();
        $this->assertEquals(200, $zones['status']);
        $this->assertArrayHasKey('Guest WiFi Zone', $zones['data']);

        // 3. Create Zone
        $createRes = $pfApi->createCaptivePortalZone([
            'description' => 'Staff WiFi Zone',
            'interfaces' => 'lan',
        ]);
        $this->assertEquals('saved', $createRes['data']['result']);

        // 4. Update Zone
        $opnApi = new OpnSenseApiService($fw);
        $updateRes = $opnApi->updateCaptivePortalZone('cp-zone-1', [
            'description' => 'Updated Guest WiFi Zone',
            'interfaces' => 'lan',
        ]);
        $this->assertEquals('saved', $updateRes['data']['result']);

        // 5. Delete Zone
        $delRes = $pfApi->deleteCaptivePortalZone('cp-zone-1');
        $this->assertEquals('deleted', $delRes['data']['result']);

        // 6. Active Sessions Search
        $sessions = $pfApi->getCaptivePortalSessions();
        $this->assertEquals(200, $sessions['status']);
        $this->assertCount(1, $sessions['data']);
        $this->assertEquals('john.doe', $sessions['data'][0]['userName']);

        // 7. Service Status
        $svcStatus = $opnApi->getCaptivePortalServiceStatus();
        $this->assertEquals('running', $svcStatus['status']);
    }
}
