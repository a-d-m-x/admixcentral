<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Firewall;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FirewallVirtualIpOpnSenseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:' . base64_encode(str_repeat('x', 32))]);
        $this->withoutVite();
    }

    public function test_opnsense_virtual_ip_lifecycle()
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
            'netgate_id' => 'fw-vip-test',
            'url' => 'https://192.168.240.11',
            'api_key' => 'test-key',
            'api_secret' => 'test-secret',
            'os_type' => 'opnsense',
            'company_id' => $company->id,
            'is_online' => true,
        ]);

        Http::fake([
            '*api/interfaces/vip_settings/searchItem*' => Http::response([
                'rows' => [
                    [
                        'uuid' => 'vip-test-uuid',
                        'mode' => 'ipalias',
                        'interface' => 'wan',
                        'address' => '192.168.240.220/32',
                        'descr' => 'Secondary Public IP',
                    ],
                ],
            ], 200),
            '*api/interfaces/vip_settings/addItem*' => Http::response([
                'result' => 'saved',
                'uuid' => 'vip-new-uuid',
            ], 200),
            '*api/interfaces/vip_settings/setItem/vip-test-uuid*' => Http::response([
                'result' => 'saved',
            ], 200),
            '*api/interfaces/vip_settings/delItem/vip-test-uuid*' => Http::response([
                'result' => 'deleted',
            ], 200),
            '*api/interfaces/vip_settings/reconfigure*' => Http::response([
                'status' => 'ok',
            ], 200),
        ]);

        // 1. VIP List
        $resList = $this->actingAs($admin)->get("/firewall/{$fw->netgate_id}/firewall/virtual_ips");
        $resList->assertStatus(200);
        $resList->assertSee('192.168.240.220');
        $resList->assertSee('Secondary Public IP');

        // 2. Store VIP
        $resStore = $this->actingAs($admin)->post("/firewall/{$fw->netgate_id}/firewall/virtual_ips", [
            'mode' => 'ipalias',
            'interface' => 'wan',
            'subnet' => '192.168.240.221',
            'subnet_bits' => 32,
            'descr' => 'New VIP Descr',
        ]);
        $resStore->assertRedirect("/firewall/{$fw->netgate_id}/firewall/virtual_ips");

        // 3. Update VIP (UUID preservation)
        $resUpdate = $this->actingAs($admin)->put("/firewall/{$fw->netgate_id}/firewall/virtual_ips/vip-test-uuid", [
            'mode' => 'ipalias',
            'interface' => 'wan',
            'subnet' => '192.168.240.220',
            'subnet_bits' => 32,
            'descr' => 'Updated Public IP',
        ]);
        $resUpdate->assertRedirect("/firewall/{$fw->netgate_id}/firewall/virtual_ips");

        // 4. Destroy VIP (UUID preservation)
        $resDelete = $this->actingAs($admin)->delete("/firewall/{$fw->netgate_id}/firewall/virtual_ips/vip-test-uuid");
        $resDelete->assertRedirect("/firewall/{$fw->netgate_id}/firewall/virtual_ips");
    }
}
