<?php

namespace App\Http\Controllers;

use App\Models\Firewall;
use App\Services\PfSenseApiService;
use Illuminate\Http\Request;

class DiagnosticsBackupController extends Controller
{
    public function index(Firewall $firewall)
    {
        // Mostly a form to submit backup/restore requests
        return view('diagnostics.backup.index', compact('firewall'));
    }

    public function backup(Firewall $firewall)
    {
        $api = new PfSenseApiService($firewall);
        try {
            $response = $api->backupConfiguration();
            // Assuming response is the file content or a download link
            // For now, if raw content, stream download.

            // If API returns JSON with data, handle it. 
            // Simplest assumption: It returns XML content directly or JSON with base64.
            // Given get() usually returns JSON decoded array in our service wrapper...
            // We might need a raw method if it returns file stream.
            // But let's assume standard API behavior for now.

            return response($response, 200, [
                'Content-Type' => 'application/xml',
                'Content-Disposition' => 'attachment; filename="' . ($firewall->name ? \Illuminate\Support\Str::slug($firewall->name) . '-' : '') . 'config.xml"',
            ]);

        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), '404') || str_contains($e->getMessage(), 'Not Found')) {
                return view('diagnostics.backup.unsupported', compact('firewall'));
            }
            return back()->with('error', 'Backup failed: ' . $e->getMessage());
        }
    }

    public function restore(Firewall $firewall, Request $request)
    {
        $request->validate([
            'config_file' => 'required|file|mimes:xml',
        ]);

        $api = new PfSenseApiService($firewall);

        try {
            $file = $request->file('config_file');
            $content = file_get_contents($file->getRealPath());

            $api->restoreConfiguration(['config' => base64_encode($content)]);

            return back()->with('success', 'Configuration restored successfully. Firewall may reboot.');
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), '404') || str_contains($e->getMessage(), 'Not Found')) {
                return view('diagnostics.backup.unsupported', compact('firewall'));
            }
            return back()->with('error', 'Restore failed: ' . $e->getMessage());
        }
    }
}
