<?php

namespace App\Http\Controllers;

use App\Models\Firewall;
use App\Services\PfSenseApiService;
use Illuminate\Http\Request;

class ServicesDnsResolverController extends Controller
{
    public function index(Firewall $firewall)
    {
        try {
            $api = new PfSenseApiService($firewall);
            $response = $api->getDnsResolver();

            $config = $response['data'] ?? [];

            return view('services.dns.resolver', compact('firewall', 'config'));
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to fetch DNS Resolver configuration: ' . $e->getMessage());
        }
    }

    public function update(Request $request, Firewall $firewall)
    {
        $validated = $request->validate([
            'enable' => 'boolean',
            'port' => 'nullable|integer|min:1|max:65535',
            'dnssec' => 'boolean',
            'forwarding' => 'boolean',
            'regdhcp' => 'boolean',
            'regdhcpstatic' => 'boolean',
            'custom_options' => 'nullable|string',
        ]);

        $data = [
            'enable' => $request->has('enable'),
            'port' => $validated['port'] ?? 53,
            'dnssec' => $request->has('dnssec'),
            'forwarding' => $request->has('forwarding'),
            'regdhcp' => $request->has('regdhcp'),
            'regdhcpstatic' => $request->has('regdhcpstatic'),
            'custom_options' => $validated['custom_options'] ?? '',
        ];

        try {
            $api = new PfSenseApiService($firewall);
            $api->updateDnsResolver($data);

            return back()->with('success', 'DNS Resolver configuration updated successfully.');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Failed to update DNS Resolver: ' . $e->getMessage());
        }
    }

    public function hostOverrides(Firewall $firewall)
    {
        try {
            $api = new PfSenseApiService($firewall);
            $response = $api->getDnsResolverHostOverrides();

            $hosts = $response['data'] ?? [];

            return view('services.dns.host-overrides', compact('firewall', 'hosts'));
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to fetch host overrides: ' . $e->getMessage());
        }
    }

    public function storeHostOverride(Request $request, Firewall $firewall)
    {
        $validated = $request->validate([
            'host' => 'required|string',
            'domain' => 'required|string',
            'ip' => 'required|ip',
            'descr' => 'nullable|string',
        ]);

        try {
            $api = new PfSenseApiService($firewall);
            $api->createDnsResolverHostOverride($validated);

            return back()->with('success', 'Host override added successfully.');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Failed to add host override: ' . $e->getMessage());
        }
    }

    public function destroyHostOverride(Firewall $firewall, string $id)
    {
        try {
            $api = new PfSenseApiService($firewall);
            $api->deleteDnsResolverHostOverride($id);

            return back()->with('success', 'Host override deleted successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to delete host override: ' . $e->getMessage());
        }
    }
}
