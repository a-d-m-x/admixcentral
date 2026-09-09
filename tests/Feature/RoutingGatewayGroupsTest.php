<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Firewall;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RoutingGatewayGroupsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:' . base64_encode(str_repeat('x', 32))]);
        $this->withoutVite();
    }

    public function test_opnsense_gateway_groups_read_only_and_rejected()
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
            'netgate_id' => 'fw-opn-gg',
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
        ]);

        // 1. Gateway Groups tab renders with OPNsense information notice and no Add button trigger
        $response = $this->actingAs($admin)->get("/firewall/{$fw->netgate_id}/system/routing?tab=gateway_groups");
        $response->assertStatus(200);
        $response->assertSee('Gateway Groups on OPNsense are managed directly in the OPNsense Web GUI');
        $response->assertSee('system_gateway_groups.php');
        $response->assertDontSee('@click="openGatewayGroupModal()"', false);

        // 2. Attempting to create a gateway group on OPNsense is rejected
        $resCreate = $this->actingAs($admin)->post("/firewall/{$fw->netgate_id}/system/routing/gateway-groups", [
            'name' => 'TestGroup',
            'trigger' => 'down',
            'tiers' => ['WAN_DHCP' => '1'],
        ]);
        $resCreate->assertSessionHasErrors(['error']);
        $this->assertStringContainsString('not supported via API on OPNsense', session('errors')->first('error'));

        // 3. Attempting to update a gateway group on OPNsense is rejected
        $resUpdate = $this->actingAs($admin)->patch("/firewall/{$fw->netgate_id}/system/routing/gateway-groups/some-id", [
            'name' => 'TestGroup',
            'trigger' => 'down',
            'tiers' => ['WAN_DHCP' => '1'],
        ]);
        $resUpdate->assertSessionHasErrors(['error']);

        // 4. Attempting to delete a gateway group on OPNsense is rejected
        $resDelete = $this->actingAs($admin)->delete("/firewall/{$fw->netgate_id}/system/routing/gateway-groups/some-id");
        $resDelete->assertSessionHasErrors(['error']);
    }

    public function test_pfsense_gateway_groups_lifecycle_and_tier_conversion()
    {
        $company = Company::create(['name' => 'Acme Corp']);
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@central.test',
            'password' => 'password',
            'role' => 'admin',
        ]);
        $fw = Firewall::create([
            'name' => 'pfSense Firewall',
            'netgate_id' => 'fw-pfsense-gg',
            'url' => 'https://192.168.1.1',
            'api_key' => 'test-key',
            'api_secret' => 'test-secret',
            'os_type' => 'pfsense',
            'company_id' => $company->id,
            'is_online' => true,
        ]);

        Http::fake([
            '*routing/gateway/groups*' => Http::response([
                'status' => 'ok',
                'code' => 200,
                'data' => [
                    [
                        'id' => '0',
                        'name' => 'FailoverGW',
                        'item' => ['WAN_DHCP|1', 'OPT1_DHCP|2'],
                        'trigger' => 'down',
                        'descr' => 'Primary failover group',
                    ],
                ],
            ], 200),
            '*routing/gateway*' => Http::response([
                'status' => 'ok',
                'code' => 200,
                'data' => [
                    ['name' => 'WAN_DHCP', 'gateway' => '192.168.1.254', 'interface' => 'wan'],
                    ['name' => 'OPT1_DHCP', 'gateway' => '192.168.2.254', 'interface' => 'opt1'],
                ],
            ], 200),
        ]);

        // 1. Gateway Groups tab renders for pfSense with Add button trigger
        $response = $this->actingAs($admin)->get("/firewall/{$fw->netgate_id}/system/routing?tab=gateway_groups");
        $response->assertStatus(200);
        $response->assertSee('openGatewayGroupModal()');
        $response->assertSee('FailoverGW');

        // 2. Validation fails if no gateway is selected in tiers
        $resNoTier = $this->actingAs($admin)->post("/firewall/{$fw->netgate_id}/system/routing/gateway-groups", [
            'name' => 'NewGroup',
            'trigger' => 'down',
            'tiers' => ['WAN_DHCP' => 'never'],
        ]);
        $resNoTier->assertSessionHasErrors(['item']);

        // 3. Create gateway group converts tiers to item array
        $resCreate = $this->actingAs($admin)->post("/firewall/{$fw->netgate_id}/system/routing/gateway-groups", [
            'name' => 'NewGroup',
            'trigger' => 'down',
            'tiers' => ['WAN_DHCP' => '1', 'OPT1_DHCP' => '2'],
            'descr' => 'Dual WAN failover',
        ]);
        $resCreate->assertRedirect("/firewall/{$fw->netgate_id}/system/routing?tab=gateway_groups");
        $resCreate->assertSessionHas('success');

        Http::assertSent(function ($request) {
            if (str_contains($request->url(), '/routing/gateway/group') && $request->method() === 'POST') {
                return $request['item'] === ['WAN_DHCP|1', 'OPT1_DHCP|2']
                    && $request['name'] === 'NewGroup';
            }
            return true;
        });

        // 4. Update gateway group
        $resUpdate = $this->actingAs($admin)->patch("/firewall/{$fw->netgate_id}/system/routing/gateway-groups/0", [
            'name' => 'UpdatedGroup',
            'trigger' => 'packetloss',
            'tiers' => ['WAN_DHCP' => '1'],
            'descr' => 'Updated descr',
        ]);
        $resUpdate->assertRedirect("/firewall/{$fw->netgate_id}/system/routing?tab=gateway_groups");

        // 5. Delete gateway group
        $resDelete = $this->actingAs($admin)->delete("/firewall/{$fw->netgate_id}/system/routing/gateway-groups/0");
        $resDelete->assertRedirect("/firewall/{$fw->netgate_id}/system/routing?tab=gateway_groups");
    }
}
