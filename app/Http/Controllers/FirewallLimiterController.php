<?php

namespace App\Http\Controllers;

use App\Models\Firewall;
use App\Services\PfSenseApiService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class FirewallLimiterController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('deny.readonly', only: ['create', 'store', 'edit', 'update', 'destroy'])];
    }

    public function index(Firewall $firewall)
    {
        try {
            $api = new PfSenseApiService($firewall);
            $response = $api->getLimiters();
            $limiters = $response['data'] ?? [];

            return view('firewall.limiters.index', compact('firewall', 'limiters'));
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to fetch limiters: ' . $e->getMessage());
        }
    }

    public function store(Request $request, Firewall $firewall)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'descr' => 'nullable|string',
            'bandwidth_value' => 'required|integer',
            'bandwidth_scale' => 'required|string|in:b,Kb,Mb,Gb',
            'mask' => 'required|string|in:none,srcaddress,dstaddress',
            'maskbits' => 'nullable|integer',
            'aqm' => 'required|string',
            'sched' => 'required|string',
        ]);

        // Transform payload
        $data = [
            'name' => $validated['name'],
            'descr' => $validated['descr'] ?? '',
            'bandwidth' => [
                'item' => [
                    'bw' => (int) $validated['bandwidth_value'],
                    'bwscale' => $validated['bandwidth_scale']
                ]
            ],
            'mask' => $validated['mask'],
            'aqm' => $validated['aqm'],
            'sched' => $validated['sched'],
        ];

        if (!empty($validated['maskbits'])) {
            $data['maskbits'] = (int) $validated['maskbits'];
        }

        try {
            $api = new PfSenseApiService($firewall);
            $api->createLimiter($data);
            $firewall->update(['is_dirty' => true]);

            return redirect()->route('firewall.limiters.index', $firewall)
                ->with('success', 'Limiter created successfully. Please apply changes.');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Failed to create limiter: ' . $e->getMessage());
        }
    }

    public function update(Request $request, Firewall $firewall, $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'descr' => 'nullable|string',
            'bandwidth_value' => 'required|integer',
            'bandwidth_scale' => 'required|string|in:b,Kb,Mb,Gb',
            'mask' => 'required|string|in:none,srcaddress,dstaddress',
            'maskbits' => 'nullable|integer',
            'aqm' => 'required|string',
            'sched' => 'required|string',
        ]);

        // Transform payload
        $data = [
            'name' => $validated['name'],
            'descr' => $validated['descr'] ?? '',
            'bandwidth' => [
                'item' => [
                    'bw' => (int) $validated['bandwidth_value'],
                    'bwscale' => $validated['bandwidth_scale']
                ]
            ],
            'mask' => $validated['mask'],
            'aqm' => $validated['aqm'],
            'sched' => $validated['sched'],
        ];

        if (!empty($validated['maskbits'])) {
            $data['maskbits'] = (int) $validated['maskbits'];
        }

        try {
            $api = new PfSenseApiService($firewall);
            $limiterId = is_numeric($id) ? (int) $id : (string) $id;
            $api->updateLimiter($limiterId, $data);
            $firewall->update(['is_dirty' => true]);

            return redirect()->route('firewall.limiters.index', $firewall)
                ->with('success', 'Limiter updated successfully. Please apply changes.');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Failed to update limiter: ' . $e->getMessage());
        }
    }

    public function destroy(Firewall $firewall, $id)
    {
        try {
            $api = new PfSenseApiService($firewall);
            $limiterId = is_numeric($id) ? (int) $id : (string) $id;
            $api->deleteLimiter($limiterId);
            $firewall->update(['is_dirty' => true]);

            return redirect()->route('firewall.limiters.index', $firewall)
                ->with('success', 'Limiter deleted successfully. Please apply changes.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to delete limiter: ' . $e->getMessage());
        }
    }
}
