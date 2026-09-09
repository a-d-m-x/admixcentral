<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Firewall;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FirewallRulesOpnSenseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:' . base64_encode(str_repeat('x', 32))]);
        $this->withoutVite();
    }

    public function test_opnsense_rule_store_success_with_normalized_payload()
    {
        $company = Company::create(['name' => 'Acme Corp']);
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@central.test',
            'password' => 'password',
            'role' => 'admin',
        ]);
        $fw = Firewall::create([
            'name' => 'opnsense-01.lab.internal',
            'netgate_id' => 'opn-test-rules-01',
            'url' => 'https://192.168.240.11',
            'api_key' => 'test-key',
            'api_secret' => 'test-secret',
            'os_type' => 'opnsense',
            'company_id' => $company->id,
            'is_online' => true,
        ]);

        $recordedPayload = null;

        Http::fake([
            '*api/firewall/filter/addRule*' => function ($request) use (&$recordedPayload) {
                $recordedPayload = $request->data();
                return Http::response([
                    'result' => 'saved',
                    'uuid' => 'test-uuid-1234',
                ], 200);
            },
            '*api/firewall/filter/apply*' => Http::response(['status' => 'OK'], 200),
        ]);

        $response = $this->actingAs($admin)->postJson("/firewall/{$fw->netgate_id}/firewall/rules", [
            'interface' => 'WAN', // Uppercase should normalize to 'wan'
            'type' => 'pass',
            'protocol' => 'tcp',
            'ipprotocol' => 'inet',
            'source_type' => 'network',
            'source_address' => '10.0.0.0/24',
            'destination_type' => 'wan:ip',
            'destination_port_from' => '80',
            'destination_port_to' => '443',
            'descr' => 'Allow Web to WAN IP',
            'statetype' => 'keep state',
            'log' => '1',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->assertNotNull($recordedPayload);
        $rule = $recordedPayload['rule'] ?? [];
        $this->assertEquals('wan', $rule['interface']);
        $this->assertEquals('pass', $rule['action']);
        $this->assertEquals('tcp', $rule['protocol']);
        $this->assertEquals('inet', $rule['ipprotocol']);
        $this->assertEquals('10.0.0.0/24', $rule['source_net']);
        $this->assertEquals('0', $rule['source_not']);
        $this->assertEquals('wanip', $rule['destination_net']); // wan:ip converted to wanip
        $this->assertEquals('80-443', $rule['destination_port']); // 80:443 converted to 80-443
        $this->assertEquals('keep', $rule['statetype']); // keep state converted to keep
        $this->assertEquals('1', $rule['log']);
        $this->assertEquals('Allow Web to WAN IP', $rule['description']);

        $this->assertTrue($fw->fresh()->is_dirty);
    }

    public function test_opnsense_rule_store_validation_failure_surfaces_error()
    {
        $company = Company::create(['name' => 'Acme Corp']);
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@central.test',
            'password' => 'password',
            'role' => 'admin',
        ]);
        $fw = Firewall::create([
            'name' => 'opnsense-01.lab.internal',
            'netgate_id' => 'opn-test-rules-02',
            'url' => 'https://192.168.240.11',
            'api_key' => 'test-key',
            'api_secret' => 'test-secret',
            'os_type' => 'opnsense',
            'company_id' => $company->id,
            'is_online' => true,
        ]);

        Http::fake([
            '*api/firewall/filter/addRule*' => Http::response([
                'result' => 'failed',
                'validations' => [
                    'rule.destination_port' => 'Please specify a valid portnumber, name, alias or range.',
                ],
            ], 200),
        ]);

        $response = $this->actingAs($admin)->postJson("/firewall/{$fw->netgate_id}/firewall/rules", [
            'interface' => 'wan',
            'type' => 'pass',
            'protocol' => 'tcp',
            'source_type' => 'any',
            'destination_type' => 'any',
            'destination_port_from' => 'invalid_port',
            'descr' => 'Failing Rule',
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'error' => 'OPNsense rule error: rule.destination_port: Please specify a valid portnumber, name, alias or range.',
        ]);
    }

    public function test_opnsense_rules_index_displays_ports_and_gateway()
    {
        $company = Company::create(['name' => 'Acme Corp']);
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@central.test',
            'password' => 'password',
            'role' => 'admin',
        ]);
        $fw = Firewall::create([
            'name' => 'opnsense-01.lab.internal',
            'netgate_id' => 'opn-test-rules-03',
            'url' => 'https://192.168.240.11',
            'api_key' => 'test-key',
            'api_secret' => 'test-secret',
            'os_type' => 'opnsense',
            'company_id' => $company->id,
            'is_online' => true,
        ]);

        Http::fake([
            '*api/firewall/filter/searchRule*' => Http::response([
                'rows' => [
                    [
                        'uuid' => 'rule-uuid-1',
                        'enabled' => 1,
                        'action' => 'pass',
                        'interface' => 'wan',
                        'ipprotocol' => 'inet',
                        'protocol' => 'TCP',
                        'source_net' => '96.56.210.116',
                        'source_port' => '',
                        'destination_net' => '192.168.14.111',
                        'destination_port' => '4411',
                        'gateway' => 'WAN_DHCP',
                        'description' => 'NAT: face',
                        'is_automatic' => 0,
                    ],
                ],
            ], 200),
            '*api/interfaces/overview/interfaces*' => Http::response([
                'rows' => [
                    ['identifier' => 'wan', 'device' => 'vtnet0', 'description' => 'WAN', 'status' => 'up', 'ipaddr' => '192.168.240.11/24'],
                    ['identifier' => 'lan', 'device' => 'vtnet1', 'description' => 'LAN', 'status' => 'up', 'ipaddr' => '192.168.11.1/24'],
                ],
            ], 200),
            '*api/diagnostics/interface/getInterfaceData*' => Http::response([
                'wan' => ['inbytes' => 100, 'outbytes' => 200],
            ], 200),
        ]);

        $response = $this->actingAs($admin)->get("/firewall/{$fw->netgate_id}/firewall/rules?interface=wan");

        $response->assertStatus(200);
        $response->assertSee('4411'); // Destination port must be visible!
        $response->assertSee('WAN_DHCP'); // Gateway must be visible!
        $response->assertSee('NAT: face');
    }
}
