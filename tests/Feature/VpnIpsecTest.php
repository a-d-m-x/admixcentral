<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Firewall;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VpnIpsecTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:' . base64_encode(str_repeat('x', 32))]);
        $this->withoutVite();
    }

    public function test_pfsense_phase1_and_phase2_creation_and_auto_keylen()
    {
        $company = Company::create(['name' => 'Test Corp']);
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin_pfsense@central.test',
            'password' => 'password',
            'role' => 'admin',
        ]);
        $fw = Firewall::create([
            'name' => 'pfSense Firewall',
            'netgate_id' => 'pfsense-ipsec-test',
            'url' => 'https://192.168.240.15',
            'api_key' => 'admin',
            'api_secret' => 'koedfell',
            'os_type' => 'pfsense',
            'company_id' => $company->id,
            'is_online' => true,
        ]);

        Http::fake([
            '*api/v2/vpn/ipsec/phase1' => function ($request) {
                if ($request->method() === 'POST') {
                    $body = json_decode($request->body(), true);
                    $this->assertEquals('Central Test P1', $body['descr']);
                    $this->assertEquals('aes128gcm', $body['encryption'][0]['encryption_algorithm_name']);
                    $this->assertEquals(128, $body['encryption'][0]['encryption_algorithm_keylen']);
                    return Http::response([
                        'code' => 200,
                        'status' => 'ok',
                        'data' => ['id' => 0, 'ikeid' => 1, 'descr' => 'Central Test P1'],
                    ], 200);
                }
                return Http::response([], 200);
            },
            '*api/v2/vpn/ipsec/phase2' => function ($request) {
                if ($request->method() === 'POST') {
                    $body = json_decode($request->body(), true);
                    // Verify keylen is integer 0, NOT string 'auto'!
                    $this->assertSame(0, $body['encryption_algorithm_option'][0]['keylen']);
                    $this->assertEquals('aes', $body['encryption_algorithm_option'][0]['name']);
                    $this->assertEquals(14, $body['pfsgroup']);
                    return Http::response([
                        'code' => 200,
                        'status' => 'ok',
                        'data' => [
                            'id' => 0,
                            'uniqid' => '6aa1234567890',
                            'ikeid' => 1,
                            'descr' => 'Central Test P2 Auto Keylen',
                        ],
                    ], 200);
                }
                return Http::response([], 200);
            },
        ]);

        // 1. Create Phase 1
        $p1Res = $this->actingAs($admin)->post(route('vpn.ipsec.phase1.store', $fw), [
            'iketype' => 'ikev2',
            'protocol' => 'inet',
            'interface' => 'wan',
            'remote_gateway' => '172.21.11.199',
            'descr' => 'Central Test P1',
            'authentication_method' => 'pre_shared_key',
            'pre_shared_key' => 'secret123456',
            'myid_type' => 'myaddress',
            'peerid_type' => 'peeraddress',
            'encryption_algorithm_name' => 'aes128gcm',
            'encryption_algorithm_keylen' => '128',
            'hash_algorithm' => 'sha384',
            'dhgroup' => 14,
            'lifetime' => 28800,
        ]);
        $p1Res->assertRedirect(route('vpn.ipsec', $fw));
        $p1Res->assertSessionHas('success');

        // 2. Create Phase 2 with 'auto' keylen (previously caused Field 'keylen' must be of type 'integer')
        $p2Res = $this->actingAs($admin)->post(route('vpn.ipsec.phase2.store', [$fw, 1]), [
            'descr' => 'Central Test P2 Auto Keylen',
            'mode' => 'tunnel',
            'localid_type' => 'lan',
            'remoteid_type' => 'network',
            'remoteid_address' => '10.200.1.0',
            'remoteid_netbits' => 24,
            'protocol' => 'esp',
            'encryption_algorithm_name' => 'aes',
            'encryption_algorithm_keylen' => 'auto',
            'hash_algorithm' => ['hmac_sha256', 'hmac_sha512'],
            'pfsgroup' => 14,
            'lifetime' => 3600,
        ]);
        $p2Res->assertRedirect(route('vpn.ipsec.phase2', [$fw, 1]));
        $p2Res->assertSessionHas('success');
    }
}
