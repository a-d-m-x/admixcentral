<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PullFirewallConfigBackupJob implements ShouldQueue
{
    use Queueable;

    public int $firewallId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $firewallId)
    {
        $this->firewallId = $firewallId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $lock = \Illuminate\Support\Facades\Cache::lock("firewall-backup:{$this->firewallId}", 120);

        if (!$lock->get()) {
            return;
        }

        try {
            $firewall = \App\Models\Firewall::find($this->firewallId);
            if (!$firewall) {
                return;
            }

            $backupRecord = $firewall->configBackup()->firstOrCreate(
                ['firewall_id' => $firewall->id],
                ['status' => 'missing']
            );

            $backupRecord->update(['last_attempted_at' => now()]);

            if (empty($firewall->ssh_username) || empty($firewall->ssh_password)) {
                $backupRecord->update([
                    'status' => 'failed',
                    'error_message' => 'SSH credentials missing for this firewall.'
                ]);
                return;
            }

            $host = parse_url($firewall->url, PHP_URL_HOST);
            if (!$host) {
                $backupRecord->update([
                    'status' => 'failed',
                    'error_message' => 'Could not determine host from firewall URL.'
                ]);
                return;
            }

            $privateDir = 'private/firewall-backups/' . $firewall->id;
            \Illuminate\Support\Facades\Storage::disk('local')->makeDirectory($privateDir);

            $tmpFile = $privateDir . '/config.xml.tmp';
            $finalFile = $privateDir . '/config.xml';
            $tmpFilePath = \Illuminate\Support\Facades\Storage::disk('local')->path($tmpFile);

            $process = new \Symfony\Component\Process\Process([
                'sshpass', '-p', $firewall->ssh_password,
                'scp', '-P', (string) ($firewall->ssh_port ?? 22),
                '-o', 'StrictHostKeyChecking=no',
                '-o', 'UserKnownHostsFile=/dev/null',
                '-o', 'ConnectTimeout=10',
                $firewall->ssh_username . '@' . $host . ':/cf/conf/config.xml',
                $tmpFilePath
            ]);

            $process->setTimeout(60);
            $process->run();

            if (!$process->isSuccessful()) {
                $backupRecord->update([
                    'status' => 'failed',
                    'error_message' => 'SCP failed: ' . $process->getErrorOutput()
                ]);
                if (\Illuminate\Support\Facades\Storage::disk('local')->exists($tmpFile)) {
                    \Illuminate\Support\Facades\Storage::disk('local')->delete($tmpFile);
                }
                return;
            }

            // Validate file
            if (!\Illuminate\Support\Facades\Storage::disk('local')->exists($tmpFile)) {
                $backupRecord->update([
                    'status' => 'failed',
                    'error_message' => 'File downloaded but not found.'
                ]);
                return;
            }

            $content = \Illuminate\Support\Facades\Storage::disk('local')->get($tmpFile);
            if (empty($content) || !str_contains($content, '<pfsense>')) {
                $backupRecord->update([
                    'status' => 'failed',
                    'error_message' => 'Invalid configuration file. Missing <pfsense> tag.'
                ]);
                \Illuminate\Support\Facades\Storage::disk('local')->delete($tmpFile);
                return;
            }

            // Rename and replace
            if (\Illuminate\Support\Facades\Storage::disk('local')->exists($finalFile)) {
                \Illuminate\Support\Facades\Storage::disk('local')->delete($finalFile);
            }
            \Illuminate\Support\Facades\Storage::disk('local')->move($tmpFile, $finalFile);

            // Update metadata
            $backupRecord->update([
                'path' => $finalFile,
                'sha256_hash' => hash_file('sha256', \Illuminate\Support\Facades\Storage::disk('local')->path($finalFile)),
                'size_bytes' => \Illuminate\Support\Facades\Storage::disk('local')->size($finalFile),
                'status' => 'success',
                'pulled_at' => now(),
                'error_message' => null,
            ]);

        } finally {
            $lock->release();
        }
    }
}
