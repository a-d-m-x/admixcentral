<?php

namespace App\Services;

use App\Models\Firewall;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class OpnSenseApiService
{
    protected Firewall $firewall;
    protected string $baseUrl;
    protected ?string $apiKey;
    protected ?string $apiSecret;

    public function __construct(Firewall $firewall)
    {
        $this->firewall = $firewall;
        $this->baseUrl = rtrim($firewall->url, '/');
        $this->apiKey = $firewall->api_key;
        $this->apiSecret = $firewall->api_secret;
    }

    /**
     * Bootstrap helper: logs in to OPNsense web GUI session with username/password,
     * calls /api/auth/user/add_api_key/{username}, and returns ['key' => ..., 'secret' => ..., 'hostname' => ...]
     */
    public static function provisionApiKeyFromCredentials(string $url, string $username, string $password): array
    {
        $base = rtrim($url, '/');
        $jar = new \GuzzleHttp\Cookie\CookieJar();
        $client = Http::withOptions([
            'verify' => false,
            'cookies' => $jar,
            'allow_redirects' => false,
        ])->timeout(12);

        // 1. Fetch login page to extract CSRF token and session cookie
        $loginPage = $client->get("{$base}/index.php");
        if (!$loginPage->successful()) {
            throw new \Exception("Could not reach OPNsense login page at {$base} (HTTP {$loginPage->status()})");
        }

        $html = $loginPage->body();
        if (!preg_match('/<input type="hidden" name="([^"]+)" value="([^"]+)"/', $html, $matches)) {
            throw new \Exception("Could not find CSRF token on OPNsense login page.");
        }

        $csrfFieldName = $matches[1];
        $csrfTokenValue = $matches[2];

        // 2. Submit login form
        $loginResp = $client->asForm()->post("{$base}/index.php", [
            $csrfFieldName => $csrfTokenValue,
            'usernamefld' => $username,
            'passwordfld' => $password,
            'login' => '1',
        ]);

        if ($loginResp->status() !== 302 && $loginResp->status() !== 200) {
            throw new \Exception("Authentication failed for {$username} on {$base} (HTTP {$loginResp->status()})");
        }

        // 3. Fetch /ui/auth/user to get the X-CSRFToken for API calls
        $userPage = $client->get("{$base}/ui/auth/user");
        if (!preg_match('/"X-CSRFToken",\s*"([^"]+)"/', $userPage->body(), $tokenMatch)) {
            throw new \Exception("Logged in successfully, but could not extract API CSRF token from OPNsense.");
        }

        $apiCsrfToken = $tokenMatch[1];

        // 4. Call /api/auth/user/add_api_key/{username}
        $addKeyResp = $client->withHeaders([
            'X-CSRFToken' => $apiCsrfToken,
            'Accept' => 'application/json',
        ])->post("{$base}/api/auth/user/add_api_key/{$username}");

        if (!$addKeyResp->successful()) {
            throw new \Exception("Failed to generate API key on OPNsense: " . $addKeyResp->body());
        }

        $keyData = $addKeyResp->json();
        if (empty($keyData['key']) || empty($keyData['secret'])) {
            throw new \Exception("OPNsense returned invalid API key payload: " . json_encode($keyData));
        }

        return [
            'key' => $keyData['key'],
            'secret' => $keyData['secret'],
            'hostname' => $keyData['hostname'] ?? null,
        ];
    }

    /**
     * Core HTTP request handler using HTTP Basic Auth (key:secret)
     */
    public function request(string $method, string $endpoint, array $data = [])
    {
        $url = $this->baseUrl . '/' . ltrim($endpoint, '/');

        $client = Http::withOptions(['verify' => false])
            ->acceptJson()
            ->timeout(12);

        if ($this->apiKey && $this->apiSecret) {
            $client->withBasicAuth($this->apiKey, $this->apiSecret);
        }

        if ($method === 'GET') {
            $data['_t'] = time();
            $queryString = http_build_query($data);
            $fullUrl = $queryString ? $url . '?' . $queryString : $url;
            $response = $client->get($fullUrl);
        } elseif ($method === 'DELETE') {
            $response = $client->send('DELETE', $url, ['json' => $data]);
        } else {
            $response = $client->asJson()->$method($url, $data);
        }

        if ($response->successful()) {
            return $response->json();
        }

        $status = $response->status();
        $body = $response->json();
        $message = $body['message'] ?? $body['errorMessage'] ?? $response->body();
        throw new \Exception("OPNsense API error ({$status}): {$message}", $status);
    }

    public function get(string $endpoint, array $params = [])
    {
        return $this->request('GET', $endpoint, $params);
    }

    public function post(string $endpoint, array $data = [])
    {
        return $this->request('POST', $endpoint, $data);
    }

    public function delete(string $endpoint, array $data = [])
    {
        return $this->request('DELETE', $endpoint, $data);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // System Status & Health (Unified with AdmixCentral data structure)
    // ─────────────────────────────────────────────────────────────────────────

    public function getSystemStatus()
    {
        $info = $this->getSystemInformation();
        $time = $this->getSystemTime();
        $resources = $this->getSystemResources();
        $disk = $this->getSystemDisk();
        $activity = $this->getSystemActivity();
        $gateways = $this->getGateways();

        // Calculate CPU usage from activity (100 - idle%)
        $cpuUsage = 0.0;
        foreach ($activity['headers'] ?? [] as $header) {
            if (preg_match('/(\d+(?:\.\d+)?)%\s+idle/i', $header, $m)) {
                $idle = (float) $m[1];
                $cpuUsage = max(0.0, min(100.0, round(100.0 - $idle, 2)));
                break;
            }
        }

        // Memory Usage
        $memUsage = 0.0;
        $memTotal = (float) ($resources['memory']['total'] ?? 0);
        $memUsed = (float) ($resources['memory']['used'] ?? 0);
        if ($memTotal > 0) {
            $memUsage = round(($memUsed / $memTotal) * 100, 2);
        }

        // Disk Usage
        $diskUsage = 0.0;
        if (!empty($disk['devices'][0]['used_pct'])) {
            $diskUsage = (float) $disk['devices'][0]['used_pct'];
        }

        // Product version
        $version = $info['versions'][0] ?? 'OPNsense';
        $vParts = explode(' ', $version);
        $productVersion = $vParts[1] ?? $version;

        $gwList = $gateways['data']['gateway'] ?? [];

        return [
            'status' => 200,
            'data' => [
                'hostname' => $info['name'] ?? $this->firewall->name,
                'product_version' => $productVersion,
                'os_version' => $info['versions'][1] ?? 'FreeBSD',
                'api_version' => 'OPNsense Core',
                'uptime' => $time['uptime'] ?? 'N/A',
                'load_average' => explode(',', $time['loadavg'] ?? '0, 0, 0'),
                'cpu_usage' => $cpuUsage,
                'mem_usage' => $memUsage,
                'disk_usage' => $diskUsage,
                'swap_usage' => 0.0,
                'update_available' => !empty($info['updates']) && !str_contains(strtolower($info['updates']), 'click to check'),
                'gateways' => $gwList,
            ],
            'product_version' => $productVersion,
            'api_version' => 'OPNsense Core',
        ];
    }

    public function refreshSystemStatus(): array
    {
        $status = $this->getSystemStatus();
        $data = $status['data'];

        // Add interfaces and compute bandwidth deltas
        try {
            $ifaces = $this->getInterfacesStatus();
            $interfaceData = $ifaces['data'] ?? [];

            $bytesCacheKey = 'firewall_iface_bytes_' . $this->firewall->id;
            $nowFloat = microtime(true);
            $prevSnapshot = Cache::get($bytesCacheKey);

            if ($prevSnapshot && isset($prevSnapshot['time'], $prevSnapshot['interfaces'])) {
                $timeDiff = $nowFloat - $prevSnapshot['time'];
                if ($timeDiff >= 1.0) {
                    foreach ($interfaceData as $key => &$iface) {
                        if (!isset($prevSnapshot['interfaces'][$key])) {
                            continue;
                        }
                        $prev = $prevSnapshot['interfaces'][$key];
                        $inNow = (float) ($iface['inbytes'] ?? 0);
                        $outNow = (float) ($iface['outbytes'] ?? 0);
                        $inPrev = (float) ($prev['in'] ?? 0);
                        $outPrev = (float) ($prev['out'] ?? 0);

                        $iface['in_rate_bps'] = $inNow >= $inPrev ? (($inNow - $inPrev) * 8 / $timeDiff) : 0;
                        $iface['out_rate_bps'] = $outNow >= $outPrev ? (($outNow - $outPrev) * 8 / $timeDiff) : 0;
                    }
                    unset($iface);
                }
            }

            $newSnapshot = ['time' => $nowFloat, 'interfaces' => []];
            foreach ($interfaceData as $key => $iface) {
                $newSnapshot['interfaces'][$key] = [
                    'in' => (float) ($iface['inbytes'] ?? 0),
                    'out' => (float) ($iface['outbytes'] ?? 0),
                ];
            }
            Cache::put($bytesCacheKey, $newSnapshot, now()->addMinutes(15));

            $data['interfaces'] = $interfaceData;
        } catch (\Exception $e) {
            $data['interfaces'] = [];
        }

        return $data;
    }

    public function getSystemInformation(): array
    {
        return $this->get('/api/diagnostics/system/systemInformation');
    }

    public function getSystemTime(): array
    {
        return $this->get('/api/diagnostics/system/systemTime');
    }

    public function getSystemResources(): array
    {
        return $this->get('/api/diagnostics/system/systemResources');
    }

    public function getSystemDisk(): array
    {
        return $this->get('/api/diagnostics/system/systemDisk');
    }

    public function getSystemActivity(): array
    {
        return $this->get('/api/diagnostics/activity/getActivity');
    }

    public function getSystemVersion(): array
    {
        $info = $this->getSystemInformation();
        $version = $info['versions'][0] ?? 'OPNsense';
        $vParts = explode(' ', $version);

        return [
            'data' => [
                'product_version' => $vParts[1] ?? $version,
                'product_name' => 'OPNsense',
                'os_version' => $info['versions'][1] ?? 'FreeBSD',
                'version' => $vParts[1] ?? $version,
                'api_version' => 'OPNsense Core',
            ],
        ];
    }

    public function getApiVersion(): array
    {
        return [
            'data' => [
                'output' => 'OPNsense Core API',
            ],
        ];
    }

    public function getSystemHostname(): array
    {
        $info = $this->getSystemInformation();
        return [
            'data' => [
                'hostname' => $info['name'] ?? $this->firewall->name,
            ],
        ];
    }

    public function getConfigHistory(): array
    {
        // OPNsense config history is available in backup history
        return [
            'data' => [
                [
                    'time' => time(),
                    'date' => date('r'),
                    'description' => 'Current running configuration',
                ],
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Gateways & Routing
    // ─────────────────────────────────────────────────────────────────────────

    public function getGateways(): array
    {
        $gwStatus = $this->get('/api/routes/gateway/status');
        $items = $gwStatus['items'] ?? [];

        $gateways = [];
        foreach ($items as $item) {
            $gateways[] = [
                'id' => $item['name'] ?? '',
                'name' => $item['name'] ?? 'GW',
                'interface' => $item['interface'] ?? 'WAN',
                'address' => $item['address'] ?? '',
                'status' => $item['status_translated'] ?? ($item['status'] === 'none' ? 'Online' : 'Offline'),
                'loss' => $item['loss'] === '~' ? '0.0%' : $item['loss'],
                'delay' => $item['delay'] === '~' ? '0.0ms' : $item['delay'],
                'stddev' => $item['stddev'] === '~' ? '0.0ms' : $item['stddev'],
            ];
        }

        return [
            'status' => 200,
            'data' => $gateways,
        ];
    }

    public function getRoutingGateways(): array
    {
        return $this->getGateways();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Interfaces
    // ─────────────────────────────────────────────────────────────────────────

    public function getInterfacesStatus(): array
    {
        $overview = $this->get('/api/interfaces/overview/interfacesInfo');
        $statsResp = $this->get('/api/diagnostics/interface/getInterfaceStatistics');
        $stats = $statsResp['statistics'] ?? [];

        $formatted = [];
        foreach ($overview['rows'] ?? [] as $row) {
            $device = $row['device'] ?? '';
            $desc = $row['description'] ?? $device;
            if ($desc === 'Unassigned Interface') {
                continue;
            }

            // Find matching stats
            $inBytes = 0;
            $outBytes = 0;
            foreach ($stats as $statKey => $statData) {
                if (($statData['name'] ?? '') === $device) {
                    $inBytes = (int) ($statData['received-bytes'] ?? 0);
                    $outBytes = (int) ($statData['sent-bytes'] ?? 0);
                    break;
                }
            }

            $ip = $row['ipv4'][0]['ipaddr'] ?? ($row['ipv6'][0]['ipaddr'] ?? 'N/A');

            $formatted[$device] = [
                'id' => strtolower($row['identifier'] ?? $desc),
                'if' => $device,
                'descr' => $desc,
                'device' => $device,
                'status' => $row['status'] ?? 'up',
                'ipaddr' => $ip,
                'inbytes' => $inBytes,
                'outbytes' => $outBytes,
                'in_rate_bps' => 0,
                'out_rate_bps' => 0,
            ];
        }

        return [
            'status' => 200,
            'data' => $formatted,
        ];
    }

    public function getInterfaces(): array
    {
        return $this->getInterfacesStatus();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Firewall Aliases
    // ─────────────────────────────────────────────────────────────────────────

    public function getFirewallAliases(): array
    {
        $res = $this->get('/api/firewall/alias/searchItem');
        $rows = $res['rows'] ?? [];

        $aliases = [];
        foreach ($rows as $row) {
            $aliases[] = [
                'id' => $row['uuid'] ?? $row['name'],
                'name' => $row['name'] ?? '',
                'type' => $row['type'] ?? '',
                'descr' => $row['description'] ?? '',
                'address' => is_array($row['content'] ?? null) ? implode(' ', $row['content']) : ($row['content'] ?? ''),
                'detail' => $row['description'] ?? '',
            ];
        }

        return [
            'status' => 200,
            'data' => $aliases,
        ];
    }

    public function getFirewallAlias(string $id): array
    {
        $item = $this->get("/api/firewall/alias/getItem/{$id}");
        $alias = $item['alias'] ?? [];

        $type = 'host';
        foreach ($alias['type'] ?? [] as $tKey => $tVal) {
            if (!empty($tVal['selected'])) {
                $type = $tKey;
                break;
            }
        }

        $addresses = [];
        foreach ($alias['content'] ?? [] as $cKey => $cVal) {
            if (!empty($cVal['selected']) && $cKey !== '') {
                $addresses[] = $cKey;
            }
        }

        return [
            'status' => 200,
            'data' => [
                'id' => $id,
                'name' => $alias['name'] ?? '',
                'type' => $type,
                'descr' => $alias['description'] ?? '',
                'address' => $addresses,
                'detail' => array_fill(0, max(1, count($addresses)), $alias['description'] ?? ''),
            ],
        ];
    }

    public function createFirewallAlias(array $data): array
    {
        $content = $data['address'] ?? ($data['content'] ?? '');
        if (is_array($content)) {
            $content = implode("\n", array_filter($content));
        }

        $payload = [
            'alias' => [
                'enabled' => '1',
                'name' => $data['name'] ?? '',
                'type' => $data['type'] ?? 'host',
                'description' => $data['descr'] ?? ($data['description'] ?? ''),
                'content' => $content,
            ],
        ];

        $res = $this->post('/api/firewall/alias/addItem', $payload);
        $this->applyFirewallAliases();
        return [
            'status' => 200,
            'data' => $res,
            'result' => $res['result'] ?? 'saved',
            'uuid' => $res['uuid'] ?? null,
        ];
    }

    public function updateFirewallAlias($idOrData, ?array $data = null): array
    {
        if (is_array($idOrData) && $data === null) {
            $data = $idOrData;
            $uuid = $data['id'] ?? ($data['uuid'] ?? '');
        } else {
            $uuid = (string) $idOrData;
            $data = $data ?? [];
        }

        $content = $data['address'] ?? ($data['content'] ?? '');
        if (is_array($content)) {
            $content = implode("\n", array_filter($content));
        }

        $payload = [
            'alias' => [
                'enabled' => '1',
                'name' => $data['name'] ?? '',
                'type' => $data['type'] ?? 'host',
                'description' => $data['descr'] ?? ($data['description'] ?? ''),
                'content' => $content,
            ],
        ];

        $res = $this->post("/api/firewall/alias/setItem/{$uuid}", $payload);
        $this->applyFirewallAliases();
        return [
            'status' => 200,
            'data' => $res,
            'result' => $res['result'] ?? 'saved',
        ];
    }

    public function deleteFirewallAlias(string $id): array
    {
        $res = $this->post("/api/firewall/alias/delItem/{$id}");
        $this->applyFirewallAliases();
        return [
            'status' => 200,
            'data' => $res,
            'result' => $res['result'] ?? 'deleted',
        ];
    }

    public function applyFirewallAliases(): array
    {
        return $this->post('/api/firewall/alias/reconfigure');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Firewall Rules
    // ─────────────────────────────────────────────────────────────────────────

    public function getFirewallRules(): array
    {
        $res = $this->get('/api/firewall/filter/searchRule');
        $rows = $res['rows'] ?? [];

        $rules = [];
        foreach ($rows as $row) {
            $rules[] = [
                'id' => $row['uuid'] ?? '',
                'tracker' => $row['uuid'] ?? ($row['#priority'] ?? 0),
                'interface' => $row['interface'] ?? 'any',
                'ipprotocol' => $row['ipprotocol'] ?? 'inet',
                'protocol' => $row['protocol'] ?? 'any',
                'type' => $row['action'] ?? 'pass',
                'source' => $row['source_net'] ?? 'any',
                'destination' => $row['destination_net'] ?? 'any',
                'descr' => $row['description'] ?? '',
                'disabled' => empty($row['enabled']) || $row['enabled'] === '0',
            ];
        }

        return [
            'status' => 200,
            'data' => $rules,
        ];
    }

    public function getFirewallRule($id): array
    {
        if (is_numeric($id)) {
            $rules = $this->getFirewallRules()['data'] ?? [];
            if (isset($rules[$id])) {
                return ['status' => 200, 'data' => $rules[$id]];
            }
        }

        $item = $this->get("/api/firewall/filter/getRule/{$id}");
        $rule = $item['rule'] ?? [];

        return [
            'status' => 200,
            'data' => [
                'id' => $id,
                'tracker' => $rule['uuid'] ?? ($id ?: 0),
                'interface' => $rule['interface'] ?? 'lan',
                'ipprotocol' => $rule['ipprotocol'] ?? 'inet',
                'protocol' => $rule['protocol'] ?? 'any',
                'type' => $rule['action'] ?? 'pass',
                'source' => $rule['source_net'] ?? 'any',
                'destination' => $rule['destination_net'] ?? 'any',
                'descr' => $rule['description'] ?? '',
                'disabled' => empty($rule['enabled']) || $rule['enabled'] === '0',
            ],
        ];
    }

    public function createFirewallRule(array $data): array
    {
        $iface = $data['interface'] ?? 'lan';
        if (is_array($iface)) {
            $iface = $iface[0] ?? 'lan';
        }

        $source = $data['source'] ?? 'any';
        if (is_array($source)) {
            $source = $source['network'] ?? ($source['any'] ? 'any' : 'any');
        }

        $dest = $data['destination'] ?? 'any';
        if (is_array($dest)) {
            $dest = $dest['network'] ?? ($dest['any'] ? 'any' : 'any');
        }

        $payload = [
            'rule' => [
                'enabled' => empty($data['disabled']) ? '1' : '0',
                'action' => $data['type'] ?? ($data['action'] ?? 'pass'),
                'interface' => $iface,
                'ipprotocol' => $data['ipprotocol'] ?? 'inet',
                'protocol' => $data['protocol'] ?? 'any',
                'description' => $data['descr'] ?? ($data['description'] ?? ''),
                'source_net' => $source,
                'destination_net' => $dest,
            ],
        ];

        $res = $this->post('/api/firewall/filter/addRule', $payload);
        $this->applyFirewallRules();
        return [
            'status' => 200,
            'data' => $res,
            'result' => $res['result'] ?? 'saved',
            'uuid' => $res['uuid'] ?? null,
        ];
    }

    public function updateFirewallRule($id, array $data): array
    {
        $uuid = $data['id'] ?? ($data['uuid'] ?? $id);
        if (is_numeric($uuid)) {
            $rules = $this->getFirewallRules()['data'] ?? [];
            if (isset($rules[$uuid]['id'])) {
                $uuid = $rules[$uuid]['id'];
            }
        }

        $iface = $data['interface'] ?? 'lan';
        if (is_array($iface)) {
            $iface = $iface[0] ?? 'lan';
        }

        $source = $data['source'] ?? 'any';
        if (is_array($source)) {
            $source = $source['network'] ?? ($source['any'] ? 'any' : 'any');
        }

        $dest = $data['destination'] ?? 'any';
        if (is_array($dest)) {
            $dest = $dest['network'] ?? ($dest['any'] ? 'any' : 'any');
        }

        $payload = [
            'rule' => [
                'enabled' => empty($data['disabled']) ? '1' : '0',
                'action' => $data['type'] ?? ($data['action'] ?? 'pass'),
                'interface' => $iface,
                'ipprotocol' => $data['ipprotocol'] ?? 'inet',
                'protocol' => $data['protocol'] ?? 'any',
                'description' => $data['descr'] ?? ($data['description'] ?? ''),
                'source_net' => $source,
                'destination_net' => $dest,
            ],
        ];

        $res = $this->post("/api/firewall/filter/setRule/{$uuid}", $payload);
        $this->applyFirewallRules();
        return [
            'status' => 200,
            'data' => $res,
            'result' => $res['result'] ?? 'saved',
        ];
    }

    public function deleteFirewallRule($id): array
    {
        if (is_numeric($id)) {
            $rules = $this->getFirewallRules()['data'] ?? [];
            if (isset($rules[$id]['id'])) {
                $id = $rules[$id]['id'];
            }
        }

        $res = $this->post("/api/firewall/filter/delRule/{$id}");
        $this->applyFirewallRules();
        return [
            'status' => 200,
            'data' => $res,
            'result' => $res['result'] ?? 'deleted',
        ];
    }

    public function applyFirewallRules(): array
    {
        return $this->post('/api/firewall/filter/apply');
    }

    public function applyChanges(): array
    {
        $alias = $this->applyFirewallAliases();
        $rules = $this->applyFirewallRules();
        return [
            'status' => 200,
            'data' => [
                'aliases' => $alias,
                'rules' => $rules,
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Diagnostics
    // ─────────────────────────────────────────────────────────────────────────

    public function getStates(): array
    {
        $res = $this->post('/api/diagnostics/firewall/query_states', []);
        $rows = $res['rows'] ?? [];

        $states = [];
        foreach ($rows as $row) {
            $states[] = [
                'id' => $row['id'] ?? '',
                'interface' => $row['interface'] ?? '',
                'proto' => $row['proto'] ?? '',
                'src' => ($row['src_addr'] ?? '') . ($row['src_port'] ? ':' . $row['src_port'] : ''),
                'dst' => ($row['dst_addr'] ?? '') . ($row['dst_port'] ? ':' . $row['dst_port'] : ''),
                'state' => $row['state'] ?? '',
                'packets' => is_array($row['pkts'] ?? null) ? implode(' / ', $row['pkts']) : ($row['pkts'] ?? ''),
                'bytes' => is_array($row['bytes'] ?? null) ? implode(' / ', $row['bytes']) : ($row['bytes'] ?? ''),
                'age' => $row['age'] ?? '',
                'expires' => $row['expires'] ?? '',
                'rule' => $row['descr'] ?? '',
            ];
        }

        return [
            'status' => 200,
            'data' => $states,
        ];
    }

    public function getArp(): array
    {
        $rows = $this->get('/api/diagnostics/interface/getArp');
        return [
            'status' => 200,
            'data' => is_array($rows) ? $rows : [],
        ];
    }

    public function ping(array $data): array
    {
        $host = $data['host'] ?? '8.8.8.8';
        $res = $this->post('/api/diagnostics/ping/set', ['host' => $host]);
        return [
            'status' => 200,
            'data' => $res,
        ];
    }

    public function traceroute(array $data): array
    {
        $host = $data['host'] ?? '8.8.8.8';
        $res = $this->post('/api/diagnostics/traceroute/set', ['host' => $host]);
        return [
            'status' => 200,
            'data' => $res,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Configuration Backups
    // ─────────────────────────────────────────────────────────────────────────

    public function downloadBackup(): string
    {
        $url = $this->baseUrl . '/api/core/backup/download/this';
        $response = Http::withOptions(['verify' => false])
            ->timeout(30)
            ->withBasicAuth($this->apiKey, $this->apiSecret)
            ->get($url);

        if (!$response->successful()) {
            throw new \Exception("Failed to download OPNsense backup: HTTP " . $response->status());
        }

        return $response->body();
    }

    public function getFirewallStates(): array
    {
        return $this->getStates();
    }

    public function getArpTable(): array
    {
        return $this->getArp();
    }

    public function backupConfiguration(): string
    {
        return $this->downloadBackup();
    }
}
