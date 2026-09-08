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

class MonitMonitoringOpnSenseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:' . base64_encode(str_repeat('x', 32))]);
        $this->withoutVite();
    }

    public function test_opnsense_monit_lifecycle()
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
            'netgate_id' => 'fw-monit-test',
            'url' => 'https://192.168.240.11',
            'api_key' => 'test-key',
            'api_secret' => 'test-secret',
            'os_type' => 'opnsense',
            'company_id' => $company->id,
            'is_online' => true,
        ]);

        Http::fake([
            '*api/monit/service/status*' => Http::response([
                'status' => 'running',
            ], 200),
            '*api/monit/settings/get*' => Http::response([
                'monit' => [
                    'general' => [
                        'enabled' => '1',
                        'interval' => '120',
                        'startdelay' => '120',
                        'mailserver' => ['127.0.0.1' => ['value' => '127.0.0.1', 'selected' => 1]],
                        'port' => '25',
                        'username' => '',
                        'password' => '',
                        'ssl' => '0',
                        'sslverify' => '1',
                        'httpdEnabled' => '0',
                        'httpdPort' => '2812',
                    ],
                ],
            ], 200),
            '*api/monit/settings/searchService*' => Http::response([
                'rows' => [
                    [
                        'uuid' => 'monit-svc-1',
                        'enabled' => '1',
                        'name' => 'root_fs',
                        'type' => 'filesystem',
                        '%type' => 'Filesystem',
                        'path' => '/',
                        'tests' => 'test-uuid-1',
                        '%tests' => 'SpaceUsage',
                        'description' => 'Root filesystem disk usage check',
                    ],
                ],
                'rowCount' => 1,
                'total' => 1,
            ], 200),
            '*api/monit/settings/searchAlert*' => Http::response([
                'rows' => [
                    [
                        'uuid' => 'monit-alert-1',
                        'enabled' => '1',
                        'recipient' => 'ops@admixcentral.local',
                        'description' => 'Ops notification list',
                    ],
                ],
                'rowCount' => 1,
                'total' => 1,
            ], 200),
            '*api/monit/settings/searchTest*' => Http::response([
                'rows' => [
                    [
                        'uuid' => 'test-uuid-1',
                        'name' => 'SpaceUsage',
                        'type' => 'SpaceUsage',
                        'condition' => 'space usage is greater than 75%',
                        'action' => 'alert',
                        '%action' => 'Alert',
                    ],
                ],
                'rowCount' => 1,
                'total' => 1,
            ], 200),
            '*api/monit/settings/addService*' => Http::response([
                'result' => 'saved',
                'uuid' => 'monit-svc-2',
            ], 200),
            '*api/monit/settings/delService/monit-svc-1*' => Http::response([
                'result' => 'deleted',
            ], 200),
            '*api/monit/settings/toggleService/monit-svc-1*' => Http::response([
                'result' => 'Disabled',
                'changed' => true,
            ], 200),
            '*api/monit/settings/addAlert*' => Http::response([
                'result' => 'saved',
                'uuid' => 'monit-alert-2',
            ], 200),
            '*api/monit/settings/delAlert/monit-alert-1*' => Http::response([
                'result' => 'deleted',
            ], 200),
            '*api/monit/settings/toggleAlert/monit-alert-1*' => Http::response([
                'result' => 'Disabled',
                'changed' => true,
            ], 200),
            '*api/monit/settings/set*' => Http::response([
                'result' => 'saved',
            ], 200),
            '*api/monit/service/reconfigure*' => Http::response([
                'status' => 'ok',
            ], 200),
            '*api/monit/service/restart*' => Http::response([
                'response' => 'restarted',
            ], 200),
            '*api/monit/service/start*' => Http::response([
                'response' => 'started',
            ], 200),
            '*api/monit/service/stop*' => Http::response([
                'response' => 'stopped',
            ], 200),
        ]);

        // 1. Status -> Monitoring redirect on OPNsense
        $resRedirect = $this->actingAs($admin)->get("/firewall/{$fw->netgate_id}/status/monitoring");
        $resRedirect->assertRedirect(route('services.monit.index', $fw));

        // 2. Monit Web UI Index
        $resUi = $this->actingAs($admin)->get("/firewall/{$fw->netgate_id}/services/monit");
        $resUi->assertStatus(200);
        $resUi->assertSee('Monit Daemon Status');
        $resUi->assertSee('Running');
        $resUi->assertSee('root_fs');
        $resUi->assertSee('Filesystem');
        $resUi->assertSee('ops@admixcentral.local');
        $resUi->assertSee('SpaceUsage');

        // 3. PfSenseApiService / OpnSenseApiService direct calls
        $api = new PfSenseApiService($fw);
        $status = $api->getMonitServiceStatus();
        $this->assertEquals('running', $status['status']);

        $settings = $api->getMonitSettings();
        $this->assertEquals('1', $settings['monit']['general']['enabled']);

        $services = $api->getMonitServices();
        $this->assertEquals(200, $services['status']);
        $this->assertCount(1, $services['data']);
        $this->assertEquals('root_fs', $services['data'][0]['name']);

        $alerts = $api->getMonitAlerts();
        $this->assertEquals(200, $alerts['status']);
        $this->assertCount(1, $alerts['data']);
        $this->assertEquals('ops@admixcentral.local', $alerts['data'][0]['recipient']);

        $tests = $api->getMonitTests();
        $this->assertEquals(200, $tests['status']);
        $this->assertCount(1, $tests['data']);
        $this->assertEquals('SpaceUsage', $tests['data'][0]['name']);

        // 4. Create Monit Service via Web UI
        $resStoreSvc = $this->actingAs($admin)->post("/firewall/{$fw->netgate_id}/services/monit/services", [
            'name' => 'nginx_worker',
            'type' => 'process',
            'pidfile' => '/var/run/nginx.pid',
            'description' => 'Nginx web server check',
            'enabled' => 1,
        ]);
        $resStoreSvc->assertRedirect();
        $resStoreSvc->assertSessionHas('success');

        // 5. Toggle Monit Service via Web UI
        $resToggleSvc = $this->actingAs($admin)->post("/firewall/{$fw->netgate_id}/services/monit/services/monit-svc-1/toggle");
        $resToggleSvc->assertRedirect();
        $resToggleSvc->assertSessionHas('success');

        // 6. Delete Monit Service via Web UI
        $resDelSvc = $this->actingAs($admin)->delete("/firewall/{$fw->netgate_id}/services/monit/services/monit-svc-1");
        $resDelSvc->assertRedirect();
        $resDelSvc->assertSessionHas('success');

        // 7. Create Monit Alert Recipient via Web UI
        $resStoreAlert = $this->actingAs($admin)->post("/firewall/{$fw->netgate_id}/services/monit/alerts", [
            'recipient' => 'security@admixcentral.local',
            'description' => 'Security team',
            'enabled' => 1,
        ]);
        $resStoreAlert->assertRedirect();
        $resStoreAlert->assertSessionHas('success');

        // 8. Toggle Alert Recipient via Web UI
        $resToggleAlert = $this->actingAs($admin)->post("/firewall/{$fw->netgate_id}/services/monit/alerts/monit-alert-1/toggle");
        $resToggleAlert->assertRedirect();
        $resToggleAlert->assertSessionHas('success');

        // 9. Delete Alert Recipient via Web UI
        $resDelAlert = $this->actingAs($admin)->delete("/firewall/{$fw->netgate_id}/services/monit/alerts/monit-alert-1");
        $resDelAlert->assertRedirect();
        $resDelAlert->assertSessionHas('success');

        // 10. Update General Settings via Web UI
        $resSettings = $this->actingAs($admin)->post("/firewall/{$fw->netgate_id}/services/monit/settings", [
            'enabled' => 1,
            'interval' => 60,
            'startdelay' => 30,
            'mailserver' => 'smtp.admixcentral.local',
            'port' => 587,
        ]);
        $resSettings->assertRedirect();
        $resSettings->assertSessionHas('success');

        // 11. Service actions
        $resRestart = $this->actingAs($admin)->post("/firewall/{$fw->netgate_id}/services/monit/service/restart");
        $resRestart->assertRedirect();
        $resRestart->assertSessionHas('success');

        $resReconf = $this->actingAs($admin)->post("/firewall/{$fw->netgate_id}/services/monit/service/reconfigure");
        $resReconf->assertRedirect();
        $resReconf->assertSessionHas('success');
    }
}
