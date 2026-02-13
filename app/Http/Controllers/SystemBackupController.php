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
        if (!Storage::exists('backups/' . $filename)) {
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
        ]);

        try {
            if ($request->hasFile('backup_file')) {
                $path = $request->file('backup_file')->storeAs('temp_restores', 'restore_' . time() . '.json');
                $fullPath = Storage::path($path);
                $this->backupService->restoreFromPath($fullPath, $request->password);
                unlink($fullPath);
            } else {
                // Restore from local file
                $filename = $request->local_filename;
                if (!Storage::exists('backups/' . $filename)) {
                    return back()->with('error', 'Local backup file not found.');
                }
                $content = Storage::get('backups/' . $filename);
                $this->backupService->restoreFromContent($content, $request->password);
            }

            return back()->with('success', 'System restored successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Restore failed: ' . $e->getMessage());
        }
    }

    public function destroy($filename)
    {
        if (Storage::exists('backups/' . $filename)) {
            Storage::delete('backups/' . $filename);
            return back()->with('success', 'Backup deleted successfully.');
        }

        return back()->with('error', 'Backup file not found.');
    }
}
