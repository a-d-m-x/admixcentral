<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Firewall;
use App\Models\User;
use App\Services\FirewallHttpOptions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FirewallAutoPinTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_pin_detects_untrusted_certificates(): void
    {
        Http::fake([
            'https://trusted-ca.example*' => Http::response(['status' => 'ok'], 200),
            'https://untrusted.example*' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('cURL error 60: SSL certificate problem: self signed certificate');
            },
        ]);

        $this->assertFalse(FirewallHttpOptions::requiresPin('https://trusted-ca.example'));
        $this->assertTrue(FirewallHttpOptions::requiresPin('https://untrusted.example'));
        $this->assertFalse(FirewallHttpOptions::requiresPin('http://insecure.example'));
    }

    public function test_auto_enroll_migration_updates_unpinned_firewalls(): void
    {
        $company = Company::create(['name' => 'Test Corp']);
        $firewall = Firewall::create([
            'company_id' => $company->id,
            'name' => 'Unpinned FW',
            'os_type' => 'pfsense',
            'netgate_id' => 'unpinned-01',
            'url' => 'https://192.168.240.15',
            'auth_method' => 'basic',
            'api_key' => 'key',
            'api_secret' => 'secret',
            'tls_public_key_pin' => null,
        ]);

        $this->assertNull($firewall->tls_public_key_pin);

        // Run the migration
        $migration = require database_path('migrations/2026_09_10_000002_auto_enroll_unpinned_firewall_certificates.php');
        $migration->up();

        $firewall->refresh();
        $this->assertNotNull($firewall->tls_public_key_pin);
        $this->assertStringStartsWith('sha256//', $firewall->tls_public_key_pin);
    }

    public function test_add_firewall_auto_pins_when_certificate_omitted(): void
    {
        $company = Company::create(['name' => 'Test Corp']);
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('secret'),
            'role' => 'admin',
            'company_id' => null,
        ]);

        // Mock the initial status check
        Http::fake([
            'https://192.168.240.15/api/v2/status/system*' => Http::response([
                'code' => 200,
                'data' => ['netgate_id' => 'autopin-test-id'],
            ], 200),
        ]);

        $response = $this->actingAs($admin)->post('/firewalls', [
            'company_id' => $company->id,
            'name' => 'Auto-Pinned pfSense',
            'os_type' => 'pfsense',
            'url' => 'https://192.168.240.15',
            'auth_method' => 'basic',
            'api_key' => 'test-key',
            'api_secret' => 'test-secret',
            // Note: NO tls_certificate or tls_public_key_pin provided!
        ]);

        $response->assertSessionHasNoErrors();
        $created = Firewall::where('name', 'Auto-Pinned pfSense')->first();
        $this->assertNotNull($created);
        $this->assertNotNull($created->tls_public_key_pin);
        $this->assertStringStartsWith('sha256//', $created->tls_public_key_pin);
    }

    public function test_check_firewall_status_job_auto_enrolls_unpinned_firewall(): void
    {
        $company = Company::create(['name' => 'Test Corp']);
        $firewall = Firewall::create([
            'company_id' => $company->id,
            'name' => 'Unpinned Job FW',
            'os_type' => 'pfsense',
            'netgate_id' => 'unpinned-job-01',
            'url' => 'https://192.168.240.15',
            'auth_method' => 'basic',
            'api_key' => 'key',
            'api_secret' => 'secret',
            'tls_public_key_pin' => null,
        ]);

        $this->assertNull($firewall->tls_public_key_pin);

        Http::fake([
            'https://192.168.240.15/api/v2/system/version*' => Http::response(['data' => ['version' => '2.9.0']], 200),
            'https://192.168.240.15/api/v2/status/system*' => Http::response([
                'code' => 200,
                'data' => [
                    'uptime' => '1 day',
                    'cpu_usage' => 5,
                    'gateways' => [],
                    'interfaces' => [],
                ],
            ], 200),
        ]);

        \App\Jobs\CheckFirewallStatusJob::dispatchSync($firewall);

        $firewall->refresh();
        $this->assertNotNull($firewall->tls_public_key_pin);
        $this->assertStringStartsWith('sha256//', $firewall->tls_public_key_pin);
    }
}
