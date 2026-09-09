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



    public function ipsec(Request $request, Firewall $firewall)
    {
        $tab = $request->query('tab', 'overview');
        if (!in_array($tab, ['overview', 'leases', 'sads', 'spds'])) {
            $tab = 'overview';
        }

        $api = new \App\Services\PfSenseApiService($firewall);
        $overview = [];
        $leases = [];
        $sads = [];
        $spds = [];

        try {
            if ($tab === 'overview') {
                $statusRes = $api->getIpsecStatus();
                $activeSas = $statusRes['data'] ?? [];

                $configuredP1s = [];
                $configuredP2s = [];
                try {
                    $p1Res = $api->getIpsecPhase1s();
                    $configuredP1s = $p1Res['data'] ?? [];
                    $p2Res = $api->getIpsecPhase2s();
                    $configuredP2s = $p2Res['data'] ?? [];
                } catch (\Throwable $e) {
                    // Non-fatal if phase config cannot be loaded
                }

                $overview = $this->buildIpsecOverview($activeSas, $configuredP1s, $configuredP2s, $firewall);
            } elseif ($tab === 'leases') {
                $res = $api->getIpsecLeases();
                $leases = $res['data'] ?? [];
            } elseif ($tab === 'sads') {
                $res = $api->getIpsecSads();
                $sads = $res['data'] ?? [];
            } elseif ($tab === 'spds') {
                $res = $api->getIpsecSpds();
                $spds = $res['data'] ?? [];
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Failed to fetch IPsec status for tab {$tab}: " . $e->getMessage());
        }

        // Backward compatibility for legacy tests/views
        $status = $overview;

        return view('status.ipsec', compact('firewall', 'tab', 'overview', 'status', 'leases', 'sads', 'spds'));
    }

    protected function buildIpsecOverview(array $activeSas, array $configuredP1s, array $configuredP2s, Firewall $firewall): array
    {
        $overview = [];
        $matchedP1Ids = [];

        $findP1 = function ($sa, $index) use (&$configuredP1s) {
            $conId = $sa['name'] ?? ($sa['con-id'] ?? '');
            if (preg_match('/con(\d+)/i', $conId, $m)) {
                $ikeid = (int)$m[1];
                foreach ($configuredP1s as $p1) {
                    if ((isset($p1['ikeid']) && (int)$p1['ikeid'] === $ikeid) || (isset($p1['id']) && (int)$p1['id'] === $ikeid)) {
                        return $p1;
                    }
                }
            }
            $remote = $sa['remote_host'] ?? ($sa['remote-host'] ?? ($sa['remoteid'] ?? ''));
            if ($remote) {
                foreach ($configuredP1s as $p1) {
                    $gw = $p1['remote-gateway'] ?? ($p1['remote_gateway'] ?? ($p1['remote_addrs'] ?? ''));
                    if ($gw && $gw === $remote) {
                        return $p1;
                    }
                }
            }
            $descr = $sa['descr'] ?? ($sa['name'] ?? '');
            if ($descr) {
                foreach ($configuredP1s as $p1) {
                    if (($p1['descr'] ?? ($p1['description'] ?? '')) === $descr) {
                        return $p1;
                    }
                }
            }
            return null;
        };

        foreach ($activeSas as $index => $sa) {
            $matchedP1 = $findP1($sa, $index);
            if ($matchedP1) {
                $p1Key = $matchedP1['ikeid'] ?? ($matchedP1['id'] ?? ($matchedP1['uuid'] ?? $index));
                $matchedP1Ids[$p1Key] = true;
            }

            $conId = $sa['name'] ?? ($sa['con-id'] ?? ('con' . ($index + 1)));
            $uniqueId = $sa['uniqueid'] ?? ($sa['id'] ?? '');
            $rekeyTime = (int)($sa['rekey_time'] ?? ($sa['rekey-time'] ?? 0));
            $reauthTime = (int)($sa['reauth_time'] ?? ($sa['reauth-time'] ?? 0));
            $established = (int)($sa['established'] ?? 0);
            $descr = $matchedP1['descr'] ?? ($matchedP1['description'] ?? ($sa['descr'] ?? ''));

            $childSas = [];
            $rawChildren = $sa['child_sas'] ?? ($sa['child-sas'] ?? []);
            foreach ($rawChildren as $child) {
                $cRekey = (int)($child['rekey_time'] ?? ($child['rekey-time'] ?? 0));
                $cLife = (int)($child['life_time'] ?? ($child['life-time'] ?? 0));
                $cInstall = (int)($child['install_time'] ?? ($child['install-time'] ?? 0));
                $bIn = (int)($child['bytes_in'] ?? ($child['bytes-in'] ?? 0));
                $bOut = (int)($child['bytes_out'] ?? ($child['bytes-out'] ?? 0));
                $pIn = (int)($child['packets_in'] ?? ($child['packets-in'] ?? 0));
                $pOut = (int)($child['packets_out'] ?? ($child['packets-out'] ?? 0));

                $childName = $child['name'] ?? '';
                $matchedP2 = null;
                if (!empty($child['reqid'])) {
                    foreach ($configuredP2s as $p2) {
                        if ((isset($p2['reqid']) && (int)$p2['reqid'] === (int)$child['reqid']) || (isset($p2['uniqid']) && $p2['uniqid'] == $child['reqid'])) {
                            $matchedP2 = $p2;
                            break;
                        }
                    }
                }
                if (!$matchedP2 && !empty($child['descr'])) {
                    foreach ($configuredP2s as $p2) {
                        if (($p2['descr'] ?? ($p2['description'] ?? '')) === $child['descr']) {
                            $matchedP2 = $p2;
                            break;
                        }
                    }
                }

                $childSas[] = [
                    'name' => $childName,
                    'uniqueid' => $child['uniqueid'] ?? ($child['id'] ?? ''),
                    'reqid' => $child['reqid'] ?? null,
                    'descr' => $matchedP2['descr'] ?? ($matchedP2['description'] ?? ($child['descr'] ?? '')),
                    'p2_id' => $matchedP2['uniqid'] ?? ($matchedP2['id'] ?? ($matchedP2['uuid'] ?? null)),
                    'state' => strtoupper($child['state'] ?? 'INSTALLED'),
                    'mode' => $child['mode'] ?? 'tunnel',
                    'protocol' => strtoupper($child['protocol'] ?? 'ESP'),
                    'spi_in' => $child['spi_in'] ?? ($child['spi-in'] ?? ''),
                    'spi_out' => $child['spi_out'] ?? ($child['spi-out'] ?? ''),
                    'encr_alg' => $child['encr_alg'] ?? ($child['encr-alg'] ?? ''),
                    'encr_keysize' => $child['encr_keysize'] ?? ($child['encr-keysize'] ?? ''),
                    'integ_alg' => $child['integ_alg'] ?? ($child['integ-alg'] ?? ''),
                    'dh_group' => $child['dh_group'] ?? ($child['dh-group'] ?? ''),
                    'ipcomp' => $child['ipcomp'] ?? 'None',
                    'bytes_in' => $bIn,
                    'bytes_in_formatted' => $this->formatBytes($bIn),
                    'bytes_out' => $bOut,
                    'bytes_out_formatted' => $this->formatBytes($bOut),
                    'packets_in' => $pIn,
                    'packets_in_formatted' => number_format($pIn),
                    'packets_out' => $pOut,
                    'packets_out_formatted' => number_format($pOut),
                    'rekey_time' => $cRekey,
                    'rekey_dhms' => $this->formatDhms($cRekey),
                    'life_time' => $cLife,
                    'life_dhms' => $this->formatDhms($cLife),
                    'install_time' => $cInstall,
                    'install_dhms' => $this->formatDhms($cInstall),
                    'local_ts' => is_array($child['local_ts'] ?? ($child['local-ts'] ?? null)) ? ($child['local_ts'] ?? $child['local-ts']) : (array)($child['local_ts'] ?? ($child['local-ts'] ?? [])),
                    'remote_ts' => is_array($child['remote_ts'] ?? ($child['remote-ts'] ?? null)) ? ($child['remote_ts'] ?? $child['remote-ts']) : (array)($child['remote_ts'] ?? ($child['remote-ts'] ?? [])),
                ];
            }

            $state = strtoupper($sa['state'] ?? ($sa['status'] ?? 'ESTABLISHED'));
            $localHost = $sa['local_host'] ?? ($sa['local-host'] ?? ($sa['localid'] ?? ''));
            $remoteHost = $sa['remote_host'] ?? ($sa['remote-host'] ?? ($sa['remoteid'] ?? ($matchedP1['remote-gateway'] ?? ($matchedP1['remote_gateway'] ?? ($matchedP1['remote_addrs'] ?? '')))));

            $overview[] = [
                'descr' => $descr ?: 'IPsec SA',
                'localid' => $sa['local_id'] ?? ($sa['local-id'] ?? ($sa['localid'] ?? $localHost)),
                'remoteid' => $sa['remote_id'] ?? ($sa['remote-id'] ?? ($sa['remoteid'] ?? $remoteHost)),
                'status' => strtolower($state),
                'connected' => $state === 'ESTABLISHED' ? 'Yes' : 'No',

                'con_id' => $conId,
                'uniqueid' => $uniqueId,
                'p1_id' => $matchedP1['ikeid'] ?? ($matchedP1['id'] ?? ($matchedP1['uuid'] ?? null)),
                'local_id' => $sa['local_id'] ?? ($sa['local-id'] ?? ($sa['localid'] ?? ($matchedP1['myid_value'] ?? ''))),
                'local_host' => $localHost,
                'local_port' => $sa['local_port'] ?? ($sa['local-port'] ?? '500'),
                'remote_id' => $sa['remote_id'] ?? ($sa['remote-id'] ?? ($sa['remoteid'] ?? ($matchedP1['peerid_value'] ?? ''))),
                'remote_host' => $remoteHost,
                'remote_port' => $sa['remote_port'] ?? ($sa['remote-port'] ?? '500'),
                'initiator' => $sa['initiator'] ?? 'yes',
                'initiator_spi' => $sa['initiator_spi'] ?? ($sa['initiator-spi'] ?? ''),
                'responder_spi' => $sa['responder_spi'] ?? ($sa['responder-spi'] ?? ''),
                'nat_local' => !empty($sa['nat_local'] ?? ($sa['nat-local'] ?? false)),
                'nat_remote' => !empty($sa['nat_remote'] ?? ($sa['nat-remote'] ?? ($sa['nat_any'] ?? ($sa['nat-any'] ?? false)))),
                'version' => (int)($sa['version'] ?? 2),
                'state' => $state,
                'rekey_time' => $rekeyTime,
                'rekey_dhms' => $this->formatDhms($rekeyTime),
                'reauth_time' => $reauthTime,
                'reauth_dhms' => $this->formatDhms($reauthTime),
                'encr_alg' => $sa['encr_alg'] ?? ($sa['encr-alg'] ?? ''),
                'encr_keysize' => $sa['encr_keysize'] ?? ($sa['encr-keysize'] ?? ''),
                'integ_alg' => $sa['integ_alg'] ?? ($sa['integ-alg'] ?? ''),
                'prf_alg' => $sa['prf_alg'] ?? ($sa['prf-alg'] ?? ''),
                'dh_group' => $sa['dh_group'] ?? ($sa['dh-group'] ?? ''),
                'established' => $established,
                'established_dhms' => $this->formatDhms($established),
                'child_sas' => $childSas,
            ];
        }

        // Add configured Phase 1s that are disconnected
        foreach ($configuredP1s as $p1) {
            $p1Key = $p1['ikeid'] ?? ($p1['id'] ?? ($p1['uuid'] ?? null));
            if ($p1Key !== null && isset($matchedP1Ids[$p1Key])) {
                continue;
            }
            $p1Descr = $p1['descr'] ?? ($p1['description'] ?? '');
            if ($p1Descr && collect($overview)->contains('descr', $p1Descr)) {
                continue;
            }

            $conId = isset($p1['ikeid']) ? ('con' . $p1['ikeid']) : ($p1['name'] ?? 'con');
            $remoteHost = $p1['remote-gateway'] ?? ($p1['remote_gateway'] ?? ($p1['remote_addrs'] ?? ''));

            $overview[] = [
                'descr' => $p1Descr ?: 'IPsec SA',
                'localid' => $p1['myid_value'] ?? '',
                'remoteid' => $p1['peerid_value'] ?? $remoteHost,
                'status' => 'disconnected',
                'connected' => 'No',

                'con_id' => $conId,
                'uniqueid' => '',
                'p1_id' => $p1Key,
                'local_id' => $p1['myid_value'] ?? '',
                'local_host' => '',
                'local_port' => '500',
                'remote_id' => $p1['peerid_value'] ?? '',
                'remote_host' => $remoteHost,
                'remote_port' => '500',
                'initiator' => 'yes',
                'initiator_spi' => '',
                'responder_spi' => '',
                'nat_local' => false,
                'nat_remote' => false,
                'version' => str_contains($p1['iketype'] ?? '', '1') ? 1 : 2,
                'state' => 'DISCONNECTED',
                'rekey_time' => 0,
                'rekey_dhms' => 'Disabled',
                'reauth_time' => 0,
                'reauth_dhms' => 'Disabled',
                'encr_alg' => $p1['encryption_algorithm_name'] ?? '',
                'encr_keysize' => $p1['encryption_algorithm_keylen'] ?? '',
                'integ_alg' => $p1['hash_algorithm'] ?? '',
                'prf_alg' => '',
                'dh_group' => $p1['dhgroup'] ?? '',
                'established' => 0,
                'established_dhms' => '',
                'child_sas' => [],
            ];
        }

        return $overview;
    }

    protected function formatDhms(int $sec): string
    {
        if ($sec <= 0) return '00:00:00';
        $d = floor($sec / 86400);
        $h = floor(($sec % 86400) / 3600);
        $m = floor(($sec % 3600) / 60);
        $s = $sec % 60;
        if ($d > 0) {
            return sprintf("%dd %02d:%02d:%02d", $d, $h, $m, $s);
        }
        return sprintf("%02d:%02d:%02d", $h, $m, $s);
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) return '0 B';
        $units = ['B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB'];
        $i = floor(log($bytes, 1024));
        if ($i >= count($units)) $i = count($units) - 1;
        return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
    }

    public function disconnectIpsec(Request $request, Firewall $firewall)
    {
        $validated = $request->validate([
            'type' => 'required|in:p1,p2,ike,child',
            'conid' => 'nullable|string',
            'uniqueid' => 'nullable|string',
            'name' => 'nullable|string',
        ]);

        try {
            $api = new \App\Services\PfSenseApiService($firewall);
            $type = in_array($validated['type'], ['p1', 'ike']) ? 'p1' : 'p2';
            if ($type === 'p1') {
                $api->disconnectIpsecP1($validated['conid'] ?? null, $validated['uniqueid'] ?? null);
                return back()->with('success', 'IPsec Phase 1 tunnel disconnection requested.');
            } else {
                $api->disconnectIpsecP2($validated['name'] ?? ($validated['conid'] ?? null), $validated['uniqueid'] ?? null);
                return back()->with('success', 'IPsec Phase 2 child SA disconnection requested.');
            }
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed to disconnect IPsec SA: ' . $e->getMessage());
        }
    }

    public function connectIpsec(Request $request, Firewall $firewall)
    {
        $validated = $request->validate([
            'type' => 'required|in:p1,p2,ike,child',
            'conid' => 'nullable|string',
            'name' => 'nullable|string',
        ]);

        try {
            $api = new \App\Services\PfSenseApiService($firewall);
            if (in_array($validated['type'], ['p1', 'ike'])) {
                $api->connectIpsecP1($validated['conid'] ?? '');
                return back()->with('success', 'IPsec Phase 1 tunnel connection initiated.');
            } else {
                $api->connectIpsecP2($validated['name'] ?? ($validated['conid'] ?? ''));
                return back()->with('success', 'IPsec Phase 2 child SA connection initiated.');
            }
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed to connect IPsec SA: ' . $e->getMessage());
        }
    }

    public function destroyIpsecSad(Request $request, Firewall $firewall)
    {
        $validated = $request->validate([
            'src' => 'required|string',
            'dst' => 'required|string',
            'proto' => 'required|string',
            'spi' => 'required|string',
        ]);

        try {
            $api = new \App\Services\PfSenseApiService($firewall);
            $api->deleteIpsecSad(
                $validated['src'],
                $validated['dst'],
                $validated['proto'],
                $validated['spi']
            );
            return redirect()->route('status.ipsec', [$firewall, 'tab' => 'sads'])
                ->with('success', 'Security Association (SAD) entry deleted successfully.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed to delete SAD entry: ' . $e->getMessage());
        }
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
