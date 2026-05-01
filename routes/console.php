<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Models\Firewall;
use App\Jobs\CheckFirewallStatusJob;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Backend firewall status scheduler.
//
// Dispatches CheckFirewallStatusJob for every firewall every minute,
// decoupling status production from browser activity.
//
// Protection layers:
//   withoutOverlapping(120) — prevents concurrent dispatch closures (edge case).
//   ShouldBeUnique on job  — prevents duplicate pending queue entries per firewall.
//   Cache::lock in handle()— prevents concurrent pfSense API calls per firewall.
//
// Burst: N LPUSH operations at minute boundary (~0.1ms each, negligible).
// Workers drain N api-call jobs in seconds. Safe for fleets under ~400 firewalls.
//
// Firewall::all() is correct here — the scheduler has no user context.
// Private channel authorization ensures subscribers only receive updates
// for firewalls they are permitted to see.
Schedule::call(function () {
    Firewall::all()->each(fn (Firewall $fw) => CheckFirewallStatusJob::dispatch($fw));
})->everyMinute()
  ->name('firewall-status-poll')
  ->withoutOverlapping(120);

Schedule::command('firewalls:backup-stale-configs')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->onOneServer();
