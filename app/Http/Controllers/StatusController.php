<?php

namespace App\Http\Controllers;

use App\Models\Firewall;
use Illuminate\Http\Request;

class StatusController extends Controller
{
    public function captivePortal(Firewall $firewall)
    {
        return view('status.captive-portal', compact('firewall'));
    }

    public function carp(Firewall $firewall)
    {
        $api = new \App\Services\PfSenseApiService($firewall);
        $carpStatus = [];
        $virtualIps = [];

        try {
            $carpStatus = $api->getCarpStatus()['data'] ?? [];
            $virtualIps = $api->getVirtualIps()['data'] ?? [];
        } catch (\Exception $e) {
            // Log error
        }

        return view('status.carp', compact('firewall', 'carpStatus', 'virtualIps'));
    }

    public function updateCarp(Request $request, Firewall $firewall)
    {
        try {
            $api = new \App\Services\PfSenseApiService($firewall);
            $data = [
                'enable' => $request->has('enable'),
                'maintenance_mode' => $request->has('maintenance_mode'),
            ];
            $api->updateCarpStatus($data);
            return back()->with('success', 'CARP status updated successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to update CARP status: ' . $e->getMessage());
        }
    }

    public function dhcpLeases(Firewall $firewall)
    {
        $api = new \App\Services\PfSenseApiService($firewall);
        $leases = [];
        try {
            $leases = $api->getDhcpLeases()['data'] ?? [];
        } catch (\Exception $e) {
            // Log error
        }
        return view('status.dhcp-leases', compact('firewall', 'leases'));
    }

    public function dhcpv6Leases(Firewall $firewall)
    {
        return view('status.dhcpv6-leases', compact('firewall'));
    }

    public function filterReload(Firewall $firewall)
    {
        return view('status.filter-reload', compact('firewall'));
    }



    public function ipsec(Firewall $firewall)
    {
        $api = new \App\Services\PfSenseApiService($firewall);
        $status = [];
        try {
            $status = $api->getIpsecStatus()['data'] ?? [];
        } catch (\Exception $e) {
            // Log error
        }
        return view('status.ipsec', compact('firewall', 'status'));
    }

    public function monitoring(Firewall $firewall)
    {
        if ($firewall->isOpnSense()) {
            return redirect()->route('services.monit.index', $firewall);
        }

        return view('status.monitoring', compact('firewall'));
    }

    public function ntp(Firewall $firewall)
    {
        return view('status.ntp', compact('firewall'));
    }

    public function openvpn(Firewall $firewall)
    {
        $api = new \App\Services\PfSenseApiService($firewall);
        $status = [];
        try {
            $status = $api->getOpenVpnServerStatus()['data'] ?? [];
        } catch (\Exception $e) {
            // Log error
        }
        return view('status.openvpn', compact('firewall', 'status'));
    }

    public function dhcp(Firewall $firewall)
    {
        $api = new \App\Services\PfSenseApiService($firewall);
        $leases = [];
        try {
            $leases = $api->getDhcpLeases()['data'] ?? [];
        } catch (\Exception $e) {
            // Log error
        }
        return view('status.dhcp', compact('firewall', 'leases'));
    }

    public function gateways(Firewall $firewall)
    {
        if (request()->wantsJson()) {
            session_write_close();
        }

        $api = new \App\Services\PfSenseApiService($firewall);
        $gateways = [];
        try {
            // Fetch gateway status (online, loss, delay, etc.)
            $statusData = $api->getGateways()['data'] ?? [];

            // Fetch gateway configuration (includes descr field)
            $configData = $api->getRoutingGateways()['data'] ?? [];

            // Merge descr field from config into status data by matching names
            foreach ($statusData as &$gateway) {
                $config = collect($configData)->firstWhere('name', $gateway['name']);
                if ($config) {
                    $gateway['descr'] = $config['descr'] ?? '';
                }
            }

            $gateways = $statusData;
        } catch (\Exception $e) {
            // Log error
        }

        if (request()->wantsJson()) {
            return response()->json($gateways);
        }

        return view('status.gateways', compact('firewall', 'gateways'));
    }

    public function interfaces(Firewall $firewall)
    {
        if (request()->wantsJson()) {
            session_write_close();
        }

        $api = new \App\Services\PfSenseApiService($firewall);
        $interfaces = [];
        try {
            $interfaces = $api->getInterfacesStatus()['data'] ?? [];
        } catch (\Exception $e) {
            // Log error
        }

        if (request()->wantsJson()) {
            return response()->json($interfaces);
        }

        return view('status.interfaces', compact('firewall', 'interfaces'));
    }

    public function services(Firewall $firewall)
    {
        $api = new \App\Services\PfSenseApiService($firewall);
        $services = [];
        try {
            $services = $api->getServicesStatus()['data'] ?? [];
        } catch (\Exception $e) {
            // Log error
        }
        return view('status.services', compact('firewall', 'services'));
    }

    public function serviceAction(Request $request, Firewall $firewall, string $service, string $action)
    {
        if (!in_array($action, ['start', 'stop', 'restart'])) {
            return back()->with('error', 'Invalid service action.');
        }

        try {
            $api = new \App\Services\PfSenseApiService($firewall);
            $api->post("/services/{$action}/{$service}");
            return back()->with('success', "Service {$service} " . ($action === 'stop' ? 'stopped' : $action . 'ed') . " successfully.");
        } catch (\Exception $e) {
            return back()->with('error', "Failed to {$action} service {$service}: " . $e->getMessage());
        }
    }

    public function system(Firewall $firewall)
    {
        $api = new \App\Services\PfSenseApiService($firewall);
        $system = [];
        try {
            $system = $api->getSystemStatus()['data'] ?? [];
        } catch (\Exception $e) {
            // Log error
        }
        return view('status.system', compact('firewall', 'system'));
    }

    public function queues(Firewall $firewall)
    {
        $pipes = [];
        $queues = [];
        $rules = [];

        if ($firewall->isOpnSense()) {
            try {
                $api = new \App\Services\PfSenseApiService($firewall);
                $opn = $api->getOpnSense();
                if ($opn) {
                    $pipes = $opn->getTrafficShaperPipes()['data'] ?? [];
                    $queues = $opn->getTrafficShaperQueues()['data'] ?? [];
                    $rules = $opn->getTrafficShaperRules()['data'] ?? [];
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Failed to fetch traffic shaper status: " . $e->getMessage());
            }
        }

        return view('status.queues', compact('firewall', 'pipes', 'queues', 'rules'));
    }


    public function systemLogs(Firewall $firewall)
    {
        $api = new \App\Services\PfSenseApiService($firewall);
        $rawLogs = [];
        $logs = [];
        $type = request('type', 'system');
        $syslogSettings = [];
        $syslogDestinations = [];
        $syslogServiceStatus = [];
        $syslogStats = [];

        if ($type === 'settings') {
            try {
                $syslogSettings = $api->getSyslogSettings()['data'] ?? [];
                $syslogDestinations = $api->getSyslogDestinations()['data'] ?? [];
                $syslogServiceStatus = $api->getSyslogServiceStatus();
                $syslogStats = $api->getSyslogStats();
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Failed to fetch syslog settings: " . $e->getMessage());
            }
            return view('status.system-logs', compact('firewall', 'syslogSettings', 'syslogDestinations', 'syslogServiceStatus', 'syslogStats'));
        }

        try {
            $rawLogs = $api->getSystemLogs($type)['data'] ?? [];

            foreach ($rawLogs as $log) {
                if (isset($log['message'])) {
                    $logs[] = [
                        'time' => $log['time'] ?? '-',
                        'process' => $log['process'] ?? '-',
                        'pid' => $log['pid'] ?? '-',
                        'message' => $log['message'],
                    ];
                } elseif (isset($log['text'])) {
                    // Parse syslog format: "Month Day Time Host Process[PID]: Message"
                    // Regex: Time Host Process [PID]? : Message
                    if (preg_match('/^([A-Z][a-z]{2}\s+\d+\s\d{2}:\d{2}:\d{2})\s+(\S+)\s+([^:\[\s]+)(?:\[(\d+)\])?:\s+(.*)$/', $log['text'], $matches)) {
                        $logs[] = [
                            'time' => $matches[1],
                            // 'host' => $matches[2], 
                            'process' => $matches[3],
                            'pid' => $matches[4] ?? '-',
                            'message' => $matches[5],
                        ];
                    } else {
                        // Fallback for non-matching lines
                        $logs[] = [
                            'time' => '-',
                            'process' => '-',
                            'pid' => '-',
                            'message' => $log['text'],
                        ];
                    }
                }
            }

            $logs = array_reverse($logs); // Show newest first

        } catch (\Exception $e) {
            // Log error
        }
        return view('status.system-logs', compact('firewall', 'logs'));
    }

    public function storeSyslogDestination(Request $request, Firewall $firewall)
    {
        $validated = $request->validate([
            'hostname' => 'required|string|max:255',
            'port' => 'required|integer|min:1|max:65535',
            'transport' => 'required|string|in:udp4,tcp4,udp6,tcp6,tls4,tls6',
            'description' => 'nullable|string|max:255',
            'enabled' => 'nullable|boolean',
        ]);

        try {
            $api = new \App\Services\PfSenseApiService($firewall);
            $api->createSyslogDestination($validated);

            return redirect()->route('status.system-logs', [$firewall, 'type' => 'settings'])
                ->with('success', 'Remote syslog destination added successfully.');
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Failed to add syslog destination: ' . $e->getMessage());
        }
    }

    public function destroySyslogDestination(Firewall $firewall, string $uuid)
    {
        try {
            $api = new \App\Services\PfSenseApiService($firewall);
            $api->deleteSyslogDestination($uuid);

            return redirect()->route('status.system-logs', [$firewall, 'type' => 'settings'])
                ->with('success', 'Remote syslog destination deleted successfully.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed to delete syslog destination: ' . $e->getMessage());
        }
    }

    public function trafficGraph(Firewall $firewall)
    {
        return view('status.traffic-graph', compact('firewall'));
    }

    public function upnp(Firewall $firewall)
    {
        return view('status.upnp', compact('firewall'));
    }

    public function packages(Firewall $firewall)
    {
        if (request()->wantsJson()) {
            session_write_close();
        }

        $api = new \App\Services\PfSenseApiService($firewall);
        $packages = [];
        try {
            $response = $api->getSystemPackages();
            // Handle different API response structures
            if (isset($response['data']['package']) && is_array($response['data']['package'])) {
                $packages = $response['data']['package'];
            } elseif (isset($response['data']) && is_array($response['data'])) {
                $packages = $response['data'];
            }
        } catch (\Exception $e) {
            // Log error
        }

        if (request()->wantsJson()) {
            return response()->json($packages);
        }

        return redirect()->route('system.package_manager.index', $firewall);
    }
}
