<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\SystemBackupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SystemBackupController extends Controller
{
    protected $backupService;

    public function __construct(SystemBackupService $backupService)
    {
        $this->backupService = $backupService;
    }

    public function index()
    {
        $backups = [];
        if (Storage::exists('backups')) {
            $files = Storage::files('backups');
            foreach ($files as $file) {
                $backups[] = [
                    'name' => basename($file),
                    'size' => Storage::size($file),
                    'last_modified' => Storage::lastModified($file),
                ];
            }
            // Sort by newest first
            usort($backups, function ($a, $b) {
                return $b['last_modified'] <=> $a['last_modified'];
            });
        }
        return view('system.backups.index', compact('backups'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'password' => 'required|confirmed|min:8',
        ]);

        try {
            $filename = $this->backupService->createBackup($request->password);
            return back()->with('success', 'Backup created successfully: ' . $filename);
        } catch (\Exception $e) {
            return back()->with('error', 'Backup failed: ' . $e->getMessage());
        }
    }

    public function download($filename)
    {
        $filename = basename($filename);
        if (!preg_match('/^backup-[\w\W]+\.json\.enc$/', $filename)) {
            return back()->with('error', 'Invalid filename.');
        }

        if (!Storage::exists('backups/' . $filename)) {
            // Check default path
            return back()->with('error', 'Backup file not found.');
        }

        return Storage::download('backups/' . $filename);
    }

    public function restore(Request $request)
    {
        $request->validate([
            'password' => 'required',
            'backup_file' => 'required_without:local_filename|file',
            'local_filename' => 'required_without:backup_file|string',
            'exclude_global_admins' => 'nullable|boolean',
            'exclude_end_users' => 'nullable|boolean',
            'exclude_hostname' => 'nullable|boolean',
        ]);

        $options = [
            'exclude_global_admins' => $request->boolean('exclude_global_admins'),
            'exclude_end_users' => $request->boolean('exclude_end_users'), // Covers both End Users and Company Admins per request
            'exclude_hostname' => $request->boolean('exclude_hostname'),
        ];

        try {
            if ($request->hasFile('backup_file')) {
                $path = $request->file('backup_file')->storeAs('temp_restores', 'restore_' . time() . '.json');
                $fullPath = Storage::path($path);
                $this->backupService->restoreFromPath($fullPath, $request->password, $options);
                unlink($fullPath);
            } else {
                // Restore from local file
                $filename = basename($request->local_filename);
                if (!preg_match('/^backup-[\w\W]+\.json\.enc$/', $filename)) {
                    throw new \Exception("Invalid filename security check.");
                }

                if (!Storage::exists('backups/' . $filename)) {
                    return back()->with('error', 'Local backup file not found.');
                }
                $content = Storage::get('backups/' . $filename);
                $this->backupService->restoreFromContent($content, $request->password, $options);
            }

            return back()->with('success', 'System restored successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Restore failed: ' . $e->getMessage());
        }
    }

    public function destroy($filename)
    {
        $filename = basename($filename);
        if (!preg_match('/^backup-[\w\W]+\.json\.enc$/', $filename)) {
            return back()->with('error', 'Invalid filename.');
        }

        if (Storage::exists('backups/' . $filename)) {
            Storage::delete('backups/' . $filename);
            return back()->with('success', 'Backup deleted successfully.');
        }

        return back()->with('error', 'Backup file not found.');
    }
}
