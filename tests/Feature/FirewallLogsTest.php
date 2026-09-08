<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Firewall;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FirewallLogsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:' . base64_encode(str_repeat('x', 32))]);
        $this->withoutVite();
    }

    public function test_opnsense_firewall_logs_display_in_system_logs()
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
            'netgate_id' => 'fw-log-test',
            'url' => 'https://192.168.240.11',
            'api_key' => 'test-key',
            'api_secret' => 'test-secret',
            'os_type' => 'opnsense',
            'company_id' => $company->id,
            'is_online' => true,
        ]);

        Http::fake([
            '*api/diagnostics/firewall/log*' => Http::response([
                [
                    'rulenr' => '69',
                    'action' => 'pass',
                    'dir' => 'out',
                    'interface' => 'vtnet0',
                    'protoname' => 'udp',
                    'src' => '192.168.240.11',
                    'srcport' => '123',
                    'dst' => '207.58.172.126',
                    'dstport' => '123',
                    'label' => 'let out anything from firewall',
                    '__timestamp__' => '2026-09-08T17:37:45-04:00',
                ],
                [
                    'rulenr' => '12',
                    'action' => 'block',
                    'dir' => 'in',
                    'interface' => 'vtnet0',
                    'protoname' => 'tcp',
                    'src' => '10.0.0.99',
                    'srcport' => '44321',
                    'dst' => '192.168.240.11',
                    'dstport' => '22',
                    'label' => 'block ssh',
                    '__timestamp__' => '2026-09-08T17:35:00-04:00',
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)->get("/firewall/{$fw->netgate_id}/status/system-logs?type=firewall");
        $response->assertStatus(200);
        $response->assertSee('PASS');
        $response->assertSee('BLOCK');
        $response->assertSee('vtnet0');
        $response->assertSee('192.168.240.11:123');
        $response->assertSee('207.58.172.126:123');
        $response->assertSee('let out anything from firewall');
        $response->assertSee('block ssh');
    }

    public function test_opnsense_system_logs_display_in_system_logs()
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
            'netgate_id' => 'fw-log-sys-test',
            'url' => 'https://192.168.240.11',
            'api_key' => 'test-key',
            'api_secret' => 'test-secret',
            'os_type' => 'opnsense',
            'company_id' => $company->id,
            'is_online' => true,
        ]);

        Http::fake([
            '*api/diagnostics/log/core/system*' => Http::response([
                'rows' => [
                    [
                        'timestamp' => '2026-09-08T17:31:51-04:00',
                        'process_name' => 'kernel',
                        'pid' => '1234',
                        'line' => 'link state changed to UP',
                    ],
                ],
                'total_rows' => 1,
            ], 200),
        ]);

        $response = $this->actingAs($user)->get("/firewall/{$fw->netgate_id}/status/system-logs?type=system");
        $response->assertStatus(200);
        $response->assertSee('kernel');
        $response->assertSee('1234');
        $response->assertSee('link state changed to UP');
    }
}
