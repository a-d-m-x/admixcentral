<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Firewall;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IpsecStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:' . base64_encode(str_repeat('x', 32))]);
        $this->withoutVite();
    }

    protected function createPfSenseFirewall(): array
    {
        $company = Company::create(['name' => 'Acme Corp']);
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@central.test',
            'password' => 'password',
            'role' => 'admin',
        ]);
        $readonly = User::create([
            'name' => 'Readonly User',
            'email' => 'readonly@central.test',
            'password' => 'password',
            'role' => 'readonly',
        ]);
        $fw = Firewall::create([
            'name' => 'pfSense Firewall',
            'netgate_id' => 'fw-pfsense-status-test',
            'url' => 'https://192.168.240.15',
            'api_key' => 'admin',
            'api_secret' => 'pfsense',
            'os_type' => 'pfsense',
            'company_id' => $company->id,
            'is_online' => true,
        ]);

        return [$admin, $readonly, $fw];
    }

    public function test_ipsec_status_overview_renders_phase1_and_child_sas()
    {
        [$admin, , $fw] = $this->createPfSenseFirewall();

        Http::fake([
            '*api/v2/status/ipsec/sas*' => Http::response([
                'code' => 200,
                'status' => 'ok',
                'data' => [
                    [
                        'name' => 'con1',
                        'uniqueid' => 8040,
                        'version' => 2,
                        'state' => 'ESTABLISHED',
                        'local_host' => '192.0.2.1',
                        'local_port' => 500,
                        'local_id' => '192.0.2.1',
                        'remote_host' => '198.51.100.1',
                        'remote_port' => 500,
                        'remote_id' => '198.51.100.1',
                        'initiator' => 'yes',
                        'initiator_spi' => '3ca981bbd77648ff',
                        'responder_spi' => '9ba383eec1cf4635',
                        'encr_alg' => 'AES_CBC',
                        'encr_keysize' => 256,
                        'integ_alg' => 'HMAC_SHA2_256_128',
                        'prf_alg' => 'PRF_HMAC_SHA2_256',
                        'dh_group' => 'MODP_2048',
                        'established' => 2580,
                        'rekey_time' => 7467,
                        'reauth_time' => 8120,
                        'child_sas' => [
                            [
                                'name' => 'con1_1',
                                'uniqueid' => 8039,
                                'state' => 'INSTALLED',
                                'mode' => 'tunnel',
                                'protocol' => 'ESP',
                                'spi_in' => 'c29a8a77',
                                'spi_out' => 'c9b0e1e2',
                                'encr_alg' => 'AES_CBC',
                                'encr_keysize' => 256,
                                'integ_alg' => 'HMAC_SHA2_256_128',
                                'dh_group' => 'MODP_2048',
                                'bytes_in' => 1258291,
                                'bytes_out' => 2516582,
                                'packets_in' => 12345,
                                'packets_out' => 23456,
                                'rekey_time' => 2543,
                                'life_time' => 3600,
                                'install_time' => 1057,
                                'local_ts' => ['10.10.1.0/24'],
                                'remote_ts' => ['10.10.2.0/24'],
                            ]
                        ]
                    ]
                ]
            ], 200),
            '*api/v2/vpn/ipsec/phase1s*' => Http::response([
                'code' => 200,
                'status' => 'ok',
                'data' => [
                    [
                        'ikeid' => 1,
                        'descr' => 'EastNY-BayRidge',
                        'remote_gateway' => '198.51.100.1',
                    ]
                ]
            ], 200),
            '*api/v2/vpn/ipsec/phase2s*' => Http::response([
                'code' => 200,
                'status' => 'ok',
                'data' => [
                    [
                        'uniqid' => 'p2-1',
                        'descr' => 'EastNY-BayRidge-subnet',
                    ]
                ]
            ], 200),
        ]);

        $res = $this->actingAs($admin)->get("/firewall/{$fw->netgate_id}/status/ipsec");
        $res->assertStatus(200);

        // Sub tabs
        $res->assertSee('Overview');
        $res->assertSee('Leases');
        $res->assertSee('SADs');
        $res->assertSee('SPDs');

        // Phase 1 SA
        $res->assertSee('con1');
        $res->assertSee('#8040');
        $res->assertSee('EastNY-BayRidge');
        $res->assertSee('192.0.2.1');
        $res->assertSee('198.51.100.1');
        $res->assertSee('3ca981bbd77648ff');
        $res->assertSee('IKEv2');
        $res->assertSee('Initiator');
        $res->assertSee('AES_CBC');
        $res->assertSee('HMAC_SHA2_256_128');
        $res->assertSee('Established');
        $res->assertSee('Disconnect P1');

        // Child SA
        $res->assertSee('con1_1');
        $res->assertSee('#8039');
        $res->assertSee('10.10.1.0/24');
        $res->assertSee('10.10.2.0/24');
        $res->assertSee('c29a8a77');
        $res->assertSee('c9b0e1e2');
        $res->assertSee('Installed');
        $res->assertSee('Show child SA entries');
        $res->assertSee('Disconnect P2');
    }

    public function test_ipsec_status_leases_tab()
    {
        [$admin, , $fw] = $this->createPfSenseFirewall();

        Http::fake([
            '*api/v2/diagnostics/command_prompt*' => Http::response([
                'code' => 200,
                'status' => 'ok',
                'data' => [
                    'output' => json_encode([
                        'pool' => [
                            [
                                'name' => 'RoadWarriorPool',
                                'base' => '10.200.0.0/24',
                                'online' => 2,
                                'offline' => 3,
                                'size' => 10,
                            ]
                        ],
                        'leases' => [
                            [
                                'id' => 'mobile-user-1',
                                'host' => '203.0.113.50',
                                'address' => '10.200.0.5',
                                'status' => 'Online',
                            ]
                        ]
                    ]),
                    'result_code' => 0,
                ]
            ], 200),
        ]);

        $res = $this->actingAs($admin)->get("/firewall/{$fw->netgate_id}/status/ipsec?tab=leases");
        $res->assertStatus(200);
        $res->assertSee('RoadWarriorPool');
        $res->assertSee('10.200.0.0/24');
        $res->assertSee('mobile-user-1');
        $res->assertSee('10.200.0.5');
    }

    public function test_ipsec_status_sads_tab_and_deletion()
    {
        [$admin, , $fw] = $this->createPfSenseFirewall();

        Http::fake([
            '*api/v2/diagnostics/command_prompt*' => Http::response([
                'code' => 200,
                'status' => 'ok',
                'data' => [
                    'output' => json_encode([
                        [
                            'src' => '192.0.2.1',
                            'dst' => '198.51.100.1',
                            'proto' => 'esp',
                            'spi' => 'c29a8a77',
                            'ealgo' => 'AES-CBC 256',
                            'aalgo' => 'HMAC-SHA256',
                            'data' => '1258291 bytes',
                        ]
                    ]),
                    'result_code' => 0,
                ]
            ], 200),
        ]);

        $res = $this->actingAs($admin)->get("/firewall/{$fw->netgate_id}/status/ipsec?tab=sads");
        $res->assertStatus(200);
        $res->assertSee('192.0.2.1');
        $res->assertSee('198.51.100.1');
        $res->assertSee('c29a8a77');
        $res->assertSee('AES-CBC 256');

        // Test delete SAD
        $delRes = $this->actingAs($admin)->post("/firewall/{$fw->netgate_id}/status/ipsec/sad/delete", [
            'src' => '192.0.2.1',
            'dst' => '198.51.100.1',
            'proto' => 'esp',
            'spi' => 'c29a8a77',
        ]);
        $delRes->assertRedirect("/firewall/{$fw->netgate_id}/status/ipsec?tab=sads");
        $delRes->assertSessionHas('success');
    }

    public function test_ipsec_status_spds_tab()
    {
        [$admin, , $fw] = $this->createPfSenseFirewall();

        Http::fake([
            '*api/v2/diagnostics/command_prompt*' => Http::response([
                'code' => 200,
                'status' => 'ok',
                'data' => [
                    'output' => json_encode([
                        [
                            'scope' => 'tunnel',
                            'srcid' => '10.10.1.0/24',
                            'dstid' => '10.10.2.0/24',
                            'dir' => 'out',
                            'proto' => 'any',
                            'src' => '192.0.2.1',
                            'dst' => '198.51.100.1',
                        ]
                    ]),
                    'result_code' => 0,
                ]
            ], 200),
        ]);

        $res = $this->actingAs($admin)->get("/firewall/{$fw->netgate_id}/status/ipsec?tab=spds");
        $res->assertStatus(200);
        $res->assertSee('10.10.1.0/24');
        $res->assertSee('10.10.2.0/24');
        $res->assertSee('Tunnel');
        $res->assertSee('Outbound');
    }

    public function test_ipsec_disconnect_and_connect_actions()
    {
        [$admin, $readonly, $fw] = $this->createPfSenseFirewall();

        Http::fake([
            '*api/v2/diagnostics/command_prompt*' => Http::response([
                'code' => 200,
                'status' => 'ok',
                'data' => [
                    'output' => '',
                    'result_code' => 0,
                ]
            ], 200),
        ]);

        // Disconnect P1
        $resDiscP1 = $this->actingAs($admin)->post("/firewall/{$fw->netgate_id}/status/ipsec/disconnect", [
            'type' => 'p1',
            'conid' => 'con1',
            'uniqueid' => '8040',
        ]);
        $resDiscP1->assertSessionHas('success');

        // Disconnect P2
        $resDiscP2 = $this->actingAs($admin)->post("/firewall/{$fw->netgate_id}/status/ipsec/disconnect", [
            'type' => 'p2',
            'name' => 'con1_1',
            'uniqueid' => '8039',
        ]);
        $resDiscP2->assertSessionHas('success');

        // Connect P1
        $resConnP1 = $this->actingAs($admin)->post("/firewall/{$fw->netgate_id}/status/ipsec/connect", [
            'type' => 'p1',
            'conid' => 'con1',
        ]);
        $resConnP1->assertSessionHas('success');

        // Readonly user is blocked
        $resReadonly = $this->actingAs($readonly)->post("/firewall/{$fw->netgate_id}/status/ipsec/disconnect", [
            'type' => 'p1',
            'conid' => 'con1',
            'uniqueid' => '8040',
        ]);
        $resReadonly->assertStatus(403);
    }
}
