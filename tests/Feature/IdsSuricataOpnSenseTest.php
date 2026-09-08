<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Firewall;
use App\Models\User;
use App\Services\OpnSenseApiService;
use App\Services\PfSenseApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IdsSuricataOpnSenseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:' . base64_encode(str_repeat('x', 32))]);
        $this->withoutVite();
    }

    public function test_opnsense_ids_suricata_lifecycle()
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
            'netgate_id' => 'fw-ids-test',
            'url' => 'https://192.168.240.11',
            'api_key' => 'test-key',
            'api_secret' => 'test-secret',
            'os_type' => 'opnsense',
            'company_id' => $company->id,
            'is_online' => true,
        ]);

        Http::fake([
            '*api/ids/service/status*' => Http::response([
                'status' => 'running',
                'widget' => [],
            ], 200),
            '*api/ids/settings/get*' => Http::response([
                'ids' => [
                    'general' => [
                        'enabled' => '1',
                        'mode' => [
                            'pcap' => ['value' => 'PCAP live mode (IDS)', 'selected' => 1],
                            'netmap' => ['value' => 'Netmap (IPS)', 'selected' => 0],
                        ],
                        'promisc' => '0',
                        'syslog' => '1',
                        'syslog_eve' => '0',
                        'LogPayload' => '0',
                    ],
                ],
            ], 200),
            '*api/ids/service/queryAlerts*' => Http::response([
                [
                    'timestamp' => '2026-09-08T18:00:00Z',
                    'src_ip' => '192.168.1.100',
                    'src_port' => 45123,
                    'dest_ip' => '192.168.1.1',
                    'dest_port' => 80,
                    'alert' => [
                        'severity' => 'Alert',
                        'signature' => 'ET SCAN Potential SSH Scan',
                    ],
                ],
            ], 200),
            '*api/ids/settings/listRulesets*' => Http::response([
                'rows' => [
                    [
                        'description' => 'abuse.ch/Feodo Tracker',
                        'filename' => 'abuse.ch.feodotracker.rules',
                        'documentation_url' => 'https://feodotracker.abuse.ch/blocklist/',
                        'enabled' => '0',
                    ],
                ],
                'rowCount' => 1,
                'total' => 1,
            ], 200),
            '*api/ids/settings/searchUserRule*' => Http::response([
                'rows' => [
                    [
                        'uuid' => 'user-rule-uuid-1',
                        'enabled' => '1',
                        'action' => 'drop',
                        '%action' => 'Drop',
                        'description' => 'Block test malicious actor',
                        'source' => '10.0.0.99',
                        'destination' => 'any',
                    ],
                ],
                'rowCount' => 1,
                'total' => 1,
            ], 200),
            '*api/ids/settings/addUserRule*' => Http::response([
                'result' => 'saved',
                'uuid' => 'user-rule-uuid-2',
            ], 200),
            '*api/ids/settings/toggleUserRule/user-rule-uuid-1*' => Http::response([
                'result' => 'Disabled',
                'changed' => true,
            ], 200),
            '*api/ids/settings/delUserRule/user-rule-uuid-1*' => Http::response([
                'result' => 'deleted',
            ], 200),
            '*api/ids/settings/toggleRuleset/abuse.ch.feodotracker.rules*' => Http::response([
                'status' => '1',
            ], 200),
            '*api/ids/settings/set*' => Http::response([
                'result' => 'saved',
            ], 200),
            '*api/ids/service/reconfigure*' => Http::response([
                'status' => 'OK',
            ], 200),
            '*api/ids/service/updateRules*' => Http::response([
                'status' => 'OK',
            ], 200),
            '*api/ids/service/restart*' => Http::response([
                'response' => 'restarted',
            ], 200),
            '*api/ids/service/start*' => Http::response([
                'response' => 'started',
            ], 200),
            '*api/ids/service/stop*' => Http::response([
                'response' => 'stopped',
            ], 200),
        ]);

        // 1. Web UI: IDS Index page
        $resUi = $this->actingAs($admin)->get("/firewall/{$fw->netgate_id}/services/ids");
        $resUi->assertStatus(200);
        $resUi->assertSee('Suricata Engine Status');
        $resUi->assertSee('Running');
        $resUi->assertSee('ET SCAN Potential SSH Scan');
        $resUi->assertSee('abuse.ch/Feodo Tracker');
        $resUi->assertSee('Block test malicious actor');

        // 2. Direct API via PfSenseApiService / OpnSenseApiService delegation
        $api = new PfSenseApiService($fw);
        $status = $api->getIdsStatus();
        $this->assertEquals('running', $status['status']);

        $settings = $api->getIdsSettings();
        $this->assertEquals('1', $settings['ids']['general']['enabled']);

        $alerts = $api->getIdsAlerts();
        $this->assertCount(1, $alerts);
        $this->assertEquals('192.168.1.100', $alerts[0]['src_ip']);

        $rulesets = $api->getIdsRulesets();
        $this->assertEquals(200, $rulesets['status']);
        $this->assertCount(1, $rulesets['data']);
        $this->assertEquals('abuse.ch.feodotracker.rules', $rulesets['data'][0]['filename']);

        $userRules = $api->getIdsUserRules();
        $this->assertEquals(200, $userRules['status']);
        $this->assertCount(1, $userRules['data']);
        $this->assertEquals('Block test malicious actor', $userRules['data'][0]['description']);

        // 3. Web UI: Store User Rule via POST
        $resStoreRule = $this->actingAs($admin)->post("/firewall/{$fw->netgate_id}/services/ids/rules", [
            'action' => 'drop',
            'description' => 'Drop malicious traffic',
            'source' => '192.168.10.50',
            'destination' => 'any',
            'enabled' => 1,
        ]);
        $resStoreRule->assertRedirect();
        $resStoreRule->assertSessionHas('success');

        // 4. Web UI: Toggle User Rule via POST
        $resToggleRule = $this->actingAs($admin)->post("/firewall/{$fw->netgate_id}/services/ids/rules/user-rule-uuid-1/toggle");
        $resToggleRule->assertRedirect();
        $resToggleRule->assertSessionHas('success');

        // 5. Web UI: Delete User Rule via DELETE
        $resDelRule = $this->actingAs($admin)->delete("/firewall/{$fw->netgate_id}/services/ids/rules/user-rule-uuid-1");
        $resDelRule->assertRedirect();
        $resDelRule->assertSessionHas('success');

        // 6. Web UI: Toggle Ruleset via POST
        $resToggleRs = $this->actingAs($admin)->post("/firewall/{$fw->netgate_id}/services/ids/rulesets/abuse.ch.feodotracker.rules/toggle");
        $resToggleRs->assertRedirect();
        $resToggleRs->assertSessionHas('success');

        // 7. Web UI: Update Settings via POST
        $resSettings = $this->actingAs($admin)->post("/firewall/{$fw->netgate_id}/services/ids/settings", [
            'enabled' => 1,
            'mode' => 'pcap',
            'promisc' => 1,
            'syslog' => 1,
        ]);
        $resSettings->assertRedirect();
        $resSettings->assertSessionHas('success');

        // 8. Service actions
        $resRestart = $this->actingAs($admin)->post("/firewall/{$fw->netgate_id}/services/ids/service/restart");
        $resRestart->assertRedirect();
        $resRestart->assertSessionHas('success');

        $resReconf = $this->actingAs($admin)->post("/firewall/{$fw->netgate_id}/services/ids/service/reconfigure");
        $resReconf->assertRedirect();
        $resReconf->assertSessionHas('success');

        $resUpdateRules = $this->actingAs($admin)->post("/firewall/{$fw->netgate_id}/services/ids/service/update-rules");
        $resUpdateRules->assertRedirect();
        $resUpdateRules->assertSessionHas('success');
    }
}
