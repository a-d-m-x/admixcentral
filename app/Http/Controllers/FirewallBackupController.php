<?php
namespace App\Http\Controllers;
use App\Models\Firewall;
use Illuminate\Support\Carbon;

class FirewallBackupController extends Controller
{
    public function download(Firewall $firewall)
    {
        $backup = $firewall->configBackup;
        if (!$backup || $backup->status !== 'success' || !preg_match('/\.xml$/', $backup->path) || !\Illuminate\Support\Facades\Storage::disk('local')->exists($backup->path)) {
            return back()->with('error', 'Backup file not found or invalid.');
        }
        return \Illuminate\Support\Facades\Storage::disk('local')->download(
            $backup->path,
            "{$firewall->name}_config_" . $backup->pulled_at->format('Ymd_His') . ".xml"
        );
    }

    public function trigger(Firewall $firewall)
    {
        if (empty($firewall->ssh_username) || empty($firewall->ssh_password)) {
            return response()->json([
                'error' => 'SSH credentials are not configured. Add a username and password in the firewall settings before running a backup.',
            ], 422);
        }

        \App\Jobs\PullFirewallConfigBackupJob::dispatch($firewall->id);
        return response()->json(['queued' => true]);
    }

    public function status(Firewall $firewall)
    {
        $backup = $firewall->configBackup;
        if (!$backup) return response()->json(['status' => 'none']);

        // Auto-expire stale 'running' status after 2 minutes so the UI never locks up
        if ($backup->status === 'running' && $backup->last_attempted_at?->lt(Carbon::now()->subMinutes(2))) {
            $backup->update(['status' => 'failed', 'error_message' => 'Backup timed out. Please try again.']);
        }

        return response()->json([
            'status'       => $backup->status,
            'pulled_at'    => $backup->pulled_at?->utc()->toIso8601String(),
            'attempted_at' => $backup->last_attempted_at?->utc()->toIso8601String(),
            'size_kb'      => $backup->size_bytes ? number_format($backup->size_bytes / 1024, 2) : null,
            'hash'         => $backup->sha256_hash ? substr($backup->sha256_hash, 0, 12) : null,
            'error'        => $backup->error_message,
        ]);
    }
}
