<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Firewall;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InterfacesLoopbackTest extends TestCase
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
            'netgate_id' => 'fw-loopback-test',
            'url' => 'https://192.168.240.11',
            'api_key' => 'test-key',
            'api_secret' => 'test-secret',
            'os_type' => $osType,
            'company_id' => $company->id,
            'is_online' => true,
        ]);

        return [$user, $firewall];
    }

    public function test_loopback_crud_lifecycle()
    {
        [$user, $fw] = $this->fixture();

        Http::fake([
            '*/api/interfaces/loopback_settings/searchItem' => Http::response([
                'rows' => [
                    [
                        'uuid' => 'loopback-uuid-1',
                        'deviceId' => 1,
                        'description' => 'Primary Loopback',
                    ]
                ],
                'rowCount' => 1,
                'total' => 1,
            ], 200),
            '*api/interfaces/loopback_settings/getItem*' => Http::response([
                'loopback' => [
                    'deviceId' => 1,
                    'description' => 'Primary Loopback',
                ]
            ], 200),
            '*api/interfaces/loopback_settings/addItem*' => Http::response([
                'result' => 'saved',
                'uuid' => 'loopback-uuid-2',
            ], 200),
            '*api/interfaces/loopback_settings/setItem*' => Http::response([
                'result' => 'saved',
            ], 200),
            '*api/interfaces/loopback_settings/delItem*' => Http::response([
                'result' => 'deleted',
            ], 200),
            '*api/interfaces/loopback_settings/reconfigure*' => Http::response([
                'status' => 'ok',
            ], 200),
        ]);

        // 1. Index
        $resIndex = $this->actingAs($user)->get("/firewall/{$fw->netgate_id}/interfaces/loopbacks");
        $resIndex->assertStatus(200);
        $resIndex->assertSee('Primary Loopback');
        $resIndex->assertSee('lo1');

        // 2. Create Page
        $resCreate = $this->actingAs($user)->get("/firewall/{$fw->netgate_id}/interfaces/loopbacks/create");
        $resCreate->assertStatus(200);

        // 3. Store
        $resStore = $this->actingAs($user)->post("/firewall/{$fw->netgate_id}/interfaces/loopbacks", [
            'deviceId' => 2,
            'description' => 'Secondary Loopback',
        ]);
        $resStore->assertRedirect("/firewall/{$fw->netgate_id}/interfaces/loopbacks");
        $resStore->assertSessionHas('success');

        // 4. Edit Page
        $resEdit = $this->actingAs($user)->get("/firewall/{$fw->netgate_id}/interfaces/loopbacks/loopback-uuid-1/edit");
        $resEdit->assertStatus(200);
        $resEdit->assertSee('Primary Loopback');

        // 5. Update
        $resUpdate = $this->actingAs($user)->patch("/firewall/{$fw->netgate_id}/interfaces/loopbacks/loopback-uuid-1", [
            'deviceId' => 1,
            'description' => 'Updated Loopback',
        ]);
        $resUpdate->assertRedirect("/firewall/{$fw->netgate_id}/interfaces/loopbacks");
        $resUpdate->assertSessionHas('success');

        // 6. Destroy
        $resDestroy = $this->actingAs($user)->delete("/firewall/{$fw->netgate_id}/interfaces/loopbacks/loopback-uuid-1");
        $resDestroy->assertRedirect("/firewall/{$fw->netgate_id}/interfaces/loopbacks");
        $resDestroy->assertSessionHas('success');
    }

    public function test_loopback_unsupported_on_pfsense()
    {
        [$user, $fw] = $this->fixture('admin', 'pfsense');

        $res = $this->actingAs($user)->get("/firewall/{$fw->netgate_id}/interfaces/loopbacks");
        $res->assertStatus(200);
        $res->assertSee('API Not Supported');
    }

    public function test_readonly_user_cannot_modify_loopbacks()
    {
        [$user, $fw] = $this->fixture('readonly', 'opnsense');

        $resStore = $this->actingAs($user)->post("/firewall/{$fw->netgate_id}/interfaces/loopbacks", [
            'deviceId' => 3,
            'description' => 'Unauthorized Loopback',
        ]);
        $resStore->assertStatus(403);

        $resDestroy = $this->actingAs($user)->delete("/firewall/{$fw->netgate_id}/interfaces/loopbacks/some-uuid");
        $resDestroy->assertStatus(403);
    }
}
