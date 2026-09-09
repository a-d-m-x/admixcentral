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

class SyslogSettingsOpnSenseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:' . base64_encode(str_repeat('x', 32))]);
        $this->withoutVite();
    }

    public function test_opnsense_syslog_settings_lifecycle()
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
            'netgate_id' => 'fw-syslog-test',
            'url' => 'https://192.168.240.11',
            'api_key' => 'test-key',
            'api_secret' => 'test-secret',
            'os_type' => 'opnsense',
            'company_id' => $company->id,
            'is_online' => true,
        ]);

        Http::fake([
            '*api/syslog/settings/get*' => Http::response([
                'syslog' => [
                    'general' => [
                        'enabled' => '1',
                        'loglocal' => '1',
                    ],
                ],
            ], 200),
            '*api/syslog/service/status*' => Http::response([
                'status' => 'running',
            ], 200),
            '*api/syslog/service/stats*' => Http::response([
                'stats' => [],
            ], 200),
            '*api/syslog/settings/searchDestinations*' => Http::response([
                'rows' => [
                    [
                        'uuid' => 'syslog-uuid-1',
                        'enabled' => '1',
                        'transport' => 'udp4',
                        'hostname' => '192.168.240.50',
                        'port' => '514',
                        'description' => 'Primary SIEM',
                    ],
                ],
                'rowCount' => 1,
                'total' => 1,
            ], 200),
            '*api/syslog/settings/addDestination*' => Http::response([
                'result' => 'saved',
                'uuid' => 'syslog-uuid-2',
            ], 200),
            '*api/syslog/settings/delDestination/syslog-uuid-1*' => Http::response([
                'result' => 'deleted',
            ], 200),
            '*api/syslog/service/reconfigure*' => Http::response([
                'status' => 'ok',
            ], 200),
        ]);

        // 1. Web UI: Syslog Settings / Remote Destinations
        $resUi = $this->actingAs($admin)->get("/firewall/{$fw->netgate_id}/status/system-logs?type=settings");
        $resUi->assertStatus(200);
        $resUi->assertSee('Syslog Service (Syslog-ng)');
        $resUi->assertSee('Remote Syslog Destinations');
        $resUi->assertSee('192.168.240.50');
        $resUi->assertSee('Primary SIEM');

        // 2. Direct API via PfSenseApiService & OpnSenseApiService delegation
        $pfApi = new PfSenseApiService($fw);
        $settings = $pfApi->getSyslogSettings();
        $this->assertEquals(200, $settings['status']);
        $this->assertEquals('1', $settings['data']['general']['enabled']);

        $dests = $pfApi->getSyslogDestinations();
        $this->assertEquals(200, $dests['status']);
        $this->assertCount(1, $dests['data']);
        $this->assertEquals('192.168.240.50', $dests['data'][0]['hostname']);

        $svcStatus = $pfApi->getSyslogServiceStatus();
        $this->assertEquals('running', $svcStatus['status']);

        // 3. Web UI: Add Remote Syslog Destination via POST
        $resStore = $this->actingAs($admin)->post("/firewall/{$fw->netgate_id}/status/system-logs/destinations", [
            'hostname' => '192.168.240.51',
            'port' => 514,
            'transport' => 'tcp4',
            'description' => 'Secondary SIEM',
            'enabled' => 1,
        ]);
        $resStore->assertRedirect("/firewall/{$fw->netgate_id}/status/system-logs?type=settings");
        $resStore->assertSessionHas('success');

        // 4. Web UI: Delete Remote Syslog Destination via DELETE
        $resDel = $this->actingAs($admin)->delete("/firewall/{$fw->netgate_id}/status/system-logs/destinations/syslog-uuid-1");
        $resDel->assertRedirect("/firewall/{$fw->netgate_id}/status/system-logs?type=settings");
        $resDel->assertSessionHas('success');
    }
}
