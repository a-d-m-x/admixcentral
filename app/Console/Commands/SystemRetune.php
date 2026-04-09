<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SystemRetune extends Command
{
    protected $signature = 'system:retune {--dry-run : Preview changes without applying them}';
    protected $description = 'Auto-tune worker count, Reverb processes, and PHP-FPM based on current hardware.';

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

    protected array $result = [];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $cpuCores   = $this->detectCpuCores();
        $ramMb      = $this->detectRamMb();

        $recWorkers  = max(8, min(16, $cpuCores * 2));
        $recChildren = max(10, min(40, (int) ($ramMb / 50)));
        $recReverb   = 3;

        $workerConf = $this->findSupervisorConf('admix-worker');
        $reverbConf = $this->findSupervisorConf('admix-reverb');
        $fpmConf    = $this->findFpmPoolConf();

        $this->result = [
            'cpu_cores'    => $cpuCores,
            'total_ram_mb' => $ramMb,
            'workers' => [
                'current'     => $workerConf ? $this->readNumprocs($workerConf) : 0,
                'recommended' => $recWorkers,
                'applied'     => false,
                'conf'        => $workerConf,
            ],
            'reverb' => [
                'current'     => $reverbConf ? $this->readNumprocs($reverbConf) : 0,
                'recommended' => $recReverb,
                'applied'     => false,
                'conf'        => $reverbConf,
            ],
            'fpm_children' => [
                'current'     => $fpmConf ? $this->readFpmChildren($fpmConf) : 0,
                'recommended' => $recChildren,
                'applied'     => false,
                'conf'        => $fpmConf,
            ],
            'dry_run' => $dryRun,
            'errors'  => [],
        ];

        if (!$dryRun) {
            $this->tuneWorkers($workerConf, $recWorkers);
            $this->tuneReverb($reverbConf, $recReverb);
            $this->tuneFpm($fpmConf, $recChildren);
            $this->reloadServices($fpmConf);
        }

        $this->line(json_encode($this->result));
        return 0;
    }

    // ── Hardware detection ────────────────────────────────────────────────

    protected function detectCpuCores(): int
    {
        $cores = (int) trim((string) shell_exec('nproc 2>/dev/null || echo 4'));
        return max(1, $cores);
    }

    protected function detectRamMb(): int
    {
        $memInfo = @file_get_contents('/proc/meminfo');
        if ($memInfo && preg_match('/MemTotal:\s+(\d+)\s+kB/i', $memInfo, $m)) {
            return (int) ($m[1] / 1024);
        }
        return 1024; // safe fallback
    }

    // ── Config file helpers ───────────────────────────────────────────────

    protected function findSupervisorConf(string $name): ?string
    {
        foreach ($this->supervisorDirs as $dir) {
            foreach (["{$dir}/{$name}.conf", "{$dir}/{$name}.ini"] as $path) {
                if (file_exists($path)) return $path;
            }
        }
        return null;
    }

    protected function findFpmPoolConf(): ?string
    {
        foreach ($this->fpmPoolPaths as $path) {
            if (file_exists($path)) return $path;
        }
        return null;
    }

    protected function readFile(string $path): ?string
    {
        $content = @file_get_contents($path);
        if ($content === false) {
            // Fall back to sudo cat for root-owned files
            $content = shell_exec('sudo cat ' . escapeshellarg($path) . ' 2>/dev/null');
        }
        return ($content !== false && $content !== null) ? $content : null;
    }

    protected function writeFile(string $path, string $content): bool
    {
        $tmp = tempnam(sys_get_temp_dir(), 'admix_tune_');
        file_put_contents($tmp, $content);
        $out = shell_exec('sudo cp ' . escapeshellarg($tmp) . ' ' . escapeshellarg($path) . ' 2>&1');
        @unlink($tmp);
        return true;
    }

    protected function readNumprocs(string $path): int
    {
        $content = $this->readFile($path);
        if ($content && preg_match('/^numprocs\s*=\s*(\d+)/m', $content, $m)) {
            return (int) $m[1];
        }
        return 1;
    }

    protected function readFpmChildren(string $path): int
    {
        $content = $this->readFile($path);
        if ($content && preg_match('/^pm\.max_children\s*=\s*(\d+)/m', $content, $m)) {
            return (int) $m[1];
        }
        return 0;
    }

    // ── Tuning ────────────────────────────────────────────────────────────

    protected function tuneWorkers(?string $confPath, int $recommended): void
    {
        if (!$confPath) {
            $this->result['errors'][] = 'admix-worker supervisor config not found';
            return;
        }

        $content = $this->readFile($confPath);
        if (!$content) {
            $this->result['errors'][] = "Cannot read: {$confPath}";
            return;
        }

        // Idempotent: strip any existing 'redis' arg, then re-add cleanly
        $content = preg_replace('/queue:work\s+redis\s+/i', 'queue:work ', $content);
        $content = preg_replace('/queue:work\s+/i', 'queue:work redis ', $content);
        $content = preg_replace('/^numprocs\s*=\s*\d+/m', "numprocs={$recommended}", $content);

        $this->writeFile($confPath, $content);
        $this->result['workers']['applied'] = true;
    }

    protected function tuneReverb(?string $confPath, int $recommended): void
    {
        if (!$confPath) return; // Reverb absence is not an error

        $content = $this->readFile($confPath);
        if (!$content) {
            $this->result['errors'][] = "Cannot read: {$confPath}";
            return;
        }

        // Add process_name if missing (Supervisor requires it when numprocs > 1)
        if (!preg_match('/^process_name\s*=/m', $content)) {
            $content = preg_replace(
                '/^\[program:admix-reverb\]/m',
                "[program:admix-reverb]\nprocess_name=%(program_name)s_%(process_num)02d",
                $content
            );
        }

        if (preg_match('/^numprocs\s*=/m', $content)) {
            $content = preg_replace('/^numprocs\s*=\s*\d+/m', "numprocs={$recommended}", $content);
        } else {
            $content = preg_replace('/^autostart\s*=\s*true/m', "autostart=true\nnumprocs={$recommended}", $content);
        }

        $this->writeFile($confPath, $content);
        $this->result['reverb']['applied'] = true;
    }

    protected function tuneFpm(?string $confPath, int $recommended): void
    {
        if (!$confPath) {
            $this->result['errors'][] = 'PHP-FPM pool config not found';
            return;
        }

        $content = $this->readFile($confPath);
        if (!$content) {
            $this->result['errors'][] = "Cannot read: {$confPath}";
            return;
        }

        $start = max(2, (int) ($recommended / 4));
        $min   = $start;
        $max   = max(4, (int) ($recommended / 2));

        $content = preg_replace('/^pm\s*=\s*.*/m',                        'pm = dynamic',                    $content);
        $content = preg_replace('/^pm\.max_children\s*=\s*\d+/m',         "pm.max_children = {$recommended}", $content);
        $content = preg_replace('/^pm\.start_servers\s*=\s*\d+/m',        "pm.start_servers = {$start}",      $content);
        $content = preg_replace('/^pm\.min_spare_servers\s*=\s*\d+/m',    "pm.min_spare_servers = {$min}",    $content);
        $content = preg_replace('/^pm\.max_spare_servers\s*=\s*\d+/m',    "pm.max_spare_servers = {$max}",    $content);
        $content = preg_replace('/^;*pm\.max_requests\s*=\s*\d+/m',       'pm.max_requests = 500',            $content);

        $this->writeFile($confPath, $content);
        $this->result['fpm_children']['applied'] = true;
    }

    // ── Service reload ────────────────────────────────────────────────────

    protected function reloadServices(?string $fpmConf): void
    {
        shell_exec('sudo supervisorctl reread 2>&1');
        shell_exec('sudo supervisorctl update 2>&1');
        shell_exec('sudo supervisorctl restart admix-worker:* 2>&1');
        shell_exec('sudo supervisorctl restart admix-reverb:* 2>&1');

        // Restart whichever php-fpm variant is active
        foreach (['php8.3-fpm', 'php8.2-fpm', 'php8.1-fpm', 'php-fpm', 'php8-fpm'] as $svc) {
            $state = trim((string) shell_exec("systemctl is-active {$svc} 2>/dev/null"));
            if ($state === 'active') {
                shell_exec("sudo systemctl restart {$svc} 2>&1");
                break;
            }
        }
    }
}
