<?php

namespace App\Http\Controllers;

use App\Models\Firewall;
use App\Services\PfSenseApiService;
use Illuminate\Http\Request;

class InterfacesVxlanController extends Controller
{
    public function index(Firewall $firewall)
    {
        $api = new PfSenseApiService($firewall);
        $vxlans = [];
        $error = null;

        try {
            $response = $api->getVxlans();
            $vxlans = $response['data'] ?? [];
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), '404') ||
                str_contains($e->getMessage(), 'Not Found') ||
                str_contains($e->getMessage(), 'not supported')) {
                return view('interfaces.vxlans.unsupported', compact('firewall'));
            }
            $error = $e->getMessage();
        }

        return view('interfaces.vxlans.index', compact('firewall', 'vxlans', 'error'));
    }

    public function create(Firewall $firewall)
    {
        $api = new PfSenseApiService($firewall);
        $interfaces = [];

        try {
            $response = $api->getInterfaces();
            $interfaces = $response['data'] ?? [];
        } catch (\Exception $e) {}

        return view('interfaces.vxlans.create', compact('firewall', 'interfaces'));
    }

    public function store(Firewall $firewall, Request $request)
    {
        $request->validate([
            'deviceId' => 'required|integer|min:0|max:65535',
            'vxlanid' => 'required|integer|min:1|max:16777215',
            'vxlanlocal' => 'required|string',
            'vxlanremote' => 'nullable|string',
            'vxlangroup' => 'nullable|string',
            'vxlanlocalport' => 'nullable|integer|min:1|max:65535',
            'vxlanremoteport' => 'nullable|integer|min:1|max:65535',
            'vxlandev' => 'nullable|string',
        ]);

        if (empty($request->vxlanremote) && empty($request->vxlangroup)) {
            return back()->with('error', 'Either Remote Address (unicast) or Multicast Group must be specified.')->withInput();
        }

        $api = new PfSenseApiService($firewall);
        $data = $request->only([
            'deviceId', 'vxlanid', 'vxlanlocal', 'vxlanlocalport',
            'vxlanremote', 'vxlanremoteport', 'vxlangroup', 'vxlandev'
        ]);

        try {
            $api->createVxlan($data);
            return redirect()->route('interfaces.vxlans.index', $firewall)
                ->with('success', 'VXLAN interface created successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to create VXLAN interface: ' . $e->getMessage())->withInput();
        }
    }

    public function edit(Firewall $firewall, string $id)
    {
        $api = new PfSenseApiService($firewall);
        $vxlan = null;
        $interfaces = [];

        try {
            $response = $api->getVxlan($id);
            $vxlan = $response['data'] ?? null;

            $ifResponse = $api->getInterfaces();
            $interfaces = $ifResponse['data'] ?? [];
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to fetch VXLAN interface: ' . $e->getMessage());
        }

        if (!$vxlan) {
            return redirect()->route('interfaces.vxlans.index', $firewall)
                ->with('error', 'VXLAN interface not found.');
        }

        return view('interfaces.vxlans.edit', compact('firewall', 'vxlan', 'interfaces', 'id'));
    }

    public function update(Firewall $firewall, string $id, Request $request)
    {
        $request->validate([
            'deviceId' => 'required|integer|min:0|max:65535',
            'vxlanid' => 'required|integer|min:1|max:16777215',
            'vxlanlocal' => 'required|string',
            'vxlanremote' => 'nullable|string',
            'vxlangroup' => 'nullable|string',
            'vxlanlocalport' => 'nullable|integer|min:1|max:65535',
            'vxlanremoteport' => 'nullable|integer|min:1|max:65535',
            'vxlandev' => 'nullable|string',
        ]);

        if (empty($request->vxlanremote) && empty($request->vxlangroup)) {
            return back()->with('error', 'Either Remote Address (unicast) or Multicast Group must be specified.')->withInput();
        }

        $api = new PfSenseApiService($firewall);
        $data = $request->only([
            'deviceId', 'vxlanid', 'vxlanlocal', 'vxlanlocalport',
            'vxlanremote', 'vxlanremoteport', 'vxlangroup', 'vxlandev'
        ]);

        try {
            $api->updateVxlan($id, $data);
            return redirect()->route('interfaces.vxlans.index', $firewall)
                ->with('success', 'VXLAN interface updated successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to update VXLAN interface: ' . $e->getMessage())->withInput();
        }
    }

    public function destroy(Firewall $firewall, string $id)
    {
        $api = new PfSenseApiService($firewall);

        try {
            $api->deleteVxlan($id);
            return redirect()->route('interfaces.vxlans.index', $firewall)
                ->with('success', 'VXLAN interface deleted successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to delete VXLAN interface: ' . $e->getMessage());
        }
    }
}
