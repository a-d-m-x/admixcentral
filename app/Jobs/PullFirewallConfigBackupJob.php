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

            $host = parse_url($firewall->url, PHP_URL_HOST);
            if (!$host) {
                $backupRecord->update(['status' => 'failed', 'error_message' => 'Could not determine host from firewall URL.']);
                return;
            }

            if ($firewall->isOpnSense()) {
                $api = new \App\Services\OpnSenseApiService($firewall);
                $res = $api->downloadBackup();
                $content = is_array($res) ? ($res['data'] ?? '') : (string) $res;

                if (empty($content) || !str_contains($content, '<opnsense>')) {
                    $backupRecord->update(['status' => 'failed', 'error_message' => 'OPNsense API backup download failed or returned invalid XML.']);
                    return;
                }
            } else {
                if (empty($firewall->ssh_username) || empty($firewall->ssh_password)) {
                    $backupRecord->update(['status' => 'failed', 'error_message' => 'SSH credentials are not configured. Add SSH username and password in firewall settings.']);
                    return;
                }

                if (empty($firewall->ssh_host_key_fingerprint)) {
                    $backupRecord->update(['status' => 'failed', 'error_message' => 'A verified SSH host key fingerprint is required in firewall settings before sending credentials.']);
                    return;
                }

                $sftp = new SFTP($host, (int) ($firewall->ssh_port ?? 22), 15);

                if (!\App\Services\SshHostKeyVerifier::verify($sftp, $firewall->ssh_host_key_fingerprint)) {
                    $sftp->disconnect();
                    $backupRecord->update(['status' => 'failed', 'error_message' => 'SSH host key verification failed. Verify the fingerprint through the firewall console.']);
                    return;
                }

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
            }

            $finalFile = "firewall-backups/{$firewall->company_id}/{$firewall->id}/config.xml";
            Storage::disk('local')->makeDirectory("firewall-backups/{$firewall->company_id}/{$firewall->id}");
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
