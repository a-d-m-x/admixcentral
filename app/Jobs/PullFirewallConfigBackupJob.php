<?php

namespace App\Jobs;

use App\Models\Firewall;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use phpseclib3\Net\SFTP;

class PullFirewallConfigBackupJob implements ShouldQueue
{
    use Queueable;

    public int $firewallId;

    public function __construct(int $firewallId)
    {
        $this->firewallId = $firewallId;
    }

    public function handle(): void
    {
        $lock = Cache::lock("firewall-backup:{$this->firewallId}", 120);
        if (!$lock->get()) return;

        try {
            $firewall = Firewall::find($this->firewallId);
            if (!$firewall) return;

            $backupRecord = $firewall->configBackup()->firstOrCreate(
                ['firewall_id' => $firewall->id],
                ['status' => 'missing']
            );

            $backupRecord->update(['status' => 'running', 'last_attempted_at' => now(), 'error_message' => null]);

            if (empty($firewall->ssh_username) || empty($firewall->ssh_password)) {
                $backupRecord->update(['status' => 'failed', 'error_message' => 'SSH credentials are not configured. Add SSH username and password in firewall settings.']);
                return;
            }

            $host = parse_url($firewall->url, PHP_URL_HOST);
            if (!$host) {
                $backupRecord->update(['status' => 'failed', 'error_message' => 'Could not determine host from firewall URL.']);
                return;
            }

            $sftp = new SFTP($host, (int) ($firewall->ssh_port ?? 22), 15);

            if (!$sftp->login($firewall->ssh_username, $firewall->ssh_password)) {
                $backupRecord->update(['status' => 'failed', 'error_message' => 'SSH authentication failed. Check username and password.']);
                return;
            }

            $content = $sftp->get('/cf/conf/config.xml');

            if ($content === false || empty($content)) {
                $backupRecord->update(['status' => 'failed', 'error_message' => 'SFTP download failed or returned empty file.']);
                return;
            }

            if (!str_contains($content, '<pfsense>')) {
                $backupRecord->update(['status' => 'failed', 'error_message' => 'Downloaded file is not a valid pfSense configuration.']);
                return;
            }

            $folderSlug = Str::slug($firewall->name);
            $finalFile  = "firewall-backups/{$folderSlug}/{$host}.xml";
            Storage::disk('local')->makeDirectory("firewall-backups/{$folderSlug}");
            Storage::disk('local')->put($finalFile, $content);

            $backupRecord->update([
                'path'          => $finalFile,
                'sha256_hash'   => hash('sha256', $content),
                'size_bytes'    => strlen($content),
                'status'        => 'success',
                'pulled_at'     => now(),
                'error_message' => null,
            ]);

        } finally {
            $lock->release();
        }
    }
}
