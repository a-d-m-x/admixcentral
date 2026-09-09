<?php

namespace App\Http\Controllers;

use App\Models\Firewall;
use App\Services\PfSenseApiService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class FirewallVirtualIpController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('deny.readonly', only: ['create', 'store', 'edit', 'update', 'destroy'])];
    }

    public function index(Firewall $firewall)
    {
        try {
            $api = new PfSenseApiService($firewall);
            $response = $api->getVirtualIps();
            $vips = $response['data'] ?? [];

            return view('firewall.virtual_ips.index', compact('firewall', 'vips'));
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to fetch Virtual IPs: ' . $e->getMessage());
        }
    }

    public function store(Request $request, Firewall $firewall)
    {
        $validated = $request->validate([
            'mode' => 'required|string|in:ipalias,carp,proxyarp,other',
            'interface' => 'required|string',
            'subnet' => 'required|string',
            'subnet_bits' => 'required|integer',
            'descr' => 'nullable|string',
            'password' => 'nullable|string',
            'vhid' => 'nullable|integer',
        ]);

        // Transform payload
        $data = [
            'mode' => $validated['mode'],
            'interface' => $validated['interface'],
            'subnet' => $validated['subnet'],
            'subnet_bits' => (int) $validated['subnet_bits'],
            'descr' => $validated['descr'] ?? '',
        ];

        if ($validated['mode'] === 'carp') {
            $data['password'] = $validated['password'] ?? '';
            $data['vhid'] = (int) ($validated['vhid'] ?? 0);
        }

        try {
            $api = new PfSenseApiService($firewall);
            $api->createVirtualIp($data);
            $firewall->update(['is_dirty' => true]);

            return redirect()->route('firewall.virtual_ips.index', $firewall)
                ->with('success', 'Virtual IP created successfully. Please apply changes.');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Failed to create Virtual IP: ' . $e->getMessage());
        }
    }

    public function update(Request $request, Firewall $firewall, $id)
    {
        $validated = $request->validate([
            'mode' => 'required|string|in:ipalias,carp,proxyarp,other',
            'interface' => 'required|string',
            'subnet' => 'required|string',
            'subnet_bits' => 'required|integer',
            'descr' => 'nullable|string',
            'password' => 'nullable|string',
            'vhid' => 'nullable|integer',
        ]);

        // Transform payload
        $data = [
            'mode' => $validated['mode'],
            'interface' => $validated['interface'],
            'subnet' => $validated['subnet'],
            'subnet_bits' => (int) $validated['subnet_bits'],
            'descr' => $validated['descr'] ?? '',
            'id' => is_numeric($id) ? (int) $id : (string) $id,
        ];

        if ($validated['mode'] === 'carp') {
            $data['password'] = $validated['password'] ?? '';
            $data['vhid'] = (int) ($validated['vhid'] ?? 0);
        }

        try {
            $api = new PfSenseApiService($firewall);
            $api->updateVirtualIp($data['id'], $data);
            $firewall->update(['is_dirty' => true]);

            return redirect()->route('firewall.virtual_ips.index', $firewall)
                ->with('success', 'Virtual IP updated successfully. Please apply changes.');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Failed to update Virtual IP: ' . $e->getMessage());
        }
    }

    public function destroy(Firewall $firewall, $id)
    {
        try {
            $api = new PfSenseApiService($firewall);
            $idParam = is_numeric($id) ? (int) $id : (string) $id;
            $api->deleteVirtualIp($idParam);
            $firewall->update(['is_dirty' => true]);

            return redirect()->route('firewall.virtual_ips.index', $firewall)
                ->with('success', 'Virtual IP deleted successfully. Please apply changes.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to delete Virtual IP: ' . $e->getMessage());
        }
    }
}
