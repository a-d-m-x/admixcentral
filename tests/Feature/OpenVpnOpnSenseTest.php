<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Firewall;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenVpnOpnSenseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:' . base64_encode(str_repeat('x', 32))]);
        $this->withoutVite();
    }

    public function test_opnsense_openvpn_pages_and_instances()
    {
        $company = Company::create(['name' => 'Acme Corp']);
        $user = User::create([
            'name' => 'Admin User',
            'email' => 'admin@central.test',
            'password' => 'password',
            'role' => 'admin',
        ]);
        $fw = Firewall::create([
            'name' => 'OPNsense Firewall',
            'netgate_id' => 'fw-openvpn-test',
            'url' => 'https://192.168.240.11',
            'api_key' => 'test-key',
            'api_secret' => 'test-secret',
            'os_type' => 'opnsense',
            'company_id' => $company->id,
            'is_online' => true,
        ]);

        Http::fake([
            '*api/openvpn/instances/search*' => Http::response([
                'rows' => [
                    [
                        'uuid' => 'ovpn-srv-1',
                        'role' => 'server',
                        'proto' => 'UDP',
                        'port' => '1194',
                        'dev_type' => 'tun',
                        'server' => '10.8.0.0/24',
                        'description' => 'Office Remote VPN',
                        'enabled' => '1',
                    ],
                    [
                        'uuid' => 'ovpn-cli-1',
                        'role' => 'client',
                        'proto' => 'UDP',
                        'port' => '1195',
                        'dev_type' => 'tun',
                        'remote' => 'vpn.example.com',
                        'description' => 'Branch Connection',
                        'enabled' => '1',
                    ],
                ],
                'rowCount' => 2,
                'total' => 2,
            ], 200),
            '*api/openvpn/instances/del/*' => Http::response([
                'result' => 'deleted',
            ], 200),
        ]);

        // 1. View Servers Page
        $resServers = $this->actingAs($user)->get("/firewall/{$fw->netgate_id}/vpn/openvpn/server");
        $resServers->assertStatus(200);
        $resServers->assertSee('Office Remote VPN');
        $resServers->assertSee('10.8.0.0/24');

        // 2. View Clients Page
        $resClients = $this->actingAs($user)->get("/firewall/{$fw->netgate_id}/vpn/openvpn/client");
        $resClients->assertStatus(200);
        $resClients->assertSee('Branch Connection');
        $resClients->assertSee('vpn.example.com');

        // 3. View OpenVPN Status Page
        $resStatus = $this->actingAs($user)->get("/firewall/{$fw->netgate_id}/status/openvpn");
        $resStatus->assertStatus(200);
        $resStatus->assertSee('Office Remote VPN');
        $resStatus->assertSee('Up');

        // 4. Delete Server Instance
        $resDel = $this->actingAs($user)->delete("/firewall/{$fw->netgate_id}/vpn/openvpn/server/ovpn-srv-1");
        $resDel->assertRedirect();
        $resDel->assertSessionHas('success');
    }
}
