<?php

namespace App\Jobs;

use App\Models\Firewall;
use App\Services\PfSenseApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CheckFirewallStatusJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * One attempt only. On lock-miss the job exits cleanly (no exception),
     * so Laravel marks it complete and does NOT retry or re-queue it.
     * The next scheduler tick (Phase 1.5) or the next triggerUpdate() cycle
     * handles the next attempt naturally.
     */
    public int $tries = 1;

    /**
     * Layer 2 — queue deduplication.
     * Prevents a second job from being queued while one for the same firewall
     * is already pending (not yet picked up by a worker).
     * Lock is released by Laravel when the job starts executing — it does NOT
     * prevent concurrent execution. See Layer 3 (Cache::lock in handle()) for that.
     */
    public function uniqueId(): string
    {
        return 'firewall_status_' . $this->firewall->id;
    }

    /**
     * Queue-dedup lock TTL. If a job is abandoned without releasing its lock,
     * this prevents the firewall from being permanently suppressed from the queue.
     */
    public int $uniqueFor = 120;

    protected $firewall;

    /**
     * Create a new job instance.
     */
    public function __construct(Firewall $firewall)
    {
        $this->firewall = $firewall;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        $firewall     = $this->firewall;
        $cacheKey     = 'firewall_status_' . $firewall->id;
        $hashKey      = 'firewall_status_hash_' . $firewall->id;
        $lockAcquired = false;

        // Layer 3 — concurrent execution prevention.
        //
        // ShouldBeUnique (Layer 2) only deduplicates the queue. Once a worker starts
        // this job, the ShouldBeUnique lock is released and a new dispatch can proceed.
        // This Cache::lock() is the actual guard against two workers calling the pfSense
        // API for the same firewall at the same time.
        //
        // TTL: 90s — must exceed the maximum pfSense API response time (typically ≤30s).
        // If the process crashes without reaching `finally`, the lock auto-expires.
        $executionLock = Cache::lock('firewall_poll_exec_' . $firewall->id, 90);

        try {
            if (!$executionLock->get()) {
                // Lock is held by a prior handle() still executing.
                // Exit cleanly — no exception means Laravel marks this job complete,
                // does NOT retry it, and does NOT re-queue it.
                // The next scheduler tick or triggerUpdate() cycle handles the next attempt.
                Log::debug("Firewall [{$firewall->id}] poll already executing — skipping cycle.");
                return;
            }

            $lockAcquired = true;

            $api  = new PfSenseApiService($firewall);

            try {
                $data   = $api->refreshSystemStatus();
                $status = [
                    'online'      => true,
                    'data'        => $data,
                    'api_version' => $data['api_version'] ?? ($data['data']['api_version'] ?? null),
                    'updated_at'  => now()->toIso8601String(),
                ];

                // Always update cache — HTTP fallback reads depend on fresh data.
                Cache::put($cacheKey, $status, now()->addDay());

                // --- Change-detection fingerprint ---
                // Only fields that represent actionable UI state changes.
                // Noisy telemetry (gateway loss/delay, cpu, byte counters) is excluded.
                // Those are still available via HTTP fallback (fetchSystemStatus).
                $inner = $data['data'] ?? $data;

                $gatewayStatuses = collect($inner['gateways'] ?? [])
                    ->map(fn ($gw) => [
                        'name'   => $gw['name']   ?? null,
                        'status' => $gw['status'] ?? null,
                    ])
                    ->sortBy('name')
                    ->values()
                    ->toArray();

                $fingerprint = md5(json_encode([
                    'online'               => true,
                    'update_available'     => $inner['update_available']     ?? false,
                    'api_update_available' => $inner['api_update_available'] ?? false,
                    'product_version'      => $inner['product_version']      ?? null,
                    'api_version'          => $status['api_version'],
                    'gateway_statuses'     => $gatewayStatuses,
                ]));

                if ($fingerprint !== Cache::get($hashKey)) {
                    Cache::put($hashKey, $fingerprint, now()->addDay());
                    event(new \App\Events\DeviceStatusUpdateEvent($firewall, $status));
                    Log::debug("Firewall [{$firewall->id}] state changed — broadcasting.");
                } else {
                    Log::debug("Firewall [{$firewall->id}] state unchanged — skipping broadcast.");
                }

            } catch (\Exception $e) {
                $cached = Cache::get($cacheKey);

                $offlineStatus = [
                    'online'      => false,
                    'error'       => $e->getMessage(),
                    'updated_at'  => now()->toIso8601String(),
                    'data'        => $cached['data']        ?? null,
                    'api_version' => $cached['api_version'] ?? null,
                    'gateways'    => $cached['gateways']    ?? [],
                ];

                // Always update cache.
                Cache::put($cacheKey, $offlineStatus, now()->addDay());

                // Broadcast offline transition only once — suppress while already known-offline.
                if (Cache::get($hashKey) !== 'offline') {
                    Cache::put($hashKey, 'offline', now()->addDay());
                    event(new \App\Events\DeviceStatusUpdateEvent($firewall, $offlineStatus));
                    Log::debug("Firewall [{$firewall->id}] went offline — broadcasting.");
                } else {
                    Log::debug("Firewall [{$firewall->id}] still offline — skipping broadcast.");
                }
            }

        } finally {
            // Only release if we actually acquired the lock.
            // Prevents accidentally releasing a lock owned by a concurrent execution.
            if ($lockAcquired) {
                $executionLock->release();
            }
        }
    }
}
