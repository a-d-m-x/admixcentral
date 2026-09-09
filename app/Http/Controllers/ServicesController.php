<?php

namespace App\Http\Controllers;

use App\Models\Firewall;
use App\Services\PfSenseApiService;
use Illuminate\Http\Request;

class ServicesController extends Controller
{
    public function captivePortal(Firewall $firewall)
    {
        return view('services.captive-portal', compact('firewall'));
    }

    public function autoConfigBackup(Firewall $firewall)
    {
        return view('services.auto-config-backup', compact('firewall'));
    }

    public function dhcpRelay(Firewall $firewall)
    {
        $api = new \App\Services\PfSenseApiService($firewall);
        $config = [];

        try {
            $config = $api->getDhcpRelay()['data'] ?? [];
        } catch (\Exception $e) {
            // Log error
        }

        return view('services.dhcp-relay', compact('firewall', 'config'));
    }

    public function updateDhcpRelay(Request $request, Firewall $firewall)
    {
        $api = new \App\Services\PfSenseApiService($firewall);

        $data = $request->all();

        // Transform booleans
        $data['enable'] = $request->has('enable');
        $data['agentoption'] = $request->has('agentoption');

        // Transform server string to array
        if (isset($data['server']) && is_string($data['server'])) {
            $data['server'] = array_map('trim', explode(',', $data['server']));
        }

        // Ensure interface is array
        if (!isset($data['interface'])) {
            $data['interface'] = [];
        }

        try {
            $api->updateDhcpRelay($data);
            return redirect()->back()->with('status', 'DHCP Relay settings updated.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function dhcpv6Relay(Firewall $firewall)
    {
        return view('services.dhcpv6-relay', compact('firewall'));
    }

    public function dhcpv6Server(Firewall $firewall)
    {
        return view('services.dhcpv6-server', compact('firewall'));
    }

    public function dnsForwarder(Firewall $firewall)
    {
        $api = new PfSenseApiService($firewall);
        $settings = [];
        $serviceStatus = [];
        $hostOverrides = [];

        try {
            $settingsRes = $api->getDnsForwarderSettings();
            $settings = $settingsRes['data'] ?? [];

            if ($api->getOpnSense()) {
                $serviceStatus = $api->getOpnSense()->getDnsForwarderServiceStatus();
            }

            $hostsRes = $api->getDnsForwarderHostOverrides();
            $hostOverrides = $hostsRes['data'] ?? [];
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Failed to fetch DNS forwarder info: " . $e->getMessage());
        }

        return view('services.dns-forwarder', compact('firewall', 'settings', 'serviceStatus', 'hostOverrides'));
    }

    public function storeDnsForwarderHost(Request $request, Firewall $firewall)
    {
        $validated = $request->validate([
            'host' => 'required|string|max:255',
            'domain' => 'required|string|max:255',
            'ip' => 'required|ip',
            'descr' => 'nullable|string|max:255',
        ]);

        try {
            $api = new PfSenseApiService($firewall);
            $api->createDnsForwarderHostOverride($validated);

            return redirect()->route('services.dns-forwarder', $firewall)
                ->with('success', 'Host override added successfully.');
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Failed to add host override: ' . $e->getMessage());
        }
    }

    public function destroyDnsForwarderHost(Firewall $firewall, string $uuid)
    {
        try {
            $api = new PfSenseApiService($firewall);
            $api->deleteDnsForwarderHostOverride($uuid);

            return redirect()->route('services.dns-forwarder', $firewall)
                ->with('success', 'Host override deleted successfully.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed to delete host override: ' . $e->getMessage());
        }
    }

    public function dynamicDns(Firewall $firewall)
    {
        return view('services.dynamic-dns', compact('firewall'));
    }

    public function igmpProxy(Firewall $firewall)
    {
        return view('services.igmp-proxy', compact('firewall'));
    }

    public function ntp(Firewall $firewall)
    {
        return view('services.ntp', compact('firewall'));
    }

    public function pppoeServer(Firewall $firewall)
    {
        return view('services.pppoe-server', compact('firewall'));
    }

    public function routerAdvertisement(Firewall $firewall)
    {
        return view('services.router-advertisement', compact('firewall'));
    }

    public function snmp(Firewall $firewall)
    {
        return view('services.snmp', compact('firewall'));
    }

    public function upnp(Firewall $firewall)
    {
        return view('services.upnp', compact('firewall'));
    }

    public function wakeOnLan(Firewall $firewall)
    {
        return view('services.wake-on-lan', compact('firewall'));
    }
}
