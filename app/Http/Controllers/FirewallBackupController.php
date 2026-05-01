<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class FirewallBackupController extends Controller
{
    public function download(\App\Models\Firewall $firewall)
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

    public function trigger(\App\Models\Firewall $firewall)
    {
        \App\Jobs\PullFirewallConfigBackupJob::dispatch($firewall->id);
        return back()->with('success', 'Backup job queued. The configuration will be pulled shortly.');
    }
}
