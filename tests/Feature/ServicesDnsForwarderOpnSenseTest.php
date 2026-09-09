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

class ServicesDnsForwarderOpnSenseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:' . base64_encode(str_repeat('x', 32))]);
        $this->withoutVite();
    }

    public function test_opnsense_dns_forwarder_lifecycle()
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
            'netgate_id' => 'fw-dnsmasq-test',
            'url' => 'https://192.168.240.11',
            'api_key' => 'test-key',
            'api_secret' => 'test-secret',
            'os_type' => 'opnsense',
            'company_id' => $company->id,
            'is_online' => true,
        ]);

        Http::fake([
            '*api/dnsmasq/settings/get*' => Http::response([
                'dnsmasq' => [
                    'enable' => '1',
                    'port' => '53053',
                ],
            ], 200),
            '*api/dnsmasq/service/status*' => Http::response([
                'status' => 'running',
            ], 200),
            '*api/dnsmasq/settings/searchHost*' => Http::response([
                'rows' => [
                    [
                        'uuid' => 'host-uuid-1',
                        'host' => 'fileserver',
                        'domain' => 'lab.internal',
                        'ip' => '192.168.1.15',
                        'descr' => 'Main NAS server',
                    ],
                ],
                'total' => 1,
            ], 200),
            '*api/dnsmasq/settings/addHost*' => Http::response([
                'result' => 'saved',
                'uuid' => 'host-uuid-2',
            ], 200),
            '*api/dnsmasq/settings/delHost/host-uuid-1*' => Http::response([
                'result' => 'deleted',
            ], 200),
            '*api/dnsmasq/service/reconfigure*' => Http::response([
                'status' => 'ok',
            ], 200),
        ]);

        // 1. Web UI: DNS Forwarder View
        $resUi = $this->actingAs($admin)->get("/firewall/{$fw->netgate_id}/services/dns-forwarder");
        $resUi->assertStatus(200);
        $resUi->assertSee('Dnsmasq Service');
        $resUi->assertSee('fileserver');
        $resUi->assertSee('192.168.1.15');
        $resUi->assertSee('Main NAS server');

        // 2. Direct API via PfSenseApiService & OpnSenseApiService delegation
        $pfApi = new PfSenseApiService($fw);
        $settings = $pfApi->getDnsForwarderSettings();
        $this->assertEquals(200, $settings['status']);
        $this->assertEquals('1', $settings['data']['enable']);

        $hosts = $pfApi->getDnsForwarderHostOverrides();
        $this->assertEquals(200, $hosts['status']);
        $this->assertCount(1, $hosts['data']);
        $this->assertEquals('fileserver', $hosts['data'][0]['host']);

        // 3. Web UI: Add Host Override via POST
        $resStore = $this->actingAs($admin)->post("/firewall/{$fw->netgate_id}/services/dns-forwarder/hosts", [
            'host' => 'db01',
            'domain' => 'lab.internal',
            'ip' => '192.168.1.20',
            'descr' => 'Database server',
        ]);
        $resStore->assertRedirect("/firewall/{$fw->netgate_id}/services/dns-forwarder");
        $resStore->assertSessionHas('success');

        // 4. Web UI: Delete Host Override via DELETE
        $resDel = $this->actingAs($admin)->delete("/firewall/{$fw->netgate_id}/services/dns-forwarder/hosts/host-uuid-1");
        $resDel->assertRedirect("/firewall/{$fw->netgate_id}/services/dns-forwarder");
        $resDel->assertSessionHas('success');
    }
}
