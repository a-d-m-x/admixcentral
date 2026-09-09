<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Firewall;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DashboardStatusWidgetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:' . base64_encode(str_repeat('x', 32))]);
        $this->withoutVite();
    }

    public function test_dashboard_offline_count_reflects_actual_offline_firewalls()
    {
        $company = Company::create(['name' => 'Acme Corp']);
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@central.test',
            'password' => 'password',
            'role' => 'admin',
        ]);

        // 3 online firewalls
        for ($i = 1; $i <= 3; $i++) {
            $fw = Firewall::create([
                'name' => "opnsense-0{$i}",
                'netgate_id' => "opn-0{$i}",
                'url' => "https://192.168.240.1{$i}",
                'api_key' => 'key',
                'api_secret' => 'secret',
                'os_type' => 'opnsense',
                'company_id' => $company->id,
                'is_online' => true,
            ]);
            Cache::put("firewall_status_{$fw->id}", [
                'online' => true,
                'data' => ['product_version' => '24.7'],
            ], now()->addMinutes(10));
        }

        // 2 offline firewalls
        for ($i = 1; $i <= 2; $i++) {
            $fw = Firewall::create([
                'name' => "pfSense{$i}",
                'netgate_id' => "pfsense-0{$i}",
                'url' => "https://192.168.1.1{$i}",
                'api_key' => 'key',
                'api_secret' => 'secret',
                'os_type' => 'pfsense',
                'company_id' => $company->id,
                'is_online' => false,
            ]);
            Cache::put("firewall_status_{$fw->id}", [
                'online' => false,
                'error' => 'Connection timed out',
            ], now()->addMinutes(10));
        }

        $response = $this->actingAs($admin)->get('/dashboard');
        $response->assertStatus(200);

        // Verify view variables
        $response->assertViewHas('totalFirewalls', 5);
        $response->assertViewHas('offlineFirewalls', 2);

        // Verify Alpine initial seed data
        $response->assertSee('offlineCount: 2', false);
        $response->assertSee('recalculateOfflineCount()', false);
    }
}
