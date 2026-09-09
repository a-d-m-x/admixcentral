<?php

namespace App\Http\Controllers;

use App\Models\Firewall;
use App\Services\PfSenseApiService;
use Illuminate\Http\Request;

class ServicesSnmpController extends Controller
{
    public function index(Firewall $firewall)
    {
        $api = new PfSenseApiService($firewall);
        $snmp = [];
        $error = null;

        try {
            $response = $api->getSnmp();
            $snmp = $response['data'] ?? [];
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), '404') || str_contains($e->getMessage(), 'Not Found')) {
                return view('services.snmp.unsupported', compact('firewall'));
            }
            $error = $e->getMessage();
        }

        if (auth()->user()->isReadOnly()) {
            unset($snmp['rocommunity'], $snmp['rwcommunity']);
        }

        return view('services.snmp.index', compact('firewall', 'snmp', 'error'));
    }

    public function update(Firewall $firewall, Request $request)
    {
        $api = new PfSenseApiService($firewall);
        $data = $request->except(['_token', '_method']);

        try {
            $api->updateSnmp($data);
            return redirect()->route('services.snmp', $firewall)
                ->with('success', 'SNMP settings updated successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to update SNMP settings: ' . $e->getMessage())->withInput();
        }
    }
}
