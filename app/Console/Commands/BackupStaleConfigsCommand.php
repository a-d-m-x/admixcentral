<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class BackupStaleConfigsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'firewalls:backup-stale-configs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Find firewalls with stale or missing config backups and dispatch backup jobs';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $thirtyDaysAgo = now()->subDays(30);

        // Find firewalls where:
        // 1. No backup record exists
        // 2. Or backup status is missing
        // 3. Or pulled_at is null
        // 4. Or pulled_at is older than 30 days
        $firewalls = \App\Models\Firewall::whereDoesntHave('configBackup')
            ->orWhereHas('configBackup', function ($query) use ($thirtyDaysAgo) {
                $query->whereNull('pulled_at')
                      ->orWhere('pulled_at', '<', $thirtyDaysAgo)
                      ->orWhere('status', 'missing');
            })
            ->get();

        $this->info("Found {$firewalls->count()} firewalls needing backup.");

        foreach ($firewalls as $firewall) {
            $this->info("Dispatching backup job for firewall ID: {$firewall->id}");
            \App\Jobs\PullFirewallConfigBackupJob::dispatch($firewall->id);
        }

        $this->info("Done dispatching jobs.");
    }
}
