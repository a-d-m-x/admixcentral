<?php

namespace App\Http\Controllers;

use App\Models\Firewall;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class FirewallController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('deny.readonly', only: ['create', 'store', 'edit', 'update', 'destroy']),
        ];
    }

    /**
     * Display a listing of firewalls.
     *
     * Global Admins see all firewalls.
     * Company Admins see only firewalls belonging to their company.
     *
     * Status is enriched from Cache for performance.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->isGlobalAdmin()) {
            $firewalls = Firewall::with(['company', 'configBackup'])->orderBy('name')->get();
        } else {
            $firewalls = Firewall::where('company_id', $user->company_id)->orderBy('name')->get();
        }

        // Check if status caching is enabled (Default to true)
        $cacheSetting = \App\Models\SystemSetting::where('key', 'enable_status_cache')->value('value');
        // Use filter_var to correctly handle "0", "false", "off" as false.
        $useCache = $cacheSetting !== null ? filter_var($cacheSetting, FILTER_VALIDATE_BOOLEAN) : true;

        // Collect status for each firewall
        $firewalls->each(function ($firewall) use ($useCache) {
            $cached = $useCache ? \Illuminate\Support\Facades\Cache::get('firewall_status_' . $firewall->id) : null;

            // If caching is disabled, we force a "null" state which UI handles as "Checking..." or "Unknown"
            // This prevents "Waterfall" delay of checking live, but also prevents showing stale data.

            if ($cached) {
                $firewall->cached_status = $cached;
                $firewall->is_online = (bool) ($cached['online'] ?? false);
            } else {
                $firewall->cached_status = null;
                $firewall->is_online = null; // UI should treat null as "Unknown/Pending"
            }
        });

        $totalFirewalls = $firewalls->count();
        $offlineFirewalls = $firewalls->where('is_online', false)->count();
        $systemUpdates = $firewalls->filter(function ($fw) {
            return $fw->cached_status['update_available'] ?? false;
        })->count();
        $apiUpdates = $firewalls->filter(function ($fw) {
            return $fw->cached_status['api_update_available'] ?? false;
        })->count();

        $missingAddresses = $firewalls->whereNull('address')->count();

        return view('firewalls.index', compact('firewalls', 'totalFirewalls', 'offlineFirewalls', 'systemUpdates', 'apiUpdates', 'missingAddresses'));
    }

    /**
     * Refresh a batch of firewalls synchronously (formerly refreshAll).
     */
    /**
     * Dispatch status check jobs for firewalls.
     */
    public function refreshAll(Request $request)
    {
        set_time_limit(300); // Allow more time for sync processing

        $ids = $request->input('ids', []);
        // Also support GET param for ids
        if (empty($ids) && $request->has('ids')) {
            $ids = explode(',', $request->input('ids'));
        }

        $user = $request->user();
        $query = Firewall::query();
        if ($user && !$user->isGlobalAdmin()) {
            $query->where('company_id', $user->company_id);
        }
        if (!empty($ids)) {
            $query->whereIn('id', $ids);
        }
        $firewalls = $query->get();

        if ($request->boolean('sync')) {
            $results = [];
            foreach ($firewalls as $firewall) {
                try {
                    $api = new \App\Services\PfSenseApiService($firewall);
                    $data = $api->refreshSystemStatus();

                    // Match Event structure
                    $status = [
                        'online' => true,
                        'data' => $data,
                        'api_version' => $data['api_version'] ?? null,
                        'updated_at' => now()->toIso8601String(),
                    ];

                    // Update Cache
                    \Illuminate\Support\Facades\Cache::put('firewall_status_' . $firewall->id, $status, now()->addDay());

                    // Fire Event
                    event(new \App\Events\DeviceStatusUpdateEvent($firewall, $status));

                    $results[$firewall->id] = $status;
                } catch (\Exception $e) {
                    $offlineStatus = [
                        'online' => false,
                        'error' => $e->getMessage(),
                        'data' => null,
                        'updated_at' => now()->toIso8601String()
                    ];
                    \Illuminate\Support\Facades\Cache::put('firewall_status_' . $firewall->id, $offlineStatus, now()->addDay());
                    event(new \App\Events\DeviceStatusUpdateEvent($firewall, $offlineStatus));

                    $results[$firewall->id] = $offlineStatus;
                }
            }
            return response()->json(['results' => $results]);
        }

        // Default: Queue Mode
        $firewalls->each(function ($firewall) {
            \App\Jobs\CheckFirewallStatusJob::dispatch($firewall);
        });

        // Return current cached data immediately
        $results = [];
        foreach ($firewalls as $firewall) {
            $cached = \Illuminate\Support\Facades\Cache::get('firewall_status_' . $firewall->id);
            if ($cached) {
                $results[$firewall->id] = [
                    'online' => (bool) ($cached['online'] ?? false),
                    'data' => $cached['data'] ?? null,
                    'api_version' => $cached['api_version'] ?? null
                ];
            } else {
                $results[$firewall->id] = ['online' => false, 'data' => null];
            }
        }

        return response()->json([
            'results' => $results,
            'queued' => $firewalls->count()
        ]);
    }

    /**
     * Dashboard status-poll endpoint.
     *
     * Returns cached status + freshness metadata for all requested firewalls,
     * and dispatches CheckFirewallStatusJob per firewall (deduplicated at three levels):
     *   1. Route throttle:4,1  — 4 requests/user/minute max
     *   2. dispatch_debounce cache key (45s) — prevents re-dispatching per firewall within one cycle
     *   3. ShouldBeUnique + Cache::lock in the job itself
     *
     * No synchronous pfSense API calls are ever made in this method.
     */
    public function statusPoll(Request $request)
    {
        // Release session lock immediately — don't block concurrent requests
        session_write_close();

        $ids = $request->input('ids', []);

        $user = $request->user();
        $query = Firewall::query();
        if ($user && !$user->isGlobalAdmin()) {
            $query->where('company_id', $user->company_id);
        }
        if (!empty($ids)) {
            $query->whereIn('id', $ids);
        }
        $firewalls = $query->get();

        // Dispatch debounce window (seconds).
        // ShouldBeUnique covers most duplicates, but this prevents even the
        // cheap dispatch() call from being made on every poll from every tab.
        $dispatchDebounceSeconds = 45;

        // Cache age threshold for 'fresh' vs 'stale' classification.
        // One job cycle is ~10-30s, so 90s gives two missed cycles before stale.
        $staleThresholdSeconds = 90;

        $results = [];

        foreach ($firewalls as $firewall) {
            $cacheKey    = 'firewall_status_' . $firewall->id;
            $debounceKey = 'firewall_dispatch_debounce_' . $firewall->id;

            // Only dispatch if no debounce lock is held for this firewall.
            // All three dedup layers prevent actual duplicate API calls,
            // but this stops even the Redis enqueue attempt from repeating.
            if (!\Illuminate\Support\Facades\Cache::has($debounceKey)) {
                \App\Jobs\CheckFirewallStatusJob::dispatch($firewall);
                \Illuminate\Support\Facades\Cache::put($debounceKey, 1, now()->addSeconds($dispatchDebounceSeconds));
            }

            $cached = \Illuminate\Support\Facades\Cache::get($cacheKey);

            if (!$cached) {
                $results[$firewall->id] = [
                    'freshness'  => 'pending',
                    'online'     => null,
                    'updated_at' => null,
                    'data'       => null,
                    'api_version'=> null,
                    'error'      => null,
                ];
                continue;
            }

            $updatedAt  = $cached['updated_at'] ?? null;
            $ageSeconds = $updatedAt
                ? max(0, now()->timestamp - \Carbon\Carbon::parse($updatedAt)->timestamp)
                : PHP_INT_MAX;

            $results[$firewall->id] = [
                'online'      => (bool) ($cached['online'] ?? false),
                'updated_at'  => $updatedAt,
                'freshness'   => $ageSeconds < $staleThresholdSeconds ? 'fresh' : 'stale',
                'data'        => $cached['data'] ?? null,
                'api_version' => $cached['api_version'] ?? null,
                'error'       => $cached['error'] ?? null,
            ];
        }

        return response()->json(['results' => $results]);
    }


    public function create()
    {
        $user = auth()->user();
        if (!$user || (!$user->isGlobalAdmin() && !$user->isCompanyAdmin())) {
            abort(403);
        }

        if ($user->isGlobalAdmin()) {
            $companies = Company::orderBy('name')->get();
        } else {
            $companies = collect([$user->company]);
        }
        return view('firewalls.create', compact('companies'));
    }


    /**
     * Store a newly created firewall in storage.
     *
     * Validates input, ensures company ownership permissions, and attempts
     * an initial connection to fetch the Netgate ID if possible.
     */
    public function store(Request $request)
    {
        $user = auth()->user();
        if (!$user || (!$user->isGlobalAdmin() && !$user->isCompanyAdmin())) {
            abort(403);
        }

        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'name' => 'required|string|max:255',
            'os_type' => 'nullable|in:pfsense,opnsense',
            'url' => 'required|url',
            'auth_method' => 'required|in:basic,token',
            'api_key' => 'nullable|string',
            'api_secret' => 'nullable|string',
            'api_token' => 'nullable|string',
            'opn_username' => 'nullable|string',
            'opn_password' => 'nullable|string',
            'description' => 'nullable|string',
            'ssh_port' => 'nullable|integer',
            'ssh_username' => 'nullable|string|max:255',
            'ssh_password' => 'nullable|string',
        ]);

        if ($user->isCompanyAdmin()) {
            if ((int) $validated['company_id'] !== (int) $user->company_id) {
                abort(403, 'You can only create firewalls for your own company.');
            }
        } elseif (!$user->isGlobalAdmin()) {
            abort(403);
        }

        $this->validateFirewallUrl($validated['url']);

        $validated['os_type'] = $validated['os_type'] ?? 'pfsense';

        if ($validated['os_type'] === 'opnsense' && !empty($validated['opn_username']) && !empty($validated['opn_password'])) {
            try {
                $keys = \App\Services\OpnSenseApiService::provisionApiKeyFromCredentials(
                    $validated['url'],
                    $validated['opn_username'],
                    $validated['opn_password']
                );
                $validated['auth_method'] = 'basic';
                $validated['api_key'] = $keys['key'];
                $validated['api_secret'] = $keys['secret'];
            } catch (\Exception $e) {
                return back()
                    ->withInput()
                    ->with('error', 'OPNsense API key auto-generation failed: ' . $e->getMessage())
                    ->withErrors(['url' => $e->getMessage()]);
            }
        }
        unset($validated['opn_username'], $validated['opn_password']);

        if ($validated['auth_method'] === 'basic' && (empty($validated['api_key']) || empty($validated['api_secret']))) {
            return back()
                ->withInput()
                ->withErrors(['api_key' => 'API Key and Secret are required for Basic Authentication.']);
        }
        if ($validated['auth_method'] === 'token' && empty($validated['api_token'])) {
            return back()
                ->withInput()
                ->withErrors(['api_token' => 'API Token is required for Token Authentication.']);
        }

        $firewall = new Firewall($validated);

        try {
            $api = new \App\Services\PfSenseApiService($firewall);
            $response = $api->get('/status/system');
            if (isset($response['data']['netgate_id'])) {
                $validated['netgate_id'] = $response['data']['netgate_id'];
            } elseif ($firewall->isOpnSense()) {
                $validated['netgate_id'] = 'opn-' . substr(md5($validated['url'] . '_' . microtime()), 0, 12);
            }
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->with('error', 'Connection failed: ' . $e->getMessage())
                ->withErrors(['url' => 'Connection failed: ' . $e->getMessage()]);
        }

        try {
            $firewall = Firewall::create($validated);
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            // Check if violation is on netgate_id
            if (str_contains($e->getMessage(), 'firewalls_netgate_id_unique')) {
                return back()
                    ->withInput()
                    ->with('error', 'This firewall is already managed by Admix Central (Duplicate Netgate ID).');
            }
            throw $e;
        }

        if (!$firewall->netgate_id) {
            $firewall->netgate_id = (string) $firewall->id;
            $firewall->save();
        }

        // We already confirmed connectivity above via the API call, so seed the cache as online
        // immediately. This prevents the firewall from appearing "Offline" on the list page
        // before the background poller has had a chance to run.
        $cacheKey = 'firewall_status_' . $firewall->id;
        \Illuminate\Support\Facades\Cache::put($cacheKey, [
            'online' => true,
            'data'   => [],
        ], now()->addMinutes(5));

        // Dispatch a full status check in the background to populate version/update info.
        \App\Jobs\CheckFirewallStatusJob::dispatch($firewall);

        return redirect()->route('firewalls.index')->with('success', 'Firewall created successfully.');
    }

    public function show(Firewall $firewall)
    {
        // Middleware EnsureTenantScope handles access, but good to be explicit or leave it.
        // We'll rely on middleware for 'firewall' bound model access, 
        // but here it's implicit binding.
        // EnsureTenantScope usually applies to routes under /firewall/{firewall}.
        // This specific route might not be covered if not in that group?
        // Route::resource('firewalls') has middleware EnsureTenantScope applied in web.php.
        return redirect()->route('firewall.dashboard', $firewall);
    }

    public function edit(Firewall $firewall)
    {
        $user = auth()->user();
        if (!$user || (!$user->isGlobalAdmin() && !($user->isCompanyAdmin() && (int)$firewall->company_id === (int)$user->company_id))) {
            abort(403);
        }

        if ($user->isGlobalAdmin()) {
            $companies = Company::orderBy('name')->get();
        } else {
            $companies = collect([$user->company]);
        }
        return view('firewalls.edit', compact('firewall', 'companies'));
    }

    /**
     * Update the specified firewall in storage.
     *
     * Handles authentication method switching (Token vs Basic) and
     * clears unused credentials based on the selected method.
     */
    public function update(Request $request, Firewall $firewall)
    {
        $user = auth()->user();
        if (!$user || (!$user->isGlobalAdmin() && !($user->isCompanyAdmin() && (int)$firewall->company_id === (int)$user->company_id))) {
            abort(403);
        }

        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'name' => 'required|string|max:255',
            'os_type' => 'nullable|in:pfsense,opnsense',
            'url' => 'required|url',
            'auth_method' => 'required|in:basic,token',
            'api_key' => 'nullable|string',
            'api_secret' => 'nullable|string',
            'api_token' => 'nullable|string',
            'description' => 'nullable|string',
            'address' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'ssh_port' => 'nullable|integer',
            'ssh_username' => 'nullable|string|max:255',
            'ssh_password' => 'nullable|string',
        ]);

        $this->validateFirewallUrl($validated['url']);

        if (!$user->isGlobalAdmin()) {
            if ((int) $validated['company_id'] !== (int) $firewall->company_id) {
                abort(403, 'Company reassignment is restricted to global administrators.');
            }
            $validated['company_id'] = $firewall->company_id;
        }

        $urlChanged = (rtrim($validated['url'], '/') !== rtrim($firewall->url, '/'));

        if ($validated['auth_method'] === 'token') {
            $validated['api_key'] = null;
            $validated['api_secret'] = null;
            if (empty($validated['api_token'])) {
                if ($urlChanged) {
                    return back()->withErrors(['api_token' => 'A new API Token is required when changing the firewall URL.'])->withInput();
                }
                if ($firewall->auth_method !== 'token' || empty($firewall->api_token)) {
                    return back()->withErrors(['api_token' => 'API Token is required for Token Authentication.'])->withInput();
                }
                unset($validated['api_token']);
            }
        } else {
            $validated['api_token'] = null;
            if (empty($validated['api_key'])) {
                if ($urlChanged || $firewall->auth_method !== 'basic' || empty($firewall->api_key)) {
                    return back()->withErrors(['api_key' => 'API Key/Username is required for Basic Authentication.'])->withInput();
                }
                unset($validated['api_key']);
            }
            if (empty($validated['api_secret'])) {
                if ($urlChanged) {
                    return back()->withErrors(['api_secret' => 'Password is required when changing the firewall URL.'])->withInput();
                }
                if (empty($firewall->api_secret) && $firewall->auth_method !== 'basic') {
                    return back()->withErrors(['api_secret' => 'Password is required when switching to Basic Authentication.'])->withInput();
                }
                unset($validated['api_secret']);
            }
        }

        if (empty($validated['ssh_password'])) {
            unset($validated['ssh_password']);
        }

        $firewall->update($validated);

        return redirect()->route('firewalls.index')->with('success', 'Firewall updated successfully.');
    }

    public function destroy(Firewall $firewall)
    {
        $user = auth()->user();
        if (!$user || (!$user->isGlobalAdmin() && !($user->isCompanyAdmin() && (int)$firewall->company_id === (int)$user->company_id))) {
            abort(403);
        }

        $firewall->delete();

        return redirect()->route('firewalls.index')->with('success', 'Firewall deleted successfully.');
    }

    protected function validateFirewallUrl(string $url): void
    {
        $parsed = parse_url($url);
        if (!isset($parsed['scheme']) || !in_array(strtolower($parsed['scheme']), ['http', 'https'], true)) {
            abort(422, 'Invalid URL scheme. Only HTTP and HTTPS are permitted.');
        }

        $host = $parsed['host'] ?? '';
        if (empty($host)) {
            abort(422, 'Invalid firewall URL.');
        }

        $lowerHost = strtolower($host);
        if (in_array($lowerHost, ['localhost', 'metadata.google.internal', 'instance-data'], true) || str_ends_with($lowerHost, '.localhost')) {
            abort(422, 'Target hostname is restricted.');
        }

        $ips = [];
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ips[] = $host;
        } else {
            $resolved = @gethostbynamel($host);
            if ($resolved) {
                $ips = $resolved;
            }
        }

        foreach ($ips as $ip) {
            if ($ip === '::1' || str_starts_with($ip, '127.')) {
                abort(422, 'Target resolves to a loopback address.');
            }
            if (str_starts_with($ip, '169.254.') || str_starts_with(strtolower($ip), 'fe80:')) {
                abort(422, 'Target resolves to a link-local or metadata address.');
            }
            if ($ip === '0.0.0.0' || $ip === '::') {
                abort(422, 'Target resolves to an invalid address.');
            }
        }
    }

    /**
     * Get cached status for firewalls (Polling fallback).
     */
    public function getCachedStatus(Request $request)
    {
        $user = $request->user();
        $ids = $request->input('ids', []);
        $query = Firewall::query();
        if ($user && !$user->isGlobalAdmin()) {
            $query->where('company_id', $user->company_id);
        }
        if (!empty($ids)) {
            $query->whereIn('id', $ids);
        }
        $firewalls = $query->get();

        $results = [];
        foreach ($firewalls as $firewall) {
            $cached = \Illuminate\Support\Facades\Cache::get('firewall_status_' . $firewall->id);
            if ($cached) {
                // Determine online status
                $isOnline = $cached['online'] ?? true;
                if (isset($cached['error'])) {
                    $isOnline = false;
                }

                $results[$firewall->id] = [
                    'online' => $isOnline,
                    'data' => $cached // Contains 'data' key with full info
                ];
            }
        }
        return response()->json(['results' => $results]);
    }
}
