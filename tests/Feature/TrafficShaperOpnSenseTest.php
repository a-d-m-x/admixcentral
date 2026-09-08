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

class TrafficShaperOpnSenseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:' . base64_encode(str_repeat('x', 32))]);
        $this->withoutVite();
    }

    public function test_opnsense_traffic_shaper_lifecycle()
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
            'netgate_id' => 'fw-shaper-test',
            'url' => 'https://192.168.240.11',
            'api_key' => 'test-key',
            'api_secret' => 'test-secret',
            'os_type' => 'opnsense',
            'company_id' => $company->id,
            'is_online' => true,
        ]);

        Http::fake([
            '*api/trafficshaper/settings/searchPipes*' => Http::response([
                'rows' => [
                    [
                        'uuid' => 'pipe-uuid-1',
                        'number' => '1',
                        'enabled' => '1',
                        'bandwidth' => '100',
                        'bandwidthMetric' => 'Mbit',
                        'mask' => 'src-ip',
                        'scheduler' => 'fifo',
                        'description' => 'Download Limiter 100M',
                    ],
                ],
                'rowCount' => 1,
                'total' => 1,
            ], 200),
            '*api/trafficshaper/settings/getPipe/pipe-uuid-1*' => Http::response([
                'pipe' => [
                    'enabled' => '1',
                    'bandwidth' => '100',
                    'bandwidthMetric' => 'Mbit',
                    'description' => 'Download Limiter 100M',
                ],
            ], 200),
            '*api/trafficshaper/settings/addPipe*' => Http::response([
                'result' => 'saved',
                'uuid' => 'pipe-uuid-new',
            ], 200),
            '*api/trafficshaper/settings/setPipe/pipe-uuid-1*' => Http::response([
                'result' => 'saved',
            ], 200),
            '*api/trafficshaper/settings/delPipe/pipe-uuid-1*' => Http::response([
                'result' => 'deleted',
            ], 200),
            '*api/trafficshaper/settings/searchQueues*' => Http::response([
                'rows' => [
                    [
                        'uuid' => 'queue-uuid-1',
                        'number' => '1',
                        'enabled' => '1',
                        'pipe' => 'pipe-uuid-1',
                        'weight' => '50',
                        'description' => 'High Priority Queue',
                    ],
                ],
                'rowCount' => 1,
                'total' => 1,
            ], 200),
            '*api/trafficshaper/settings/searchRules*' => Http::response([
                'rows' => [
                    [
                        'uuid' => 'rule-uuid-1',
                        'sequence' => '1',
                        'enabled' => '1',
                        'target' => 'pipe-uuid-1',
                        'proto' => 'tcp',
                        'source' => 'lan',
                        'destination' => 'any',
                        'description' => 'Shape Web Traffic',
                    ],
                ],
                'rowCount' => 1,
                'total' => 1,
            ], 200),
            '*api/trafficshaper/service/reconfigure*' => Http::response([
                'status' => 'ok',
            ], 200),
        ]);

        // 1. Web UI: Limiters (Traffic Shaper) Index
        $resLimiters = $this->actingAs($admin)->get("/firewall/{$fw->netgate_id}/firewall/limiters");
        $resLimiters->assertStatus(200);
        $resLimiters->assertSee('Download Limiter 100M');
        $resLimiters->assertSee('100 Mb');

        // 2. Web UI: Status Queues View
        $resQueues = $this->actingAs($admin)->get("/firewall/{$fw->netgate_id}/status/queues");
        $resQueues->assertStatus(200);
        $resQueues->assertSee('Traffic Shaper Pipes');
        $resQueues->assertSee('Download Limiter 100M');
        $resQueues->assertSee('Traffic Shaper Queues');
        $resQueues->assertSee('High Priority Queue');
        $resQueues->assertSee('Traffic Shaper Rules');
        $resQueues->assertSee('Shape Web Traffic');

        // 3. Direct API via PfSenseApiService & OpnSenseApiService delegation
        $pfApi = new PfSenseApiService($fw);
        $limiters = $pfApi->getLimiters();
        $this->assertEquals(200, $limiters['status']);
        $this->assertCount(1, $limiters['data']);
        $this->assertEquals('Download Limiter 100M', $limiters['data'][0]['name']);
        $this->assertEquals('srcaddress', $limiters['data'][0]['mask']);

        // 4. Web UI: Store Limiter via POST
        $resStore = $this->actingAs($admin)->post("/firewall/{$fw->netgate_id}/firewall/limiters", [
            'name' => 'Upload Limiter 50M',
            'descr' => 'Upload Limiter 50M',
            'bandwidth_value' => 50,
            'bandwidth_scale' => 'Mb',
            'mask' => 'dstaddress',
            'aqm' => 'droptail',
            'sched' => 'fifo',
        ]);
        $resStore->assertRedirect(route('firewall.limiters.index', $fw));
        $resStore->assertSessionHas('success');

        // 5. Web UI: Update Limiter via PUT with string UUID
        $resUpdate = $this->actingAs($admin)->put("/firewall/{$fw->netgate_id}/firewall/limiters/pipe-uuid-1", [
            'name' => 'Download Limiter 150M',
            'descr' => 'Download Limiter 150M',
            'bandwidth_value' => 150,
            'bandwidth_scale' => 'Mb',
            'mask' => 'none',
            'aqm' => 'codel',
            'sched' => 'fifo',
        ]);
        $resUpdate->assertRedirect(route('firewall.limiters.index', $fw));
        $resUpdate->assertSessionHas('success');

        // 6. Web UI: Delete Limiter via DELETE with string UUID
        $resDelete = $this->actingAs($admin)->delete("/firewall/{$fw->netgate_id}/firewall/limiters/pipe-uuid-1");
        $resDelete->assertRedirect(route('firewall.limiters.index', $fw));
        $resDelete->assertSessionHas('success');
    }
}
