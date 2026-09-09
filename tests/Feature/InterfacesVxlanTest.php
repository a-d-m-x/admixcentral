<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Firewall;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InterfacesVxlanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:' . base64_encode(str_repeat('x', 32))]);
        $this->withoutVite();
    }

    private function fixture(string $role = 'admin', string $osType = 'opnsense'): array
    {
        $company = Company::create(['name' => 'Acme Corp']);
        $user = User::create([
            'name' => 'Test User',
            'email' => $role . '@central.test',
            'password' => 'password',
            'role' => $role,
            'company_id' => $role === 'admin' ? null : $company->id,
        ]);
        $firewall = Firewall::create([
            'name' => 'Test Firewall',
            'netgate_id' => 'fw-vxlan-test',
            'url' => 'https://192.168.240.11',
            'api_key' => 'test-key',
            'api_secret' => 'test-secret',
            'os_type' => $osType,
            'company_id' => $company->id,
            'is_online' => true,
        ]);

        return [$user, $firewall];
    }

    public function test_vxlan_crud_lifecycle()
    {
        [$user, $fw] = $this->fixture();

        Http::fake([
            '*api/interfaces/overview/interfacesInfo*' => Http::response(['rows' => []], 200),
            '*api/diagnostics/interface/getInterfaceStatistics*' => Http::response(['statistics' => []], 200),
            '*api/interfaces/vxlan_settings/searchItem*' => Http::response([
                'rows' => [
                    [
                        'uuid' => 'vxlan-uuid-1',
                        'deviceId' => 1,
                        'vxlanid' => 100,
                        'vxlanlocal' => '192.168.240.11',
                        'vxlanremote' => '192.168.240.12',
                        'vxlandev' => '',
                    ]
                ],
                'rowCount' => 1,
                'total' => 1,
            ], 200),
            '*api/interfaces/vxlan_settings/getItem*' => Http::response([
                'vxlan' => [
                    'deviceId' => 1,
                    'vxlanid' => 100,
                    'vxlanlocal' => '192.168.240.11',
                    'vxlanremote' => '192.168.240.12',
                    'vxlandev' => '',
                ]
            ], 200),
            '*api/interfaces/vxlan_settings/addItem*' => Http::response([
                'result' => 'saved',
                'uuid' => 'vxlan-uuid-2',
            ], 200),
            '*api/interfaces/vxlan_settings/setItem*' => Http::response([
                'result' => 'saved',
            ], 200),
            '*api/interfaces/vxlan_settings/delItem*' => Http::response([
                'result' => 'deleted',
            ], 200),
            '*api/interfaces/vxlan_settings/reconfigure*' => Http::response([
                'status' => 'ok',
            ], 200),
            '*api/interfaces/overview/interfacesInfo*' => Http::response([], 200),
        ]);

        // 1. Index
        $resIndex = $this->actingAs($user)->get("/firewall/{$fw->netgate_id}/interfaces/vxlans");
        $resIndex->assertStatus(200);
        $resIndex->assertSee('vxlan1');
        $resIndex->assertSee('100');
        $resIndex->assertSee('192.168.240.11');

        // 2. Create Page
        $resCreate = $this->actingAs($user)->get("/firewall/{$fw->netgate_id}/interfaces/vxlans/create");
        $resCreate->assertStatus(200);

        // 3. Store
        $resStore = $this->actingAs($user)->post("/firewall/{$fw->netgate_id}/interfaces/vxlans", [
            'deviceId' => 2,
            'vxlanid' => 200,
            'vxlanlocal' => '192.168.240.11',
            'vxlanremote' => '192.168.240.13',
        ]);
        $resStore->assertRedirect("/firewall/{$fw->netgate_id}/interfaces/vxlans");
        $resStore->assertSessionHas('success');

        // 4. Edit Page
        $resEdit = $this->actingAs($user)->get("/firewall/{$fw->netgate_id}/interfaces/vxlans/vxlan-uuid-1/edit");
        $resEdit->assertStatus(200);
        $resEdit->assertSee('192.168.240.11');

        // 5. Update
        $resUpdate = $this->actingAs($user)->patch("/firewall/{$fw->netgate_id}/interfaces/vxlans/vxlan-uuid-1", [
            'deviceId' => 1,
            'vxlanid' => 101,
            'vxlanlocal' => '192.168.240.11',
            'vxlanremote' => '192.168.240.14',
        ]);
        $resUpdate->assertRedirect("/firewall/{$fw->netgate_id}/interfaces/vxlans");
        $resUpdate->assertSessionHas('success');

        // 6. Destroy
        $resDestroy = $this->actingAs($user)->delete("/firewall/{$fw->netgate_id}/interfaces/vxlans/vxlan-uuid-1");
        $resDestroy->assertRedirect("/firewall/{$fw->netgate_id}/interfaces/vxlans");
        $resDestroy->assertSessionHas('success');
    }

    public function test_vxlan_unsupported_on_pfsense()
    {
        [$user, $fw] = $this->fixture('admin', 'pfsense');

        $res = $this->actingAs($user)->get("/firewall/{$fw->netgate_id}/interfaces/vxlans");
        $res->assertStatus(200);
        $res->assertSee('API Not Supported');
    }

    public function test_readonly_user_cannot_modify_vxlans()
    {
        [$user, $fw] = $this->fixture('readonly', 'opnsense');

        $resStore = $this->actingAs($user)->post("/firewall/{$fw->netgate_id}/interfaces/vxlans", [
            'deviceId' => 3,
            'vxlanid' => 300,
            'vxlanlocal' => '192.168.240.11',
            'vxlanremote' => '192.168.240.15',
        ]);
        $resStore->assertStatus(403);

        $resDestroy = $this->actingAs($user)->delete("/firewall/{$fw->netgate_id}/interfaces/vxlans/some-uuid");
        $resDestroy->assertStatus(403);
    }
}
