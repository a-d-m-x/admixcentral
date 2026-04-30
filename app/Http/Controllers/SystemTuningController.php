<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class SystemTuningController extends Controller
{
    protected array $supervisorDirs = [
        '/etc/supervisor/conf.d',
        '/etc/supervisord.d',
        '/etc/supervisor.d',
    ];

    protected array $fpmPoolPaths = [
        '/etc/php/8.3/fpm/pool.d/www.conf',
        '/etc/php/8.2/fpm/pool.d/www.conf',
        '/etc/php/8.1/fpm/pool.d/www.conf',
        '/etc/php-fpm.d/www.conf',
        '/etc/php/php-fpm.d/www.conf',
    ];

    /**
     * GET /system/settings/tuning/status
     * Returns current hardware specs and current vs recommended config values.
     */
    public function status(): JsonResponse
    {
        $cpuCores = max(1, (int) trim((string) shell_exec('nproc 2>/dev/null || echo 4')));

        $ramMb   = 0;
        $memInfo = @file_get_contents('/proc/meminfo');
        if ($memInfo && preg_match('/MemTotal:\s+(\d+)\s+kB/i', $memInfo, $m)) {
            $ramMb = (int) ($m[1] / 1024);
        }

        $currentWorkers  = $this->readNumprocs('admix-worker');
        $currentChildren = $this->readFpmChildren();

        $recWorkers  = max(8, min(16, $cpuCores * 2));
        $recChildren = max(10, min(40, (int) ($ramMb / 50)));

        return response()->json([
            'hardware' => [
                'cpu_cores'    => $cpuCores,
                'total_ram_mb' => $ramMb,
            ],
            'current' => [
                'workers'      => $currentWorkers,
                'fpm_children' => $currentChildren,
            ],
            'recommended' => [
                'workers'      => $recWorkers,
                'fpm_children' => $recChildren,
            ],
            'needs_tuning' => (
                $currentWorkers  !== $recWorkers  ||
                $currentChildren !== $recChildren
            ),
        ]);
    }

    /**
     * POST /system/settings/tuning/preview
     * Dry-run: returns what would change without applying.
     */
    public function preview(): JsonResponse
    {
        try {
            Artisan::call('system:retune', ['--dry-run' => true]);
            $result = json_decode(trim(Artisan::output()), true) ?? [];
            return response()->json(['success' => true, 'result' => $result]);
        } catch (\Throwable $e) {
            Log::error('SystemRetune preview failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /system/settings/tuning/apply
     * Applies recommended tuning values and restarts affected services.
     */
    public function apply(): JsonResponse
    {
        try {
            Artisan::call('system:retune');
            $result = json_decode(trim(Artisan::output()), true) ?? [];

            Log::channel('single')->info('Performance tuning applied via UI', [
                'user'   => auth()->user()?->email,
                'result' => $result,
            ]);

            return response()->json(['success' => true, 'result' => $result]);
        } catch (\Throwable $e) {
            Log::error('SystemRetune apply failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    protected function readNumprocs(string $name): int
    {
        foreach ($this->supervisorDirs as $dir) {
            foreach (["{$dir}/{$name}.conf", "{$dir}/{$name}.ini"] as $path) {
                if (!file_exists($path)) continue;
                $content = @file_get_contents($path);
                if ($content && preg_match('/^numprocs\s*=\s*(\d+)/m', $content, $m)) {
                    return (int) $m[1];
                }
                // Config found but no explicit numprocs — Supervisor default is 1
                return 1;
            }
        }
        return 0; // config not found at all
    }

    protected function readFpmChildren(): int
    {
        foreach ($this->fpmPoolPaths as $path) {
            if (!file_exists($path)) continue;
            $content = @file_get_contents($path);
            if ($content && preg_match('/^pm\.max_children\s*=\s*(\d+)/m', $content, $m)) {
                return (int) $m[1];
            }
        }
        return 0;
    }
}
