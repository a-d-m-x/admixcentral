<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Firewall;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DiagnosticsDnsLookupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:' . base64_encode(str_repeat('x', 32))]);
        $this->withoutVite();
    }

    public function test_opnsense_reverse_dns_lookup()
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
            'netgate_id' => 'fw-dns-test',
            'url' => 'https://192.168.240.11',
            'api_key' => 'test-key',
            'api_secret' => 'test-secret',
            'os_type' => 'opnsense',
            'company_id' => $company->id,
            'is_online' => true,
        ]);

        Http::fake([
            '*api/diagnostics/dns/reverse_lookup*' => Http::response([
                '1.1.1.1' => 'one.one.one.one',
            ], 200),
        ]);

        // 1. GET page
        $resGet = $this->actingAs($user)->get("/firewall/{$fw->netgate_id}/diagnostics/dns-lookup");
        $resGet->assertStatus(200);

        // 2. POST reverse lookup for 1.1.1.1
        $resPost = $this->actingAs($user)->post("/firewall/{$fw->netgate_id}/diagnostics/dns-lookup", [
            'host' => '1.1.1.1',
        ]);
        $resPost->assertStatus(200);
        $resPost->assertSee('Reverse DNS lookup for 1.1.1.1:');
        $resPost->assertSee('one.one.one.one');
    }
}
