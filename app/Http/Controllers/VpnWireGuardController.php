<?php

namespace App\Http\Controllers;

use App\Models\Firewall;
use App\Services\PfSenseApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VpnWireGuardController extends Controller
{
    public function index(Firewall $firewall)
    {
        $api = new PfSenseApiService($firewall);
        $tunnels = [];
        $peers = [];
        $general = ['enabled' => false];
        $serviceStatus = ['status' => 'unknown'];
        $handshakes = [];

        try {
            $tunnels = $api->getWireGuardTunnels()['data'] ?? [];
            $peers = $api->getWireGuardPeers()['data'] ?? [];

            if ($firewall->isOpnSense()) {
                $general = $api->getWireGuardGeneral();
                $serviceStatus = $api->getWireGuardServiceStatus()['data'] ?? ['status' => 'unknown'];
                $handshakes = $api->getWireGuardServiceShow()['data'] ?? [];
            }
        } catch (\Exception $e) {
            Log::error('Failed to fetch WireGuard data: ' . $e->getMessage());
        }

        return view('vpn.wireguard.index', compact('firewall', 'tunnels', 'peers', 'general', 'serviceStatus', 'handshakes'));
    }

    public function updateGeneral(Request $request, Firewall $firewall)
    {
        $validated = $request->validate([
            'enabled' => 'nullable',
        ]);

        try {
            $api = new PfSenseApiService($firewall);
            $api->setWireGuardGeneral([
                'enabled' => $request->has('enabled') ? '1' : '0',
            ]);
            return back()->with('success', 'WireGuard general settings updated successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to update WireGuard general settings: ' . $e->getMessage());
            return back()->with('error', 'Failed to update settings: ' . $e->getMessage());
        }
    }

    public function serviceAction(Request $request, Firewall $firewall, string $action)
    {
        try {
            $api = new PfSenseApiService($firewall);
            $api->serviceWireGuardAction($action);
            return back()->with('success', "WireGuard service " . ucfirst($action) . " executed successfully.");
        } catch (\Exception $e) {
            Log::error("Failed to execute WireGuard service {$action}: " . $e->getMessage());
            return back()->with('error', "Failed to {$action} service: " . $e->getMessage());
        }
    }

    public function generateKeyPair(Request $request, Firewall $firewall)
    {
        try {
            $api = new PfSenseApiService($firewall);
            $keyPair = $api->generateWireGuardKeyPair();
            return response()->json([
                'status' => 'success',
                'pubkey' => $keyPair['pubkey'] ?? '',
                'privkey' => $keyPair['privkey'] ?? '',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to generate WireGuard key pair: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function storeTunnel(Request $request, Firewall $firewall)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:64',
            'listenport' => 'nullable|numeric|between:1,65535',
            'tunneladdress' => 'required|string',
            'pubkey' => 'required|string',
            'privkey' => 'required|string',
            'mtu' => 'nullable|numeric|between:576,65535',
            'dns' => 'nullable|string',
            'disableroutes' => 'nullable',
            'peers' => 'nullable|array',
            'peers.*' => 'string',
            'enabled' => 'nullable',
        ]);

        $validated['enabled'] = $request->has('enabled') ? '1' : '0';
        $validated['disableroutes'] = $request->has('disableroutes') ? '1' : '0';

        try {
            $api = new PfSenseApiService($firewall);
            $api->createWireGuardTunnel($validated);
            return back()->with('success', "WireGuard instance '{$validated['name']}' created successfully.");
        } catch (\Exception $e) {
            Log::error('Failed to create WireGuard tunnel: ' . $e->getMessage());
            return back()->with('error', 'Failed to create instance: ' . $e->getMessage());
        }
    }

    public function updateTunnel(Request $request, Firewall $firewall, string $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:64',
            'listenport' => 'nullable|numeric|between:1,65535',
            'tunneladdress' => 'required|string',
            'pubkey' => 'required|string',
            'privkey' => 'required|string',
            'mtu' => 'nullable|numeric|between:576,65535',
            'dns' => 'nullable|string',
            'disableroutes' => 'nullable',
            'peers' => 'nullable|array',
            'peers.*' => 'string',
            'enabled' => 'nullable',
        ]);

        $validated['enabled'] = $request->has('enabled') ? '1' : '0';
        $validated['disableroutes'] = $request->has('disableroutes') ? '1' : '0';

        try {
            $api = new PfSenseApiService($firewall);
            $api->updateWireGuardTunnel($id, $validated);
            return back()->with('success', "WireGuard instance '{$validated['name']}' updated successfully.");
        } catch (\Exception $e) {
            Log::error('Failed to update WireGuard tunnel: ' . $e->getMessage());
            return back()->with('error', 'Failed to update instance: ' . $e->getMessage());
        }
    }

    public function destroyTunnel(Request $request, Firewall $firewall, string $id)
    {
        try {
            $api = new PfSenseApiService($firewall);
            $api->deleteWireGuardTunnel($id);
            return back()->with('success', 'WireGuard instance deleted successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to delete WireGuard tunnel: ' . $e->getMessage());
            return back()->with('error', 'Failed to delete instance: ' . $e->getMessage());
        }
    }

    public function toggleTunnel(Request $request, Firewall $firewall, string $id)
    {
        try {
            $api = new PfSenseApiService($firewall);
            $api->toggleWireGuardTunnel($id);
            return back()->with('success', 'WireGuard instance status toggled successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to toggle WireGuard tunnel: ' . $e->getMessage());
            return back()->with('error', 'Failed to toggle instance: ' . $e->getMessage());
        }
    }

    public function storePeer(Request $request, Firewall $firewall)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:64',
            'pubkey' => 'required|string',
            'psk' => 'nullable|string',
            'tunneladdress' => 'required|string',
            'serveraddress' => 'nullable|string',
            'serverport' => 'nullable|numeric|between:1,65535',
            'keepalive' => 'nullable|numeric|between:0,86400',
            'servers' => 'nullable|array',
            'servers.*' => 'string',
            'enabled' => 'nullable',
        ]);

        $validated['enabled'] = $request->has('enabled') ? '1' : '0';

        try {
            $api = new PfSenseApiService($firewall);
            $api->createWireGuardPeer($validated);
            return back()->with('success', "WireGuard endpoint '{$validated['name']}' created successfully.");
        } catch (\Exception $e) {
            Log::error('Failed to create WireGuard peer: ' . $e->getMessage());
            return back()->with('error', 'Failed to create endpoint: ' . $e->getMessage());
        }
    }

    public function updatePeer(Request $request, Firewall $firewall, string $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:64',
            'pubkey' => 'required|string',
            'psk' => 'nullable|string',
            'tunneladdress' => 'required|string',
            'serveraddress' => 'nullable|string',
            'serverport' => 'nullable|numeric|between:1,65535',
            'keepalive' => 'nullable|numeric|between:0,86400',
            'servers' => 'nullable|array',
            'servers.*' => 'string',
            'enabled' => 'nullable',
        ]);

        $validated['enabled'] = $request->has('enabled') ? '1' : '0';

        try {
            $api = new PfSenseApiService($firewall);
            $api->updateWireGuardPeer($id, $validated);
            return back()->with('success', "WireGuard endpoint '{$validated['name']}' updated successfully.");
        } catch (\Exception $e) {
            Log::error('Failed to update WireGuard peer: ' . $e->getMessage());
            return back()->with('error', 'Failed to update endpoint: ' . $e->getMessage());
        }
    }

    public function destroyPeer(Request $request, Firewall $firewall, string $id)
    {
        try {
            $api = new PfSenseApiService($firewall);
            $api->deleteWireGuardPeer($id);
            return back()->with('success', 'WireGuard endpoint deleted successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to delete WireGuard peer: ' . $e->getMessage());
            return back()->with('error', 'Failed to delete endpoint: ' . $e->getMessage());
        }
    }

    public function togglePeer(Request $request, Firewall $firewall, string $id)
    {
        try {
            $api = new PfSenseApiService($firewall);
            $api->toggleWireGuardPeer($id);
            return back()->with('success', 'WireGuard endpoint status toggled successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to toggle WireGuard peer: ' . $e->getMessage());
            return back()->with('error', 'Failed to toggle endpoint: ' . $e->getMessage());
        }
    }
}
