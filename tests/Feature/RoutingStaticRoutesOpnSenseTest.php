<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Firewall;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RoutingStaticRoutesOpnSenseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:' . base64_encode(str_repeat('x', 32))]);
        $this->withoutVite();
    }

    public function test_opnsense_static_routes_lifecycle()
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
            'netgate_id' => 'fw-route-test',
            'url' => 'https://192.168.240.11',
            'api_key' => 'test-key',
            'api_secret' => 'test-secret',
            'os_type' => 'opnsense',
            'company_id' => $company->id,
            'is_online' => true,
        ]);

        Http::fake([
            '*api/routes/gateway/status*' => Http::response([
                'items' => [
                    [
                        'name' => 'WAN_DHCP',
                        'address' => '192.168.240.1',
                        'status' => 'none',
                        'status_translated' => 'Online',
                    ],
                ],
            ], 200),
            '*api/routes/routes/searchroute*' => Http::response([
                'rows' => [
                    [
                        'uuid' => 'r-test-uuid',
                        'network' => '10.200.1.0/24',
                        'gateway' => 'WAN_DHCP',
                        'descr' => 'Branch Office Route',
                        'enabled' => '1',
                    ],
                ],
            ], 200),
            '*api/routes/routes/addroute*' => Http::response([
                'result' => 'saved',
                'uuid' => 'r-new-uuid',
            ], 200),
            '*api/routes/routes/setroute/r-test-uuid*' => Http::response([
                'result' => 'saved',
            ], 200),
            '*api/routes/routes/delroute/r-test-uuid*' => Http::response([
                'result' => 'deleted',
            ], 200),
            '*api/routes/routes/reconfigure*' => Http::response([
                'status' => 'ok',
            ], 200),
        ]);

        // 1. Gateways tab
        $resGw = $this->actingAs($admin)->get("/firewall/{$fw->netgate_id}/system/routing?tab=gateways");
        $resGw->assertStatus(200);
        $resGw->assertSee('WAN_DHCP');
        $resGw->assertSee('192.168.240.1');

        // 2. Static Routes tab
        $resSr = $this->actingAs($admin)->get("/firewall/{$fw->netgate_id}/system/routing?tab=static_routes");
        $resSr->assertStatus(200);
        $resSr->assertSee('10.200.1.0/24');
        $resSr->assertSee('Branch Office Route');

        // 3. Store Static Route
        $resStore = $this->actingAs($admin)->post("/firewall/{$fw->netgate_id}/system/routing/static-routes", [
            'network' => '10.250.0.0/16',
            'gateway' => 'WAN_DHCP',
            'descr' => 'New Static Route',
        ]);
        $resStore->assertRedirect("/firewall/{$fw->netgate_id}/system/routing?tab=static_routes");

        // 4. Update Static Route
        $resUpdate = $this->actingAs($admin)->patch("/firewall/{$fw->netgate_id}/system/routing/static-routes/r-test-uuid", [
            'network' => '10.200.1.0/24',
            'gateway' => 'WAN_DHCP',
            'descr' => 'Updated Branch Office Route',
        ]);
        $resUpdate->assertRedirect("/firewall/{$fw->netgate_id}/system/routing?tab=static_routes");

        // 5. Delete Static Route
        $resDelete = $this->actingAs($admin)->delete("/firewall/{$fw->netgate_id}/system/routing/static-routes/r-test-uuid");
        $resDelete->assertRedirect("/firewall/{$fw->netgate_id}/system/routing?tab=static_routes");
    }
}
