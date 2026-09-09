<?php

namespace App\Http\Controllers;

use App\Models\Firewall;
use App\Services\PfSenseApiService;
use Illuminate\Support\Facades\Cache;

class FirewallVpnSummaryController extends Controller
{
    /**
     * Return a normalized VPN status summary for the firewall dashboard card.
     *
     * Peer status — 3 states:
     *   active   = enabled AND handshake within 180s (OPNsense) / enabled on pfSense
     *   inactive = enabled BUT handshake is stale or absent
     *   disabled = administratively disabled
     *
     * Cached 30s.
     */
    public function summary(Firewall $firewall)
    {
        $cacheKey = 'firewall_vpn_summary_' . $firewall->id;

        try {
            $data = Cache::remember($cacheKey, 5, function () use ($firewall) {
                $api = new PfSenseApiService($firewall);

                return [
                    'ipsec'     => $this->fetchIpsec($api),
                    'openvpn'   => $this->fetchOpenVpn($api),
                    'wireguard' => $this->fetchWireGuard($api, $firewall),
                ];
            });

            return response()->json($data);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function fetchIpsec(PfSenseApiService $api): array
    {
        $tunnels = [];
        try {
            $res = $api->getIpsecStatus();
            $sas = $res['data'] ?? [];

            // ── Build Phase 1 description maps ────────────────────────────
            // pfSense SA status uses the remote peer IP as the "name", not the
            // human description. We build three lookup indexes so we can find
            // the admin label regardless of what key the SA exposes:
            //   $p1ByConName  — keyed by connection name  (e.g. "con17_16")
            //   $p1ByGateway  — keyed by remote_gateway IP (pfSense primary case)
            //   $p1ByIkeid    — keyed by IKE phase1 ID    (integer fallback)
            $p1ByConName = [];
            $p1ByGateway = [];
            $p1ByIkeid   = [];
            $p2Descr     = [];

            try {
                foreach ($api->getIpsecPhase1s()['data'] ?? [] as $p1) {
                    $label = $p1['descr'] ?? ($p1['description'] ?? null);
                    if (!$label) continue;

                    // Index by connection/name field
                    $conName = $p1['name'] ?? null;
                    if ($conName && $conName !== $label) {
                        $p1ByConName[$conName] = $label;
                    }

                    // Index by remote gateway IP (pfSense uses this as the SA name)
                    $gw = $p1['remote_gateway'] ?? ($p1['remote-gateway'] ?? ($p1['remote_gateway_v6'] ?? null));
                    if ($gw) {
                        $p1ByGateway[$gw] = $label;
                    }

                    // Index by IKE ID integer
                    $ikeid = $p1['ikeid'] ?? ($p1['id'] ?? null);
                    if ($ikeid !== null) {
                        $p1ByIkeid[(string) $ikeid] = $label;
                    }
                }
            } catch (\Throwable) {}

            try {
                foreach ($api->getIpsecPhase2s()['data'] ?? [] as $p2) {
                    $conName = $p2['name'] ?? ($p2['descr'] ?? null);
                    $label   = $p2['descr'] ?? ($p2['description'] ?? ($p2['name'] ?? null));
                    if ($conName && $label && $label !== $conName) {
                        $p2Descr[$conName] = $label;
                    }
                }
            } catch (\Throwable) {}

            foreach ($sas as $sa) {
                $rawState     = strtolower($sa['state'] ?? $sa['status'] ?? '');
                $isUp         = in_array($rawState, ['established', 'up', 'connected', 'installed']);
                $saConName    = $sa['name'] ?? ($sa['connection'] ?? '');
                $saRemoteHost = $sa['remote_host'] ?? ($sa['remoteid'] ?? '');

                // Resolve human label — try every index before giving up:
                //   1. connection name → p1 descr
                //   2. remote IP → p1 descr  (pfSense primary: SA name IS the peer IP)
                //   3. sa.descr  (OPNsense already normalizes this)
                //   4. connection name itself (better than an IP)
                //   5. remote host IP (last resort)
                $tunnelName = $p1ByConName[$saConName]
                    ?? $p1ByGateway[$saConName]
                    ?? $p1ByGateway[$saRemoteHost]
                    ?? $sa['descr']
                    ?? ($saConName && $saConName !== $saRemoteHost ? $saConName : null)
                    ?? $saRemoteHost
                    ?? 'IPsec SA';

                // Phase 2 child SAs — three states
                $peerList = [];
                foreach ($sa['child_sas'] ?? [] as $child) {
                    $childState   = strtoupper(trim($child['state'] ?? 'UNKNOWN'));
                    $peerStatus   = match (true) {
                        in_array($childState, ['INSTALLED', 'REKEYED', 'ESTABLISHED']) => 'active',
                        in_array($childState, ['CONNECTING', 'REKEYING'])              => 'inactive',
                        default                                                        => 'disabled',
                    };
                    $childConName = $child['name'] ?? ($child['uniqueid'] ?? '');
                    $childLabel   = $p2Descr[$childConName]
                        ?? ($childConName ?: 'SA');

                    $peerList[] = [
                        'name'      => $childLabel,
                        'con_name'  => $childConName,  // raw connection name as subtitle
                        'status'    => $peerStatus,
                        'state'     => $childState,
                        'local_ts'  => implode(', ', (array) ($child['local_ts']  ?? [])),
                        'remote_ts' => implode(', ', (array) ($child['remote_ts'] ?? [])),
                        'bytes_in'  => (int) ($child['bytes_in']  ?? 0),
                        'bytes_out' => (int) ($child['bytes_out'] ?? 0),
                    ];
                }

                $tunnels[] = [
                    'name'        => $tunnelName,
                    'con_name'    => $saConName,
                    'status'      => $isUp ? 'up' : 'down',
                    'remote'      => $sa['remote_host'] ?? $sa['remoteid'] ?? '',
                    'remote_id'   => $sa['remote_id']   ?? $sa['remoteid'] ?? '',
                    'local'       => $sa['local_host']  ?? $sa['localid']  ?? '',
                    'local_id'    => $sa['local_id']    ?? $sa['localid']  ?? '',
                    'established' => (int) ($sa['established'] ?? 0),
                    'version'     => (int) ($sa['version'] ?? 2),
                    'type'        => 'ipsec',
                    'peers'       => $peerList,
                ];
            }
        } catch (\Throwable) {
            // IPsec not configured or API error
        }

        return $this->summarize($tunnels);
    }

    private function fetchOpenVpn(PfSenseApiService $api): array
    {
        $tunnels = [];
        try {
            $res     = $api->getOpenVpnServerStatus();
            $servers = $res['data'] ?? [];

            foreach ($servers as $server) {
                $isUp      = strtolower($server['status'] ?? 'down') === 'up';
                $tunnels[] = [
                    'name'        => $server['name'] ?? 'Unknown',
                    'status'      => $isUp ? 'up' : 'down',
                    'remote'      => $server['remote_host'] ?? $server['virtual_addr'] ?? '',
                    'established' => 0,
                    'type'        => 'openvpn',
                    'role'        => 'server',
                    'peers'       => [],
                ];
            }

            $clientRes = $api->getOpenVpnClients();
            foreach ($clientRes['data'] ?? [] as $client) {
                $isEnabled = !empty($client['enabled']) && $client['enabled'] !== false;
                $tunnels[] = [
                    'name'        => $client['description'] ?? 'OpenVPN Client',
                    'status'      => $isEnabled ? 'up' : 'down',
                    'remote'      => $client['server_addr'] ?? '',
                    'established' => 0,
                    'type'        => 'openvpn',
                    'role'        => 'client',
                    'peers'       => [],
                ];
            }
        } catch (\Throwable) {
            // OpenVPN not configured or API error
        }

        return $this->summarize($tunnels);
    }

    private function fetchWireGuard(PfSenseApiService $api, $firewall): array
    {
        $tunnels = [];
        try {
            $res    = $api->getWireGuardTunnels();
            $wgList = $res['data'] ?? [];

            // ── Build handshake map: pubkey → live data ─────────────────────
            // Both pfSense (via `wg show all dump` through diagnostics command)
            // and OPNsense (via /api/wireguard/service/show) return the same
            // normalized row structure with hyphenated keys.
            $handshakeMap     = [];   // pubkey => unix timestamp (int)
            $transferMap      = [];   // pubkey => ['rx' => string, 'tx' => string]
            $hasHandshakeData = false;

            try {
                $showRes = $api->getWireGuardServiceShow();
                foreach ($showRes['data'] ?? [] as $row) {
                    $pubkey    = $row['public-key'] ?? ($row['pubkey'] ?? ($row['public_key'] ?? null));
                    $handshake = $row['latest-handshake'] ?? ($row['latest_handshake'] ?? null);
                    $rxRaw     = $row['transfer-rx'] ?? ($row['rx'] ?? '0 B');
                    $txRaw     = $row['transfer-tx'] ?? ($row['tx'] ?? '0 B');

                    if ($pubkey) {
                        $ts = is_numeric($handshake) ? (int) $handshake : 0;
                        $handshakeMap[$pubkey] = $ts;
                        $transferMap[$pubkey]  = ['rx' => $rxRaw, 'tx' => $txRaw, 'ts' => $ts];
                        $hasHandshakeData      = true;
                    }
                }
            } catch (\Throwable) {
                // Service show not available on this platform
            }

            $now = time();

            // ── Build peer list indexed by parent tunnel UUID ───────────────
            $peersByTunnel = [];
            try {
                $peersRes = $api->getWireGuardPeers();
                foreach ($peersRes['data'] ?? [] as $peer) {
                    $isEnabled  = !empty($peer['enabled']) && (string) $peer['enabled'] !== '0';
                    // getPeers normalizes pubkey to both 'pubkey' and 'public_key'
                    $peerPubkey = $peer['pubkey'] ?? ($peer['public_key'] ?? '');

                    // 3-state status
                    if (!$isEnabled) {
                        $peerStatus = 'disabled';
                    } elseif ($hasHandshakeData) {
                        $ts         = $handshakeMap[$peerPubkey] ?? 0;
                        $peerStatus = ($ts > 0 && ($now - $ts) <= 180) ? 'active' : 'inactive';
                    } else {
                        // pfSense: no handshake data — enabled = best signal available
                        $peerStatus = 'active';
                    }

                    $transfer     = $transferMap[$peerPubkey] ?? null;
                    $rawTs        = $transfer['ts'] ?? ($handshakeMap[$peerPubkey] ?? 0);

                    $normalizedPeer = [
                        'name'           => $peer['name'] ?? $peer['descr'] ?? 'Unknown',
                        'status'         => $peerStatus,
                        'endpoint'       => $peer['endpoint'] ?? $peer['serveraddress'] ?? '',
                        'allowed_ips'    => $peer['allowedips'] ?? $peer['tunneladdress'] ?? '',
                        'pubkey'         => $peerPubkey,
                        'last_handshake' => $rawTs,  // unix timestamp, 0 = never
                        'rx'             => $transfer['rx'] ?? null,
                        'tx'             => $transfer['tx'] ?? null,
                    ];

                    $parentServers = $peer['servers'] ?? [];
                    if (!is_array($parentServers)) {
                        $parentServers = array_filter(array_map('trim', explode(',', (string) $parentServers)));
                    }

                    if (empty($parentServers)) {
                        $peersByTunnel['__all'][] = $normalizedPeer;
                    } else {
                        foreach ($parentServers as $serverUuid) {
                            $peersByTunnel[$serverUuid][] = $normalizedPeer;
                        }
                    }
                }
            } catch (\Throwable) {
                // Peer fetch failed
            }

            foreach ($wgList as $wg) {
                $isEnabled  = !empty($wg['enabled']) && (string) $wg['enabled'] !== '0';
                $tunnelUuid = $wg['uuid'] ?? $wg['id'] ?? '';
                $peerList   = $peersByTunnel[$tunnelUuid] ?? $peersByTunnel['__all'] ?? [];

                $tunnels[] = [
                    'name'      => $wg['name'] ?? $wg['descr'] ?? 'Unknown',
                    'status'    => $isEnabled ? 'up' : 'down',
                    'remote'    => $wg['address'] ?? ($wg['addresses'][0] ?? ''),
                    'established' => 0,
                    'port'      => $wg['port'] ?? $wg['listenport'] ?? '',
                    'type'      => 'wireguard',
                    'peers'     => $peerList,
                ];
            }
        } catch (\Throwable) {
            // WireGuard not configured or API error
        }

        return $this->summarize($tunnels);
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function summarize(array $tunnels): array
    {
        $up   = count(array_filter($tunnels, fn($t) => $t['status'] === 'up'));
        $down = count($tunnels) - $up;

        return [
            'total'   => count($tunnels),
            'up'      => $up,
            'down'    => $down,
            'tunnels' => $tunnels,
        ];
    }
}
