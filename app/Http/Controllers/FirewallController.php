<?php

namespace App\Http\Controllers;

use App\Models\Firewall;
use App\Models\Company;
use Illuminate\Http\Request;

class FirewallController extends Controller
{
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
            $firewalls = Firewall::with('company')->orderBy('name')->get();
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

        if (!empty($ids)) {
            $firewalls = Firewall::whereIn('id', $ids)->get();
        } else {
            $user = $request->user();
            if ($user->isGlobalAdmin()) {
                $firewalls = Firewall::all();
            } else {
                $firewalls = Firewall::where('company_id', $user->company_id)->get();
            }
        }

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

    public function create()
    {
        $user = auth()->user();
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

        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'name' => 'required|string|max:255',
            'url' => 'required|url',
            'auth_method' => 'required|in:basic,token',
            'api_key' => 'required_if:auth_method,basic|nullable|string',
            'api_secret' => 'required_if:auth_method,basic|nullable|string',
            'api_token' => 'required_if:auth_method,token|nullable|string',
            'description' => 'nullable|string',
        ]);

        if ($user->isCompanyAdmin()) {
            if ((int) $validated['company_id'] !== (int) $user->company_id) {
                abort(403, 'You can only create firewalls for your own company.');
            }
        } elseif (!$user->isGlobalAdmin()) {
            abort(403);
        }

        $firewall = new Firewall($validated);

        try {
            $api = new \App\Services\PfSenseApiService($firewall);
            // We need to set credentials manually on the service if the model isn't saved/mutated yet?
            // PfSenseApiService constructor uses model attributes.
            // Model attributes are set in new Firewall($validated).
            // Encrypted casting might not happen if not saving? 
            // Actually, casts happen on set/save. If we just 'new', attributes are raw.
            // But Service expects raw/decrypted.
            // Wait, if I set 'api_key' on model, it might auto-encrypt if cast is 'encrypted'.
            // If I read it back, it decrypts.
            // So new Firewall($validated) should work in memory.

            $response = $api->get('/status/system');
            if (isset($response['data']['netgate_id'])) {
                $validated['netgate_id'] = $response['data']['netgate_id'];
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
        // Scope check
        $user = auth()->user();
        if ($user->isCompanyAdmin() && $firewall->company_id !== $user->company_id) {
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
        if ($user->isCompanyAdmin() && $firewall->company_id !== $user->company_id) {
            abort(403);
        }

        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'name' => 'required|string|max:255',
            'url' => 'required|url',
            'auth_method' => 'required|in:basic,token',
            'api_key' => 'required_if:auth_method,basic|nullable|string',
            'api_secret' => 'nullable|string',
            'api_token' => 'required_if:auth_method,token|nullable|string',
            'description' => 'nullable|string',
            'address' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);

        if ($user->isCompanyAdmin()) {
            if ((int) $validated['company_id'] !== (int) $user->company_id) {
                // Prevent moving firewall to another company
                abort(403);
            }
        }

        if ($validated['auth_method'] === 'token') {
            $validated['api_key'] = null;
            $validated['api_secret'] = null;
        } else {
            $validated['api_token'] = null;
            if (empty($validated['api_secret'])) {
                if (empty($firewall->api_secret) && $firewall->auth_method !== 'basic') {
                    return back()->withErrors(['api_secret' => 'Password is required when switching to Basic Authentication.'])->withInput();
                }
                unset($validated['api_secret']);
            }
        }

        $firewall->update($validated);

        return redirect()->route('firewalls.index')->with('success', 'Firewall updated successfully.');
    }

    public function destroy(Firewall $firewall)
    {
        $user = auth()->user();
        if ($user->isCompanyAdmin() && $firewall->company_id !== $user->company_id) {
            abort(403);
        }
        if (!$user->isGlobalAdmin() && !$user->isCompanyAdmin()) {
            abort(403);
        }

        $firewall->delete();

        return redirect()->route('firewalls.index')->with('success', 'Firewall deleted successfully.');
    }

    /**
     * Get cached status for firewalls (Polling fallback).
     */
    public function getCachedStatus(Request $request)
    {
        $ids = $request->input('ids', []);
        $firewalls = Firewall::whereIn('id', $ids)->get();

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
