<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class FirewallBackupTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_global_admin_can_access_backup_routes(): void
    {
        $company = \App\Models\Company::factory()->create();
        $user = \App\Models\User::factory()->create(['role' => 'user', 'company_id' => $company->id]);
        $firewall = \App\Models\Firewall::factory()->create(['company_id' => $company->id]);

        $response = $this->actingAs($user)->get(route('firewall.backup.download', $firewall));
        $response->assertStatus(403);

        $response = $this->actingAs($user)->post(route('firewall.backup.trigger', $firewall));
        $response->assertStatus(403);
    }

    public function test_global_admin_can_trigger_backup(): void
    {
        \Illuminate\Support\Facades\Queue::fake();

        $company = \App\Models\Company::factory()->create();
        $admin = \App\Models\User::factory()->create(['role' => 'global_admin', 'company_id' => $company->id]);
        $firewall = \App\Models\Firewall::factory()->create(['company_id' => $company->id]);

        $response = $this->actingAs($admin)->post(route('firewall.backup.trigger', $firewall));
        
        $response->assertRedirect();
        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\PullFirewallConfigBackupJob::class);
    }

    public function test_backup_stale_configs_command_dispatches_jobs(): void
    {
        \Illuminate\Support\Facades\Queue::fake();

        $company = \App\Models\Company::factory()->create();
        
        // Stale firewall (no backup)
        $firewall1 = \App\Models\Firewall::factory()->create(['company_id' => $company->id]);
        
        // Fresh firewall
        $firewall2 = \App\Models\Firewall::factory()->create(['company_id' => $company->id]);
        $firewall2->configBackup()->create([
            'status' => 'success',
            'pulled_at' => now(),
            'path' => 'test.xml'
        ]);

        $this->artisan('firewalls:backup-stale-configs')->assertExitCode(0);

        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\PullFirewallConfigBackupJob::class, function ($job) use ($firewall1) {
            return $job->firewallId === $firewall1->id;
        });
        
        \Illuminate\Support\Facades\Queue::assertNotPushed(\App\Jobs\PullFirewallConfigBackupJob::class, function ($job) use ($firewall2) {
            return $job->firewallId === $firewall2->id;
        });
    }
}
