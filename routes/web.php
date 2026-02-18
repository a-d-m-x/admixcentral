<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoutingController;

use App\Http\Controllers\VpnIpsecController;
use App\Http\Controllers\VpnOpenVpnController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;

// Register broadcasting authentication routes
// Broadcast::routes(['middleware' => ['web', 'auth']]);

Route::post('/login/magic', [App\Http\Controllers\Auth\MagicLoginController::class, 'send'])->middleware('throttle:3,1')->name('login.magic');
Route::get('/login/magic/{id}', [App\Http\Controllers\Auth\MagicLoginController::class, 'verify'])->name('login.magic.verify');

Route::get('/', function () {
    return redirect('/login');
});

Route::get('/manifest.json', function () {
    $settings = \App\Models\SystemSetting::pluck('value', 'key')->toArray();
    $appName = $settings['app_name'] ?? config('app.name', 'AdmixCentral');

    // Resolve icon path: priority to icon_path, then favicon, then logo, then default
    $rawPath = $settings['icon_path'] ?? ($settings['favicon_path'] ?? ($settings['logo_path'] ?? '/images/logo.png'));

    // Ensure it's a full URL if it's not already
    $iconUrl = \Illuminate\Support\Str::startsWith($rawPath, ['http://', 'https://']) ? $rawPath : asset($rawPath);

    return response()->json([
        "name" => $appName,
        "short_name" => $appName,
        "description" => "AdmixCentral Dashboard",
        "id" => "/?source=pwa",
        "start_url" => "/?source=pwa",
        "scope" => "/",
        "display" => "standalone",
        "background_color" => "#111827",
        "theme_color" => "#111827",
        "orientation" => "portrait",
        "icons" => [
            [
                "src" => $iconUrl,
                "sizes" => "192x192",
                "type" => "image/png",
                "purpose" => "any"
            ],
            [
                "src" => $iconUrl,
                "sizes" => "512x512",
                "type" => "image/png",
                "purpose" => "any"
            ]
        ]
    ]);
})->name('manifest');





// Setup Wizard (protected by CheckSystemSetup middleware to only be accessible when no users exist)
Route::get('/setup', [App\Http\Controllers\SetupController::class, 'welcome'])->name('setup.welcome');
Route::post('/setup', [App\Http\Controllers\SetupController::class, 'store'])->name('setup.store');

// WebSocket Routes (for device communication)
Route::prefix('ws')->name('ws.')->group(function () {
    Route::post('/device/auth', [App\Http\Controllers\WebSocket\DeviceWebSocketController::class, 'authenticate'])->name('device.auth');
    Route::post('/device/connect', [App\Http\Controllers\WebSocket\DeviceWebSocketController::class, 'connect'])->name('device.connect');
    Route::post('/device/message', [App\Http\Controllers\WebSocket\DeviceWebSocketController::class, 'handleMessage'])->name('device.message');
    Route::post('/device/disconnect', [App\Http\Controllers\WebSocket\DeviceWebSocketController::class, 'disconnect'])->name('device.disconnect');
    Route::get('/info', [App\Http\Controllers\WebSocket\DeviceWebSocketController::class, 'info'])->name('info');
});

// Hostname Reachability Check (Public/Open for verification, or generally accessible)
Route::any('/system/check-hostname', [App\Http\Controllers\SystemCustomizationController::class, 'checkHostname'])
    ->name('system.check-hostname');

// Server-side proxy check (protected by auth middleware via 'web' group if needed, but here public for logic simplicity or move down)
// Actually, let's keep it protected or ensure it's safe. It's safe as it just proxies to check-hostname.
Route::post('/system/proxy-check', [App\Http\Controllers\SystemCustomizationController::class, 'proxyCheck'])
    ->middleware(['auth', 'verified'])
    ->name('system.proxy-check');

Route::middleware(['auth', 'verified'])->group(function () {
    // Profile management
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Geocoding Proxy (to avoid CORS)
    Route::get('/geocode/suggest', [App\Http\Controllers\GeocodeController::class, 'suggest'])->name('geocode.suggest');
    Route::get('/geocode/retrieve', [App\Http\Controllers\GeocodeController::class, 'retrieve'])->name('geocode.retrieve');

    // System: Routing
    Route::get('/firewall/{firewall}/system/routing', [RoutingController::class, 'index'])->name('firewall.system.routing');

    // Routing: Gateways
    Route::post('/firewall/{firewall}/system/routing/gateways', [RoutingController::class, 'storeGateway'])->name('firewall.system.routing.gateways.store');
    Route::patch('/firewall/{firewall}/system/routing/gateways/{id}', [RoutingController::class, 'updateGateway'])->name('firewall.system.routing.gateways.update');
    Route::delete('/firewall/{firewall}/system/routing/gateways/{id}', [RoutingController::class, 'destroyGateway'])->name('firewall.system.routing.gateways.destroy');

    // Routing: Static Routes
    Route::post('/firewall/{firewall}/system/routing/static-routes', [RoutingController::class, 'storeStaticRoute'])->name('firewall.system.routing.static-routes.store');
    Route::patch('/firewall/{firewall}/system/routing/static-routes/{id}', [RoutingController::class, 'updateStaticRoute'])->name('firewall.system.routing.static-routes.update');
    Route::delete('/firewall/{firewall}/system/routing/static-routes/{id}', [RoutingController::class, 'destroyStaticRoute'])->name('firewall.system.routing.static-routes.destroy');

    // Routing: Gateway Groups
    Route::post('/firewall/{firewall}/system/routing/gateway-groups', [RoutingController::class, 'storeGatewayGroup'])->name('firewall.system.routing.gateway-groups.store');
    Route::patch('/firewall/{firewall}/system/routing/gateway-groups/{id}', [RoutingController::class, 'updateGatewayGroup'])->name('firewall.system.routing.gateway-groups.update');
    Route::delete('/firewall/{firewall}/system/routing/gateway-groups/{id}', [RoutingController::class, 'destroyGatewayGroup'])->name('firewall.system.routing.gateway-groups.destroy');

    // System Status (Global)
    Route::get('/system/status', [App\Http\Controllers\SystemStatusController::class, 'check'])->name('system.status');

    // Main Dashboard
    Route::get('/dashboard', [App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');
    Route::get('/firewall/{firewall}/check-status', [App\Http\Controllers\DashboardController::class, 'checkStatus'])->name('firewall.check-status');

    // Bulk Firewall Actions
    Route::post('/firewalls/bulk/action', [App\Http\Controllers\FirewallBulkController::class, 'handle'])->name('firewalls.bulk.action');
    Route::get('/firewalls/bulk/create/{type}', [App\Http\Controllers\FirewallBulkController::class, 'create'])->name('firewalls.bulk.create');
    Route::post('/firewalls/bulk/store/{type}', [App\Http\Controllers\FirewallBulkController::class, 'store'])->name('firewalls.bulk.store');

    // Companies (admin + user)
    Route::resource('companies', App\Http\Controllers\CompanyController::class)
        ->middleware([App\Http\Middleware\CheckRole::class . ':admin,user']);

    Route::post('/firewalls/refresh-all', [App\Http\Controllers\FirewallController::class, 'refreshAll'])->name('firewalls.refresh-all');
    Route::post('/firewalls/status', [App\Http\Controllers\FirewallController::class, 'getCachedStatus'])->name('firewalls.status');

    // Firewalls CRUD - Place BEFORE the specific firewall routes
    // Exclude 'show' because we use a custom dashboard instead
    Route::resource('firewalls', App\Http\Controllers\FirewallController::class)
        ->except(['show'])
        ->middleware(App\Http\Middleware\EnsureTenantScope::class);

    // Status Dashboard (Must be before generic firewall dashboard route)
    Route::get('/firewall/{firewall}/status', [App\Http\Controllers\StatusDashboardController::class, 'index'])
        ->middleware(App\Http\Middleware\EnsureTenantScope::class)
        ->name('status.dashboard');


    // Firewall-specific dashboard (must come AFTER resource routes to avoid conflicts)
    Route::get('/firewall/{firewall}', [App\Http\Controllers\DashboardController::class, 'firewall'])
        ->middleware(App\Http\Middleware\EnsureTenantScope::class)
        ->name('firewall.dashboard');

    // Interfaces (Assignments, VLANs) - Must be before generic interface routes
    Route::prefix('firewall/{firewall}/interfaces')
        ->middleware(App\Http\Middleware\EnsureTenantScope::class)
        ->name('interfaces.')->group(function () {
            // Assignments
            Route::get('/assignments', [App\Http\Controllers\InterfacesController::class, 'assignments'])->name('assignments');
            Route::post('/assignments', [App\Http\Controllers\InterfacesController::class, 'storeAssignment'])->name('assignments.store');
            Route::delete('/assignments/{id}', [App\Http\Controllers\InterfacesController::class, 'destroyAssignment'])->name('assignments.destroy');

            // VLANs
            Route::get('/vlans', [App\Http\Controllers\InterfacesController::class, 'vlans'])->name('vlans.index');
            Route::get('/vlans/create', [App\Http\Controllers\InterfacesController::class, 'createVlan'])->name('vlans.create');
            Route::post('/vlans', [App\Http\Controllers\InterfacesController::class, 'storeVlan'])->name('vlans.store');
            Route::get('/vlans/{id}/edit', [App\Http\Controllers\InterfacesController::class, 'editVlan'])->name('vlans.edit');
            Route::patch('/vlans/{id}', [App\Http\Controllers\InterfacesController::class, 'updateVlan'])->name('vlans.update');
            Route::delete('/vlans/{id}', [App\Http\Controllers\InterfacesController::class, 'destroyVlan'])->name('vlans.destroy');

            // Bridges
            Route::get('/bridges', [App\Http\Controllers\InterfacesBridgeController::class, 'index'])->name('bridges.index');
            Route::get('/bridges/create', [App\Http\Controllers\InterfacesBridgeController::class, 'create'])->name('bridges.create');
            Route::post('/bridges', [App\Http\Controllers\InterfacesBridgeController::class, 'store'])->name('bridges.store');
            Route::get('/bridges/{id}/edit', [App\Http\Controllers\InterfacesBridgeController::class, 'edit'])->name('bridges.edit');
            Route::patch('/bridges/{id}', [App\Http\Controllers\InterfacesBridgeController::class, 'update'])->name('bridges.update');
            Route::delete('/bridges/{id}', [App\Http\Controllers\InterfacesBridgeController::class, 'destroy'])->name('bridges.destroy');

            // LAGGs
            Route::get('/laggs', [App\Http\Controllers\InterfacesLaggController::class, 'index'])->name('laggs.index');
            Route::get('/laggs/create', [App\Http\Controllers\InterfacesLaggController::class, 'create'])->name('laggs.create');
            Route::post('/laggs', [App\Http\Controllers\InterfacesLaggController::class, 'store'])->name('laggs.store');
            Route::get('/laggs/{id}/edit', [App\Http\Controllers\InterfacesLaggController::class, 'edit'])->name('laggs.edit');
            Route::patch('/laggs/{id}', [App\Http\Controllers\InterfacesLaggController::class, 'update'])->name('laggs.update');
            Route::delete('/laggs/{id}', [App\Http\Controllers\InterfacesLaggController::class, 'destroy'])->name('laggs.destroy');

            // GRE
            Route::get('/gre', [App\Http\Controllers\InterfacesGreController::class, 'index'])->name('gre.index');
            Route::get('/gre/create', [App\Http\Controllers\InterfacesGreController::class, 'create'])->name('gre.create');
            Route::post('/gre', [App\Http\Controllers\InterfacesGreController::class, 'store'])->name('gre.store');
            Route::get('/gre/{id}/edit', [App\Http\Controllers\InterfacesGreController::class, 'edit'])->name('gre.edit');
            Route::patch('/gre/{id}', [App\Http\Controllers\InterfacesGreController::class, 'update'])->name('gre.update');
            Route::delete('/gre/{id}', [App\Http\Controllers\InterfacesGreController::class, 'destroy'])->name('gre.destroy');

            // Wireless
            Route::get('/wireless', [App\Http\Controllers\InterfacesWirelessController::class, 'index'])->name('wireless.index');
            Route::get('/wireless/create', [App\Http\Controllers\InterfacesWirelessController::class, 'create'])->name('wireless.create');
            Route::post('/wireless', [App\Http\Controllers\InterfacesWirelessController::class, 'store'])->name('wireless.store');
            Route::get('/wireless/{id}/edit', [App\Http\Controllers\InterfacesWirelessController::class, 'edit'])->name('wireless.edit');
            Route::patch('/wireless/{id}', [App\Http\Controllers\InterfacesWirelessController::class, 'update'])->name('wireless.update');
            Route::delete('/wireless/{id}', [App\Http\Controllers\InterfacesWirelessController::class, 'destroy'])->name('wireless.destroy');

            // Interface Groups
            Route::get('/groups', [App\Http\Controllers\InterfacesGroupController::class, 'index'])->name('groups.index');
            Route::get('/groups/create', [App\Http\Controllers\InterfacesGroupController::class, 'create'])->name('groups.create');
            Route::post('/groups', [App\Http\Controllers\InterfacesGroupController::class, 'store'])->name('groups.store');
            Route::get('/groups/{id}/edit', [App\Http\Controllers\InterfacesGroupController::class, 'edit'])->name('groups.edit');
            Route::patch('/groups/{id}', [App\Http\Controllers\InterfacesGroupController::class, 'update'])->name('groups.update');
            Route::delete('/groups/{id}', [App\Http\Controllers\InterfacesGroupController::class, 'destroy'])->name('groups.destroy');
        });

    // Interfaces management
    Route::get('/firewall/{firewall}/interfaces/{interface}/edit', [App\Http\Controllers\InterfaceController::class, 'edit'])
        ->middleware(App\Http\Middleware\EnsureTenantScope::class)
        ->name('firewall.interfaces.edit');
    Route::put('/firewall/{firewall}/interfaces/{interface}', [App\Http\Controllers\InterfaceController::class, 'update'])
        ->middleware(App\Http\Middleware\EnsureTenantScope::class)
        ->name('firewall.interfaces.update');
    Route::get('/firewall/{firewall}/interfaces', [App\Http\Controllers\InterfaceController::class, 'index'])
        ->middleware(App\Http\Middleware\EnsureTenantScope::class)
        ->name('firewall.interfaces.index');
    Route::get('/firewall/{firewall}/interfaces/{interface}', [App\Http\Controllers\InterfaceController::class, 'show'])
        ->middleware(App\Http\Middleware\EnsureTenantScope::class)
        ->name('firewall.interfaces.show');



    // Firewall Resources Group
    Route::prefix('firewall/{firewall}')->name('firewall.')
        ->middleware(App\Http\Middleware\EnsureTenantScope::class)
        ->group(function () {

            // Nested Firewall Configuration (Matches menu structure)
            Route::prefix('firewall')->group(function () {
                // Rules
                Route::get('/rules', [App\Http\Controllers\FirewallRuleController::class, 'index'])->name('rules.index');
                Route::get('/rules/create', [App\Http\Controllers\FirewallRuleController::class, 'create'])->name('rules.create');
                Route::post('/rules', [App\Http\Controllers\FirewallRuleController::class, 'store'])->name('rules.store');
                Route::get('/rules/{tracker}/edit', [App\Http\Controllers\FirewallRuleController::class, 'edit'])->name('rules.edit');
                Route::put('/rules/{tracker}', [App\Http\Controllers\FirewallRuleController::class, 'update'])->name('rules.update');
                Route::delete('/rules/{tracker}', [App\Http\Controllers\FirewallRuleController::class, 'destroy'])->name('rules.destroy');
                Route::post('/rules/bulk-action', [App\Http\Controllers\FirewallRuleController::class, 'bulkAction'])->name('rules.bulk-action');
                Route::post('/rules/{tracker}/move', [App\Http\Controllers\FirewallRuleController::class, 'move'])->name('rules.move');
                Route::patch('/rules/{tracker}/toggle', [App\Http\Controllers\FirewallRuleController::class, 'toggle'])->name('rules.toggle');
                Route::post('/apply', [App\Http\Controllers\FirewallApplyController::class, 'apply'])->name('apply');

                // Aliases
                Route::get('/aliases', [App\Http\Controllers\FirewallAliasController::class, 'index'])->name('aliases.index');
                Route::get('/aliases/create', [App\Http\Controllers\FirewallAliasController::class, 'create'])->name('aliases.create');
                Route::post('/aliases', [App\Http\Controllers\FirewallAliasController::class, 'store'])->name('aliases.store');
                Route::get('/aliases/{id}/edit', [App\Http\Controllers\FirewallAliasController::class, 'edit'])->name('aliases.edit');
                Route::put('/aliases/{id}', [App\Http\Controllers\FirewallAliasController::class, 'update'])->name('aliases.update');
                Route::delete('/aliases/{id}', [App\Http\Controllers\FirewallAliasController::class, 'destroy'])->name('aliases.destroy');

                // NAT
                Route::get('/nat/port-forward', [App\Http\Controllers\FirewallNatController::class, 'portForward'])->name('nat.port-forward');
                Route::get('/nat/port-forward/create', [App\Http\Controllers\FirewallNatController::class, 'createPortForward'])->name('nat.port-forward.create');
                Route::post('/nat/port-forward', [App\Http\Controllers\FirewallNatController::class, 'storePortForward'])->name('nat.port-forward.store');
                Route::put('/nat/port-forward/{id}', [App\Http\Controllers\FirewallNatController::class, 'updatePortForward'])->name('nat.port-forward.update');
                Route::delete('/nat/port-forward/{id}', [App\Http\Controllers\FirewallNatController::class, 'destroyPortForward'])->name('nat.port-forward.destroy');
                Route::patch('/nat/port-forward/{id}/toggle', [App\Http\Controllers\FirewallNatController::class, 'togglePortForward'])->name('nat.port-forward.toggle');
                Route::get('/nat/outbound', [App\Http\Controllers\FirewallNatController::class, 'outbound'])->name('nat.outbound');
                Route::get('/nat/outbound/create', [App\Http\Controllers\FirewallNatController::class, 'createOutbound'])->name('nat.outbound.create');
                Route::post('/nat/outbound', [App\Http\Controllers\FirewallNatController::class, 'storeOutbound'])->name('nat.outbound.store');
                Route::get('/nat/outbound/{id}/edit', [App\Http\Controllers\FirewallNatController::class, 'editOutbound'])->name('nat.outbound.edit');
                Route::put('/nat/outbound/{id}', [App\Http\Controllers\FirewallNatController::class, 'updateOutbound'])->name('nat.outbound.update');
                Route::patch('/nat/outbound/{id}/toggle', [App\Http\Controllers\FirewallNatController::class, 'toggleOutbound'])->name('nat.outbound.toggle');
                Route::delete('/nat/outbound/{id}', [App\Http\Controllers\FirewallNatController::class, 'destroyOutbound'])->name('nat.outbound.destroy');
                Route::patch('/nat/outbound/mode', [App\Http\Controllers\FirewallNatController::class, 'updateOutboundMode'])->name('nat.outbound.mode');
                Route::get('/nat/one-to-one', [App\Http\Controllers\FirewallNatController::class, 'oneToOne'])->name('nat.one-to-one');
                Route::post('/nat/one-to-one', [App\Http\Controllers\FirewallNatController::class, 'storeOneToOne'])->name('nat.one-to-one.store');
                Route::put('/nat/one-to-one/{id}', [App\Http\Controllers\FirewallNatController::class, 'updateOneToOne'])->name('nat.one-to-one.update');
                Route::patch('/nat/one-to-one/{id}/toggle', [App\Http\Controllers\FirewallNatController::class, 'toggleOneToOne'])->name('nat.one-to-one.toggle');
                Route::delete('/nat/one-to-one/{id}', [App\Http\Controllers\FirewallNatController::class, 'destroyOneToOne'])->name('nat.one-to-one.destroy');

                // Schedules
                Route::resource('schedules', App\Http\Controllers\FirewallScheduleController::class);

                // Traffic Shaper Limiters
                Route::resource('limiters', App\Http\Controllers\FirewallLimiterController::class);

                // Virtual IPs
                Route::resource('virtual_ips', App\Http\Controllers\FirewallVirtualIpController::class);
            });

            // Status
            Route::get('/status/interfaces', [App\Http\Controllers\StatusInterfaceController::class, 'index'])->name('status.interfaces.index');



            // OpenVPN
            Route::get('/vpn/openvpn/server', [App\Http\Controllers\VpnOpenVpnController::class, 'servers'])->name('vpn.openvpn.servers');
            Route::get('/vpn/openvpn/client', [App\Http\Controllers\VpnOpenVpnController::class, 'clients'])->name('vpn.openvpn.clients');
            Route::get('/vpn/openvpn/server/create', [App\Http\Controllers\VpnOpenVpnController::class, 'createServer'])->name('vpn.openvpn.server.create');
            Route::post('/vpn/openvpn/server', [App\Http\Controllers\VpnOpenVpnController::class, 'storeServer'])->name('vpn.openvpn.server.store');


        });

    // User Management
    Route::get('/users/geocode', [App\Http\Controllers\UserController::class, 'geocode'])
        ->middleware(['throttle:10,1', App\Http\Middleware\CheckRole::class . ':admin,user'])
        ->name('users.geocode');
    Route::post('/users/check-email', [App\Http\Controllers\UserController::class, 'checkEmail'])
        ->middleware(['throttle:6,1', App\Http\Middleware\CheckRole::class . ':admin,user'])
        ->name('users.check-email');
    Route::post('/users/bulk-action', [App\Http\Controllers\UserController::class, 'bulkAction'])
        ->middleware([App\Http\Middleware\CheckRole::class . ':admin,user'])
        ->name('users.bulk-action');
    Route::resource('users', App\Http\Controllers\UserController::class)
        ->middleware([App\Http\Middleware\CheckRole::class . ':admin,user']);

    // System Settings (Global Admin)
    Route::get('/system/settings', [App\Http\Controllers\SystemCustomizationController::class, 'index'])
        ->middleware([App\Http\Middleware\CheckRole::class . ':global_admin'])
        ->name('system.settings.index');
    Route::post('/system/settings', [App\Http\Controllers\SystemCustomizationController::class, 'update'])
        ->middleware([App\Http\Middleware\CheckRole::class . ':global_admin'])
        ->name('system.settings.update');
    Route::post('/system/settings/restore', [App\Http\Controllers\SystemCustomizationController::class, 'restore'])
        ->middleware([App\Http\Middleware\CheckRole::class . ':global_admin'])
        ->name('system.settings.restore');

    Route::post('/system/settings/test-email', [App\Http\Controllers\SystemCustomizationController::class, 'testEmail'])
        ->middleware([App\Http\Middleware\CheckRole::class . ':global_admin'])
        ->name('system.settings.test-email');

    Route::post('/system/ssl/install', [App\Http\Controllers\SystemSslController::class, 'store'])
        ->middleware([App\Http\Middleware\CheckRole::class . ':global_admin'])
        ->name('system.ssl.install');

    Route::delete('/system/ssl/uninstall', [App\Http\Controllers\SystemSslController::class, 'destroy'])
        ->middleware([App\Http\Middleware\CheckRole::class . ':global_admin'])
        ->name('system.ssl.uninstall');

    // Global System Backups
    Route::get('/system/backups', [App\Http\Controllers\SystemBackupController::class, 'index'])
        ->middleware([App\Http\Middleware\CheckRole::class . ':global_admin'])
        ->name('system.backups.index');
    Route::post('/system/backups', [App\Http\Controllers\SystemBackupController::class, 'store'])
        ->middleware([App\Http\Middleware\CheckRole::class . ':global_admin'])
        ->name('system.backups.store');
    Route::post('/system/backups/restore', [App\Http\Controllers\SystemBackupController::class, 'restore'])
        ->middleware([App\Http\Middleware\CheckRole::class . ':global_admin'])
        ->name('system.backups.restore');
    Route::get('/system/backups/download/{filename}', [App\Http\Controllers\SystemBackupController::class, 'download'])
        ->middleware([App\Http\Middleware\CheckRole::class . ':global_admin'])
        ->name('system.backups.download');
    Route::delete('/system/backups/{filename}', [App\Http\Controllers\SystemBackupController::class, 'destroy'])
        ->middleware([App\Http\Middleware\CheckRole::class . ':global_admin'])
        ->name('system.backups.destroy');

    // System Updates (Settings Card)
    Route::post('/system/settings/updates/check', [App\Http\Controllers\SystemCustomizationController::class, 'checkUpdates'])
        ->middleware([App\Http\Middleware\CheckRole::class . ':global_admin'])
        ->name('system.updates.check');
    Route::post('/system/settings/updates/install', [App\Http\Controllers\SystemCustomizationController::class, 'installUpdate'])
        ->middleware([App\Http\Middleware\CheckRole::class . ':global_admin'])
        ->name('system.updates.install');

    Route::get('/system/settings/updates/check-global', [App\Http\Controllers\SystemCustomizationController::class, 'checkGlobal'])
        ->middleware([App\Http\Middleware\CheckRole::class . ':global_admin'])
        ->name('system.updates.check-global');

    Route::get('/system/settings/updates/status', [App\Http\Controllers\SystemCustomizationController::class, 'checkUpdateStatus'])
        ->middleware([App\Http\Middleware\CheckRole::class . ':global_admin'])
        ->name('system.updates.status');

    Route::post('/system/settings/updates/reset', [App\Http\Controllers\SystemCustomizationController::class, 'resetUpdates'])
        ->middleware([App\Http\Middleware\CheckRole::class . ':global_admin'])
        ->name('system.updates.reset');

    Route::post('/system/settings/updates/dismiss', [App\Http\Controllers\SystemCustomizationController::class, 'dismissUpdate'])
        ->middleware([App\Http\Middleware\CheckRole::class . ':global_admin'])
        ->name('system.updates.dismiss');





    Route::get('/firewall/{firewall}/system/rest-api', [App\Http\Controllers\SystemRestApiController::class, 'index'])
        ->middleware([App\Http\Middleware\CheckRole::class . ':global_admin'])
        ->name('system.rest-api.index');
    Route::post('/firewall/{firewall}/system/rest-api', [App\Http\Controllers\SystemRestApiController::class, 'update'])
        ->middleware([App\Http\Middleware\CheckRole::class . ':admin'])
        ->name('system.rest-api.update');
    Route::post('/firewall/{firewall}/system/rest-api/revert', [App\Http\Controllers\SystemRestApiController::class, 'revert'])
        ->middleware([App\Http\Middleware\CheckRole::class . ':admin'])
        ->name('system.rest-api.revert');

    // Services - DHCP Server
    Route::get('/firewall/{firewall}/services/dhcp/{interface?}', [App\Http\Controllers\ServicesDhcpServerController::class, 'index'])
        ->middleware(App\Http\Middleware\EnsureTenantScope::class)
        ->name('services.dhcp.index');
    Route::patch('/firewall/{firewall}/services/dhcp/{interface}', [App\Http\Controllers\ServicesDhcpServerController::class, 'update'])
        ->middleware(App\Http\Middleware\EnsureTenantScope::class)
        ->name('services.dhcp.update');

    // Services - DNS Resolver
    Route::get('/firewall/{firewall}/services/dns-resolver', [App\Http\Controllers\ServicesDnsResolverController::class, 'index'])
        ->middleware(App\Http\Middleware\EnsureTenantScope::class)
        ->name('services.dns.resolver');
    Route::patch('/firewall/{firewall}/services/dns-resolver', [App\Http\Controllers\ServicesDnsResolverController::class, 'update'])
        ->middleware(App\Http\Middleware\EnsureTenantScope::class)
        ->name('services.dns.resolver.update');
    Route::get('/firewall/{firewall}/services/dns-resolver/host-overrides', [App\Http\Controllers\ServicesDnsResolverController::class, 'hostOverrides'])
        ->middleware(App\Http\Middleware\EnsureTenantScope::class)
        ->name('services.dns.host-overrides');
    Route::post('/firewall/{firewall}/services/dns-resolver/host-overrides', [App\Http\Controllers\ServicesDnsResolverController::class, 'storeHostOverride'])
        ->middleware(App\Http\Middleware\EnsureTenantScope::class)
        ->name('services.dns.host-overrides.store');





    // System
    Route::prefix('firewall/{firewall}/system')->name('system.')->group(function () {
        Route::get('/advanced', [App\Http\Controllers\SystemController::class, 'advanced'])->name('advanced');
        Route::post('/advanced', [App\Http\Controllers\SystemController::class, 'updateAdvanced'])->name('advanced.update');
        Route::post('/advanced/tunables', [App\Http\Controllers\SystemController::class, 'storeTunable'])->name('advanced.tunables.store');
        Route::patch('/advanced/tunables/{id}', [App\Http\Controllers\SystemController::class, 'updateTunable'])->name('advanced.tunables.update');
        Route::delete('/advanced/tunables/{id}', [App\Http\Controllers\SystemController::class, 'destroyTunable'])->name('advanced.tunables.destroy');
        Route::post('/advanced/tunables/apply', [App\Http\Controllers\SystemController::class, 'applyTunables'])->name('advanced.tunables.apply');

        Route::get('/general-setup', [App\Http\Controllers\SystemController::class, 'generalSetup'])->name('general-setup');
        Route::post('/general-setup', [App\Http\Controllers\SystemController::class, 'updateGeneralSetup'])->name('general-setup.update');
        Route::get('/high-avail-sync', [App\Http\Controllers\SystemController::class, 'highAvailSync'])->name('high-avail-sync');

        // Package Manager
        Route::get('/package-manager', [App\Http\Controllers\PackageManagerController::class, 'index'])->name('package_manager.index');
        Route::post('/package-manager/install', [App\Http\Controllers\PackageManagerController::class, 'install'])->name('package_manager.install');
        Route::post('/package-manager/uninstall', [App\Http\Controllers\PackageManagerController::class, 'uninstall'])->name('package_manager.uninstall');

        Route::get('/notifications', [App\Http\Controllers\SystemController::class, 'notifications'])->name('notifications');
        Route::post('/notifications', [App\Http\Controllers\SystemController::class, 'updateNotifications'])->name('notifications.update');
        Route::post('/notifications/test', [App\Http\Controllers\SystemController::class, 'testNotifications'])->name('notifications.test');

        // Routing

        Route::get('/update', [App\Http\Controllers\SystemController::class, 'update'])->name('update');

        // User Manager
        Route::get('/user-manager', [App\Http\Controllers\UserManagerController::class, 'index'])->name('user_manager.index');

        // Users
        Route::get('/user-manager/users/create', [App\Http\Controllers\UserManagerController::class, 'createUser'])->name('user_manager.users.create');
        Route::post('/user-manager/users', [App\Http\Controllers\UserManagerController::class, 'storeUser'])->name('user_manager.users.store');
        Route::get('/user-manager/users/{id}/edit', [App\Http\Controllers\UserManagerController::class, 'editUser'])->name('user_manager.users.edit');
        Route::patch('/user-manager/users/{id}', [App\Http\Controllers\UserManagerController::class, 'updateUser'])->name('user_manager.users.update');
        Route::delete('/user-manager/users/{id}', [App\Http\Controllers\UserManagerController::class, 'destroyUser'])->name('user_manager.users.destroy');

        // Groups
        Route::get('/user-manager/groups/create', [App\Http\Controllers\UserManagerController::class, 'createGroup'])->name('user_manager.groups.create');
        Route::post('/user-manager/groups', [App\Http\Controllers\UserManagerController::class, 'storeGroup'])->name('user_manager.groups.store');
        Route::get('/user-manager/groups/{id}/edit', [App\Http\Controllers\UserManagerController::class, 'editGroup'])->name('user_manager.groups.edit');
        Route::patch('/user-manager/groups/{id}', [App\Http\Controllers\UserManagerController::class, 'updateGroup'])->name('user_manager.groups.update');
        Route::delete('/user-manager/groups/{id}', [App\Http\Controllers\UserManagerController::class, 'destroyGroup'])->name('user_manager.groups.destroy');

        // Certificate Manager
        Route::get('/certificate-manager', [App\Http\Controllers\CertificateManagerController::class, 'index'])->name('certificate_manager.index');

        // CAs
        Route::get('/certificate-manager/cas/create', [App\Http\Controllers\CertificateManagerController::class, 'createCa'])->name('certificate_manager.cas.create');
        Route::post('/certificate-manager/cas', [App\Http\Controllers\CertificateManagerController::class, 'storeCa'])->name('certificate_manager.cas.store');
        Route::delete('/certificate-manager/cas/{id}', [App\Http\Controllers\CertificateManagerController::class, 'destroyCa'])->name('certificate_manager.cas.destroy');

        // Certificates
        Route::post('/certificate-manager/certificates', [App\Http\Controllers\CertificateManagerController::class, 'storeCert'])->name('certificate_manager.certificates.store');
        Route::get('/certificate-manager/certificates/create', [App\Http\Controllers\CertificateManagerController::class, 'createCert'])->name('certificate_manager.certificates.create');
        Route::delete('/certificate-manager/certificates/{id}', [App\Http\Controllers\CertificateManagerController::class, 'destroyCert'])->name('certificate_manager.certificates.destroy');

        // CRLs
        Route::post('/certificate-manager/crls', [App\Http\Controllers\CertificateManagerController::class, 'storeCrl'])->name('certificate_manager.crls.store');
        Route::get('/certificate-manager/crls/create', [App\Http\Controllers\CertificateManagerController::class, 'createCrl'])->name('certificate_manager.crls.create');
        Route::delete('/certificate-manager/crls/{id}', [App\Http\Controllers\CertificateManagerController::class, 'destroyCrl'])->name('certificate_manager.crls.destroy');
    });



    // Services
    Route::prefix('firewall/{firewall}/services')->name('services.')->group(function () {
        Route::get('/captive-portal', [App\Http\Controllers\ServicesController::class, 'captivePortal'])->name('captive-portal');
        Route::get('/auto-config-backup', [App\Http\Controllers\ServicesController::class, 'autoConfigBackup'])->name('auto-config-backup');

        // ACME (Let's Encrypt)
        Route::get('/acme', [App\Http\Controllers\ServicesAcmeController::class, 'index'])->name('acme.index');
        Route::get('/acme/account-keys', [App\Http\Controllers\ServicesAcmeController::class, 'accountKeys'])->name('acme.account-keys');
        Route::post('/acme/account-keys', [App\Http\Controllers\ServicesAcmeController::class, 'storeAccountKey'])->name('acme.account-keys.store');
        Route::delete('/acme/account-keys/{id}', [App\Http\Controllers\ServicesAcmeController::class, 'destroyAccountKey'])->name('acme.account-keys.destroy');
        Route::get('/acme/certificates', [App\Http\Controllers\ServicesAcmeController::class, 'certificates'])->name('acme.certificates');
        Route::post('/acme/certificates', [App\Http\Controllers\ServicesAcmeController::class, 'storeCertificate'])->name('acme.certificates.store');
        Route::post('/acme/certificates/issue', [App\Http\Controllers\ServicesAcmeController::class, 'issueCertificate'])->name('acme.certificates.issue');
        Route::post('/acme/certificates/renew', [App\Http\Controllers\ServicesAcmeController::class, 'renewCertificate'])->name('acme.certificates.renew');
        Route::delete('/acme/certificates/{id}', [App\Http\Controllers\ServicesAcmeController::class, 'destroyCertificate'])->name('acme.certificates.destroy');
        Route::get('/acme/settings', [App\Http\Controllers\ServicesAcmeController::class, 'settings'])->name('acme.settings');
        Route::post('/acme/settings', [App\Http\Controllers\ServicesAcmeController::class, 'updateSettings'])->name('acme.settings.update');

        // HAProxy
        Route::get('/haproxy', [App\Http\Controllers\ServicesHaproxyController::class, 'index'])->name('haproxy.index');
        Route::get('/haproxy/settings', [App\Http\Controllers\ServicesHaproxyController::class, 'settings'])->name('haproxy.settings');
        Route::post('/haproxy/settings', [App\Http\Controllers\ServicesHaproxyController::class, 'updateSettings'])->name('haproxy.settings.update');

        // HAProxy Frontends
        Route::get('/haproxy/frontends', [App\Http\Controllers\ServicesHaproxyController::class, 'frontends'])->name('haproxy.frontends.index');
        Route::get('/haproxy/frontends/create', [App\Http\Controllers\ServicesHaproxyController::class, 'createFrontend'])->name('haproxy.frontends.create');
        Route::post('/haproxy/frontends', [App\Http\Controllers\ServicesHaproxyController::class, 'storeFrontend'])->name('haproxy.frontends.store');
        Route::get('/haproxy/frontends/{id}/edit', [App\Http\Controllers\ServicesHaproxyController::class, 'editFrontend'])->name('haproxy.frontends.edit');
        Route::put('/haproxy/frontends/{id}', [App\Http\Controllers\ServicesHaproxyController::class, 'updateFrontend'])->name('haproxy.frontends.update');
        Route::delete('/haproxy/frontends/{id}', [App\Http\Controllers\ServicesHaproxyController::class, 'destroyFrontend'])->name('haproxy.frontends.destroy');

        // HAProxy Backends
        Route::get('/haproxy/backends', [App\Http\Controllers\ServicesHaproxyController::class, 'backends'])->name('haproxy.backends.index');
        Route::get('/haproxy/backends/create', [App\Http\Controllers\ServicesHaproxyController::class, 'createBackend'])->name('haproxy.backends.create');
        Route::post('/haproxy/backends', [App\Http\Controllers\ServicesHaproxyController::class, 'storeBackend'])->name('haproxy.backends.store');
        Route::get('/haproxy/backends/{id}/edit', [App\Http\Controllers\ServicesHaproxyController::class, 'editBackend'])->name('haproxy.backends.edit');
        Route::put('/haproxy/backends/{id}', [App\Http\Controllers\ServicesHaproxyController::class, 'updateBackend'])->name('haproxy.backends.update');
        Route::delete('/haproxy/backends/{id}', [App\Http\Controllers\ServicesHaproxyController::class, 'destroyBackend'])->name('haproxy.backends.destroy');

        // FreeRADIUS
        Route::get('/freeradius', [App\Http\Controllers\ServicesFreeradiusController::class, 'index'])->name('freeradius.index');

        // Settings
        Route::get('/freeradius/settings', [App\Http\Controllers\ServicesFreeradiusController::class, 'settings'])->name('freeradius.settings');
        Route::post('/freeradius/settings', [App\Http\Controllers\ServicesFreeradiusController::class, 'updateSettings'])->name('freeradius.settings.update');

        // Users
        Route::get('/freeradius/users', [App\Http\Controllers\ServicesFreeradiusController::class, 'users'])->name('freeradius.users.index');
        Route::get('/freeradius/users/create', [App\Http\Controllers\ServicesFreeradiusController::class, 'createUser'])->name('freeradius.users.create');
        Route::post('/freeradius/users', [App\Http\Controllers\ServicesFreeradiusController::class, 'storeUser'])->name('freeradius.users.store');
        Route::get('/freeradius/users/{username}/edit', [App\Http\Controllers\ServicesFreeradiusController::class, 'editUser'])->name('freeradius.users.edit');
        Route::put('/freeradius/users/{username}', [App\Http\Controllers\ServicesFreeradiusController::class, 'updateUser'])->name('freeradius.users.update');
        Route::delete('/freeradius/users/{username}', [App\Http\Controllers\ServicesFreeradiusController::class, 'destroyUser'])->name('freeradius.users.destroy');

        // Clients/NAS
        Route::get('/freeradius/clients', [App\Http\Controllers\ServicesFreeradiusController::class, 'clients'])->name('freeradius.clients.index');
        Route::get('/freeradius/clients/create', [App\Http\Controllers\ServicesFreeradiusController::class, 'createClient'])->name('freeradius.clients.create');
        Route::post('/freeradius/clients', [App\Http\Controllers\ServicesFreeradiusController::class, 'storeClient'])->name('freeradius.clients.store');
        Route::get('/freeradius/clients/{id}/edit', [App\Http\Controllers\ServicesFreeradiusController::class, 'editClient'])->name('freeradius.clients.edit');
        Route::put('/freeradius/clients/{id}', [App\Http\Controllers\ServicesFreeradiusController::class, 'updateClient'])->name('freeradius.clients.update');
        Route::delete('/freeradius/clients/{id}', [App\Http\Controllers\ServicesFreeradiusController::class, 'destroyClient'])->name('freeradius.clients.destroy');

        // Interfaces
        Route::get('/freeradius/interfaces', [App\Http\Controllers\ServicesFreeradiusController::class, 'interfaces'])->name('freeradius.interfaces.index');
        Route::get('/freeradius/interfaces/create', [App\Http\Controllers\ServicesFreeradiusController::class, 'createInterface'])->name('freeradius.interfaces.create');
        Route::post('/freeradius/interfaces', [App\Http\Controllers\ServicesFreeradiusController::class, 'storeInterface'])->name('freeradius.interfaces.store');
        Route::delete('/freeradius/interfaces/{id}', [App\Http\Controllers\ServicesFreeradiusController::class, 'destroyInterface'])->name('freeradius.interfaces.destroy');

        // Bind (DNS Server)
        Route::get('/bind', [App\Http\Controllers\ServicesBindController::class, 'index'])->name('bind.index');
        Route::get('/bind/settings', [App\Http\Controllers\ServicesBindController::class, 'settings'])->name('bind.settings');

        Route::get('/dhcp-relay', [App\Http\Controllers\ServicesController::class, 'dhcpRelay'])->name('dhcp-relay');
        Route::post('/dhcp-relay', [App\Http\Controllers\ServicesController::class, 'updateDhcpRelay'])->name('dhcp-relay.update');
        Route::get('/dhcpv6-relay', [App\Http\Controllers\ServicesController::class, 'dhcpv6Relay'])->name('dhcpv6-relay');
        Route::get('/dhcpv6-server', [App\Http\Controllers\ServicesController::class, 'dhcpv6Server'])->name('dhcpv6-server');
        Route::get('/dns-forwarder', [App\Http\Controllers\ServicesController::class, 'dnsForwarder'])->name('dns-forwarder');
        Route::get('/dynamic-dns', [App\Http\Controllers\ServicesController::class, 'dynamicDns'])->name('dynamic-dns');
        Route::get('/igmp-proxy', [App\Http\Controllers\ServicesController::class, 'igmpProxy'])->name('igmp-proxy');
        Route::get('/ntp', [App\Http\Controllers\ServicesNtpController::class, 'index'])->name('ntp');
        Route::put('/ntp', [App\Http\Controllers\ServicesNtpController::class, 'update'])->name('ntp.update');
        Route::get('/pppoe-server', [App\Http\Controllers\ServicesController::class, 'pppoeServer'])->name('pppoe-server');
        Route::get('/router-advertisement', [App\Http\Controllers\ServicesController::class, 'routerAdvertisement'])->name('router-advertisement');
        Route::get('/snmp', [App\Http\Controllers\ServicesSnmpController::class, 'index'])->name('snmp');
        Route::put('/snmp', [App\Http\Controllers\ServicesSnmpController::class, 'update'])->name('snmp.update');
        Route::get('/upnp', [App\Http\Controllers\ServicesUpnpController::class, 'index'])->name('upnp');
        Route::put('/upnp', [App\Http\Controllers\ServicesUpnpController::class, 'update'])->name('upnp.update');
        Route::get('/captive-portal', [App\Http\Controllers\ServicesCaptivePortalController::class, 'index'])->name('captive-portal');
        Route::get('/wake-on-lan', [App\Http\Controllers\ServicesController::class, 'wakeOnLan'])->name('wake-on-lan');
    });

    // VPN
    Route::prefix('firewall/{firewall}/vpn')->name('vpn.')->group(function () {
        Route::get('/ipsec', [VpnIpsecController::class, 'tunnels'])->name('ipsec');
        Route::post('/ipsec/phase1', [VpnIpsecController::class, 'storePhase1'])->name('ipsec.phase1.store');
        Route::delete('/ipsec/phase1/{id}', [VpnIpsecController::class, 'destroyPhase1'])->name('ipsec.phase1.destroy');
        Route::get('/ipsec/phase2/{phase1}', [VpnIpsecController::class, 'phase2'])->name('ipsec.phase2');
        Route::post('/ipsec/phase2/{phase1}', [VpnIpsecController::class, 'storePhase2'])->name('ipsec.phase2.store');
        Route::delete('/ipsec/phase2/{phase1}/{uniqid}', [VpnIpsecController::class, 'destroyPhase2'])->name('ipsec.phase2.destroy');
        Route::get('/l2tp', [App\Http\Controllers\VpnController::class, 'l2tp'])->name('l2tp');

        // OpenVPN
        Route::get('/openvpn/server', [VpnOpenVpnController::class, 'servers'])->name('openvpn.servers');
        Route::get('/openvpn/server/create', [VpnOpenVpnController::class, 'createServer'])->name('openvpn.server.create');
        Route::post('/openvpn/server', [VpnOpenVpnController::class, 'storeServer'])->name('openvpn.server.store');
        Route::get('/openvpn/server/{id}/edit', [VpnOpenVpnController::class, 'editServer'])->name('openvpn.server.edit');
        Route::put('/openvpn/server/{id}', [VpnOpenVpnController::class, 'updateServer'])->name('openvpn.server.update');
        Route::delete('/openvpn/server/{id}', [VpnOpenVpnController::class, 'destroyServer'])->name('openvpn.server.destroy');
        Route::get('/openvpn/client', [VpnOpenVpnController::class, 'clients'])->name('openvpn.clients');

        Route::get('/wireguard', [App\Http\Controllers\VpnWireGuardController::class, 'index'])->name('wireguard.index');
    });



    // Status
    Route::prefix('firewall/{firewall}/status')->name('status.')->group(function () {
        Route::get('/captive-portal', [App\Http\Controllers\StatusController::class, 'captivePortal'])->name('captive-portal');
        Route::get('/carp', [App\Http\Controllers\StatusController::class, 'carp'])->name('carp');
        Route::post('/carp', [App\Http\Controllers\StatusController::class, 'updateCarp'])->name('carp.update');
        Route::get('/dhcp-leases', [App\Http\Controllers\StatusController::class, 'dhcpLeases'])->name('dhcp-leases');
        Route::get('/dhcpv6-leases', [App\Http\Controllers\StatusController::class, 'dhcpv6Leases'])->name('dhcpv6-leases');
        Route::get('/filter-reload', [App\Http\Controllers\StatusController::class, 'filterReload'])->name('filter-reload');
        Route::get('/gateways', [App\Http\Controllers\StatusController::class, 'gateways'])->name('gateways');
        Route::get('/interfaces', [App\Http\Controllers\StatusController::class, 'interfaces'])->name('interfaces.index');
        Route::get('/ipsec', [App\Http\Controllers\StatusController::class, 'ipsec'])->name('ipsec');
        Route::get('/monitoring', [App\Http\Controllers\StatusController::class, 'monitoring'])->name('monitoring');
        Route::get('/ntp', [App\Http\Controllers\StatusController::class, 'ntp'])->name('ntp');
        Route::get('/openvpn', [App\Http\Controllers\StatusController::class, 'openvpn'])->name('openvpn');
        Route::get('/queues', [App\Http\Controllers\StatusController::class, 'queues'])->name('queues');
        Route::get('/services', [App\Http\Controllers\StatusController::class, 'services'])->name('services');
        Route::get('/system-logs', [App\Http\Controllers\StatusController::class, 'systemLogs'])->name('system-logs');
        Route::get('/traffic-graph', [App\Http\Controllers\StatusController::class, 'trafficGraph'])->name('traffic-graph');
        Route::get('/upnp', [App\Http\Controllers\StatusController::class, 'upnp'])->name('upnp');
        Route::get('/dhcp', [App\Http\Controllers\StatusController::class, 'dhcp'])->name('dhcp');
        Route::get('/system', [App\Http\Controllers\StatusController::class, 'system'])->name('system');
        Route::get('/packages', [App\Http\Controllers\StatusController::class, 'packages'])->name('packages');
    });

    // Diagnostics
    Route::prefix('firewall/{firewall}/diagnostics')->name('diagnostics.')->group(function () {
        Route::get('/arp-table', [App\Http\Controllers\DiagnosticsController::class, 'arpTable'])->name('arp-table');
        Route::get('/authentication', [App\Http\Controllers\DiagnosticsController::class, 'authentication'])->name('authentication');
        Route::get('/backup', [App\Http\Controllers\DiagnosticsBackupController::class, 'index'])->name('backup.index');
        Route::get('/backup/download', [App\Http\Controllers\DiagnosticsBackupController::class, 'backup'])->name('backup.download');
        Route::post('/backup/restore', [App\Http\Controllers\DiagnosticsBackupController::class, 'restore'])->name('backup.restore');

        Route::get('/reboot', [App\Http\Controllers\DiagnosticsRebootController::class, 'index'])->name('reboot.index');
        Route::post('/reboot', [App\Http\Controllers\DiagnosticsRebootController::class, 'reboot'])->name('reboot.update');
        Route::post('/backup/restore', [App\Http\Controllers\DiagnosticsBackupController::class, 'restore'])->name('restore.upload');
        Route::match(['get', 'post'], '/command-prompt', [App\Http\Controllers\DiagnosticsController::class, 'commandPrompt'])->name('command-prompt');
        Route::match(['get', 'post'], '/dns-lookup', [App\Http\Controllers\DiagnosticsController::class, 'dnsLookup'])->name('dns-lookup');
        Route::get('/edit-file', [App\Http\Controllers\DiagnosticsController::class, 'editFile'])->name('edit-file');
        Route::get('/factory-defaults', [App\Http\Controllers\DiagnosticsController::class, 'factoryDefaults'])->name('factory-defaults');
        Route::match(['get', 'post'], '/halt-system', [App\Http\Controllers\DiagnosticsController::class, 'haltSystem'])->name('halt-system');
        Route::get('/limiter-info', [App\Http\Controllers\DiagnosticsController::class, 'limiterInfo'])->name('limiter-info');
        Route::get('/ndp-table', [App\Http\Controllers\DiagnosticsController::class, 'ndpTable'])->name('ndp-table');
        Route::get('/packet-capture', [App\Http\Controllers\DiagnosticsPacketCaptureController::class, 'index'])->name('packet_capture.index');
        Route::post('/packet-capture/start', [App\Http\Controllers\DiagnosticsPacketCaptureController::class, 'start'])->name('packet_capture.start');
        Route::post('/packet-capture/stop', [App\Http\Controllers\DiagnosticsPacketCaptureController::class, 'stop'])->name('packet_capture.stop');
        Route::get('/pf-info', [App\Http\Controllers\DiagnosticsController::class, 'pfInfo'])->name('pf-info');
        Route::get('/pf-top', [App\Http\Controllers\DiagnosticsController::class, 'pfTop'])->name('pf-top');
        Route::match(['get', 'post'], '/ping', [App\Http\Controllers\DiagnosticsController::class, 'ping'])->name('ping');
        // Route::match(['get', 'post'], '/reboot', [App\Http\Controllers\DiagnosticsController::class, 'reboot'])->name('reboot'); // Replaced by independent controller
        Route::get('/routes', [App\Http\Controllers\DiagnosticsController::class, 'routes'])->name('routes');
        Route::get('/smart-status', [App\Http\Controllers\DiagnosticsSmartStatusController::class, 'index'])->name('smart-status');
        Route::get('/sockets', [App\Http\Controllers\DiagnosticsSocketsController::class, 'index'])->name('sockets');
        Route::get('/states', [App\Http\Controllers\DiagnosticsStatesController::class, 'index'])->name('states');
        Route::get('/states-summary', [App\Http\Controllers\DiagnosticsStatesController::class, 'summary'])->name('states-summary');
        Route::get('/system-activity', [App\Http\Controllers\DiagnosticsController::class, 'systemActivity'])->name('system-activity');
        Route::get('/tables', [App\Http\Controllers\DiagnosticsController::class, 'tables'])->name('tables');
        Route::get('/test-port', [App\Http\Controllers\DiagnosticsTestPortController::class, 'index'])->name('test_port.index');
        Route::post('/test-port', [App\Http\Controllers\DiagnosticsTestPortController::class, 'test'])->name('test_port.test');
        Route::match(['get', 'post'], '/traceroute', [App\Http\Controllers\DiagnosticsController::class, 'traceroute'])->name('traceroute');
    });


});





require __DIR__ . '/auth.php';

