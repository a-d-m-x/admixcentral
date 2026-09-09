<?php

namespace App\Http\Controllers;

use App\Models\Firewall;
use App\Services\PfSenseApiService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class DiagnosticsBackupController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware(\App\Http\Middleware\CheckRole::class . ':global_admin'),
        ];
    }

    public function index(Firewall $firewall)
    {
        $user = auth()->user();
        if (!$user || !$user->isGlobalAdmin()) {
            abort(403);
        }

        return view('diagnostics.backup.index', compact('firewall'));
    }

    public function backup(Firewall $firewall)
    {
        $user = auth()->user();
        if (!$user || !$user->isGlobalAdmin()) {
            abort(403);
        }

        $api = new PfSenseApiService($firewall);
        try {
            $response = $api->backupConfiguration();

            return response($response, 200, [
                'Content-Type' => 'application/xml',
                'Content-Disposition' => 'attachment; filename="' . ($firewall->name ? \Illuminate\Support\Str::slug($firewall->name) . '-' : '') . 'config.xml"',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
                'Pragma' => 'no-cache',
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
        $user = auth()->user();
        if (!$user || !$user->isGlobalAdmin()) {
            abort(403);
        }

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
