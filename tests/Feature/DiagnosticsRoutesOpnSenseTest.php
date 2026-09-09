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

class DiagnosticsRoutesOpnSenseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:' . base64_encode(str_repeat('x', 32))]);
        $this->withoutVite();
    }

    public function test_opnsense_kernel_routes_diagnostics()
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
            'netgate_id' => 'fw-routes-diag-test',
            'url' => 'https://192.168.240.11',
            'api_key' => 'test-key',
            'api_secret' => 'test-secret',
            'os_type' => 'opnsense',
            'company_id' => $company->id,
            'is_online' => true,
        ]);

        Http::fake([
            '*api/diagnostics/interface/getRoutes*' => Http::response([
                [
                    'proto' => 'ipv4',
                    'destination' => 'default',
                    'gateway' => '192.168.240.1',
                    'flags' => 'UGS',
                    'netif' => 'vtnet0',
                    'intf_description' => 'WAN',
                    'mtu' => '1500',
                ],
                [
                    'proto' => 'ipv4',
                    'destination' => '192.168.11.0/24',
                    'gateway' => 'link#2',
                    'flags' => 'U',
                    'netif' => 'vtnet1',
                    'intf_description' => 'LAN',
                    'mtu' => '1500',
                ],
            ], 200),
        ]);

        // 1. Web UI: Diagnostics Routes View
        $resUi = $this->actingAs($admin)->get("/firewall/{$fw->netgate_id}/diagnostics/routes");
        $resUi->assertStatus(200);
        $resUi->assertSee('Routing Table (netstat -r)');
        $resUi->assertSee('192.168.240.1');
        $resUi->assertSee('192.168.11.0/24');
        $resUi->assertSee('WAN');
        $resUi->assertSee('LAN');

        // 2. Direct API via PfSenseApiService & OpnSenseApiService delegation
        $pfApi = new PfSenseApiService($fw);
        $routes = $pfApi->getKernelRoutes();
        $this->assertEquals(200, $routes['status']);
        $this->assertCount(2, $routes['data']);
        $this->assertEquals('default', $routes['data'][0]['destination']);
        $this->assertEquals('192.168.240.1', $routes['data'][0]['gateway']);

        $opnApi = new OpnSenseApiService($fw);
        $opnRoutes = $opnApi->getKernelRoutes();
        $this->assertEquals(200, $opnRoutes['status']);
        $this->assertCount(2, $opnRoutes['data']);
    }
}
