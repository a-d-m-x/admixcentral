<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Firewall;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IpsecOpnSenseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:' . base64_encode(str_repeat('x', 32))]);
        $this->withoutVite();
    }

    public function test_opnsense_ipsec_lifecycle()
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
            'netgate_id' => 'fw-ipsec-test',
            'url' => 'https://192.168.240.11',
            'api_key' => 'test-key',
            'api_secret' => 'test-secret',
            'os_type' => 'opnsense',
            'company_id' => $company->id,
            'is_online' => true,
        ]);

        Http::fake([
            '*api/ipsec/sessions/search_phase1*' => Http::response([
                'rows' => [
                    [
                        'id' => 'sa-1',
                        'name' => 'HQ-Tunnel',
                        'local-host' => '192.168.240.11',
                        'remote-host' => '198.51.100.1',
                        'state' => 'ESTABLISHED',
                    ],
                ],
                'total' => 1,
            ], 200),
            '*api/ipsec/sessions/search_phase2*' => Http::response([
                'rows' => [],
                'total' => 0,
            ], 200),
            '*api/ipsec/service/status*' => Http::response([
                'status' => 'running',
            ], 200),
            '*api/ipsec/service/reconfigure*' => Http::response([
                'status' => 'ok',
            ], 200),
            '*api/ipsec/connections/searchConnection*' => Http::response([
                'rows' => [
                    [
                        'uuid' => 'p1-uuid-1',
                        'description' => 'Branch Connection',
                        'remote_addrs' => '198.51.100.2',
                        'version' => '2',
                        'enabled' => '1',
                    ],
                ],
                'total' => 1,
            ], 200),
            '*api/ipsec/connections/addConnection*' => Http::response([
                'result' => 'saved',
                'uuid' => 'p1-uuid-new',
            ], 200),
            '*api/ipsec/connections/delConnection/p1-uuid-1*' => Http::response([
                'result' => 'deleted',
            ], 200),
            '*api/ipsec/connections/searchChild*' => Http::response([
                'rows' => [
                    [
                        'uuid' => 'p2-uuid-1',
                        'connection' => 'p1-uuid-1',
                        'description' => 'Subnet 10.0.0.0/24',
                        'mode' => 'tunnel',
                        'local_ts' => '192.168.1.0/24',
                        'remote_ts' => '10.0.0.0/24',
                    ],
                ],
                'total' => 1,
            ], 200),
            '*api/ipsec/connections/addChild*' => Http::response([
                'result' => 'saved',
                'uuid' => 'p2-uuid-new',
            ], 200),
            '*api/ipsec/connections/delChild/p2-uuid-1*' => Http::response([
                'result' => 'deleted',
            ], 200),
            '*api/interfaces/overview*' => Http::response([], 200),
            '*api/diagnostics/interface/getInterfaceNames*' => Http::response(['wan' => 'WAN', 'lan' => 'LAN'], 200),
        ]);

        // 1. IPsec Status View
        $resStatus = $this->actingAs($admin)->get("/firewall/{$fw->netgate_id}/status/ipsec");
        $resStatus->assertStatus(200);
        $resStatus->assertSee('HQ-Tunnel');
        $resStatus->assertSee('198.51.100.1');

        // 2. IPsec Tunnels View (Phase 1 & Phase 2)
        $resTunnels = $this->actingAs($admin)->get("/firewall/{$fw->netgate_id}/vpn/ipsec");
        $resTunnels->assertStatus(200);
        $resTunnels->assertSee('Branch Connection');
        $resTunnels->assertSee('Subnet 10.0.0.0/24');

        // 3. Create Phase 1
        $resCreateP1 = $this->actingAs($admin)->post("/firewall/{$fw->netgate_id}/vpn/ipsec/phase1", [
            'iketype' => 'ikev2',
            'protocol' => 'inet',
            'interface' => 'wan',
            'remote_gateway' => '203.0.113.1',
            'descr' => 'New Branch Tunnel',
            'authentication_method' => 'pre_shared_key',
            'pre_shared_key' => 'secret123456',
            'myid_type' => 'myaddress',
            'peerid_type' => 'peeraddress',
            'encryption_algorithm_name' => 'aes',
            'encryption_algorithm_keylen' => 256,
            'hash_algorithm' => 'sha256',
            'dhgroup' => 14,
            'lifetime' => 28800,
        ]);
        $resCreateP1->assertRedirect("/firewall/{$fw->netgate_id}/vpn/ipsec");
        $resCreateP1->assertSessionHas('success');

        // 4. Delete Phase 1 (UUID preservation)
        $resDelP1 = $this->actingAs($admin)->delete("/firewall/{$fw->netgate_id}/vpn/ipsec/phase1/p1-uuid-1");
        $resDelP1->assertRedirect("/firewall/{$fw->netgate_id}/vpn/ipsec");
        $resDelP1->assertSessionHas('success');

        // 5. Phase 2 Entries View
        $resP2View = $this->actingAs($admin)->get("/firewall/{$fw->netgate_id}/vpn/ipsec/phase2/p1-uuid-1");
        $resP2View->assertStatus(200);
        $resP2View->assertSee('Subnet 10.0.0.0/24');

        // 6. Create Phase 2
        $resCreateP2 = $this->actingAs($admin)->post("/firewall/{$fw->netgate_id}/vpn/ipsec/phase2/p1-uuid-1", [
            'descr' => 'LAN to Remote LAN',
            'mode' => 'tunnel',
            'localid_type' => 'network',
            'localid_address' => '192.168.1.0/24',
            'remoteid_type' => 'network',
            'remoteid_address' => '10.10.0.0/24',
            'protocol' => 'esp',
            'encryption_algorithm_name' => 'aes',
            'encryption_algorithm_keylen' => '256',
            'hash_algorithm' => ['sha256'],
            'pfsgroup' => '14',
            'lifetime' => 3600,
        ]);
        $resCreateP2->assertRedirect("/firewall/{$fw->netgate_id}/vpn/ipsec/phase2/p1-uuid-1");
        $resCreateP2->assertSessionHas('success');

        // 7. Delete Phase 2
        $resDelP2 = $this->actingAs($admin)->delete("/firewall/{$fw->netgate_id}/vpn/ipsec/phase2/p1-uuid-1/p2-uuid-1");
        $resDelP2->assertRedirect("/firewall/{$fw->netgate_id}/vpn/ipsec/phase2/p1-uuid-1");
        $resDelP2->assertSessionHas('success');
    }
}
