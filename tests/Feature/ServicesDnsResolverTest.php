<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Firewall;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ServicesDnsResolverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:' . base64_encode(str_repeat('x', 32))]);
        $this->withoutVite();
    }

    private function fixture(): array
    {
        $company = Company::create(['name' => 'Acme Corp']);
        $admin = User::create([
            'name' => 'Global Admin',
            'email' => 'admin@central.test',
            'password' => 'password',
            'role' => 'admin',
            'company_id' => null,
        ]);
        $firewall = Firewall::create([
            'name' => 'OPNsense Unit',
            'netgate_id' => 'opn-test123',
            'url' => 'https://192.168.240.11',
            'api_key' => 'test-key',
            'api_secret' => 'test-secret',
            'os_type' => 'opnsense',
            'company_id' => $company->id,
            'is_online' => true,
        ]);

        return [$admin, $firewall];
    }

    public function test_opnsense_dns_resolver_pages_and_crud()
    {
        [$user, $fw] = $this->fixture();

        Http::fake([
            '*/api/unbound/settings/get*' => Http::response([
                'unbound' => [
                    'general' => ['enabled' => '1', 'port' => '53', 'dnssec' => '0'],
                    'forwarding' => ['enabled' => '0'],
                ]
            ], 200),
            '*/api/unbound/settings/searchHostOverride' => Http::response([
                'rows' => [
                    [
                        'uuid' => 'test-host-uuid-1',
                        'hostname' => 'printer',
                        'domain' => 'acme.test',
                        'server' => '10.0.0.50',
                        'description' => 'Office Printer',
                        'enabled' => '1',
                    ]
                ],
                'rowCount' => 1,
                'total' => 1,
            ], 200),
            '*/api/unbound/settings/addHostOverride' => Http::response([
                'result' => 'saved',
                'uuid' => 'test-host-uuid-2'
            ], 200),
            '*/api/unbound/settings/delHostOverride/*' => Http::response([
                'result' => 'deleted'
            ], 200),
            '*/api/unbound/service/reconfigure' => Http::response([
                'status' => 'ok'
            ], 200),
        ]);

        // 1. View resolver settings page
        $resSettings = $this->actingAs($user)->get("/firewall/{$fw->netgate_id}/services/dns-resolver");
        $resSettings->assertStatus(200);

        // 2. View host overrides page
        $resOverrides = $this->actingAs($user)->get("/firewall/{$fw->netgate_id}/services/dns-resolver/host-overrides");
        $resOverrides->assertStatus(200);
        $resOverrides->assertSee('printer');
        $resOverrides->assertSee('10.0.0.50');

        // 3. Create host override
        $resCreate = $this->actingAs($user)->post("/firewall/{$fw->netgate_id}/services/dns-resolver/host-overrides", [
            'host' => 'nas',
            'domain' => 'acme.test',
            'ip' => '10.0.0.60',
            'descr' => 'Storage NAS'
        ]);
        $resCreate->assertSessionHasNoErrors();
        $resCreate->assertRedirect();

        // 4. Delete host override
        $resDelete = $this->actingAs($user)->delete("/firewall/{$fw->netgate_id}/services/dns-resolver/host-overrides/test-host-uuid-1");
        $resDelete->assertSessionHasNoErrors();
        $resDelete->assertRedirect();
    }
}
