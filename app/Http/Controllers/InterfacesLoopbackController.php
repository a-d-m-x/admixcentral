<?php

namespace App\Http\Controllers;

use App\Models\Firewall;
use App\Services\PfSenseApiService;
use Illuminate\Http\Request;

class InterfacesLoopbackController extends Controller
{
    public function index(Firewall $firewall)
    {
        $api = new PfSenseApiService($firewall);
        $loopbacks = [];
        $error = null;

        try {
            $response = $api->getLoopbacks();
            $loopbacks = $response['data'] ?? [];
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), '404') ||
                str_contains($e->getMessage(), 'Not Found') ||
                str_contains($e->getMessage(), 'not supported')) {
                return view('interfaces.loopbacks.unsupported', compact('firewall'));
            }
            $error = $e->getMessage();
        }

        return view('interfaces.loopbacks.index', compact('firewall', 'loopbacks', 'error'));
    }

    public function create(Firewall $firewall)
    {
        return view('interfaces.loopbacks.create', compact('firewall'));
    }

    public function store(Firewall $firewall, Request $request)
    {
        $request->validate([
            'deviceId' => 'required|integer|min:0|max:65535',
            'description' => 'required|string|max:255',
        ]);

        $api = new PfSenseApiService($firewall);
        $data = $request->only(['deviceId', 'description']);

        try {
            $api->createLoopback($data);
            return redirect()->route('interfaces.loopbacks.index', $firewall)
                ->with('success', 'Loopback interface created successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to create loopback interface: ' . $e->getMessage())->withInput();
        }
    }

    public function edit(Firewall $firewall, string $id)
    {
        $api = new PfSenseApiService($firewall);
        $loopback = null;

        try {
            $response = $api->getLoopback($id);
            $loopback = $response['data'] ?? null;
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to fetch loopback interface: ' . $e->getMessage());
        }

        if (!$loopback) {
            return redirect()->route('interfaces.loopbacks.index', $firewall)
                ->with('error', 'Loopback interface not found.');
        }

        return view('interfaces.loopbacks.edit', compact('firewall', 'loopback', 'id'));
    }

    public function update(Firewall $firewall, string $id, Request $request)
    {
        $request->validate([
            'deviceId' => 'required|integer|min:0|max:65535',
            'description' => 'required|string|max:255',
        ]);

        $api = new PfSenseApiService($firewall);
        $data = $request->only(['deviceId', 'description']);

        try {
            $api->updateLoopback($id, $data);
            return redirect()->route('interfaces.loopbacks.index', $firewall)
                ->with('success', 'Loopback interface updated successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to update loopback interface: ' . $e->getMessage())->withInput();
        }
    }

    public function destroy(Firewall $firewall, string $id)
    {
        $api = new PfSenseApiService($firewall);

        try {
            $api->deleteLoopback($id);
            return redirect()->route('interfaces.loopbacks.index', $firewall)
                ->with('success', 'Loopback interface deleted successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to delete loopback interface: ' . $e->getMessage());
        }
    }
}
