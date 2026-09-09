<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Firewall;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FirewallScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:' . base64_encode(str_repeat('x', 32))]);
        $this->withoutVite();
    }

    public function test_opnsense_schedules_read_only_and_guarded()
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
            'netgate_id' => 'fw-opn-sched',
            'url' => 'https://192.168.240.11',
            'api_key' => 'test-key',
            'api_secret' => 'test-secret',
            'os_type' => 'opnsense',
            'company_id' => $company->id,
            'is_online' => true,
        ]);

        // 1. Index renders with notice and no Add Schedule button
        $response = $this->actingAs($admin)->get("/firewall/{$fw->netgate_id}/firewall/schedules");
        $response->assertStatus(200);
        $response->assertSee('Firewall Schedules on OPNsense are managed directly in the OPNsense Web GUI');
        $response->assertSee('firewall_schedule.php');
        $response->assertDontSee('Add Schedule');

        // 2. Create route redirects to index with warning
        $resCreate = $this->actingAs($admin)->get("/firewall/{$fw->netgate_id}/firewall/schedules/create");
        $resCreate->assertRedirect("/firewall/{$fw->netgate_id}/firewall/schedules");
        $resCreate->assertSessionHas('error');
        $this->assertStringContainsString('not supported via API on OPNsense', session('error'));

        // 3. Store route is rejected
        $resStore = $this->actingAs($admin)->post("/firewall/{$fw->netgate_id}/firewall/schedules", [
            'name' => 'TestSched',
        ]);
        $resStore->assertRedirect("/firewall/{$fw->netgate_id}/firewall/schedules");
        $resStore->assertSessionHas('error');

        // 4. Edit route redirects to index with warning
        $resEdit = $this->actingAs($admin)->get("/firewall/{$fw->netgate_id}/firewall/schedules/1/edit");
        $resEdit->assertRedirect("/firewall/{$fw->netgate_id}/firewall/schedules");
        $resEdit->assertSessionHas('error');

        // 5. Update route is rejected
        $resUpdate = $this->actingAs($admin)->patch("/firewall/{$fw->netgate_id}/firewall/schedules/1", [
            'name' => 'TestSched',
        ]);
        $resUpdate->assertRedirect("/firewall/{$fw->netgate_id}/firewall/schedules");
        $resUpdate->assertSessionHas('error');

        // 6. Destroy route is rejected
        $resDelete = $this->actingAs($admin)->delete("/firewall/{$fw->netgate_id}/firewall/schedules/1");
        $resDelete->assertRedirect("/firewall/{$fw->netgate_id}/firewall/schedules");
        $resDelete->assertSessionHas('error');
    }

    public function test_readonly_user_cannot_mutate_schedules()
    {
        $company = Company::create(['name' => 'Acme Corp']);
        $readonly = User::create([
            'name' => 'ReadOnly User',
            'email' => 'ro@central.test',
            'password' => 'password',
            'role' => 'readonly',
            'company_id' => $company->id,
        ]);
        $fw = Firewall::create([
            'name' => 'pfSense Firewall',
            'netgate_id' => 'fw-pfsense-sched-ro',
            'url' => 'https://192.168.1.1',
            'api_key' => 'test-key',
            'api_secret' => 'test-secret',
            'os_type' => 'pfsense',
            'company_id' => $company->id,
            'is_online' => true,
        ]);

        $resCreate = $this->actingAs($readonly)->get("/firewall/{$fw->netgate_id}/firewall/schedules/create");
        $resCreate->assertStatus(403);

        $resStore = $this->actingAs($readonly)->post("/firewall/{$fw->netgate_id}/firewall/schedules", [
            'name' => 'TestSched',
        ]);
        $resStore->assertStatus(403);
    }

    public function test_pfsense_schedules_crud_lifecycle()
    {
        $company = Company::create(['name' => 'Acme Corp']);
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@central.test',
            'password' => 'password',
            'role' => 'admin',
        ]);
        $fw = Firewall::create([
            'name' => 'pfSense Firewall',
            'netgate_id' => 'fw-pfsense-sched',
            'url' => 'https://192.168.1.1',
            'api_key' => 'test-key',
            'api_secret' => 'test-secret',
            'os_type' => 'pfsense',
            'company_id' => $company->id,
            'is_online' => true,
        ]);

        Http::fake([
            '*firewall/schedules*' => Http::response([
                'status' => 'ok',
                'code' => 200,
                'data' => [
                    [
                        'id' => '0',
                        'name' => 'BusinessHours',
                        'descr' => 'Mon-Fri 9-5',
                        'timerange' => [
                            [
                                'month' => ['all'],
                                'day' => ['all'],
                                'hour' => ['9:00-17:00'],
                                'rangedescr' => 'Work hours',
                            ],
                        ],
                    ],
                ],
            ], 200),
            '*firewall/schedule*' => Http::response([
                'status' => 'ok',
                'code' => 200,
                'data' => ['name' => 'BusinessHours'],
            ], 200),
        ]);

        // 1. Index renders for pfSense with Add Schedule button
        $response = $this->actingAs($admin)->get("/firewall/{$fw->netgate_id}/firewall/schedules");
        $response->assertStatus(200);
        $response->assertSee('Add Schedule');
        $response->assertSee('BusinessHours');

        // 2. Create schedule
        $resStore = $this->actingAs($admin)->post("/firewall/{$fw->netgate_id}/firewall/schedules", [
            'name' => 'WeekendOff',
            'descr' => 'Weekend restriction',
            'month' => 'all',
            'day' => '1-31',
            'hour' => '0:00-23:59',
        ]);
        $resStore->assertRedirect("/firewall/{$fw->netgate_id}/firewall/schedules");
        $resStore->assertSessionHas('success');

        Http::assertSent(function ($request) {
            if (str_contains($request->url(), '/firewall/schedule') && $request->method() === 'POST') {
                return $request['name'] === 'WeekendOff';
            }
            return true;
        });

        $this->assertTrue($fw->fresh()->is_dirty);

        // 3. Edit view renders
        $resEdit = $this->actingAs($admin)->get("/firewall/{$fw->netgate_id}/firewall/schedules/0/edit");
        $resEdit->assertStatus(200);
        $resEdit->assertSee('BusinessHours');

        // 4. Update schedule
        $resUpdate = $this->actingAs($admin)->patch("/firewall/{$fw->netgate_id}/firewall/schedules/0", [
            'name' => 'BusinessHoursUpdated',
            'descr' => 'Mon-Fri 8-6',
        ]);
        $resUpdate->assertRedirect("/firewall/{$fw->netgate_id}/firewall/schedules");

        // 5. Delete schedule
        $resDelete = $this->actingAs($admin)->delete("/firewall/{$fw->netgate_id}/firewall/schedules/0");
        $resDelete->assertStatus(302);
    }
}
