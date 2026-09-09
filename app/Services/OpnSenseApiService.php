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
     * Per-request HTTP timeout in seconds. Matches PfSenseApiService.
     * Can be lowered (e.g. 5s) for known-offline firewalls to fail fast.
     */
    protected int $apiTimeout = 20;

    public function setApiTimeout(int $seconds): static
    {
        $this->apiTimeout = $seconds;
        return $this;
    }

    /**
     * Core HTTP request handler using HTTP Basic Auth (key:secret)
     */
    public function request(string $method, string $endpoint, array $data = [])
    {
        $url = $this->baseUrl . '/' . ltrim($endpoint, '/');

        $client = Http::withOptions(['verify' => false])
            ->acceptJson()
            ->timeout($this->apiTimeout);

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
        // 1. Static Information Caching (24h TTL)
        $staticCacheKey = 'firewall_static_info_' . $this->firewall->id;
        $staticInfo = Cache::get($staticCacheKey);
        if (!$staticInfo) {
            try {
                $info = $this->getSystemInformation();
                $version = $info['versions'][0] ?? 'OPNsense';
                $vParts = explode(' ', $version);
                $productVersion = $vParts[1] ?? $version;

                $staticInfo = [
                    'hostname' => $info['name'] ?? $this->firewall->name,
                    'product_version' => $productVersion,
                    'os_version' => $info['versions'][1] ?? 'FreeBSD',
                    'api_version' => 'OPNsense Core',
                    'update_available' => !empty($info['updates']) && !str_contains(strtolower($info['updates']), 'click to check'),
                    'cores' => 4,
                ];
                Cache::put($staticCacheKey, $staticInfo, now()->addDay());
            } catch (\Exception $e) {
                $staticInfo = [
                    'hostname' => $this->firewall->name,
                    'product_version' => 'OPNsense',
                    'os_version' => 'FreeBSD',
                    'api_version' => 'OPNsense Core',
                    'update_available' => false,
                    'cores' => 4,
                ];
            }
        }

        // 2. Parallel fetch for lightweight real-time telemetry (Time, Resources, Disk, Gateways, Interfaces)
        $key = $this->apiKey;
        $secret = $this->apiSecret;
        $base = $this->baseUrl;
        $now = time();

        $responses = Http::pool(fn ($pool) => [
            $pool->as('time')->withOptions(['verify' => false])->timeout(15)->withBasicAuth($key, $secret)->get("$base/api/diagnostics/system/systemTime?_t=$now"),
            $pool->as('resources')->withOptions(['verify' => false])->timeout(15)->withBasicAuth($key, $secret)->get("$base/api/diagnostics/system/systemResources?_t=$now"),
            $pool->as('disk')->withOptions(['verify' => false])->timeout(15)->withBasicAuth($key, $secret)->get("$base/api/diagnostics/system/systemDisk?_t=$now"),
            $pool->as('gateways')->withOptions(['verify' => false])->timeout(15)->withBasicAuth($key, $secret)->get("$base/api/routes/gateway/status?_t=$now"),
            $pool->as('ifOverview')->withOptions(['verify' => false])->timeout(15)->withBasicAuth($key, $secret)->get("$base/api/interfaces/overview/interfacesInfo?_t=$now"),
            $pool->as('ifStats')->withOptions(['verify' => false])->timeout(15)->withBasicAuth($key, $secret)->get("$base/api/diagnostics/interface/getInterfaceStatistics?_t=$now"),
        ]);

        $timeData = $responses['time']->json() ?? [];
        $resData = $responses['resources']->json() ?? [];
        $diskData = $responses['disk']->json() ?? [];
        $gwData = $responses['gateways']->json() ?? [];
        $ifOverview = $responses['ifOverview']->json() ?? [];
        $ifStats = $responses['ifStats']->json() ?? [];

        // CPU calculation from real-time 1m load average without spawning heavy 'top'
        $loadParts = explode(',', $timeData['loadavg'] ?? '0, 0, 0');
        $l1 = (float) trim($loadParts[0] ?? '0');
        $cores = (int) ($staticInfo['cores'] ?? 4);
        $cpuUsage = min(100.0, max(0.0, round(($l1 / (float) max(1, $cores)) * 100, 2)));

        // Memory Usage
        $memTotal = (float) ($resData['memory']['total'] ?? 0);
        $memUsed = (float) ($resData['memory']['used'] ?? 0);
        $memUsage = $memTotal > 0 ? round(($memUsed / $memTotal) * 100, 2) : 0.0;

        // Disk Usage
        $diskUsage = !empty($diskData['devices'][0]['used_pct']) ? (float) $diskData['devices'][0]['used_pct'] : 0.0;

        // Gateways
        $gateways = [];
        foreach ($gwData['items'] ?? [] as $item) {
            $addr = $item['address'] ?? '';
            $lossRaw = $item['loss'] ?? '0';
            $lossNum = trim(str_replace(['%', '~'], '', (string)$lossRaw));
            if ($lossNum === '') $lossNum = '0';

            $gateways[] = [
                'id' => $item['name'] ?? '',
                'name' => $item['name'] ?? 'GW',
                'interface' => $item['interface'] ?? 'WAN',
                'address' => $addr,
                'gateway' => $addr,
                'monitorip' => $addr,
                'srcip' => $addr,
                'status' => strtolower($item['status_translated'] ?? ($item['status'] === 'none' ? 'Online' : 'Offline')),
                'loss' => $lossNum,
                'delay' => $item['delay'] === '~' ? '0.0ms' : ($item['delay'] ?? '0.0ms'),
                'stddev' => $item['stddev'] === '~' ? '0.0ms' : ($item['stddev'] ?? '0.0ms'),
                'descr' => $item['descr'] ?? ($item['name'] ?? 'Gateway'),
            ];
        }

        // Interfaces
        $statsMap = [];
        foreach ($ifStats['statistics'] ?? [] as $s) {
            if (!empty($s['name'])) {
                $statsMap[$s['name']] = $s;
            }
        }

        $formattedIfaces = [];
        foreach ($ifOverview['rows'] ?? [] as $row) {
            $dev = $row['device'] ?? '';
            $desc = $row['description'] ?? $dev;
            if ($desc === 'Unassigned Interface') continue;
            $s = $statsMap[$dev] ?? [];
            $ip = $row['ipv4'][0]['ipaddr'] ?? ($row['ipv6'][0]['ipaddr'] ?? 'N/A');

            $formattedIfaces[$dev] = [
                'id' => strtolower($row['identifier'] ?? $desc),
                'if' => $dev,
                'name' => $desc,
                'descr' => $desc,
                'device' => $dev,
                'status' => $row['status'] ?? 'up',
                'ipaddr' => $ip,
                'inbytes' => (int) ($s['received-bytes'] ?? 0),
                'outbytes' => (int) ($s['sent-bytes'] ?? 0),
                'in_rate_bps' => 0,
                'out_rate_bps' => 0,
                'media' => $row['media'] ?? 'VirtIO',
                'speed' => $row['speed'] ?? '10 Gbps',
            ];
        }

        $productVersion = $staticInfo['product_version'] ?? 'OPNsense';

        return [
            'status' => 200,
            'data' => [
                'hostname' => $staticInfo['hostname'] ?? $this->firewall->name,
                'product_version' => $productVersion,
                'os_version' => $staticInfo['os_version'] ?? 'FreeBSD',
                'api_version' => $staticInfo['api_version'] ?? 'OPNsense Core',
                'uptime' => $timeData['uptime'] ?? 'N/A',
                'load_average' => explode(',', $timeData['loadavg'] ?? '0, 0, 0'),
                'cpu_load_avg' => array_map('trim', explode(',', $timeData['loadavg'] ?? '0, 0, 0')),
                'cpu_usage' => $cpuUsage,
                'mem_usage' => $memUsage,
                'disk_usage' => $diskUsage,
                'swap_usage' => 0.0,
                'update_available' => (bool) ($staticInfo['update_available'] ?? false),
                'gateways' => $gateways,
                'interfaces' => $formattedIfaces,
            ],
            'product_version' => $productVersion,
            'api_version' => 'OPNsense Core',
        ];
    }

    public function refreshSystemStatus(): array
    {
        $status = $this->getSystemStatus();
        $data = $status['data'];

        // Compute bandwidth deltas from interfaces
        try {
            $interfaceData = $data['interfaces'] ?? [];

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
            // Keep interfaces as is
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
            $addr = $item['address'] ?? '';
            $lossRaw = $item['loss'] ?? '0';
            $lossNum = trim(str_replace(['%', '~'], '', (string)$lossRaw));
            if ($lossNum === '') $lossNum = '0';

            $gateways[] = [
                'id' => $item['name'] ?? '',
                'name' => $item['name'] ?? 'GW',
                'interface' => $item['interface'] ?? 'WAN',
                'address' => $addr,
                'gateway' => $addr,
                'monitorip' => $addr,
                'srcip' => $addr,
                'status' => strtolower($item['status_translated'] ?? ($item['status'] === 'none' ? 'Online' : 'Offline')),
                'loss' => $lossNum,
                'delay' => (!empty($item['delay']) && $item['delay'] !== '~') ? $item['delay'] : '0.0ms',
                'stddev' => (!empty($item['stddev']) && $item['stddev'] !== '~') ? $item['stddev'] : '0.0ms',
                'descr' => $item['descr'] ?? ($item['name'] ?? 'Gateway'),
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
        $key = $this->apiKey;
        $secret = $this->apiSecret;
        $base = $this->baseUrl;
        $now = time();

        $res = Http::pool(fn ($pool) => [
            $pool->as('overview')->withOptions(['verify' => false])->timeout(15)->withBasicAuth($key, $secret)->get("$base/api/interfaces/overview/interfacesInfo?_t=$now"),
            $pool->as('stats')->withOptions(['verify' => false])->timeout(15)->withBasicAuth($key, $secret)->get("$base/api/diagnostics/interface/getInterfaceStatistics?_t=$now"),
        ]);

        $overview = $res['overview']->json() ?? [];
        $statsResp = $res['stats']->json() ?? [];
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
                'name' => $desc,
                'descr' => $desc,
                'device' => $device,
                'status' => $row['status'] ?? 'up',
                'ipaddr' => $ip,
                'inbytes' => $inBytes,
                'outbytes' => $outBytes,
                'in_rate_bps' => 0,
                'out_rate_bps' => 0,
                'media' => $row['media'] ?? 'VirtIO 10GBase-T',
                'speed' => $row['speed'] ?? '10 Gbps',
            ];
        }

        return [
            'status' => 200,
            'data' => array_values($formatted),
            'interfaces' => $formatted,
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

    public function getFirewallRules(bool $includeAutomatic = false): array
    {
        $res = $this->post('/api/firewall/filter/searchRule', ['rowCount' => -1, 'current' => 1]);
        $rows = $res['rows'] ?? [];

        $rules = [];
        foreach ($rows as $row) {
            if (!$includeAutomatic && !empty($row['is_automatic'])) {
                continue;
            }

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
                'is_automatic' => !empty($row['is_automatic']),
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
        $dnat = [];
        $snat = [];
        $o2o = [];
        try { $dnat = $this->post('/api/firewall/d_nat/apply'); } catch (\Throwable $e) {}
        try { $snat = $this->post('/api/firewall/source_nat/apply'); } catch (\Throwable $e) {}
        try { $o2o = $this->post('/api/firewall/one_to_one/apply'); } catch (\Throwable $e) {}

        return [
            'status' => 200,
            'data' => [
                'aliases' => $alias,
                'rules' => $rules,
                'dnat' => $dnat,
                'snat' => $snat,
                'one_to_one' => $o2o,
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // NAT (Port Forward / Destination NAT, Outbound / Source NAT, 1:1 NAT)
    // ─────────────────────────────────────────────────────────────────────────

    public function getNatPortForwards(): array
    {
        $res = $this->post('/api/firewall/d_nat/searchRule', ['rowCount' => -1, 'current' => 1]);
        $rows = $res['rows'] ?? [];

        $rules = [];
        foreach ($rows as $row) {
            $rules[] = [
                'id' => $row['uuid'] ?? '',
                'uuid' => $row['uuid'] ?? '',
                'interface' => $row['interface'] ?? 'wan',
                'protocol' => $row['protocol'] ?? 'tcp',
                'ipprotocol' => $row['ipprotocol'] ?? 'inet',
                'source' => [
                    'network' => $row['source.network'] ?? 'any',
                    'address' => $row['source.network'] ?? 'any',
                    'port' => $row['source.port'] ?? '',
                    'not' => $row['source.not'] ?? '0',
                ],
                'source_port' => $row['source.port'] ?? '',
                'destination' => [
                    'network' => $row['destination.network'] ?? 'wanip',
                    'address' => $row['destination.network'] ?? 'wanip',
                    'port' => $row['destination.port'] ?? '',
                    'not' => $row['destination.not'] ?? '0',
                ],
                'destination_port' => $row['destination.port'] ?? '',
                'dstport' => $row['destination.port'] ?? '',
                'target' => $row['target'] ?? '',
                'local_port' => $row['local-port'] ?? '',
                'local-port' => $row['local-port'] ?? '',
                'descr' => $row['descr'] ?? ($row['description'] ?? ''),
                'disabled' => !empty($row['disabled']) && (string) $row['disabled'] !== '0',
                'natreflection' => $row['natreflection'] ?? '',
                'associated_rule_id' => $row['pass'] ?? '',
                'is_automatic' => !empty($row['is_automatic']),
            ];
        }

        return [
            'status' => 200,
            'data' => $rules,
        ];
    }

    public function getNatPortForward($id): array
    {
        $uuid = (string) $id;
        if (is_numeric($id)) {
            $rules = $this->getNatPortForwards()['data'] ?? [];
            if (isset($rules[$id])) {
                $uuid = $rules[$id]['uuid'] ?? ($rules[$id]['id'] ?? $uuid);
            }
        }

        $res = $this->get("/api/firewall/d_nat/getRule/{$uuid}");
        return ['status' => 200, 'data' => $res['rule'] ?? []];
    }

    public function createNatPortForward(array $data): array
    {
        $payload = $this->buildDnatPayload($data);
        $res = $this->post('/api/firewall/d_nat/addRule', $payload);
        if (($res['result'] ?? '') === 'failed' || !empty($res['validations'])) {
            $errs = [];
            foreach ($res['validations'] ?? [] as $f => $m) {
                $errs[] = "$f: $m";
            }
            throw new \InvalidArgumentException(implode('; ', $errs) ?: 'Failed to create port forward rule in OPNsense');
        }

        $natUuid = $res['uuid'] ?? null;
        $filterRuleUuid = null;

        // If associated filter rule is requested ('new', 'rule', or 'linked')
        $assoc = $data['associated_rule_id'] ?? '';
        if (in_array($assoc, ['new', 'rule', 'linked'])) {
            $tag = $natUuid ? ('nat_' . str_replace('-', '', $natUuid)) : '';
            $filterPayload = [
                'rule' => [
                    'enabled' => empty($data['disabled']) ? '1' : '0',
                    'action' => 'pass',
                    'quick' => '1',
                    'interface' => strtolower($data['interface'] ?? 'wan'),
                    'direction' => 'in',
                    'ipprotocol' => $data['ipprotocol'] ?? 'inet',
                    'protocol' => strtoupper(($data['protocol'] ?? 'tcp') === 'any' ? '' : ($data['protocol'] ?? 'tcp')),
                    'source_net' => $this->normalizeNetValue($data['source'] ?? 'any'),
                    'source_port' => ($data['srcport'] ?? ($data['source_port'] ?? '')) === '*' ? '' : ($data['srcport'] ?? ($data['source_port'] ?? '')),
                    'destination_net' => $data['target'] ?? '',
                    'destination_port' => (string) ($data['local_port'] ?? ($data['local-port'] ?? '')),
                    'description' => 'NAT: ' . ($data['descr'] ?? ''),
                    'tag' => $tag,
                ]
            ];

            try {
                $filterRes = $this->post('/api/firewall/filter/addRule', $filterPayload);
                if (!empty($filterRes['uuid'])) {
                    $filterRuleUuid = $filterRes['uuid'];
                }
                $this->post('/api/firewall/filter/apply');
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Failed to create associated filter rule in OPNsense: ' . $e->getMessage());
            }
        }

        try { $this->post('/api/firewall/d_nat/apply'); } catch (\Throwable $e) {}

        return [
            'status' => 200,
            'data' => $res,
            'uuid' => $natUuid,
            'associated_rule_id' => $filterRuleUuid ?? $natUuid,
        ];
    }

    public function updateNatPortForward($id, array $data): array
    {
        $uuid = (string) $id;
        if (is_numeric($id)) {
            $rules = $this->getNatPortForwards()['data'] ?? [];
            if (isset($rules[$id])) {
                $uuid = $rules[$id]['uuid'] ?? ($rules[$id]['id'] ?? $uuid);
            }
        }

        $tag = 'nat_' . str_replace('-', '', $uuid);

        // Handle single-field toggle/update
        if (count($data) === 1 && isset($data['disabled'])) {
            $existing = $this->get("/api/firewall/d_nat/getRule/{$uuid}")['rule'] ?? [];
            $payload = [
                'rule' => [
                    'disabled' => !empty($data['disabled']) ? '1' : '0',
                    'interface' => is_array($existing['interface'] ?? null) ? ($this->getSelectedOption($existing['interface']) ?: 'wan') : ($existing['interface'] ?? 'wan'),
                    'ipprotocol' => is_array($existing['ipprotocol'] ?? null) ? ($this->getSelectedOption($existing['ipprotocol']) ?: 'inet') : ($existing['ipprotocol'] ?? 'inet'),
                    'protocol' => is_array($existing['protocol'] ?? null) ? ($this->getSelectedOption($existing['protocol']) ?: 'tcp') : ($existing['protocol'] ?? 'tcp'),
                    'source' => [
                        'network' => $existing['source']['network'] ?? 'any',
                        'port' => $existing['source']['port'] ?? '',
                        'not' => $existing['source']['not'] ?? '0',
                    ],
                    'destination' => [
                        'network' => $existing['destination']['network'] ?? 'wanip',
                        'port' => $existing['destination']['port'] ?? '',
                        'not' => $existing['destination']['not'] ?? '0',
                    ],
                    'target' => $existing['target'] ?? '',
                    'local-port' => $existing['local-port'] ?? '',
                    'descr' => $existing['descr'] ?? '',
                    'natreflection' => is_array($existing['natreflection'] ?? null) ? ($this->getSelectedOption($existing['natreflection']) ?: '') : ($existing['natreflection'] ?? ''),
                    'pass' => is_array($existing['pass'] ?? null) ? ($this->getSelectedOption($existing['pass']) ?: '') : ($existing['pass'] ?? ''),
                ]
            ];
            $res = $this->post("/api/firewall/d_nat/setRule/{$uuid}", $payload);
            try { $this->post('/api/firewall/d_nat/apply'); } catch (\Throwable $e) {}

            // Synchronize associated filter rule if exists
            try {
                $associatedFilterRule = $this->findAssociatedFilterRule($tag, $existing['descr'] ?? '');
                if ($associatedFilterRule) {
                    $this->post("/api/firewall/filter/setRule/{$associatedFilterRule['uuid']}", [
                        'rule' => [
                            'enabled' => empty($data['disabled']) ? '1' : '0',
                        ]
                    ]);
                    $this->post('/api/firewall/filter/apply');
                }
            } catch (\Throwable $e) {}

            return ['status' => 200, 'data' => $res];
        }

        $payload = $this->buildDnatPayload($data);
        $res = $this->post("/api/firewall/d_nat/setRule/{$uuid}", $payload);
        if (($res['result'] ?? '') === 'failed' || !empty($res['validations'])) {
            $errs = [];
            foreach ($res['validations'] ?? [] as $f => $m) {
                $errs[] = "$f: $m";
            }
            throw new \InvalidArgumentException(implode('; ', $errs) ?: 'Failed to update port forward rule in OPNsense');
        }

        // Update or create associated filter rule
        try {
            $assoc = $data['associated_rule_id'] ?? '';
            $existingFilter = $this->findAssociatedFilterRule($tag, $data['descr'] ?? '');
            if (in_array($assoc, ['new', 'rule', 'linked']) || $existingFilter) {
                $filterPayload = [
                    'rule' => [
                        'enabled' => empty($data['disabled']) ? '1' : '0',
                        'action' => 'pass',
                        'quick' => '1',
                        'interface' => strtolower($data['interface'] ?? 'wan'),
                        'direction' => 'in',
                        'ipprotocol' => $data['ipprotocol'] ?? 'inet',
                        'protocol' => strtoupper(($data['protocol'] ?? 'tcp') === 'any' ? '' : ($data['protocol'] ?? 'tcp')),
                        'source_net' => $this->normalizeNetValue($data['source'] ?? 'any'),
                        'source_port' => ($data['srcport'] ?? ($data['source_port'] ?? '')) === '*' ? '' : ($data['srcport'] ?? ($data['source_port'] ?? '')),
                        'destination_net' => $data['target'] ?? '',
                        'destination_port' => (string) ($data['local_port'] ?? ($data['local-port'] ?? '')),
                        'description' => 'NAT: ' . ($data['descr'] ?? ''),
                        'tag' => $tag,
                    ]
                ];

                if ($existingFilter) {
                    $this->post("/api/firewall/filter/setRule/{$existingFilter['uuid']}", $filterPayload);
                } elseif (in_array($assoc, ['new', 'rule', 'linked'])) {
                    $this->post('/api/firewall/filter/addRule', $filterPayload);
                }
                $this->post('/api/firewall/filter/apply');
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to sync associated filter rule in updateNatPortForward: ' . $e->getMessage());
        }

        try { $this->post('/api/firewall/d_nat/apply'); } catch (\Throwable $e) {}
        return ['status' => 200, 'data' => $res];
    }

    public function deleteNatPortForward($id): array
    {
        $uuid = (string) $id;
        if (is_numeric($id)) {
            $rules = $this->getNatPortForwards()['data'] ?? [];
            if (isset($rules[$id])) {
                $uuid = $rules[$id]['uuid'] ?? ($rules[$id]['id'] ?? $uuid);
            }
        }

        $tag = 'nat_' . str_replace('-', '', $uuid);

        // Delete associated filter rule if found
        try {
            $existing = $this->get("/api/firewall/d_nat/getRule/{$uuid}")['rule'] ?? [];
            $filterRule = $this->findAssociatedFilterRule($tag, $existing['descr'] ?? '');
            if ($filterRule) {
                $this->post("/api/firewall/filter/delRule/{$filterRule['uuid']}");
                $this->post('/api/firewall/filter/apply');
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to delete associated filter rule: ' . $e->getMessage());
        }

        $res = $this->post("/api/firewall/d_nat/delRule/{$uuid}");
        try { $this->post('/api/firewall/d_nat/apply'); } catch (\Throwable $e) {}
        return ['status' => 200, 'data' => $res];
    }

    public function toggleNatPortForward($id): array
    {
        $uuid = (string) $id;
        if (is_numeric($id)) {
            $rules = $this->getNatPortForwards()['data'] ?? [];
            if (isset($rules[$id])) {
                $uuid = $rules[$id]['uuid'] ?? ($rules[$id]['id'] ?? $uuid);
            }
        }

        $tag = 'nat_' . str_replace('-', '', $uuid);

        // Toggle associated filter rule if found
        try {
            $existing = $this->get("/api/firewall/d_nat/getRule/{$uuid}")['rule'] ?? [];
            $filterRule = $this->findAssociatedFilterRule($tag, $existing['descr'] ?? '');
            if ($filterRule) {
                $this->post("/api/firewall/filter/toggleRule/{$filterRule['uuid']}");
                $this->post('/api/firewall/filter/apply');
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to toggle associated filter rule: ' . $e->getMessage());
        }

        $res = $this->post("/api/firewall/d_nat/toggleRule/{$uuid}");
        try { $this->post('/api/firewall/d_nat/apply'); } catch (\Throwable $e) {}
        return ['status' => 200, 'data' => $res];
    }

    protected function findAssociatedFilterRule(string $tag, string $descr = ''): ?array
    {
        try {
            $res = $this->post('/api/firewall/filter/searchRule', ['searchPhrase' => $tag]);
            foreach ($res['rows'] ?? [] as $row) {
                if (($row['tag'] ?? '') === $tag) {
                    return $row;
                }
            }

            // Fallback: search by description "NAT: {$descr}" if descr is non-empty
            if ($descr !== '') {
                $resDesc = $this->post('/api/firewall/filter/searchRule', ['searchPhrase' => 'NAT: ' . $descr]);
                foreach ($resDesc['rows'] ?? [] as $row) {
                    if (($row['description'] ?? '') === 'NAT: ' . $descr) {
                        return $row;
                    }
                }
            }
        } catch (\Throwable $e) {}

        return null;
    }

    protected function buildDnatPayload(array $data): array
    {
        $src = $data['source'] ?? 'any';
        $srcNot = '0';
        if (is_string($src) && str_starts_with($src, '!')) {
            $srcNot = '1';
            $src = substr($src, 1);
        }
        $srcNet = is_array($src) ? ($src['network'] ?? ($src['address'] ?? 'any')) : $src;
        if (preg_match('/^([a-zA-Z0-9_-]+):ip$/i', $srcNet, $m)) {
            $srcNet = strtolower($m[1]) . 'ip';
        }
        $srcPort = is_array($src) ? ($src['port'] ?? '') : ($data['srcport'] ?? ($data['source_port'] ?? ''));
        if ($srcPort === 'any' || $srcPort === '*') $srcPort = '';

        $iface = strtolower($data['interface'] ?? 'wan');
        $dst = $data['destination'] ?? 'wanip';
        $dstNot = '0';
        if (is_string($dst) && str_starts_with($dst, '!')) {
            $dstNot = '1';
            $dst = substr($dst, 1);
        }
        $dstNet = is_array($dst) ? ($dst['network'] ?? ($dst['address'] ?? 'wanip')) : $dst;
        if (preg_match('/^([a-zA-Z0-9_-]+):ip$/i', $dstNet, $m)) {
            $dstNet = strtolower($m[1]) . 'ip';
        } elseif ($dstNet === '(self)' || $dstNet === $iface) {
            $dstNet = $iface . 'ip';
        } elseif ($dstNet === 'wan') {
            $dstNet = 'wanip';
        } elseif ($dstNet === 'lan') {
            $dstNet = 'lanip';
        }
        $dstPort = is_array($dst) ? ($dst['port'] ?? '') : ($data['dstport'] ?? ($data['destination_port'] ?? ''));
        if ($dstPort === 'any' || $dstPort === '*') $dstPort = '';

        $natref = $data['natreflection'] ?? '';
        if ($natref === 'enable' || $natref === 'purenat') {
            $natref = 'purenat';
        } elseif ($natref === 'disable') {
            $natref = 'disable';
        } else {
            $natref = '';
        }

        $pass = '';
        $assoc = $data['associated_rule_id'] ?? '';
        if ($assoc === 'pass') {
            $pass = 'pass';
        } elseif ($assoc === 'new' || $assoc === 'linked' || $assoc === 'rule') {
            $pass = 'rule';
        }

        return [
            'rule' => [
                'disabled' => !empty($data['disabled']) ? '1' : '0',
                'interface' => $iface,
                'ipprotocol' => $data['ipprotocol'] ?? 'inet',
                'protocol' => ($data['protocol'] ?? 'tcp') === 'any' ? '' : $data['protocol'],
                'source' => [
                    'network' => $srcNet ?: 'any',
                    'port' => (string) $srcPort,
                    'not' => $srcNot,
                ],
                'destination' => [
                    'network' => $dstNet ?: 'wanip',
                    'port' => (string) $dstPort,
                    'not' => $dstNot,
                ],
                'target' => $data['target'] ?? '',
                'local-port' => (string) ($data['local_port'] ?? ($data['local-port'] ?? '')),
                'descr' => $data['descr'] ?? '',
                'natreflection' => $natref,
                'pass' => $pass,
            ]
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Outbound NAT (Source NAT)
    // ─────────────────────────────────────────────────────────────────────────

    public function getNatOutboundRules(): array
    {
        $res = $this->post('/api/firewall/source_nat/searchRule', ['rowCount' => -1, 'current' => 1]);
        $rows = $res['rows'] ?? [];

        $rules = [];
        foreach ($rows as $row) {
            $rules[] = [
                'id' => $row['uuid'] ?? '',
                'uuid' => $row['uuid'] ?? '',
                'interface' => $row['interface'] ?? 'wan',
                'protocol' => $row['protocol'] ?? 'any',
                'source' => $row['source_net'] ?? 'any',
                'source_port' => $row['source_port'] ?? '',
                'destination' => $row['destination_net'] ?? 'any',
                'destination_port' => $row['destination_port'] ?? '',
                'target' => $row['target'] ?? '',
                'target_port' => $row['target_port'] ?? '',
                'staticnatport' => !empty($row['staticnatport']) && (string) $row['staticnatport'] !== '0',
                'nonat' => !empty($row['nonat']) && (string) $row['nonat'] !== '0',
                'descr' => $row['description'] ?? '',
                'disabled' => empty($row['enabled']) || (string) $row['enabled'] === '0',
                'is_automatic' => !empty($row['is_automatic']),
            ];
        }

        return [
            'status' => 200,
            'data' => $rules,
        ];
    }

    public function getNatOutboundMode(): array
    {
        $res = $this->get('/api/firewall/source_nat/get');
        $modes = $res['filter']['general']['snat_mode'] ?? [];

        $selectedMode = 'automatic';
        foreach ($modes as $key => $item) {
            if (!empty($item['selected'])) {
                $selectedMode = (string) $key;
                break;
            }
        }

        return [
            'status' => 200,
            'data' => [
                'mode' => $selectedMode,
            ],
        ];
    }

    public function updateNatOutboundMode(string $mode): array
    {
        $payload = [
            'filter' => [
                'general' => [
                    'snat_mode' => $mode,
                ],
            ],
        ];
        $res = $this->post('/api/firewall/source_nat/set', $payload);
        $this->post('/api/firewall/source_nat/apply');
        return [
            'status' => 200,
            'data' => [
                'mode' => $mode,
                'result' => $res,
            ],
        ];
    }

    protected function normalizeNetValue(mixed $val, string $default = 'any'): string
    {
        if (empty($val)) return $default;
        if (is_array($val)) {
            $val = $val['network'] ?? ($val['address'] ?? $default);
        }
        $val = (string) $val;
        if (preg_match('/^([a-zA-Z0-9_-]+):ip$/i', $val, $m)) {
            return strtolower($m[1]) . 'ip';
        }
        return $val;
    }

    public function createNatOutboundRule(array $data): array
    {
        $payload = [
            'rule' => [
                'enabled' => empty($data['disabled']) ? '1' : '0',
                'nonat' => !empty($data['nonat']) ? '1' : '0',
                'interface' => strtolower($data['interface'] ?? 'wan'),
                'ipprotocol' => $data['ipprotocol'] ?? 'inet',
                'protocol' => ($data['protocol'] ?? 'any') === 'any' ? '' : $data['protocol'],
                'source_net' => $this->normalizeNetValue($data['source'] ?? 'any'),
                'source_port' => ($data['source_port'] ?? '') === '*' ? '' : ($data['source_port'] ?? ''),
                'destination_net' => $this->normalizeNetValue($data['destination'] ?? 'any'),
                'destination_port' => ($data['destination_port'] ?? '') === '*' ? '' : ($data['destination_port'] ?? ''),
                'target' => $this->normalizeNetValue($data['target'] ?? 'wanip', 'wanip'),
                'target_port' => ($data['target_port'] ?? '') === '*' ? '' : ($data['target_port'] ?? ''),
                'staticnatport' => !empty($data['staticnatport']) ? '1' : '0',
                'description' => $data['descr'] ?? '',
            ]
        ];
        $res = $this->post('/api/firewall/source_nat/addRule', $payload);
        if (($res['result'] ?? '') === 'failed' || !empty($res['validations'])) {
            $errs = [];
            foreach ($res['validations'] ?? [] as $f => $m) {
                $errs[] = "$f: $m";
            }
            throw new \InvalidArgumentException(implode('; ', $errs) ?: 'Failed to create outbound NAT rule in OPNsense');
        }

        try { $this->post('/api/firewall/source_nat/apply'); } catch (\Throwable $e) {}

        return [
            'status' => 200,
            'data' => $res,
            'uuid' => $res['uuid'] ?? null,
        ];
    }

    public function updateNatOutboundRule($id, array $data): array
    {
        $uuid = (string) $id;
        if (is_numeric($id)) {
            $rules = $this->getNatOutboundRules()['data'] ?? [];
            if (isset($rules[$id])) {
                $uuid = $rules[$id]['uuid'] ?? ($rules[$id]['id'] ?? $uuid);
            }
        }

        if (count($data) === 1 && isset($data['disabled'])) {
            $existing = $this->get("/api/firewall/source_nat/getRule/{$uuid}")['rule'] ?? [];
            $payload = [
                'rule' => [
                    'enabled' => !empty($data['disabled']) ? '0' : '1',
                    'nonat' => $existing['nonat'] ?? '0',
                    'interface' => is_array($existing['interface'] ?? null) ? ($this->getSelectedOption($existing['interface']) ?: 'wan') : ($existing['interface'] ?? 'wan'),
                    'ipprotocol' => is_array($existing['ipprotocol'] ?? null) ? ($this->getSelectedOption($existing['ipprotocol']) ?: 'inet') : ($existing['ipprotocol'] ?? 'inet'),
                    'protocol' => is_array($existing['protocol'] ?? null) ? ($this->getSelectedOption($existing['protocol']) ?: '') : ($existing['protocol'] ?? ''),
                    'source_net' => $existing['source_net'] ?? 'any',
                    'source_port' => $existing['source_port'] ?? '',
                    'destination_net' => $existing['destination_net'] ?? 'any',
                    'destination_port' => $existing['destination_port'] ?? '',
                    'target' => $existing['target'] ?? '',
                    'target_port' => $existing['target_port'] ?? '',
                    'staticnatport' => $existing['staticnatport'] ?? '0',
                    'description' => $existing['description'] ?? '',
                ]
            ];
            $res = $this->post("/api/firewall/source_nat/setRule/{$uuid}", $payload);
            try { $this->post('/api/firewall/source_nat/apply'); } catch (\Throwable $e) {}
            return ['status' => 200, 'data' => $res];
        }

        $payload = [
            'rule' => [
                'enabled' => empty($data['disabled']) ? '1' : '0',
                'nonat' => !empty($data['nonat']) ? '1' : '0',
                'interface' => strtolower($data['interface'] ?? 'wan'),
                'ipprotocol' => $data['ipprotocol'] ?? 'inet',
                'protocol' => ($data['protocol'] ?? 'any') === 'any' ? '' : $data['protocol'],
                'source_net' => $this->normalizeNetValue($data['source'] ?? 'any'),
                'source_port' => ($data['source_port'] ?? '') === '*' ? '' : ($data['source_port'] ?? ''),
                'destination_net' => $this->normalizeNetValue($data['destination'] ?? 'any'),
                'destination_port' => ($data['destination_port'] ?? '') === '*' ? '' : ($data['destination_port'] ?? ''),
                'target' => $this->normalizeNetValue($data['target'] ?? '', ''),
                'target_port' => ($data['target_port'] ?? '') === '*' ? '' : ($data['target_port'] ?? ''),
                'staticnatport' => !empty($data['staticnatport']) ? '1' : '0',
                'description' => $data['descr'] ?? '',
            ]
        ];
        $res = $this->post("/api/firewall/source_nat/setRule/{$uuid}", $payload);
        if (($res['result'] ?? '') === 'failed' || !empty($res['validations'])) {
            $errs = [];
            foreach ($res['validations'] ?? [] as $f => $m) {
                $errs[] = "$f: $m";
            }
            throw new \InvalidArgumentException(implode('; ', $errs) ?: 'Failed to update outbound NAT rule in OPNsense');
        }

        try { $this->post('/api/firewall/source_nat/apply'); } catch (\Throwable $e) {}
        return ['status' => 200, 'data' => $res];
    }

    public function deleteNatOutboundRule($id): array
    {
        $uuid = (string) $id;
        if (is_numeric($id)) {
            $rules = $this->getNatOutboundRules()['data'] ?? [];
            if (isset($rules[$id])) {
                $uuid = $rules[$id]['uuid'] ?? ($rules[$id]['id'] ?? $uuid);
            }
        }

        $res = $this->post("/api/firewall/source_nat/delRule/{$uuid}");
        try { $this->post('/api/firewall/source_nat/apply'); } catch (\Throwable $e) {}
        return ['status' => 200, 'data' => $res];
    }

    public function toggleNatOutboundRule($id): array
    {
        $uuid = (string) $id;
        if (is_numeric($id)) {
            $rules = $this->getNatOutboundRules()['data'] ?? [];
            if (isset($rules[$id])) {
                $uuid = $rules[$id]['uuid'] ?? ($rules[$id]['id'] ?? $uuid);
            }
        }

        $res = $this->post("/api/firewall/source_nat/toggleRule/{$uuid}");
        try { $this->post('/api/firewall/source_nat/apply'); } catch (\Throwable $e) {}
        return ['status' => 200, 'data' => $res];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 1:1 NAT (One-to-One / BINAT)
    // ─────────────────────────────────────────────────────────────────────────

    public function getNatOneToOneRules(): array
    {
        $res = $this->post('/api/firewall/one_to_one/searchRule', ['rowCount' => -1, 'current' => 1]);
        $rows = $res['rows'] ?? [];

        $rules = [];
        foreach ($rows as $row) {
            $rules[] = [
                'id' => $row['uuid'] ?? '',
                'uuid' => $row['uuid'] ?? '',
                'interface' => $row['interface'] ?? 'wan',
                'external' => $row['external'] ?? '',
                'source' => $row['source_net'] ?? 'any',
                'destination' => $row['destination_net'] ?? 'any',
                'descr' => $row['description'] ?? '',
                'disabled' => empty($row['enabled']) || (string) $row['enabled'] === '0',
            ];
        }

        return [
            'status' => 200,
            'data' => $rules,
        ];
    }

    public function createNatOneToOneRule(array $data): array
    {
        $payload = [
            'rule' => [
                'enabled' => empty($data['disabled']) ? '1' : '0',
                'interface' => strtolower($data['interface'] ?? 'wan'),
                'type' => 'binat',
                'external' => $this->normalizeNetValue($data['external'] ?? '', ''),
                'source_net' => $this->normalizeNetValue($data['source'] ?? 'any'),
                'destination_net' => $this->normalizeNetValue($data['destination'] ?? 'any'),
                'description' => $data['descr'] ?? '',
                'natreflection' => $data['natreflection'] ?? '',
            ]
        ];
        $res = $this->post('/api/firewall/one_to_one/addRule', $payload);
        if (($res['result'] ?? '') === 'failed' || !empty($res['validations'])) {
            $errs = [];
            foreach ($res['validations'] ?? [] as $f => $m) {
                $errs[] = "$f: $m";
            }
            throw new \InvalidArgumentException(implode('; ', $errs) ?: 'Failed to create 1:1 NAT rule in OPNsense');
        }

        try { $this->post('/api/firewall/one_to_one/apply'); } catch (\Throwable $e) {}

        return [
            'status' => 200,
            'data' => $res,
            'uuid' => $res['uuid'] ?? null,
        ];
    }

    public function updateNatOneToOneRule($id, array $data): array
    {
        $uuid = (string) $id;
        if (is_numeric($id)) {
            $rules = $this->getNatOneToOneRules()['data'] ?? [];
            if (isset($rules[$id])) {
                $uuid = $rules[$id]['uuid'] ?? ($rules[$id]['id'] ?? $uuid);
            }
        }

        if (count($data) === 1 && isset($data['disabled'])) {
            $existing = $this->get("/api/firewall/one_to_one/getRule/{$uuid}")['rule'] ?? [];
            $payload = [
                'rule' => [
                    'enabled' => !empty($data['disabled']) ? '0' : '1',
                    'interface' => is_array($existing['interface'] ?? null) ? ($this->getSelectedOption($existing['interface']) ?: 'wan') : ($existing['interface'] ?? 'wan'),
                    'type' => is_array($existing['type'] ?? null) ? ($this->getSelectedOption($existing['type']) ?: 'binat') : ($existing['type'] ?? 'binat'),
                    'external' => $existing['external'] ?? '',
                    'source_net' => $existing['source_net'] ?? 'any',
                    'destination_net' => $existing['destination_net'] ?? 'any',
                    'description' => $existing['description'] ?? '',
                    'natreflection' => is_array($existing['natreflection'] ?? null) ? ($this->getSelectedOption($existing['natreflection']) ?: '') : ($existing['natreflection'] ?? ''),
                ]
            ];
            $res = $this->post("/api/firewall/one_to_one/setRule/{$uuid}", $payload);
            try { $this->post('/api/firewall/one_to_one/apply'); } catch (\Throwable $e) {}
            return ['status' => 200, 'data' => $res];
        }

        $payload = [
            'rule' => [
                'enabled' => empty($data['disabled']) ? '1' : '0',
                'interface' => strtolower($data['interface'] ?? 'wan'),
                'type' => 'binat',
                'external' => $this->normalizeNetValue($data['external'] ?? '', ''),
                'source_net' => $this->normalizeNetValue($data['source'] ?? 'any'),
                'destination_net' => $this->normalizeNetValue($data['destination'] ?? 'any'),
                'description' => $data['descr'] ?? '',
                'natreflection' => $data['natreflection'] ?? '',
            ]
        ];
        $res = $this->post("/api/firewall/one_to_one/setRule/{$uuid}", $payload);
        if (($res['result'] ?? '') === 'failed' || !empty($res['validations'])) {
            $errs = [];
            foreach ($res['validations'] ?? [] as $f => $m) {
                $errs[] = "$f: $m";
            }
            throw new \InvalidArgumentException(implode('; ', $errs) ?: 'Failed to update 1:1 NAT rule in OPNsense');
        }

        try { $this->post('/api/firewall/one_to_one/apply'); } catch (\Throwable $e) {}
        return ['status' => 200, 'data' => $res];
    }

    public function deleteNatOneToOneRule($id): array
    {
        $uuid = (string) $id;
        if (is_numeric($id)) {
            $rules = $this->getNatOneToOneRules()['data'] ?? [];
            if (isset($rules[$id])) {
                $uuid = $rules[$id]['uuid'] ?? ($rules[$id]['id'] ?? $uuid);
            }
        }

        $res = $this->post("/api/firewall/one_to_one/delRule/{$uuid}");
        try { $this->post('/api/firewall/one_to_one/apply'); } catch (\Throwable $e) {}
        return ['status' => 200, 'data' => $res];
    }

    public function toggleNatOneToOneRule($id): array
    {
        $uuid = (string) $id;
        if (is_numeric($id)) {
            $rules = $this->getNatOneToOneRules()['data'] ?? [];
            if (isset($rules[$id])) {
                $uuid = $rules[$id]['uuid'] ?? ($rules[$id]['id'] ?? $uuid);
            }
        }

        $res = $this->post("/api/firewall/one_to_one/toggleRule/{$uuid}");
        try { $this->post('/api/firewall/one_to_one/apply'); } catch (\Throwable $e) {}
        return ['status' => 200, 'data' => $res];
    }

    protected function getSelectedOption($field, string $default = ''): string
    {
        if (!is_array($field)) {
            return (string) $field;
        }
        foreach ($field as $key => $item) {
            if (is_array($item) && !empty($item['selected'])) {
                return (string) $key;
            }
        }
        return $default;
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

    // ─────────────────────────────────────────────────────────────────────────
    // Firmware & Updates
    // ─────────────────────────────────────────────────────────────────────────

    public function getFirmwareStatus(): array
    {
        return $this->get('/api/core/firmware/status');
    }

    public function getFirmwareInfo(): array
    {
        return $this->get('/api/core/firmware/info');
    }

    public function checkFirmwareUpdates(): array
    {
        return $this->post('/api/core/firmware/check');
    }

    public function upgradeFirmware(): array
    {
        return $this->post('/api/core/firmware/update');
    }

    public function getFirmwareUpgradeStatus(): array
    {
        return $this->get('/api/core/firmware/upgradestatus');
    }

    public function auditFirmware(): array
    {
        return $this->post('/api/core/firmware/audit', []);
    }

    public function installPackage(string $name): array
    {
        return $this->post("/api/core/firmware/install/{$name}", []);
    }

    public function removePackage(string $name): array
    {
        return $this->post("/api/core/firmware/remove/{$name}", []);
    }

    public function reinstallPackage(string $name): array
    {
        return $this->post("/api/core/firmware/reinstall/{$name}", []);
    }

    public function lockPackage(string $name): array
    {
        return $this->post("/api/core/firmware/lock/{$name}", []);
    }

    public function unlockPackage(string $name): array
    {
        return $this->post("/api/core/firmware/unlock/{$name}", []);
    }

    public function getFirmwareChangelog(string $version): array
    {
        return $this->get("/api/core/firmware/changelog/{$version}");
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Core Services Management
    // ─────────────────────────────────────────────────────────────────────────

    public function getCoreServices(): array
    {
        $res = $this->get('/api/core/service/search');
        return [
            'status' => 200,
            'data' => $res['rows'] ?? [],
        ];
    }

    public function startService(string $service): array
    {
        return $this->post("/api/core/service/start/{$service}");
    }

    public function stopService(string $service): array
    {
        return $this->post("/api/core/service/stop/{$service}");
    }

    public function restartService(string $service): array
    {
        return $this->post("/api/core/service/restart/{$service}");
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Cron / Scheduled Tasks
    // ─────────────────────────────────────────────────────────────────────────

    public function getCronJobs(): array
    {
        $res = $this->get('/api/cron/settings/searchJobs');
        return [
            'status' => 200,
            'data' => $res['rows'] ?? [],
        ];
    }

    public function getCronJob(string $uuid = ''): array
    {
        $ep = empty($uuid) ? '/api/cron/settings/getJob' : "/api/cron/settings/getJob/{$uuid}";
        return $this->get($ep);
    }

    public function createCronJob(array $data): array
    {
        $payload = [
            'job' => [
                'enabled' => !empty($data['enabled']) ? '1' : '0',
                'minutes' => $data['minutes'] ?? '*',
                'hours' => $data['hours'] ?? '*',
                'days' => $data['days'] ?? '*',
                'months' => $data['months'] ?? '*',
                'weekdays' => $data['weekdays'] ?? '*',
                'who' => $data['who'] ?? 'root',
                'command' => $data['command'] ?? '',
                'parameters' => $data['parameters'] ?? '',
                'description' => $data['description'] ?? '',
            ],
        ];

        $res = $this->post('/api/cron/settings/addJob', $payload);
        $this->reconfigureCron();
        return [
            'status' => 200,
            'data' => $res,
            'result' => $res['result'] ?? 'saved',
            'uuid' => $res['uuid'] ?? null,
        ];
    }

    public function updateCronJob(string $uuid, array $data): array
    {
        $current = [];
        if (empty($data['command']) || !isset($data['minutes'])) {
            $existing = $this->getCronJob($uuid);
            $current = $existing['job'] ?? [];
        }

        $extractVal = function($val, $default = '') {
            if (is_array($val)) {
                foreach ($val as $k => $opt) {
                    if (!empty($opt['selected'])) return (string)$k;
                }
                return $default;
            }
            return is_null($val) ? $default : (string)$val;
        };

        $command = $data['command'] ?? $extractVal($current['command'] ?? null, '');
        $who = $data['who'] ?? $extractVal($current['who'] ?? null, 'root');

        $payload = [
            'job' => [
                'enabled' => isset($data['enabled']) ? (!empty($data['enabled']) ? '1' : '0') : $extractVal($current['enabled'] ?? null, '1'),
                'minutes' => $data['minutes'] ?? ($current['minutes'] ?? '*'),
                'hours' => $data['hours'] ?? ($current['hours'] ?? '*'),
                'days' => $data['days'] ?? ($current['days'] ?? '*'),
                'months' => $data['months'] ?? ($current['months'] ?? '*'),
                'weekdays' => $data['weekdays'] ?? ($current['weekdays'] ?? '*'),
                'who' => $who,
                'command' => $command,
                'parameters' => $data['parameters'] ?? ($current['parameters'] ?? ''),
                'description' => $data['description'] ?? ($current['description'] ?? ''),
            ],
        ];

        $res = $this->post("/api/cron/settings/setJob/{$uuid}", $payload);
        $this->reconfigureCron();
        return [
            'status' => 200,
            'data' => $res,
            'result' => $res['result'] ?? 'saved',
        ];
    }

    public function deleteCronJob(string $uuid): array
    {
        $res = $this->post("/api/cron/settings/delJob/{$uuid}");
        $this->reconfigureCron();
        return [
            'status' => 200,
            'data' => $res,
            'result' => $res['result'] ?? 'deleted',
        ];
    }

    public function reconfigureCron(): array
    {
        return $this->post('/api/cron/service/reconfigure');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Firewall Categories
    // ─────────────────────────────────────────────────────────────────────────

    public function getCategories(): array
    {
        $res = $this->get('/api/firewall/category/searchItem');
        return [
            'status' => 200,
            'data' => $res['rows'] ?? [],
        ];
    }

    public function createCategory(array $data): array
    {
        $payload = [
            'category' => [
                'name' => $data['name'] ?? '',
                'color' => $data['color'] ?? '336699',
                'auto' => !empty($data['auto']) ? '1' : '0',
            ],
        ];
        $res = $this->post('/api/firewall/category/addItem', $payload);
        return [
            'status' => 200,
            'data' => $res,
            'result' => $res['result'] ?? 'saved',
            'uuid' => $res['uuid'] ?? null,
        ];
    }

    public function updateCategory(string $uuid, array $data): array
    {
        $payload = [
            'category' => [
                'name' => $data['name'] ?? '',
                'color' => $data['color'] ?? '336699',
                'auto' => !empty($data['auto']) ? '1' : '0',
            ],
        ];
        $res = $this->post("/api/firewall/category/setItem/{$uuid}", $payload);
        return [
            'status' => 200,
            'data' => $res,
            'result' => $res['result'] ?? 'saved',
        ];
    }

    public function deleteCategory(string $uuid): array
    {
        $res = $this->post("/api/firewall/category/delItem/{$uuid}");
        return [
            'status' => 200,
            'data' => $res,
            'result' => $res['result'] ?? 'deleted',
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Diagnostics & System Control
    // ─────────────────────────────────────────────────────────────────────────

    public function getActivity(): array
    {
        return $this->get('/api/diagnostics/activity/getActivity');
    }

    public function getNdp(): array
    {
        $res = $this->get('/api/diagnostics/interface/getNdp');
        return [
            'status' => 200,
            'data' => $res ?? [],
        ];
    }

    public function getRoutes(): array
    {
        $res = $this->get('/api/routes/routes/searchRoute');
        return [
            'status' => 200,
            'data' => $res['rows'] ?? [],
        ];
    }

    public function rebootSystem(): array
    {
        return $this->post('/api/core/system/reboot');
    }

    public function haltSystem(): array
    {
        return $this->post('/api/core/system/halt');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // WireGuard
    // ─────────────────────────────────────────────────────────────────────────

    public function getWireGuardGeneral(): array
    {
        $res = $this->get('/api/wireguard/general/get');
        $enabled = !empty($res['general']['enabled']) && (string) $res['general']['enabled'] !== '0';
        return ['status' => 200, 'enabled' => $enabled, 'data' => $res['general'] ?? []];
    }

    public function setWireGuardGeneral(array $data): array
    {
        $enabled = !empty($data['enabled']) && (string) $data['enabled'] !== '0' ? '1' : '0';
        $payload = [
            'general' => [
                'enabled' => $enabled,
            ]
        ];
        $res = $this->post('/api/wireguard/general/set', $payload);
        $this->post('/api/wireguard/service/reconfigure');
        return ['status' => 200, 'data' => $res];
    }

    public function getWireGuardServiceStatus(): array
    {
        $res = $this->get('/api/wireguard/service/status');
        return ['status' => 200, 'data' => $res];
    }

    public function getWireGuardServiceShow(): array
    {
        $res = $this->get('/api/wireguard/service/show');
        return ['status' => 200, 'data' => $res['rows'] ?? []];
    }

    public function serviceWireGuardAction(string $action): array
    {
        $validActions = ['start', 'stop', 'restart', 'reconfigure'];
        if (!in_array($action, $validActions)) {
            throw new \InvalidArgumentException("Invalid WireGuard service action: {$action}");
        }
        $res = $this->post("/api/wireguard/service/{$action}");
        return ['status' => 200, 'data' => $res];
    }

    public function generateWireGuardKeyPair(): array
    {
        $res = $this->get('/api/wireguard/server/keyPair');
        return ['status' => 200, 'pubkey' => $res['pubkey'] ?? '', 'privkey' => $res['privkey'] ?? ''];
    }

    public function getWireGuardTunnels(): array
    {
        $res = $this->get('/api/wireguard/server/searchServer');
        $rows = $res['rows'] ?? [];
        $tunnels = [];
        foreach ($rows as $row) {
            $peers = $row['peers'] ?? '';
            $peerList = is_string($peers) ? array_filter(array_map('trim', explode(',', $peers))) : (array) $peers;
            $tunnels[] = [
                'id' => $row['uuid'] ?? ($row['name'] ?? ''),
                'uuid' => $row['uuid'] ?? '',
                'name' => $row['name'] ?? '',
                'descr' => $row['name'] ?? '',
                'address' => $row['tunneladdress'] ?? '',
                'addresses' => array_filter(array_map('trim', explode(',', $row['tunneladdress'] ?? ''))),
                'listenport' => $row['port'] ?? '51820',
                'port' => $row['port'] ?? '51820',
                'public_key' => $row['pubkey'] ?? '',
                'pubkey' => $row['pubkey'] ?? '',
                'privkey' => $row['privkey'] ?? '',
                'interface' => $row['interface'] ?? '',
                'mtu' => $row['mtu'] ?? '',
                'dns' => $row['dns'] ?? '',
                'disableroutes' => !empty($row['disableroutes']) && (string) $row['disableroutes'] !== '0',
                'peers' => $peerList,
                'enabled' => !empty($row['enabled']) && (string) $row['enabled'] !== '0',
            ];
        }
        return ['status' => 200, 'data' => $tunnels];
    }

    public function getWireGuardTunnel(string $uuid): array
    {
        $res = $this->get("/api/wireguard/server/getServer/{$uuid}");
        return ['status' => 200, 'data' => $res['server'] ?? []];
    }

    public function createWireGuardTunnel(array $data): array
    {
        $peers = $data['peers'] ?? '';
        if (is_array($peers)) {
            $peers = implode(',', array_filter($peers));
        }

        $payload = [
            'server' => [
                'enabled' => !empty($data['enabled']) && (string) $data['enabled'] !== '0' ? '1' : (empty($data['disabled']) ? '1' : '0'),
                'name' => $data['name'] ?? '',
                'port' => (string) ($data['port'] ?? ($data['listenport'] ?? '51820')),
                'tunneladdress' => $data['address'] ?? ($data['tunneladdress'] ?? ''),
                'pubkey' => $data['pubkey'] ?? ($data['public_key'] ?? ''),
                'privkey' => $data['privkey'] ?? ($data['private_key'] ?? ''),
                'mtu' => (string) ($data['mtu'] ?? ''),
                'dns' => $data['dns'] ?? '',
                'disableroutes' => !empty($data['disableroutes']) ? '1' : '0',
                'peers' => $peers,
            ]
        ];
        $res = $this->post('/api/wireguard/server/addServer', $payload);
        $this->post('/api/wireguard/service/reconfigure');
        return ['status' => 200, 'data' => $res];
    }

    public function updateWireGuardTunnel(string $uuid, array $data): array
    {
        $peers = $data['peers'] ?? '';
        if (is_array($peers)) {
            $peers = implode(',', array_filter($peers));
        }

        $payload = [
            'server' => [
                'enabled' => !empty($data['enabled']) && (string) $data['enabled'] !== '0' ? '1' : (empty($data['disabled']) ? '1' : '0'),
                'name' => $data['name'] ?? '',
                'port' => (string) ($data['port'] ?? ($data['listenport'] ?? '51820')),
                'tunneladdress' => $data['address'] ?? ($data['tunneladdress'] ?? ''),
                'pubkey' => $data['pubkey'] ?? ($data['public_key'] ?? ''),
                'privkey' => $data['privkey'] ?? ($data['private_key'] ?? ''),
                'mtu' => (string) ($data['mtu'] ?? ''),
                'dns' => $data['dns'] ?? '',
                'disableroutes' => !empty($data['disableroutes']) ? '1' : '0',
                'peers' => $peers,
            ]
        ];
        $res = $this->post("/api/wireguard/server/setServer/{$uuid}", $payload);
        $this->post('/api/wireguard/service/reconfigure');
        return ['status' => 200, 'data' => $res];
    }

    public function deleteWireGuardTunnel(string $id): array
    {
        $res = $this->post("/api/wireguard/server/delServer/{$id}");
        $this->post('/api/wireguard/service/reconfigure');
        return ['status' => 200, 'data' => $res];
    }

    public function toggleWireGuardTunnel(string $id): array
    {
        $res = $this->post("/api/wireguard/server/toggleServer/{$id}");
        $this->post('/api/wireguard/service/reconfigure');
        return ['status' => 200, 'data' => $res];
    }

    public function getWireGuardPeers(): array
    {
        $res = $this->get('/api/wireguard/client/searchClient');
        $rows = $res['rows'] ?? [];
        $peers = [];
        foreach ($rows as $row) {
            $servers = $row['servers'] ?? '';
            $serverList = is_string($servers) ? array_filter(array_map('trim', explode(',', $servers))) : (array) $servers;
            $peers[] = [
                'id' => $row['uuid'] ?? ($row['name'] ?? ''),
                'uuid' => $row['uuid'] ?? '',
                'name' => $row['name'] ?? '',
                'descr' => $row['name'] ?? '',
                'endpoint' => $row['serveraddress'] ?? ($row['endpoint'] ?? ''),
                'serveraddress' => $row['serveraddress'] ?? '',
                'port' => $row['serverport'] ?? '',
                'serverport' => $row['serverport'] ?? '',
                'allowedips' => $row['tunneladdress'] ?? '',
                'tunneladdress' => $row['tunneladdress'] ?? '',
                'public_key' => $row['pubkey'] ?? '',
                'pubkey' => $row['pubkey'] ?? '',
                'psk' => $row['psk'] ?? '',
                'keepalive' => $row['keepalive'] ?? '',
                'servers' => $serverList,
                'enabled' => !empty($row['enabled']) && (string) $row['enabled'] !== '0',
            ];
        }
        return ['status' => 200, 'data' => $peers];
    }

    public function getWireGuardPeer(string $uuid): array
    {
        $res = $this->get("/api/wireguard/client/getClient/{$uuid}");
        return ['status' => 200, 'data' => $res['client'] ?? []];
    }

    public function createWireGuardPeer(array $data): array
    {
        $servers = $data['servers'] ?? '';
        if (is_array($servers)) {
            $servers = implode(',', array_filter($servers));
        }

        $payload = [
            'client' => [
                'enabled' => !empty($data['enabled']) && (string) $data['enabled'] !== '0' ? '1' : (empty($data['disabled']) ? '1' : '0'),
                'name' => $data['name'] ?? ($data['descr'] ?? ''),
                'serveraddress' => $data['endpoint'] ?? ($data['serveraddress'] ?? ''),
                'serverport' => (string) ($data['port'] ?? ($data['serverport'] ?? '')),
                'tunneladdress' => $data['allowedips'] ?? ($data['tunneladdress'] ?? ''),
                'pubkey' => $data['pubkey'] ?? ($data['public_key'] ?? ''),
                'psk' => $data['psk'] ?? '',
                'keepalive' => (string) ($data['keepalive'] ?? ''),
                'servers' => $servers,
            ]
        ];
        $res = $this->post('/api/wireguard/client/addClient', $payload);
        $this->post('/api/wireguard/service/reconfigure');
        return ['status' => 200, 'data' => $res];
    }

    public function updateWireGuardPeer(string $uuid, array $data): array
    {
        $servers = $data['servers'] ?? '';
        if (is_array($servers)) {
            $servers = implode(',', array_filter($servers));
        }

        $payload = [
            'client' => [
                'enabled' => !empty($data['enabled']) && (string) $data['enabled'] !== '0' ? '1' : (empty($data['disabled']) ? '1' : '0'),
                'name' => $data['name'] ?? ($data['descr'] ?? ''),
                'serveraddress' => $data['endpoint'] ?? ($data['serveraddress'] ?? ''),
                'serverport' => (string) ($data['port'] ?? ($data['serverport'] ?? '')),
                'tunneladdress' => $data['allowedips'] ?? ($data['tunneladdress'] ?? ''),
                'pubkey' => $data['pubkey'] ?? ($data['public_key'] ?? ''),
                'psk' => $data['psk'] ?? '',
                'keepalive' => (string) ($data['keepalive'] ?? ''),
                'servers' => $servers,
            ]
        ];
        $res = $this->post("/api/wireguard/client/setClient/{$uuid}", $payload);
        $this->post('/api/wireguard/service/reconfigure');
        return ['status' => 200, 'data' => $res];
    }

    public function deleteWireGuardPeer(string $id): array
    {
        $res = $this->post("/api/wireguard/client/delClient/{$id}");
        $this->post('/api/wireguard/service/reconfigure');
        return ['status' => 200, 'data' => $res];
    }

    public function toggleWireGuardPeer(string $id): array
    {
        $res = $this->post("/api/wireguard/client/toggleClient/{$id}");
        $this->post('/api/wireguard/service/reconfigure');
        return ['status' => 200, 'data' => $res];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // LAGG Interfaces
    // ─────────────────────────────────────────────────────────────────────────

    public function getLaggs(): array
    {
        $res = $this->get('/api/interfaces/lagg_settings/searchItem');
        $rows = $res['rows'] ?? [];
        $laggs = [];
        foreach ($rows as $row) {
            $members = $row['members'] ?? [];
            if (is_string($members)) {
                $members = array_filter(array_map('trim', explode(',', $members)));
            }
            $laggs[] = [
                'id' => $row['uuid'] ?? ($row['laggif'] ?? ''),
                'laggif' => $row['laggif'] ?? '',
                'proto' => $row['proto'] ?? 'lacp',
                'members' => $members,
                'descr' => $row['descr'] ?? '',
            ];
        }
        return ['status' => 200, 'data' => $laggs];
    }

    public function createLagg(array $data): array
    {
        $payload = [
            'lagg' => [
                'laggif' => $data['laggif'] ?? '',
                'proto' => $data['proto'] ?? 'lacp',
                'members' => is_array($data['members'] ?? null) ? implode(',', $data['members']) : ($data['members'] ?? ''),
                'descr' => $data['descr'] ?? '',
            ]
        ];
        $res = $this->post('/api/interfaces/lagg_settings/addItem', $payload);
        $this->post('/api/interfaces/lagg_settings/reconfigure');
        return ['status' => 200, 'data' => $res];
    }

    public function deleteLagg(string $id): array
    {
        $res = $this->post("/api/interfaces/lagg_settings/delItem/{$id}");
        $this->post('/api/interfaces/lagg_settings/reconfigure');
        return ['status' => 200, 'data' => $res];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // VLANs
    // ─────────────────────────────────────────────────────────────────────────

    public function getVlans(): array
    {
        $res = $this->get('/api/interfaces/vlan_settings/searchItem');
        $rows = $res['rows'] ?? [];
        $vlans = [];
        foreach ($rows as $row) {
            $vlans[] = [
                'id' => $row['uuid'] ?? ($row['vlanif'] ?? ''),
                'if' => $row['if'] ?? '',
                'tag' => $row['tag'] ?? '',
                'pcp' => $row['pcp'] ?? '0',
                'descr' => $row['descr'] ?? '',
                'vlanif' => $row['vlanif'] ?? '',
            ];
        }
        return ['status' => 200, 'data' => $vlans];
    }

    public function createVlan(array $data): array
    {
        $payload = [
            'vlan' => [
                'if' => $data['if'] ?? '',
                'tag' => (string) ($data['tag'] ?? '1'),
                'pcp' => (string) ($data['pcp'] ?? '0'),
                'descr' => $data['descr'] ?? '',
            ]
        ];
        $res = $this->post('/api/interfaces/vlan_settings/addItem', $payload);
        $this->post('/api/interfaces/vlan_settings/reconfigure');
        return ['status' => 200, 'data' => $res];
    }

    public function deleteVlan(string $id): array
    {
        $res = $this->post("/api/interfaces/vlan_settings/delItem/{$id}");
        $this->post('/api/interfaces/vlan_settings/reconfigure');
        return ['status' => 200, 'data' => $res];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Virtual IPs (VIPs)
    // ─────────────────────────────────────────────────────────────────────────

    public function getVirtualIps(): array
    {
        $res = $this->get('/api/interfaces/vip_settings/searchItem');
        $rows = $res['rows'] ?? [];
        $vips = [];
        foreach ($rows as $row) {
            $network = $row['network'] ?? ($row['address'] ?? '');
            $parts = explode('/', $network);
            $ip = $parts[0] ?? $network;
            $bits = (int) ($parts[1] ?? 32);

            $vips[] = [
                'id' => $row['uuid'] ?? '',
                'mode' => $row['mode'] ?? 'ipalias',
                'interface' => $row['interface'] ?? 'wan',
                'subnet' => $ip,
                'subnet_bits' => $bits,
                'descr' => $row['descr'] ?? '',
                'vhid' => $row['vhid'] ?? null,
            ];
        }
        return ['status' => 200, 'data' => $vips];
    }

    public function getVirtualIp(string $id): array
    {
        $res = $this->get("/api/interfaces/vip_settings/getItem/{$id}");
        return ['status' => 200, 'data' => $res['vip'] ?? []];
    }

    public function createVirtualIp(array $data): array
    {
        $ip = $data['subnet'] ?? ($data['address'] ?? '');
        $bits = (string) ($data['subnet_bits'] ?? '32');
        $network = str_contains($ip, '/') ? $ip : "{$ip}/{$bits}";

        $payload = [
            'vip' => [
                'mode' => $data['mode'] ?? 'ipalias',
                'interface' => $data['interface'] ?? 'wan',
                'address' => $ip,
                'network' => $network,
                'descr' => $data['descr'] ?? '',
                'password' => $data['password'] ?? '',
                'vhid' => (string) ($data['vhid'] ?? ''),
            ]
        ];
        $res = $this->post('/api/interfaces/vip_settings/addItem', $payload);
        $this->post('/api/interfaces/vip_settings/reconfigure');
        return ['status' => 200, 'data' => $res];
    }

    public function updateVirtualIp(string $id, array $data): array
    {
        $ip = $data['subnet'] ?? ($data['address'] ?? '');
        $bits = (string) ($data['subnet_bits'] ?? '32');
        $network = str_contains($ip, '/') ? $ip : "{$ip}/{$bits}";

        $payload = [
            'vip' => [
                'mode' => $data['mode'] ?? 'ipalias',
                'interface' => $data['interface'] ?? 'wan',
                'address' => $ip,
                'network' => $network,
                'descr' => $data['descr'] ?? '',
                'password' => $data['password'] ?? '',
                'vhid' => (string) ($data['vhid'] ?? ''),
            ]
        ];
        $res = $this->post("/api/interfaces/vip_settings/setItem/{$id}", $payload);
        $this->post('/api/interfaces/vip_settings/reconfigure');
        return ['status' => 200, 'data' => $res];
    }

    public function deleteVirtualIp(string $id): array
    {
        $res = $this->post("/api/interfaces/vip_settings/delItem/{$id}");
        $this->post('/api/interfaces/vip_settings/reconfigure');
        return ['status' => 200, 'data' => $res];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DHCP Leases (Kea)
    // ─────────────────────────────────────────────────────────────────────────

    public function getDhcpLeases(): array
    {
        try {
            $res = $this->get('/api/kea/leases4/search');
            $rows = $res['rows'] ?? [];
            $leases = [];
            foreach ($rows as $row) {
                $cltt = $row['cltt'] ?? null;
                $lifetime = $row['valid_lifetime'] ?? 7200;
                $leases[] = [
                    'ip' => $row['address'] ?? ($row['ip'] ?? ''),
                    'mac' => $row['hwaddr'] ?? ($row['mac'] ?? ''),
                    'if' => $row['if'] ?? ($row['interface'] ?? 'LAN'),
                    'hostname' => $row['hostname'] ?? '',
                    'start' => $cltt ? date('Y-m-d H:i:s', (int) $cltt) : '',
                    'end' => $cltt ? date('Y-m-d H:i:s', (int) $cltt + (int) $lifetime) : '',
                    'state' => $row['state'] ?? 'active',
                ];
            }
            return ['status' => 200, 'data' => $leases];
        } catch (\Exception $e) {
            return ['status' => 200, 'data' => []];
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // IDS (Suricata) & Monit
    // ─────────────────────────────────────────────────────────────────────────

    public function getIdsStatus(): array
    {
        return $this->get('/api/ids/service/status');
    }

    public function getIdsServiceStatus(): array
    {
        return $this->getIdsStatus();
    }

    public function getIdsSettings(): array
    {
        return $this->get('/api/ids/settings/get');
    }

    public function updateIdsSettings(array $data): array
    {
        $payload = isset($data['ids']) ? $data : ['ids' => ['general' => $data]];
        $res = $this->post('/api/ids/settings/set', $payload);
        if (($res['result'] ?? '') === 'failed' || !empty($res['validations'])) {
            $errs = [];
            foreach ($res['validations'] ?? [] as $f => $m) {
                $errs[] = is_array($m) ? implode(', ', $m) : "$f: $m";
            }
            throw new \InvalidArgumentException(implode('; ', $errs) ?: 'Failed to update IDS settings');
        }
        return $res;
    }

    public function getIdsAlerts(): array
    {
        try {
            $res = $this->get('/api/ids/service/queryAlerts');
            return is_array($res) ? $res : [];
        } catch (\Exception $e) {
            return [];
        }
    }

    public function startIdsService(): array
    {
        return $this->post('/api/ids/service/start');
    }

    public function stopIdsService(): array
    {
        return $this->post('/api/ids/service/stop');
    }

    public function restartIdsService(): array
    {
        return $this->post('/api/ids/service/restart');
    }

    public function reconfigureIdsService(): array
    {
        return $this->post('/api/ids/service/reconfigure', []);
    }

    public function updateIdsRules(): array
    {
        return $this->post('/api/ids/service/updateRules', []);
    }

    public function getIdsRulesets(): array
    {
        $res = $this->get('/api/ids/settings/listRulesets');
        return [
            'status' => 200,
            'data' => $res['rows'] ?? [],
            'rows' => $res['rows'] ?? [],
            'total' => $res['total'] ?? 0,
        ];
    }

    public function toggleIdsRuleset(string $filename): array
    {
        return $this->post("/api/ids/settings/toggleRuleset/{$filename}", []);
    }

    public function getIdsUserRules(): array
    {
        $res = $this->get('/api/ids/settings/searchUserRule');
        return [
            'status' => 200,
            'data' => $res['rows'] ?? [],
            'rows' => $res['rows'] ?? [],
            'total' => $res['total'] ?? 0,
        ];
    }

    public function getIdsUserRule(string $uuid): array
    {
        return $this->get("/api/ids/settings/getUserRule/{$uuid}");
    }

    public function createIdsUserRule(array $data): array
    {
        $res = $this->post('/api/ids/settings/addUserRule', ['rule' => $data]);
        if (($res['result'] ?? '') === 'failed' || !empty($res['validations'])) {
            $errs = [];
            foreach ($res['validations'] ?? [] as $f => $m) {
                $errs[] = is_array($m) ? implode(', ', $m) : "$f: $m";
            }
            throw new \InvalidArgumentException(implode('; ', $errs) ?: 'Failed to create IDS user rule');
        }
        return $res;
    }

    public function updateIdsUserRule(string $uuid, array $data): array
    {
        $res = $this->post("/api/ids/settings/setUserRule/{$uuid}", ['rule' => $data]);
        if (($res['result'] ?? '') === 'failed' || !empty($res['validations'])) {
            $errs = [];
            foreach ($res['validations'] ?? [] as $f => $m) {
                $errs[] = is_array($m) ? implode(', ', $m) : "$f: $m";
            }
            throw new \InvalidArgumentException(implode('; ', $errs) ?: 'Failed to update IDS user rule');
        }
        return $res;
    }

    public function deleteIdsUserRule(string $uuid): array
    {
        return $this->post("/api/ids/settings/delUserRule/{$uuid}", []);
    }

    public function toggleIdsUserRule(string $uuid): array
    {
        return $this->post("/api/ids/settings/toggleUserRule/{$uuid}", []);
    }

    public function getMonitStatus(): array
    {
        return $this->get('/api/monit/service/status');
    }

    public function getMonitServiceStatus(): array
    {
        return $this->getMonitStatus();
    }

    public function startMonitService(): array
    {
        return $this->post('/api/monit/service/start', []);
    }

    public function stopMonitService(): array
    {
        return $this->post('/api/monit/service/stop', []);
    }

    public function restartMonitService(): array
    {
        return $this->post('/api/monit/service/restart', []);
    }

    public function reconfigureMonitService(): array
    {
        return $this->post('/api/monit/service/reconfigure', []);
    }

    public function getMonitSettings(): array
    {
        return $this->get('/api/monit/settings/get');
    }

    public function updateMonitSettings(array $data): array
    {
        $payload = isset($data['monit']) ? $data : ['monit' => ['general' => $data]];
        $res = $this->post('/api/monit/settings/set', $payload);
        if (($res['result'] ?? '') === 'failed' || !empty($res['validations'])) {
            $errs = [];
            foreach ($res['validations'] ?? [] as $f => $m) {
                $errs[] = is_array($m) ? implode(', ', $m) : "$f: $m";
            }
            throw new \InvalidArgumentException(implode('; ', $errs) ?: 'Failed to update Monit settings');
        }
        return $res;
    }

    public function getMonitServices(): array
    {
        $res = $this->get('/api/monit/settings/searchService');
        return [
            'status' => 200,
            'data' => $res['rows'] ?? [],
            'total' => $res['total'] ?? 0,
        ];
    }

    public function getMonitService(string $uuid): array
    {
        return $this->get("/api/monit/settings/getService/{$uuid}");
    }

    public function createMonitService(array $data): array
    {
        $res = $this->post('/api/monit/settings/addService', ['service' => $data]);
        if (($res['result'] ?? '') === 'failed' || !empty($res['validations'])) {
            $errs = [];
            foreach ($res['validations'] ?? [] as $f => $m) {
                $errs[] = is_array($m) ? implode(', ', $m) : "$f: $m";
            }
            throw new \InvalidArgumentException(implode('; ', $errs) ?: 'Failed to create Monit service');
        }
        return $res;
    }

    public function updateMonitService(string $uuid, array $data): array
    {
        $res = $this->post("/api/monit/settings/setService/{$uuid}", ['service' => $data]);
        if (($res['result'] ?? '') === 'failed' || !empty($res['validations'])) {
            $errs = [];
            foreach ($res['validations'] ?? [] as $f => $m) {
                $errs[] = is_array($m) ? implode(', ', $m) : "$f: $m";
            }
            throw new \InvalidArgumentException(implode('; ', $errs) ?: 'Failed to update Monit service');
        }
        return $res;
    }

    public function deleteMonitService(string $uuid): array
    {
        return $this->post("/api/monit/settings/delService/{$uuid}", []);
    }

    public function toggleMonitService(string $uuid): array
    {
        return $this->post("/api/monit/settings/toggleService/{$uuid}", []);
    }

    public function getMonitAlerts(): array
    {
        $res = $this->get('/api/monit/settings/searchAlert');
        return [
            'status' => 200,
            'data' => $res['rows'] ?? [],
            'total' => $res['total'] ?? 0,
        ];
    }

    public function getMonitAlert(string $uuid): array
    {
        return $this->get("/api/monit/settings/getAlert/{$uuid}");
    }

    public function createMonitAlert(array $data): array
    {
        $res = $this->post('/api/monit/settings/addAlert', ['alert' => $data]);
        if (($res['result'] ?? '') === 'failed' || !empty($res['validations'])) {
            $errs = [];
            foreach ($res['validations'] ?? [] as $f => $m) {
                $errs[] = is_array($m) ? implode(', ', $m) : "$f: $m";
            }
            throw new \InvalidArgumentException(implode('; ', $errs) ?: 'Failed to create Monit alert');
        }
        return $res;
    }

    public function updateMonitAlert(string $uuid, array $data): array
    {
        $res = $this->post("/api/monit/settings/setAlert/{$uuid}", ['alert' => $data]);
        if (($res['result'] ?? '') === 'failed' || !empty($res['validations'])) {
            $errs = [];
            foreach ($res['validations'] ?? [] as $f => $m) {
                $errs[] = is_array($m) ? implode(', ', $m) : "$f: $m";
            }
            throw new \InvalidArgumentException(implode('; ', $errs) ?: 'Failed to update Monit alert');
        }
        return $res;
    }

    public function deleteMonitAlert(string $uuid): array
    {
        return $this->post("/api/monit/settings/delAlert/{$uuid}", []);
    }

    public function toggleMonitAlert(string $uuid): array
    {
        return $this->post("/api/monit/settings/toggleAlert/{$uuid}", []);
    }

    public function getMonitTests(): array
    {
        $res = $this->get('/api/monit/settings/searchTest');
        return [
            'status' => 200,
            'data' => $res['rows'] ?? [],
            'total' => $res['total'] ?? 0,
        ];
    }

    public function getMonitTest(string $uuid): array
    {
        return $this->get("/api/monit/settings/getTest/{$uuid}");
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Trust & Auth
    // ─────────────────────────────────────────────────────────────────────────

    public function getCAs(): array
    {
        return $this->getCertificateAuthorities();
    }

    public function getUsers(): array
    {
        return $this->get('/api/auth/user/searchUser');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DNS Resolver (Unbound)
    // ─────────────────────────────────────────────────────────────────────────

    public function getDnsResolver(): array
    {
        $unbound = $this->get('/api/unbound/settings/get');
        $general = $unbound['unbound']['general'] ?? [];
        $fwd = $unbound['unbound']['forwarding'] ?? [];

        return [
            'status' => 200,
            'data' => [
                'enable' => ($general['enabled'] ?? '0') === '1',
                'port' => (int) ($general['port'] ?? 53),
                'dnssec' => ($general['dnssec'] ?? '0') === '1',
                'forwarding' => ($fwd['enabled'] ?? '0') === '1',
                'regdhcp' => ($general['regdhcp'] ?? '0') === '1',
                'regdhcpstatic' => ($general['regdhcpstatic'] ?? '0') === '1',
            ],
        ];
    }

    public function updateDnsResolver(array $data): array
    {
        $payload = [
            'unbound' => [
                'general' => [
                    'enabled' => !empty($data['enable']) ? '1' : '0',
                    'port' => (string) ($data['port'] ?? '53'),
                    'dnssec' => !empty($data['dnssec']) ? '1' : '0',
                    'regdhcp' => !empty($data['regdhcp']) ? '1' : '0',
                    'regdhcpstatic' => !empty($data['regdhcpstatic']) ? '1' : '0',
                ],
                'forwarding' => [
                    'enabled' => !empty($data['forwarding']) ? '1' : '0',
                ],
            ]
        ];

        $res = $this->post('/api/unbound/settings/set', $payload);
        try {
            $this->post('/api/unbound/service/reconfigure');
        } catch (\Throwable $e) {}

        return ['status' => 200, 'data' => $res];
    }

    public function getDnsResolverHostOverrides(): array
    {
        $res = $this->post('/api/unbound/settings/searchHostOverride', ['rowCount' => -1, 'current' => 1]);
        $rows = $res['rows'] ?? [];

        $hosts = [];
        foreach ($rows as $row) {
            $hosts[] = [
                'id' => $row['uuid'] ?? '',
                'uuid' => $row['uuid'] ?? '',
                'host' => $row['hostname'] ?? '',
                'hostname' => $row['hostname'] ?? '',
                'domain' => $row['domain'] ?? '',
                'ip' => $row['server'] ?? '',
                'server' => $row['server'] ?? '',
                'descr' => $row['description'] ?? '',
                'description' => $row['description'] ?? '',
                'enabled' => !empty($row['enabled']) && (string) $row['enabled'] !== '0',
            ];
        }

        return [
            'status' => 200,
            'data' => $hosts,
        ];
    }

    public function createDnsResolverHostOverride(array $data): array
    {
        $ip = $data['ip'] ?? '';
        $isIpv6 = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;

        $payload = [
            'host' => [
                'enabled' => empty($data['disabled']) ? '1' : '0',
                'hostname' => $data['host'] ?? ($data['hostname'] ?? ''),
                'domain' => $data['domain'] ?? '',
                'rr' => $data['rr'] ?? ($isIpv6 ? 'AAAA' : 'A'),
                'server' => $ip,
                'description' => $data['descr'] ?? ($data['description'] ?? ''),
            ]
        ];

        $res = $this->post('/api/unbound/settings/addHostOverride', $payload);
        if (($res['result'] ?? '') === 'failed' || !empty($res['validations'])) {
            $errs = [];
            foreach ($res['validations'] ?? [] as $f => $m) {
                $errs[] = "$f: $m";
            }
            throw new \InvalidArgumentException(implode('; ', $errs) ?: 'Failed to create host override in OPNsense');
        }

        try {
            $this->post('/api/unbound/service/reconfigure');
        } catch (\Throwable $e) {}

        return [
            'status' => 200,
            'data' => $res,
            'uuid' => $res['uuid'] ?? null,
        ];
    }

    public function deleteDnsResolverHostOverride(string $id): array
    {
        $res = $this->post("/api/unbound/settings/delHostOverride/{$id}");
        try {
            $this->post('/api/unbound/service/reconfigure');
        } catch (\Throwable $e) {}

        return ['status' => 200, 'data' => $res];
    }

    /*
    |--------------------------------------------------------------------------
    | Interfaces: Loopback
    |--------------------------------------------------------------------------
    */

    public function getLoopbacks(): array
    {
        $response = $this->post('/api/interfaces/loopback_settings/searchItem', [
            'current' => 1,
            'rowCount' => -1,
        ]);
        return ['status' => 200, 'data' => $response['rows'] ?? []];
    }

    public function getLoopback(string $uuid): array
    {
        $response = $this->get("/api/interfaces/loopback_settings/getItem/{$uuid}");
        $item = $response['loopback'] ?? [];
        return ['status' => 200, 'data' => $item];
    }

    public function createLoopback(array $data): array
    {
        $payload = [
            'loopback' => [
                'deviceId' => (string) ($data['deviceId'] ?? $data['device_id'] ?? ''),
                'description' => $data['description'] ?? $data['descr'] ?? '',
            ],
        ];

        $res = $this->post('/api/interfaces/loopback_settings/addItem', $payload);
        if (($res['result'] ?? '') === 'failed' || !empty($res['validations'])) {
            $errs = [];
            foreach ($res['validations'] ?? [] as $f => $m) {
                $errs[] = "$f: $m";
            }
            throw new \InvalidArgumentException(implode('; ', $errs) ?: 'Failed to create loopback interface');
        }

        try {
            $this->reconfigureLoopbacks();
        } catch (\Throwable $e) {}

        return [
            'status' => 200,
            'data' => $res,
            'uuid' => $res['uuid'] ?? null,
        ];
    }

    public function updateLoopback(string $uuid, array $data): array
    {
        $payload = [
            'loopback' => [
                'deviceId' => (string) ($data['deviceId'] ?? $data['device_id'] ?? ''),
                'description' => $data['description'] ?? $data['descr'] ?? '',
            ],
        ];

        $res = $this->post("/api/interfaces/loopback_settings/setItem/{$uuid}", $payload);
        if (($res['result'] ?? '') === 'failed' || !empty($res['validations'])) {
            $errs = [];
            foreach ($res['validations'] ?? [] as $f => $m) {
                $errs[] = "$f: $m";
            }
            throw new \InvalidArgumentException(implode('; ', $errs) ?: 'Failed to update loopback interface');
        }

        try {
            $this->reconfigureLoopbacks();
        } catch (\Throwable $e) {}

        return [
            'status' => 200,
            'data' => $res,
        ];
    }

    public function deleteLoopback(string $uuid): array
    {
        $res = $this->post("/api/interfaces/loopback_settings/delItem/{$uuid}", []);
        try {
            $this->reconfigureLoopbacks();
        } catch (\Throwable $e) {}

        return ['status' => 200, 'data' => $res];
    }

    public function reconfigureLoopbacks(): array
    {
        return $this->post('/api/interfaces/loopback_settings/reconfigure', []);
    }

    /*
    |--------------------------------------------------------------------------
    | Interfaces: VXLAN
    |--------------------------------------------------------------------------
    */

    public function getVxlans(): array
    {
        $response = $this->post('/api/interfaces/vxlan_settings/searchItem', [
            'current' => 1,
            'rowCount' => -1,
        ]);
        return ['status' => 200, 'data' => $response['rows'] ?? []];
    }

    public function getVxlan(string $uuid): array
    {
        $response = $this->get("/api/interfaces/vxlan_settings/getItem/{$uuid}");
        $item = $response['vxlan'] ?? [];
        return ['status' => 200, 'data' => $item];
    }

    public function createVxlan(array $data): array
    {
        $payload = [
            'vxlan' => [
                'deviceId' => (string) ($data['deviceId'] ?? $data['device_id'] ?? ''),
                'vxlanid' => (string) ($data['vxlanid'] ?? ''),
                'vxlanlocal' => $data['vxlanlocal'] ?? '',
                'vxlanlocalport' => $data['vxlanlocalport'] ?? '',
                'vxlanremote' => $data['vxlanremote'] ?? '',
                'vxlanremoteport' => $data['vxlanremoteport'] ?? '',
                'vxlangroup' => $data['vxlangroup'] ?? '',
                'vxlandev' => !empty($data['vxlanremote']) ? '' : ($data['vxlandev'] ?? ''),
            ],
        ];

        $res = $this->post('/api/interfaces/vxlan_settings/addItem', $payload);
        if (($res['result'] ?? '') === 'failed' || !empty($res['validations'])) {
            $errs = [];
            foreach ($res['validations'] ?? [] as $f => $m) {
                $errs[] = "$f: $m";
            }
            throw new \InvalidArgumentException(implode('; ', $errs) ?: 'Failed to create VXLAN interface');
        }

        try {
            $this->reconfigureVxlans();
        } catch (\Throwable $e) {}

        return [
            'status' => 200,
            'data' => $res,
            'uuid' => $res['uuid'] ?? null,
        ];
    }

    public function updateVxlan(string $uuid, array $data): array
    {
        $payload = [
            'vxlan' => [
                'deviceId' => (string) ($data['deviceId'] ?? $data['device_id'] ?? ''),
                'vxlanid' => (string) ($data['vxlanid'] ?? ''),
                'vxlanlocal' => $data['vxlanlocal'] ?? '',
                'vxlanlocalport' => $data['vxlanlocalport'] ?? '',
                'vxlanremote' => $data['vxlanremote'] ?? '',
                'vxlanremoteport' => $data['vxlanremoteport'] ?? '',
                'vxlangroup' => $data['vxlangroup'] ?? '',
                'vxlandev' => !empty($data['vxlanremote']) ? '' : ($data['vxlandev'] ?? ''),
            ],
        ];

        $res = $this->post("/api/interfaces/vxlan_settings/setItem/{$uuid}", $payload);
        if (($res['result'] ?? '') === 'failed' || !empty($res['validations'])) {
            $errs = [];
            foreach ($res['validations'] ?? [] as $f => $m) {
                $errs[] = "$f: $m";
            }
            throw new \InvalidArgumentException(implode('; ', $errs) ?: 'Failed to update VXLAN interface');
        }

        try {
            $this->reconfigureVxlans();
        } catch (\Throwable $e) {}

        return [
            'status' => 200,
            'data' => $res,
        ];
    }

    public function deleteVxlan(string $uuid): array
    {
        $res = $this->post("/api/interfaces/vxlan_settings/delItem/{$uuid}", []);
        try {
            $this->reconfigureVxlans();
        } catch (\Throwable $e) {}

        return ['status' => 200, 'data' => $res];
    }

    public function reconfigureVxlans(): array
    {
        return $this->post('/api/interfaces/vxlan_settings/reconfigure', []);
    }

    /*
    |--------------------------------------------------------------------------
    | Diagnostics: Reverse DNS Lookup
    |--------------------------------------------------------------------------
    */

    public function reverseDnsLookup(string $address): array
    {
        return $this->get('/api/diagnostics/dns/reverse_lookup', ['address' => $address]);
    }

    /*
    |--------------------------------------------------------------------------
    | VPN: OpenVPN
    |--------------------------------------------------------------------------
    */

    public function getOpenVpnInstances(): array
    {
        $res = $this->post('/api/openvpn/instances/search', [
            'current' => 1,
            'rowCount' => -1,
        ]);
        return ['status' => 200, 'data' => $res['rows'] ?? []];
    }

    public function getOpenVpnServers(): array
    {
        $instances = $this->getOpenVpnInstances();
        $servers = [];
        foreach ($instances['data'] ?? [] as $row) {
            if (($row['role'] ?? '') === 'server' || empty($row['role'])) {
                $servers[] = [
                    'vpnid' => $row['uuid'] ?? ($row['vpnid'] ?? ''),
                    'description' => $row['description'] ?? '',
                    'protocol' => $row['proto'] ?? ($row['protocol'] ?? 'UDP'),
                    'local_port' => $row['port'] ?? '1194',
                    'interface' => $row['dev_type'] ?? 'WAN',
                    'tunnel_network' => $row['server'] ?? '',
                    'enabled' => !empty($row['enabled']) && $row['enabled'] !== '0',
                    'uuid' => $row['uuid'] ?? '',
                ];
            }
        }
        return ['status' => 200, 'data' => $servers];
    }

    public function getOpenVpnClients(): array
    {
        $instances = $this->getOpenVpnInstances();
        $clients = [];
        foreach ($instances['data'] ?? [] as $row) {
            if (($row['role'] ?? '') === 'client') {
                $clients[] = [
                    'vpnid' => $row['uuid'] ?? ($row['vpnid'] ?? ''),
                    'description' => $row['description'] ?? '',
                    'protocol' => $row['proto'] ?? ($row['protocol'] ?? 'UDP'),
                    'local_port' => $row['port'] ?? '1194',
                    'interface' => $row['dev_type'] ?? 'WAN',
                    'server_addr' => $row['remote'] ?? '',
                    'enabled' => !empty($row['enabled']) && $row['enabled'] !== '0',
                    'uuid' => $row['uuid'] ?? '',
                ];
            }
        }
        return ['status' => 200, 'data' => $clients];
    }

    public function getOpenVpnInstance(string $uuid): array
    {
        $res = $this->get("/api/openvpn/instances/get/{$uuid}");
        return ['status' => 200, 'data' => $res['instance'] ?? []];
    }

    public function deleteOpenVpnInstance(string $uuid): array
    {
        $res = $this->post("/api/openvpn/instances/del/{$uuid}", []);
        return ['status' => 200, 'data' => $res];
    }

    public function getOpenVpnServerStatus(): array
    {
        $instances = $this->getOpenVpnServers();
        $statusList = [];
        foreach ($instances['data'] ?? [] as $server) {
            $statusList[] = [
                'name' => $server['description'] ?: ('OpenVPN Server ' . ($server['local_port'] ?? '1194')),
                'status' => !empty($server['enabled']) ? 'up' : 'down',
                'remote_host' => $server['protocol'] . ':' . $server['local_port'],
                'virtual_addr' => $server['tunnel_network'] ?: '-',
                'bytes_sent' => 0,
                'bytes_recv' => 0,
            ];
        }
        return ['status' => 200, 'data' => $statusList];
    }

    /*
    |--------------------------------------------------------------------------
    | Diagnostics: Logs (Firewall & System)
    |--------------------------------------------------------------------------
    */

    public function getFirewallLogs(int $limit = 500): array
    {
        try {
            $logs = $this->get('/api/diagnostics/firewall/log');
            if (!is_array($logs)) {
                return [];
            }
            if ($limit > 0 && count($logs) > $limit) {
                $logs = array_slice($logs, 0, $limit);
            }
            return $logs;
        } catch (\Exception $e) {
            Log::warning("Failed to fetch OPNsense firewall logs: " . $e->getMessage());
            return [];
        }
    }

    public function getSystemLogs(string $type = 'system', int $limit = 500): array
    {
        if ($type === 'firewall') {
            return $this->getFirewallLogs($limit);
        }

        $moduleMap = [
            'system' => 'core/system',
            'dhcp' => 'core/dhcpd',
            'auth' => 'core/auth',
            'openvpn' => 'core/openvpn',
            'ntp' => 'core/ntp',
            'ipsec' => 'core/ipsec',
            'routing' => 'core/routes',
            'configd' => 'core/configd',
        ];

        $module = $moduleMap[$type] ?? 'core/system';

        try {
            $res = $this->post("/api/diagnostics/log/{$module}", [
                'current' => 1,
                'rowCount' => $limit,
            ]);
            return $res['rows'] ?? [];
        } catch (\Exception $e) {
            Log::warning("Failed to fetch OPNsense system logs for {$type}: " . $e->getMessage());
            return [];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | System: User & Group Management
    |--------------------------------------------------------------------------
    */

    public function getSystemUsers(): array
    {
        $res = $this->post('/api/auth/user/search', [
            'current' => 1,
            'rowCount' => -1,
        ]);
        $rows = $res['rows'] ?? [];
        $users = [];
        foreach ($rows as $row) {
            $groupMemberships = !empty($row['%group_memberships'])
                ? array_map('trim', explode(',', $row['%group_memberships']))
                : [];

            $users[] = [
                'id' => $row['uuid'],
                'uuid' => $row['uuid'],
                'name' => $row['name'],
                'descr' => $row['descr'] ?? $row['comment'] ?? '',
                'disabled' => !empty($row['disabled']) && $row['disabled'] !== '0',
                'groups' => $groupMemberships,
                'uid' => $row['uid'] ?? '',
                'scope' => $row['scope'] ?? 'user',
            ];
        }
        return ['status' => 200, 'data' => $users];
    }

    public function getSystemUser(string $id): array
    {
        $res = $this->get("/api/auth/user/get/{$id}");
        return ['status' => 200, 'data' => $res['user'] ?? []];
    }

    public function createSystemUser(array $data): array
    {
        $userPayload = [
            'name' => $data['name'] ?? '',
            'descr' => $data['descr'] ?? '',
            'disabled' => !empty($data['disabled']) ? '1' : '0',
        ];

        if (!empty($data['password'])) {
            $userPayload['password'] = $data['password'];
        }

        if (!empty($data['groups']) && is_array($data['groups'])) {
            $groups = $this->getSystemGroups()['data'] ?? [];
            $nameToGid = [];
            foreach ($groups as $g) {
                $nameToGid[$g['name']] = (string)($g['gid'] ?? $g['id']);
            }
            $gids = [];
            foreach ($data['groups'] as $groupName) {
                if (isset($nameToGid[$groupName])) {
                    $gids[] = $nameToGid[$groupName];
                } elseif (is_numeric($groupName)) {
                    $gids[] = (string)$groupName;
                }
            }
            $userPayload['group_memberships'] = implode(',', $gids);
        }

        $res = $this->post('/api/auth/user/add', ['user' => $userPayload]);
        if (($res['result'] ?? '') === 'failed') {
            $validation = json_encode($res['validations'] ?? $res);
            throw new \Exception("Failed to create user on OPNsense: {$validation}");
        }

        return ['status' => 200, 'data' => $res];
    }

    public function updateSystemUser(array $data): array
    {
        $id = $data['id'] ?? $data['uuid'] ?? null;
        if (!$id) {
            throw new \Exception("User ID is required for update.");
        }

        $userPayload = [
            'descr' => $data['descr'] ?? '',
            'disabled' => !empty($data['disabled']) ? '1' : '0',
        ];

        if (!empty($data['password'])) {
            $userPayload['password'] = $data['password'];
        }

        if (isset($data['groups']) && is_array($data['groups'])) {
            $groups = $this->getSystemGroups()['data'] ?? [];
            $nameToGid = [];
            foreach ($groups as $g) {
                $nameToGid[$g['name']] = (string)($g['gid'] ?? $g['id']);
            }
            $gids = [];
            foreach ($data['groups'] as $groupName) {
                if (isset($nameToGid[$groupName])) {
                    $gids[] = $nameToGid[$groupName];
                } elseif (is_numeric($groupName)) {
                    $gids[] = (string)$groupName;
                }
            }
            $userPayload['group_memberships'] = implode(',', $gids);
        }

        $res = $this->post("/api/auth/user/set/{$id}", ['user' => $userPayload]);
        if (($res['result'] ?? '') === 'failed') {
            $validation = json_encode($res['validations'] ?? $res);
            throw new \Exception("Failed to update user on OPNsense: {$validation}");
        }

        return ['status' => 200, 'data' => $res];
    }

    public function deleteSystemUser(string $id): array
    {
        // Safety: verify user is not root or admin
        try {
            $user = $this->getSystemUser($id);
            if (in_array($user['name'] ?? '', ['root', 'admin'])) {
                throw new \Exception("Cannot delete primary administrator account ({$user['name']}).");
            }
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'Cannot delete primary administrator account')) {
                throw $e;
            }
        }

        $res = $this->post("/api/auth/user/del/{$id}", []);
        return ['status' => 200, 'data' => $res];
    }

    public function getSystemGroups(): array
    {
        $res = $this->post('/api/auth/group/search', [
            'current' => 1,
            'rowCount' => -1,
        ]);
        $rows = $res['rows'] ?? [];
        $groups = [];
        foreach ($rows as $row) {
            $members = !empty($row['%member'])
                ? array_map('trim', explode(',', $row['%member']))
                : [];

            $groups[] = [
                'id' => $row['uuid'],
                'uuid' => $row['uuid'],
                'gid' => $row['gid'] ?? '',
                'name' => $row['name'],
                'description' => $row['description'] ?? '',
                'scope' => $row['scope'] ?? 'user',
                'member' => $members,
            ];
        }
        return ['status' => 200, 'data' => $groups];
    }

    public function getSystemGroup(string $id): array
    {
        $res = $this->get("/api/auth/group/get/{$id}");
        return ['status' => 200, 'data' => $res['group'] ?? []];
    }

    public function createSystemGroup(array $data): array
    {
        $groupPayload = [
            'name' => $data['name'] ?? '',
            'description' => $data['description'] ?? '',
        ];

        $res = $this->post('/api/auth/group/add', ['group' => $groupPayload]);
        if (($res['result'] ?? '') === 'failed') {
            $validation = json_encode($res['validations'] ?? $res);
            throw new \Exception("Failed to create group on OPNsense: {$validation}");
        }

        return ['status' => 200, 'data' => $res];
    }

    public function updateSystemGroup(array $data): array
    {
        $id = $data['id'] ?? $data['uuid'] ?? null;
        if (!$id) {
            throw new \Exception("Group ID is required for update.");
        }

        $groupPayload = [
            'description' => $data['description'] ?? '',
        ];

        $res = $this->post("/api/auth/group/set/{$id}", ['group' => $groupPayload]);
        if (($res['result'] ?? '') === 'failed') {
            $validation = json_encode($res['validations'] ?? $res);
            throw new \Exception("Failed to update group on OPNsense: {$validation}");
        }

        return ['status' => 200, 'data' => $res];
    }

    public function deleteSystemGroup(string $id): array
    {
        // Safety: verify group is not admins or all
        try {
            $group = $this->getSystemGroup($id);
            if (in_array($group['name'] ?? '', ['admins', 'all'])) {
                throw new \Exception("Cannot delete core system group ({$group['name']}).");
            }
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'Cannot delete core system group')) {
                throw $e;
            }
        }

        $res = $this->post("/api/auth/group/del/{$id}", []);
        return ['status' => 200, 'data' => $res];
    }

    /*
    |--------------------------------------------------------------------------
    | System: Routing & Static Routes
    |--------------------------------------------------------------------------
    */

    public function getRoutingStaticRoutes(): array
    {
        $res = $this->post('/api/routes/routes/searchroute', [
            'current' => 1,
            'rowCount' => -1,
        ]);
        $rows = $res['rows'] ?? [];
        $routes = [];
        foreach ($rows as $row) {
            $routes[] = [
                'id' => $row['uuid'],
                'uuid' => $row['uuid'],
                'network' => $row['network'] ?? '',
                'gateway' => $row['gateway'] ?? '',
                'descr' => $row['descr'] ?? '',
                'disabled' => empty($row['enabled']) || $row['enabled'] === '0',
            ];
        }
        return ['status' => 200, 'data' => $routes];
    }

    public function getRoutingStaticRoute(string $id): array
    {
        $res = $this->get("/api/routes/routes/getroute/{$id}");
        return ['status' => 200, 'data' => $res['route'] ?? []];
    }

    public function createRoutingStaticRoute(array $data): array
    {
        $payload = [
            'network' => $data['network'] ?? '',
            'gateway' => $data['gateway'] ?? '',
            'descr' => $data['descr'] ?? '',
            'disabled' => !empty($data['disabled']) ? '1' : '0',
        ];

        $res = $this->post('/api/routes/routes/addroute', ['route' => $payload]);
        if (($res['result'] ?? '') === 'failed') {
            $validation = json_encode($res['validations'] ?? $res);
            throw new \Exception("Failed to create static route on OPNsense: {$validation}");
        }

        $this->post('/api/routes/routes/reconfigure', []);
        return ['status' => 200, 'data' => $res];
    }

    public function updateRoutingStaticRoute(array $data): array
    {
        $id = $data['id'] ?? $data['uuid'] ?? null;
        if (!$id) {
            throw new \Exception("Static route ID is required for update.");
        }

        $payload = [
            'network' => $data['network'] ?? '',
            'gateway' => $data['gateway'] ?? '',
            'descr' => $data['descr'] ?? '',
            'disabled' => !empty($data['disabled']) ? '1' : '0',
        ];

        $res = $this->post("/api/routes/routes/setroute/{$id}", ['route' => $payload]);
        if (($res['result'] ?? '') === 'failed') {
            $validation = json_encode($res['validations'] ?? $res);
            throw new \Exception("Failed to update static route on OPNsense: {$validation}");
        }

        $this->post('/api/routes/routes/reconfigure', []);
        return ['status' => 200, 'data' => $res];
    }

    public function deleteRoutingStaticRoute(string $id): array
    {
        $res = $this->post("/api/routes/routes/delroute/{$id}", []);
        $this->post('/api/routes/routes/reconfigure', []);
        return ['status' => 200, 'data' => $res];
    }

    public function getRoutingGatewayGroups(): array
    {
        return ['status' => 200, 'data' => []];
    }

    public function createRoutingGatewayGroup(array $data): array
    {
        throw new \BadMethodCallException('Gateway groups are not supported via API on OPNsense. Please configure Gateway Groups directly in the OPNsense Web GUI.');
    }

    public function updateRoutingGatewayGroup(array $data): array
    {
        throw new \BadMethodCallException('Gateway groups are not supported via API on OPNsense. Please configure Gateway Groups directly in the OPNsense Web GUI.');
    }

    public function deleteRoutingGatewayGroup(string $id): array
    {
        throw new \BadMethodCallException('Gateway groups are not supported via API on OPNsense. Please configure Gateway Groups directly in the OPNsense Web GUI.');
    }

    public function getSchedules(): array
    {
        return ['status' => 200, 'data' => []];
    }

    public function createSchedule(array $data): array
    {
        throw new \BadMethodCallException('Firewall schedules are not supported via API on OPNsense. Please configure schedules directly in the OPNsense Web GUI.');
    }

    public function updateSchedule(int|string $id, array $data): array
    {
        throw new \BadMethodCallException('Firewall schedules are not supported via API on OPNsense. Please configure schedules directly in the OPNsense Web GUI.');
    }

    public function deleteSchedule(int|string $id): array
    {
        throw new \BadMethodCallException('Firewall schedules are not supported via API on OPNsense. Please configure schedules directly in the OPNsense Web GUI.');
    }

    /**
     * Get Certificate Authorities
     */
    public function getCertificateAuthorities(): array
    {
        $response = $this->get('/api/trust/ca/search');
        return [
            'status' => 200,
            'data' => $response['rows'] ?? [],
        ];
    }

    /**
     * Get a specific Certificate Authority
     */
    public function getCertificateAuthority(string $id): array
    {
        $cas = $this->getCertificateAuthorities()['data'] ?? [];
        foreach ($cas as $ca) {
            if (($ca['refid'] ?? '') === $id || ($ca['uuid'] ?? '') === $id) {
                return ['status' => 200, 'data' => $ca];
            }
        }
        return ['status' => 404, 'data' => null];
    }

    /**
     * Create Certificate Authority (Import)
     */
    public function createCertificateAuthority(array $data): array
    {
        $payload = [
            'descr' => $data['descr'] ?? '',
            'action' => 'existing',
            'crt_payload' => $data['cert'] ?? $data['crt'] ?? $data['crt_payload'] ?? '',
            'prv_payload' => $data['key'] ?? $data['prv'] ?? $data['prv_payload'] ?? '',
            'serial' => (string) ($data['serial'] ?? ''),
        ];

        $res = $this->post('/api/trust/ca/add', ['ca' => $payload]);
        if (($res['result'] ?? '') === 'failed') {
            $validation = json_encode($res['validations'] ?? $res);
            throw new \Exception("Failed to import Certificate Authority on OPNsense: {$validation}");
        }

        return ['status' => 200, 'data' => $res];
    }

    /**
     * Generate Certificate Authority (Internal)
     */
    public function generateCertificateAuthority(array $data): array
    {
        $payload = [
            'descr' => $data['descr'] ?? '',
            'action' => 'internal',
            'key_type' => (string) ($data['keylen'] ?? $data['key_type'] ?? '2048'),
            'digest' => strtolower($data['digest_alg'] ?? $data['digest'] ?? 'sha256'),
            'lifetime' => (string) ($data['lifetime'] ?? '3650'),
            'country' => $data['dn_country'] ?? $data['country'] ?? 'US',
            'state' => $data['dn_state'] ?? $data['state'] ?? '',
            'city' => $data['dn_city'] ?? $data['city'] ?? '',
            'organization' => $data['dn_organization'] ?? $data['organization'] ?? '',
            'email' => $data['dn_email'] ?? $data['email'] ?? '',
            'commonname' => $data['dn_commonname'] ?? $data['commonname'] ?? $data['descr'] ?? '',
        ];

        $res = $this->post('/api/trust/ca/add', ['ca' => $payload]);
        if (($res['result'] ?? '') === 'failed') {
            $validation = json_encode($res['validations'] ?? $res);
            throw new \Exception("Failed to generate Certificate Authority on OPNsense: {$validation}");
        }

        return ['status' => 200, 'data' => $res];
    }

    /**
     * Delete Certificate Authority
     */
    public function deleteCertificateAuthority(string $id): array
    {
        $uuid = $id;
        $cas = $this->getCertificateAuthorities()['data'] ?? [];
        foreach ($cas as $ca) {
            if (($ca['refid'] ?? '') === $id || ($ca['uuid'] ?? '') === $id) {
                $uuid = $ca['uuid'] ?? $id;
                break;
            }
        }

        $res = $this->post("/api/trust/ca/del/{$uuid}", []);
        return ['status' => 200, 'data' => $res];
    }

    /**
     * Get Certificates
     */
    public function getCertificates(): array
    {
        $response = $this->get('/api/trust/cert/search');
        return [
            'status' => 200,
            'data' => $response['rows'] ?? [],
        ];
    }

    /**
     * Get a specific Certificate
     */
    public function getCertificate(string $id): array
    {
        $certs = $this->getCertificates()['data'] ?? [];
        foreach ($certs as $cert) {
            if (($cert['refid'] ?? '') === $id || ($cert['uuid'] ?? '') === $id) {
                return ['status' => 200, 'data' => $cert];
            }
        }
        return ['status' => 404, 'data' => null];
    }

    /**
     * Create Certificate (Import)
     */
    public function createCertificate(array $data): array
    {
        $payload = [
            'descr' => $data['descr'] ?? '',
            'action' => 'import',
            'crt_payload' => $data['cert'] ?? $data['crt'] ?? $data['crt_payload'] ?? '',
            'prv_payload' => $data['key'] ?? $data['prv'] ?? $data['prv_payload'] ?? '',
        ];

        $res = $this->post('/api/trust/cert/add', ['cert' => $payload]);
        if (($res['result'] ?? '') === 'failed') {
            $validation = json_encode($res['validations'] ?? $res);
            throw new \Exception("Failed to import Certificate on OPNsense: {$validation}");
        }

        return ['status' => 200, 'data' => $res];
    }

    /**
     * Generate Certificate (Internal)
     */
    public function generateCertificate(array $data): array
    {
        $certType = $data['type'] ?? $data['cert_type'] ?? 'server';
        if ($certType === 'user') {
            $opnCertType = 'usr_cert';
        } elseif ($certType === 'server') {
            $opnCertType = 'server_cert';
        } else {
            $opnCertType = $certType;
        }

        $payload = [
            'descr' => $data['descr'] ?? '',
            'caref' => $data['caref'] ?? '',
            'action' => 'internal',
            'key_type' => (string) ($data['keylen'] ?? $data['key_type'] ?? '2048'),
            'digest' => strtolower($data['digest_alg'] ?? $data['digest'] ?? 'sha256'),
            'cert_type' => $opnCertType,
            'lifetime' => (string) ($data['lifetime'] ?? '397'),
            'country' => $data['dn_country'] ?? $data['country'] ?? 'US',
            'state' => $data['dn_state'] ?? $data['state'] ?? '',
            'city' => $data['dn_city'] ?? $data['city'] ?? '',
            'organization' => $data['dn_organization'] ?? $data['organization'] ?? '',
            'email' => $data['dn_email'] ?? $data['email'] ?? '',
            'commonname' => $data['dn_commonname'] ?? $data['commonname'] ?? $data['descr'] ?? '',
        ];

        $res = $this->post('/api/trust/cert/add', ['cert' => $payload]);
        if (($res['result'] ?? '') === 'failed') {
            $validation = json_encode($res['validations'] ?? $res);
            throw new \Exception("Failed to generate Certificate on OPNsense: {$validation}");
        }

        return ['status' => 200, 'data' => $res];
    }

    /**
     * Delete Certificate
     */
    public function deleteCertificate(string $id): array
    {
        $uuid = $id;
        $certs = $this->getCertificates()['data'] ?? [];
        foreach ($certs as $cert) {
            if (($cert['refid'] ?? '') === $id || ($cert['uuid'] ?? '') === $id) {
                $uuid = $cert['uuid'] ?? $id;
                break;
            }
        }

        $res = $this->post("/api/trust/cert/del/{$uuid}", []);
        return ['status' => 200, 'data' => $res];
    }

    /**
     * Get Certificate Revocation Lists (CRLs)
     */
    public function getCRLs(): array
    {
        $response = $this->get('/api/trust/crl/search');
        $rows = $response['rows'] ?? [];

        $mapped = [];
        foreach ($rows as $r) {
            $mapped[] = [
                'refid' => $r['refid'] ?? '',
                'descr' => !empty($r['crl_descr']) ? $r['crl_descr'] : ($r['descr'] ?? 'CRL'),
                'caref' => $r['descr'] ?? $r['caref'] ?? '',
                'cert' => $r['cert'] ?? [],
            ];
        }

        return [
            'status' => 200,
            'data' => $mapped,
        ];
    }

    /**
     * Get a specific CRL
     */
    public function getCRL(string $id): array
    {
        $res = $this->get("/api/trust/crl/get/{$id}");
        return ['status' => 200, 'data' => $res];
    }

    /**
     * Create CRL
     */
    public function createCRL(array $data): array
    {
        $caref = $data['caref'] ?? '';
        $payload = [
            'descr' => $data['descr'] ?? 'CRL',
            'crlmethod' => 'internal',
            'caref' => $caref,
            'lifetime' => (string) ($data['lifetime'] ?? '730'),
        ];

        $res = $this->post("/api/trust/crl/set/{$caref}", ['crl' => $payload]);
        if (($res['status'] ?? '') === 'failed') {
            $validation = json_encode($res['validations'] ?? $res);
            throw new \Exception("Failed to create CRL on OPNsense: {$validation}");
        }

        return ['status' => 200, 'data' => $res];
    }

    /**
     * Delete CRL
     */
    public function deleteCRL(string $id): array
    {
        $res = $this->post("/api/trust/crl/del/{$id}", []);
        return ['status' => 200, 'data' => $res];
    }

    /**
     * Get IPsec Status (SAs)
     */
    public function getIpsecStatus(): array
    {
        $p1 = $this->get('/api/ipsec/sessions/search_phase1');
        $rows = $p1['rows'] ?? [];

        $sas = [];
        foreach ($rows as $row) {
            $state = strtolower($row['state'] ?? 'established');
            $sas[] = [
                'descr' => $row['name'] ?? ($row['connection'] ?? 'IPsec SA'),
                'localid' => $row['local-host'] ?? ($row['local_host'] ?? ''),
                'remoteid' => $row['remote-host'] ?? ($row['remote_host'] ?? ''),
                'status' => $state,
                'connected' => $state === 'established' ? 'Yes' : 'No',
            ];
        }

        return [
            'status' => 200,
            'data' => $sas,
        ];
    }

    /**
     * Get IPsec service status
     */
    public function getIpsecServiceStatus(): array
    {
        return $this->get('/api/ipsec/service/status');
    }

    /**
     * Reconfigure IPsec service
     */
    public function reconfigureIpsecService(): array
    {
        return $this->post('/api/ipsec/service/reconfigure', []);
    }

    /**
     * Restart IPsec service
     */
    public function restartIpsecService(): array
    {
        return $this->post('/api/ipsec/service/restart', []);
    }

    /**
     * Get IPsec Phase 1s (Connections)
     */
    public function getIpsecPhase1s(): array
    {
        $res = $this->get('/api/ipsec/connections/searchConnection');
        $rows = $res['rows'] ?? [];

        $phase1s = [];
        foreach ($rows as $row) {
            $phase1s[] = [
                'ikeid' => $row['uuid'] ?? ($row['id'] ?? ''),
                'remote-gateway' => $row['remote_addrs'] ?? ($row['remote_host'] ?? ''),
                'mode' => 'IKEv' . ($row['version'] ?? '2'),
                'descr' => $row['description'] ?? ($row['name'] ?? ''),
                'disabled' => empty($row['enabled']) || (string)$row['enabled'] === '0',
            ];
        }

        return [
            'status' => 200,
            'data' => $phase1s,
        ];
    }

    /**
     * Create IPsec Phase 1 (Connection)
     */
    public function createIpsecPhase1(array $data): array
    {
        $payload = [
            'description' => $data['descr'] ?? '',
            'enabled' => !empty($data['disabled']) ? '0' : '1',
            'remote_addrs' => $data['remote_gateway'] ?? ($data['remote-gateway'] ?? ''),
            'version' => str_ends_with($data['iketype'] ?? 'v2', '1') ? '1' : '2',
            'proposals' => 'default',
        ];

        $res = $this->post('/api/ipsec/connections/addConnection', ['connection' => $payload]);
        if (($res['result'] ?? '') === 'failed') {
            $validation = json_encode($res['validations'] ?? $res);
            throw new \Exception("Failed to create IPsec Connection on OPNsense: {$validation}");
        }

        $this->reconfigureIpsecService();
        return ['status' => 200, 'data' => $res];
    }

    /**
     * Delete IPsec Phase 1 (Connection)
     */
    public function deleteIpsecPhase1(string $id): array
    {
        $res = $this->post("/api/ipsec/connections/delConnection/{$id}", []);
        $this->reconfigureIpsecService();
        return ['status' => 200, 'data' => $res];
    }

    /**
     * Get IPsec Phase 2s (Children)
     */
    public function getIpsecPhase2s(): array
    {
        $res = $this->get('/api/ipsec/connections/searchChild');
        $rows = $res['rows'] ?? [];

        $phase2s = [];
        foreach ($rows as $row) {
            $phase2s[] = [
                'uniqid' => $row['uuid'] ?? ($row['id'] ?? ''),
                'ikeid' => $row['connection'] ?? '',
                'mode' => $row['mode'] ?? 'tunnel',
                'descr' => $row['description'] ?? '',
                'localid' => [
                    'type' => 'network',
                    'address' => $row['local_ts'] ?? '',
                    'netbits' => '',
                ],
                'remoteid' => [
                    'type' => 'network',
                    'address' => $row['remote_ts'] ?? '',
                    'netbits' => '',
                ],
            ];
        }

        return [
            'status' => 200,
            'data' => $phase2s,
        ];
    }

    /**
     * Create IPsec Phase 2 (Child)
     */
    public function createIpsecPhase2(array $data): array
    {
        $payload = [
            'connection' => $data['ikeid'] ?? '',
            'description' => $data['descr'] ?? '',
            'mode' => $data['mode'] ?? 'tunnel',
            'local_ts' => $data['localid_address'] ?? ($data['local_ts'] ?? ''),
            'remote_ts' => $data['remoteid_address'] ?? ($data['remote_ts'] ?? ''),
            'enabled' => !empty($data['disabled']) ? '0' : '1',
            'esp_proposals' => 'default',
        ];

        $res = $this->post('/api/ipsec/connections/addChild', ['child' => $payload]);
        if (($res['result'] ?? '') === 'failed') {
            $validation = json_encode($res['validations'] ?? $res);
            throw new \Exception("Failed to create IPsec Child on OPNsense: {$validation}");
        }

        $this->reconfigureIpsecService();
        return ['status' => 200, 'data' => $res];
    }

    /**
     * Delete IPsec Phase 2 (Child)
     */
    public function deleteIpsecPhase2(string $id): array
    {
        $res = $this->post("/api/ipsec/connections/delChild/{$id}", []);
        $this->reconfigureIpsecService();
        return ['status' => 200, 'data' => $res];
    }

    /**
     * Get Captive Portal Zones
     */
    public function getCaptivePortalZones(): array
    {
        $res = $this->get('/api/captiveportal/settings/searchZones');
        $rows = $res['rows'] ?? [];

        $zones = [];
        foreach ($rows as $row) {
            $name = !empty($row['description']) ? $row['description'] : ('Zone ' . ($row['zoneid'] ?? ''));
            $zones[$name] = [
                'uuid' => $row['uuid'] ?? '',
                'zoneid' => $row['zoneid'] ?? '0',
                'descr' => $row['description'] ?? '',
                'interface' => $row['%interfaces'] ?? ($row['interfaces'] ?? ''),
                'enabled' => !empty($row['enabled']) && (string)$row['enabled'] !== '0',
            ];
        }

        return [
            'status' => 200,
            'data' => $zones,
        ];
    }

    /**
     * Get a specific Captive Portal Zone
     */
    public function getCaptivePortalZone(string $uuid): array
    {
        $res = $this->get("/api/captiveportal/settings/getZone/{$uuid}");
        return ['status' => 200, 'data' => $res['zone'] ?? []];
    }

    /**
     * Create Captive Portal Zone
     */
    public function createCaptivePortalZone(array $data): array
    {
        $payload = [
            'enabled' => !empty($data['enabled']) || !isset($data['enabled']) ? '1' : '0',
            'description' => $data['description'] ?? ($data['descr'] ?? ''),
            'interfaces' => $data['interfaces'] ?? ($data['interface'] ?? 'lan'),
        ];

        $res = $this->post('/api/captiveportal/settings/addZone', ['zone' => $payload]);
        if (($res['result'] ?? '') === 'failed') {
            $validation = json_encode($res['validations'] ?? $res);
            throw new \Exception("Failed to create Captive Portal zone on OPNsense: {$validation}");
        }

        $this->reconfigureCaptivePortalService();
        return ['status' => 200, 'data' => $res];
    }

    /**
     * Update Captive Portal Zone
     */
    public function updateCaptivePortalZone(string $uuid, array $data): array
    {
        $payload = [
            'enabled' => !empty($data['enabled']) || !isset($data['enabled']) ? '1' : '0',
            'description' => $data['description'] ?? ($data['descr'] ?? ''),
            'interfaces' => $data['interfaces'] ?? ($data['interface'] ?? 'lan'),
        ];

        $res = $this->post("/api/captiveportal/settings/setZone/{$uuid}", ['zone' => $payload]);
        if (($res['result'] ?? '') === 'failed') {
            $validation = json_encode($res['validations'] ?? $res);
            throw new \Exception("Failed to update Captive Portal zone on OPNsense: {$validation}");
        }

        $this->reconfigureCaptivePortalService();
        return ['status' => 200, 'data' => $res];
    }

    /**
     * Delete Captive Portal Zone
     */
    public function deleteCaptivePortalZone(string $uuid): array
    {
        $res = $this->post("/api/captiveportal/settings/delZone/{$uuid}", []);
        $this->reconfigureCaptivePortalService();
        return ['status' => 200, 'data' => $res];
    }

    /**
     * Get Captive Portal Sessions
     */
    public function getCaptivePortalSessions(): array
    {
        $res = $this->get('/api/captiveportal/session/search');
        return [
            'status' => 200,
            'data' => $res['rows'] ?? [],
        ];
    }

    /**
     * Get Captive Portal Service Status
     */
    public function getCaptivePortalServiceStatus(): array
    {
        return $this->get('/api/captiveportal/service/status');
    }

    /**
     * Reconfigure Captive Portal Service
     */
    public function reconfigureCaptivePortalService(): array
    {
        return $this->post('/api/captiveportal/service/reconfigure', []);
    }

    /**
     * Get DNS Forwarder (Dnsmasq) Settings
     */
    public function getDnsForwarderSettings(): array
    {
        $res = $this->get('/api/dnsmasq/settings/get');
        return [
            'status' => 200,
            'data' => $res['dnsmasq'] ?? [],
        ];
    }

    /**
     * Update DNS Forwarder (Dnsmasq) Settings
     */
    public function updateDnsForwarderSettings(array $data): array
    {
        $res = $this->post('/api/dnsmasq/settings/set', ['dnsmasq' => $data]);
        $this->reconfigureDnsForwarderService();
        return ['status' => 200, 'data' => $res];
    }

    /**
     * Get DNS Forwarder Service Status
     */
    public function getDnsForwarderServiceStatus(): array
    {
        return $this->get('/api/dnsmasq/service/status');
    }

    /**
     * Reconfigure DNS Forwarder Service
     */
    public function reconfigureDnsForwarderService(): array
    {
        return $this->post('/api/dnsmasq/service/reconfigure', []);
    }

    /**
     * Restart DNS Forwarder Service
     */
    public function restartDnsForwarderService(): array
    {
        return $this->post('/api/dnsmasq/service/restart', []);
    }

    /**
     * Get DNS Forwarder Host Overrides
     */
    public function getDnsForwarderHostOverrides(): array
    {
        $res = $this->get('/api/dnsmasq/settings/searchHost');
        $rows = $res['rows'] ?? [];

        $hosts = [];
        foreach ($rows as $row) {
            $hosts[] = [
                'uuid' => $row['uuid'] ?? '',
                'host' => $row['host'] ?? '',
                'domain' => $row['domain'] ?? '',
                'ip' => $row['ip'] ?? '',
                'descr' => $row['descr'] ?? ($row['comments'] ?? ''),
            ];
        }

        return [
            'status' => 200,
            'data' => $hosts,
        ];
    }

    /**
     * Create DNS Forwarder Host Override
     */
    public function createDnsForwarderHostOverride(array $data): array
    {
        $payload = [
            'host' => $data['host'] ?? '',
            'domain' => $data['domain'] ?? '',
            'ip' => $data['ip'] ?? '',
            'descr' => $data['descr'] ?? ($data['description'] ?? ''),
        ];

        $res = $this->post('/api/dnsmasq/settings/addHost', ['host' => $payload]);
        if (($res['result'] ?? '') === 'failed') {
            $validation = json_encode($res['validations'] ?? $res);
            throw new \Exception("Failed to create DNS Forwarder host override on OPNsense: {$validation}");
        }

        $this->reconfigureDnsForwarderService();
        return ['status' => 200, 'data' => $res];
    }

    /**
     * Delete DNS Forwarder Host Override
     */
    public function deleteDnsForwarderHostOverride(string $uuid): array
    {
        $res = $this->post("/api/dnsmasq/settings/delHost/{$uuid}", []);
        $this->reconfigureDnsForwarderService();
        return ['status' => 200, 'data' => $res];
    }

    /*
    |--------------------------------------------------------------------------
    | Traffic Shaper (Limiters, Pipes, Queues, Rules)
    |--------------------------------------------------------------------------
    */

    /**
     * Get Traffic Shaper Limiters (mapped to pipes)
     */
    public function getLimiters(): array
    {
        $res = $this->get('/api/trafficshaper/settings/searchPipes');
        $rows = $res['rows'] ?? [];
        $limiters = [];

        foreach ($rows as $row) {
            $metric = $row['bandwidthMetric'] ?? 'Kbit';
            $scale = match(strtolower($metric)) {
                'gbit' => 'Gb',
                'mbit' => 'Mb',
                'bit' => 'b',
                default => 'Kb',
            };
            $mask = match($row['mask'] ?? 'none') {
                'src-ip' => 'srcaddress',
                'dst-ip' => 'dstaddress',
                default => 'none',
            };

            $limiters[] = [
                'id' => $row['uuid'] ?? '',
                'uuid' => $row['uuid'] ?? '',
                'name' => $row['description'] ?? ('Pipe #' . ($row['number'] ?? '')),
                'descr' => $row['description'] ?? '',
                'bandwidth' => [
                    [
                        'bw' => $row['bandwidth'] ?? '0',
                        'bwscale' => $scale,
                    ]
                ],
                'mask' => $mask,
                'sched' => $row['scheduler'] ?? 'fifo',
                'aqm' => !empty($row['codel_enable']) ? 'codel' : (!empty($row['pie_enable']) ? 'pie' : 'none'),
                'enabled' => $row['enabled'] ?? '1',
            ];
        }

        return ['status' => 200, 'data' => $limiters];
    }

    /**
     * Create Traffic Shaper Limiter (Pipe)
     */
    public function createLimiter(array $data): array
    {
        $bw = $data['bandwidth']['item']['bw'] ?? ($data['bandwidth_value'] ?? '10');
        $scale = $data['bandwidth']['item']['bwscale'] ?? ($data['bandwidth_scale'] ?? 'Mb');
        $metric = match(strtolower($scale)) {
            'gb', 'gbit' => 'Gbit',
            'mb', 'mbit' => 'Mbit',
            'b', 'bit' => 'bit',
            default => 'Kbit',
        };
        $mask = match($data['mask'] ?? 'none') {
            'srcaddress', 'src-ip' => 'src-ip',
            'dstaddress', 'dst-ip' => 'dst-ip',
            default => 'none',
        };

        $payload = [
            'pipe' => [
                'enabled' => '1',
                'bandwidth' => (string) $bw,
                'bandwidthMetric' => $metric,
                'mask' => $mask,
                'description' => $data['descr'] ?? ($data['name'] ?? ''),
            ]
        ];

        $res = $this->post('/api/trafficshaper/settings/addPipe', $payload);
        if (($res['result'] ?? '') === 'failed') {
            $validation = json_encode($res['validations'] ?? $res);
            throw new \Exception("Failed to create traffic shaper limiter on OPNsense: {$validation}");
        }

        $this->reconfigureTrafficShaperService();
        return ['status' => 200, 'data' => $res];
    }

    /**
     * Update Traffic Shaper Limiter (Pipe)
     */
    public function updateLimiter(string $id, array $data): array
    {
        $bw = $data['bandwidth']['item']['bw'] ?? ($data['bandwidth_value'] ?? '10');
        $scale = $data['bandwidth']['item']['bwscale'] ?? ($data['bandwidth_scale'] ?? 'Mb');
        $metric = match(strtolower($scale)) {
            'gb', 'gbit' => 'Gbit',
            'mb', 'mbit' => 'Mbit',
            'b', 'bit' => 'bit',
            default => 'Kbit',
        };
        $mask = match($data['mask'] ?? 'none') {
            'srcaddress', 'src-ip' => 'src-ip',
            'dstaddress', 'dst-ip' => 'dst-ip',
            default => 'none',
        };

        $payload = [
            'pipe' => [
                'enabled' => '1',
                'bandwidth' => (string) $bw,
                'bandwidthMetric' => $metric,
                'mask' => $mask,
                'description' => $data['descr'] ?? ($data['name'] ?? ''),
            ]
        ];

        $res = $this->post("/api/trafficshaper/settings/setPipe/{$id}", $payload);
        $this->reconfigureTrafficShaperService();
        return ['status' => 200, 'data' => $res];
    }

    /**
     * Delete Traffic Shaper Limiter (Pipe)
     */
    public function deleteLimiter(string $id): array
    {
        $res = $this->post("/api/trafficshaper/settings/delPipe/{$id}", []);
        $this->reconfigureTrafficShaperService();
        return ['status' => 200, 'data' => $res];
    }

    /**
     * Get Traffic Shaper Pipes
     */
    public function getTrafficShaperPipes(): array
    {
        $res = $this->get('/api/trafficshaper/settings/searchPipes');
        return ['status' => 200, 'data' => $res['rows'] ?? []];
    }

    /**
     * Get Traffic Shaper Pipe
     */
    public function getTrafficShaperPipe(string $uuid): array
    {
        $res = $this->get("/api/trafficshaper/settings/getPipe/{$uuid}");
        return ['status' => 200, 'data' => $res['pipe'] ?? []];
    }

    /**
     * Create Traffic Shaper Pipe
     */
    public function createTrafficShaperPipe(array $data): array
    {
        $res = $this->post('/api/trafficshaper/settings/addPipe', ['pipe' => $data]);
        $this->reconfigureTrafficShaperService();
        return ['status' => 200, 'data' => $res];
    }

    /**
     * Update Traffic Shaper Pipe
     */
    public function updateTrafficShaperPipe(string $uuid, array $data): array
    {
        $res = $this->post("/api/trafficshaper/settings/setPipe/{$uuid}", ['pipe' => $data]);
        $this->reconfigureTrafficShaperService();
        return ['status' => 200, 'data' => $res];
    }

    /**
     * Delete Traffic Shaper Pipe
     */
    public function deleteTrafficShaperPipe(string $uuid): array
    {
        $res = $this->post("/api/trafficshaper/settings/delPipe/{$uuid}", []);
        $this->reconfigureTrafficShaperService();
        return ['status' => 200, 'data' => $res];
    }

    /**
     * Get Traffic Shaper Queues
     */
    public function getTrafficShaperQueues(): array
    {
        $res = $this->get('/api/trafficshaper/settings/searchQueues');
        return ['status' => 200, 'data' => $res['rows'] ?? []];
    }

    /**
     * Get Traffic Shaper Queue
     */
    public function getTrafficShaperQueue(string $uuid): array
    {
        $res = $this->get("/api/trafficshaper/settings/getQueue/{$uuid}");
        return ['status' => 200, 'data' => $res['queue'] ?? []];
    }

    /**
     * Create Traffic Shaper Queue
     */
    public function createTrafficShaperQueue(array $data): array
    {
        $res = $this->post('/api/trafficshaper/settings/addQueue', ['queue' => $data]);
        $this->reconfigureTrafficShaperService();
        return ['status' => 200, 'data' => $res];
    }

    /**
     * Delete Traffic Shaper Queue
     */
    public function deleteTrafficShaperQueue(string $uuid): array
    {
        $res = $this->post("/api/trafficshaper/settings/delQueue/{$uuid}", []);
        $this->reconfigureTrafficShaperService();
        return ['status' => 200, 'data' => $res];
    }

    /**
     * Get Traffic Shaper Rules
     */
    public function getTrafficShaperRules(): array
    {
        $res = $this->get('/api/trafficshaper/settings/searchRules');
        return ['status' => 200, 'data' => $res['rows'] ?? []];
    }

    /**
     * Get Traffic Shaper Rule
     */
    public function getTrafficShaperRule(string $uuid): array
    {
        $res = $this->get("/api/trafficshaper/settings/getRule/{$uuid}");
        return ['status' => 200, 'data' => $res['rule'] ?? []];
    }

    /**
     * Create Traffic Shaper Rule
     */
    public function createTrafficShaperRule(array $data): array
    {
        $res = $this->post('/api/trafficshaper/settings/addRule', ['rule' => $data]);
        $this->reconfigureTrafficShaperService();
        return ['status' => 200, 'data' => $res];
    }

    /**
     * Delete Traffic Shaper Rule
     */
    public function deleteTrafficShaperRule(string $uuid): array
    {
        $res = $this->post("/api/trafficshaper/settings/delRule/{$uuid}", []);
        $this->reconfigureTrafficShaperService();
        return ['status' => 200, 'data' => $res];
    }

    /**
     * Reconfigure Traffic Shaper Service
     */
    public function reconfigureTrafficShaperService(): array
    {
        return $this->post('/api/trafficshaper/service/reconfigure', []);
    }

    /*
    |--------------------------------------------------------------------------
    | Syslog & Remote Logging Settings (/api/syslog/*)
    |--------------------------------------------------------------------------
    */

    /**
     * Get Syslog General Settings
     */
    public function getSyslogSettings(): array
    {
        $res = $this->get('/api/syslog/settings/get');
        return [
            'status' => 200,
            'data' => $res['syslog'] ?? [],
        ];
    }

    /**
     * Update Syslog General Settings
     */
    public function updateSyslogSettings(array $data): array
    {
        $res = $this->post('/api/syslog/settings/set', ['syslog' => $data]);
        $this->reconfigureSyslogService();
        return ['status' => 200, 'data' => $res];
    }

    /**
     * Get Syslog Remote Destinations
     */
    public function getSyslogDestinations(): array
    {
        $res = $this->get('/api/syslog/settings/searchDestinations');
        $rows = $res['rows'] ?? [];
        $destinations = [];

        foreach ($rows as $row) {
            $destinations[] = [
                'uuid' => $row['uuid'] ?? '',
                'enabled' => $row['enabled'] ?? '1',
                'transport' => $row['transport'] ?? 'udp4',
                'hostname' => $row['hostname'] ?? '',
                'port' => $row['port'] ?? '514',
                'description' => $row['description'] ?? '',
                'facility' => $row['facility'] ?? '',
                'level' => $row['level'] ?? '',
                'program' => $row['program'] ?? '',
            ];
        }

        return ['status' => 200, 'data' => $destinations];
    }

    /**
     * Get Syslog Destination
     */
    public function getSyslogDestination(string $uuid): array
    {
        $res = $this->get("/api/syslog/settings/getDestination/{$uuid}");
        return ['status' => 200, 'data' => $res['destination'] ?? []];
    }

    /**
     * Create Syslog Remote Destination
     */
    public function createSyslogDestination(array $data): array
    {
        $payload = [
            'enabled' => !empty($data['enabled']) ? '1' : '0',
            'transport' => $data['transport'] ?? 'udp4',
            'hostname' => $data['hostname'] ?? '',
            'port' => (string) ($data['port'] ?? '514'),
            'description' => $data['description'] ?? ($data['descr'] ?? ''),
        ];

        if (!empty($data['facility'])) {
            $payload['facility'] = $data['facility'];
        }
        if (!empty($data['level'])) {
            $payload['level'] = $data['level'];
        }
        if (!empty($data['program'])) {
            $payload['program'] = $data['program'];
        }

        $res = $this->post('/api/syslog/settings/addDestination', ['destination' => $payload]);
        if (($res['result'] ?? '') === 'failed') {
            $validation = json_encode($res['validations'] ?? $res);
            throw new \Exception("Failed to create syslog destination on OPNsense: {$validation}");
        }

        $this->reconfigureSyslogService();
        return ['status' => 200, 'data' => $res];
    }

    /**
     * Update Syslog Remote Destination
     */
    public function updateSyslogDestination(string $uuid, array $data): array
    {
        $payload = [
            'enabled' => !empty($data['enabled']) ? '1' : '0',
            'transport' => $data['transport'] ?? 'udp4',
            'hostname' => $data['hostname'] ?? '',
            'port' => (string) ($data['port'] ?? '514'),
            'description' => $data['description'] ?? ($data['descr'] ?? ''),
        ];

        $res = $this->post("/api/syslog/settings/setDestination/{$uuid}", ['destination' => $payload]);
        $this->reconfigureSyslogService();
        return ['status' => 200, 'data' => $res];
    }

    /**
     * Delete Syslog Remote Destination
     */
    public function deleteSyslogDestination(string $uuid): array
    {
        $res = $this->post("/api/syslog/settings/delDestination/{$uuid}", []);
        $this->reconfigureSyslogService();
        return ['status' => 200, 'data' => $res];
    }

    /**
     * Get Syslog Service Status
     */
    public function getSyslogServiceStatus(): array
    {
        return $this->get('/api/syslog/service/status');
    }

    /**
     * Get Syslog Stats
     */
    public function getSyslogStats(): array
    {
        return $this->get('/api/syslog/service/stats');
    }

    /**
     * Reconfigure Syslog Service
     */
    public function reconfigureSyslogService(): array
    {
        return $this->post('/api/syslog/service/reconfigure', []);
    }

    /*
    |--------------------------------------------------------------------------
    | Diagnostics: Kernel Routing Table (/api/diagnostics/interface/getRoutes)
    |--------------------------------------------------------------------------
    */

    /**
     * Get Kernel Routing Table
     */
    public function getKernelRoutes(): array
    {
        $res = $this->get('/api/diagnostics/interface/getRoutes');
        return [
            'status' => 200,
            'data' => is_array($res) ? $res : [],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | High Availability & CARP (/api/core/hasync/* & /api/diagnostics/interface/getVipStatus)
    |--------------------------------------------------------------------------
    */

    /**
     * Get High Availability Sync Settings (hasync)
     */
    public function getHighAvailabilitySync(): array
    {
        return $this->get('/api/core/hasync/get');
    }

    /**
     * Update High Availability Sync Settings (hasync)
     */
    public function updateHighAvailabilitySync(array $data): array
    {
        return $this->post('/api/core/hasync/set', ['hasync' => $data]);
    }

    /**
     * Get CARP and Virtual IP Status
     */
    public function getVipStatus(): array
    {
        return $this->get('/api/diagnostics/interface/getVipStatus');
    }

    /**
     * Get CARP Status formatted for Central
     */
    public function getCarpStatus(): array
    {
        try {
            $vipStatus = $this->getVipStatus();
            $carp = $vipStatus['carp'] ?? [];
            return [
                'status' => 200,
                'data' => [
                    'enable' => ($carp['allow'] ?? '0') === '1' || ($carp['allow'] ?? 0) === 1,
                    'maintenance_mode' => !empty($carp['maintenancemode']),
                    'demotion' => $carp['demotion'] ?? '0',
                    'status_msg' => $carp['status_msg'] ?? '',
                    'rows' => $vipStatus['rows'] ?? [],
                ],
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 200,
                'data' => [
                    'enable' => true,
                    'maintenance_mode' => false,
                    'demotion' => '0',
                    'status_msg' => $e->getMessage(),
                    'rows' => [],
                ],
            ];
        }
    }
}


