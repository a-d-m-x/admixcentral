<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Auth\Events\Login;
use App\Listeners\RecordLastLogin;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Prevent DB access during installation/pre-setup
        if (!env('APP_INSTALLED', false)) {
            \Illuminate\Support\Facades\View::share('settings', []);
            \Illuminate\Support\Facades\View::share('currentVersion', '0.0.0');
            \Illuminate\Support\Facades\View::share('updateAvailable', false);
        } else {
            try {
                // Share system settings globally
                $settings = \App\Models\SystemSetting::pluck('value', 'key')->toArray();
                \Illuminate\Support\Facades\View::share('settings', $settings);

                // Share Version Info
                $versionFile = base_path('VERSION');
                $currentVersion = file_exists($versionFile) ? trim(file_get_contents($versionFile)) : '0.0.0';
                \Illuminate\Support\Facades\View::share('currentVersion', $currentVersion);

                // Share Update Status (optimize: maybe cache this or use a lightweight check)
                // For now, simple DB check is fine as it's typically cached or fast
                $hasUpdate = \App\Models\SystemUpdate::where('status', 'pending')
                    ->where('version', '!=', $currentVersion)
                    ->exists();
                \Illuminate\Support\Facades\View::share('updateAvailable', $hasUpdate);

            } catch (\Exception $e) {
                // Failsafe during migrations or if table doesn't exist
                \Illuminate\Support\Facades\View::share('settings', []);
                \Illuminate\Support\Facades\View::share('currentVersion', '0.0.0');
                \Illuminate\Support\Facades\View::share('updateAvailable', false);
            }
        }

        \Illuminate\Support\Facades\Gate::define('admin', function ($user) {
            return $user->role === 'admin';
        });

        \Illuminate\Support\Facades\Gate::define('global-admin', function ($user) {
            return $user->isGlobalAdmin();
        });

        Event::listen(Login::class, RecordLastLogin::class);
    }
}
