<?php

namespace App\Services;

use App\Models\Firewall;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class PfSenseApiService
{
    protected $firewall;
    protected $baseUrl;
    protected $username;
    protected $password;
    protected $apiToken;
    protected $authMethod;

    public function __construct(Firewall $firewall)
    {
        $this->firewall = $firewall;
        $this->baseUrl = rtrim($firewall->url, '/') . '/api/v2';
        $this->authMethod = $firewall->auth_method ?? 'basic'; // Default to basic if null

        if ($this->authMethod === 'token') {
            $this->apiToken = $firewall->api_token;
            if (empty($this->apiToken)) {
                throw new \Exception("Firewall API token is missing for firewall ID: " . $firewall->id);
            }
        } else {
            $this->username = $firewall->api_key;
            $this->password = $firewall->api_secret;

            if (empty($this->username) || empty($this->password)) {
                throw new \Exception("Firewall API credentials are missing for firewall ID: " . $firewall->id);
            }
        }
    }

    /**
     * Make a GET request to the pfSense API
     */
    public function get(string $endpoint, array $params = [])
    {
        return $this->request('GET', $endpoint, $params);
    }

    /**
     * Make a POST request to the pfSense API
     */
    public function post(string $endpoint, array $data = [])
    {
        return $this->request('POST', $endpoint, $data);
    }

    /**
     * Make a PUT request to the pfSense API
     */
    public function put(string $endpoint, array $data = [])
    {
        return $this->request('PUT', $endpoint, $data);
    }

    /**
     * Make a PATCH request to the pfSense API
     */
    public function patch(string $endpoint, array $data = [])
    {
        return $this->request('PATCH', $endpoint, $data);
    }

    /**
     * Make a DELETE request to the pfSense API
     */
    public function delete(string $endpoint, array $data = [])
    {
        return $this->request('DELETE', $endpoint, $data);
    }

    /**
     * Make a request to the pfSense API
     */
    protected function request(string $method, string $endpoint, array $data = [])
    {
        // If endpoint starts with /api/, treat it as absolute path from host root
        if (str_starts_with($endpoint, '/api/')) {
            $url = rtrim($this->firewall->url, '/') . '/' . ltrim($endpoint, '/');
        } else {
            $url = $this->baseUrl . '/' . ltrim($endpoint, '/');
        }

        $client = Http::withOptions(['verify' => false])
            ->acceptJson()
            ->timeout(10);

        if ($this->authMethod === 'token') {
            $client->withToken($this->apiToken);
        } else {
            $client->withBasicAuth($this->username, $this->password);
        }

        if ($method === 'DELETE' && !empty($data)) {
            $response = $client->send('DELETE', $url, ['json' => $data]);
        } elseif ($method === 'GET') {
            $data['_t'] = time(); // Prevent caching on firewall side
            $queryString = http_build_query($data);
            $fullUrl = $queryString ? $url . '?' . $queryString : $url;
            $response = $client->get($fullUrl);
        } else {
            $response = $client->asJson()->$method($url, $data);
        }

        if ($response->successful()) {
            return $response->json();
        }

        $requestUrl = isset($fullUrl) ? $fullUrl : $url;
        throw new \Exception("API request failed ({$requestUrl}): " . $response->body(), $response->status());
    }

    /**
     * Get system status - v2 API doesn't have /system/status endpoint
     * Return basic connection test instead
     */
    /**
     * Get system status
     */
    public function getSystemStatus()
    {
        return $this->get('/status/system');
    }

    public function getSystemVersion()
    {
        return $this->get('/system/version');
    }

    public function getSystemHostname()
    {
        return $this->get('/system/hostname');
    }



    public function updateSystemHostname(array $data)
    {
        return $this->patch('/system/hostname', $data);
    }



    public function getConfigHistory()
    {
        return $this->get('/api/v2/diagnostics/config_history/revisions');
    }

    public function getSystemTimezone()
    {
        return $this->get('/system/timezone');
    }





    public function updateSystemTimezone(array $data)
    {
        return $this->patch('/system/timezone', $data);
    }

    public function getSystemDns()
    {
        return $this->get('/system/dns');
    }

    public function updateSystemDns(array $data)
    {
        return $this->patch('/system/dns', $data);
    }

    // System Advanced - Admin Access
    public function getSystemWebGui()
    {
        return $this->get('/system/webgui/settings');
    }

    public function updateSystemWebGui(array $data)
    {
        return $this->patch('/system/webgui/settings', $data);
    }

    public function getSystemSsh()
    {
        return $this->get('/services/ssh');
    }

    public function updateSystemSsh(array $data)
    {
        return $this->patch('/services/ssh', $data);
    }

    public function getSystemConsole()
    {
        return $this->get('/system/console');
    }

    public function updateSystemConsole(array $data)
    {
        return $this->patch('/system/console', $data);
    }

    // System Advanced - Firewall & NAT
    public function getSystemFirewallAdvanced()
    {
        return $this->get('/firewall/advanced_settings');
    }

    public function updateSystemFirewallAdvanced(array $data)
    {
        return $this->patch('/firewall/advanced_settings', $data);
    }

    // System Advanced - Notifications
    public function getSystemNotifications()
    {
        return $this->get('/system/notifications/email_settings');
    }

    public function updateSystemNotifications(array $data)
    {
        return $this->patch('/system/notifications/email_settings', $data);
    }

    public function getSystemNotificationsTelegram()
    {
        return $this->get('/system/notifications/telegram_settings');
    }

    public function updateSystemNotificationsTelegram(array $data)
    {
        return $this->patch('/system/notifications/telegram_settings', $data);
    }

    public function getSystemNotificationsPushover()
    {
        return $this->get('/system/notifications/pushover_settings');
    }

    public function updateSystemNotificationsPushover(array $data)
    {
        return $this->patch('/system/notifications/pushover_settings', $data);
    }

    public function getSystemNotificationsSlack()
    {
        return $this->get('/system/notifications/slack_settings');
    }

    public function updateSystemNotificationsSlack(array $data)
    {
        return $this->patch('/system/notifications/slack_settings', $data);
    }

    public function getSystemNotificationsSounds()
    {
        return $this->get('/system/notifications/sounds_settings');
    }

    public function updateSystemNotificationsSounds(array $data)
    {
        return $this->patch('/system/notifications/sounds_settings', $data);
    }

    // System Advanced - Tunables
    public function getSystemTunables()
    {
        return $this->get('/system/tunables');
    }

    public function createSystemTunable(array $data)
    {
        $response = $this->post('/system/tunable', $data);
        $this->markSubsystemDirty('sysctl');
        return $response;
    }

    public function updateSystemTunable(array $data)
    {
        $response = $this->patch('/system/tunable', $data);
        $this->markSubsystemDirty('sysctl');
        return $response;
    }

    public function deleteSystemTunable(string $id)
    {
        $response = $this->delete('/system/tunable', ['id' => $id]);
        $this->markSubsystemDirty('sysctl');
        return $response;
    }

    public function getDirtyState()
    {
        // List all files in /tmp and check for known dirty markers
        // List all files in /tmp and /var/run (pfSense uses /var/run for most dirty flags)
        $response = $this->diagnosticsCommandPrompt('ls -1 /tmp /var/run');

        if (isset($response['data']['output'])) {
            $output = $response['data']['output'];
            $files = explode("\n", $output);

            // Known dirty markers in pfSense
            $dirtyMarkers = [
                'filter_dirty',
                'sysctl.dirty',
                'interfaces_dirty',
                'vip_dirty',
                'config.dirty'
            ];

            foreach ($files as $file) {
                $file = trim($file);
                if (empty($file))
                    continue;

                if (in_array($file, $dirtyMarkers)) {
                    return true;
                }


            }
        }

        return false;
    }

    public function markSubsystemDirty(string $subsystem)
    {
        // Force the subsystem to be marked as dirty path used by pfSense
        // /var/run/{subsystem}.dirty
        return $this->diagnosticsCommandPrompt("touch /var/run/{$subsystem}.dirty");
    }

    /**
     * Generic method to apply changes for a subsystem using native pfSense PHP commands
     * and ensuring the dirty flag is cleared.
     */
    public function applySubsystemChanges(string $subsystem)
    {
        $command = "";

        switch ($subsystem) {
            case 'sysctl':
                // System Tunables
                $command = 'require_once("config.inc"); require_once("system.inc"); require_once("util.inc"); system_setup_sysctl();';
                break;
            case 'filter':
                // Firewall Rules, NAT
                $command = 'require_once("filter.inc"); filter_configure();';
                break;
            case 'interfaces':
                // Interfaces (Careful: this can cause network interruption)
                $command = 'require_once("interfaces.inc"); interface_configure();';
                break;
            default:
                throw new \Exception("Unknown subsystem for application: $subsystem");
        }

        // Wrap connection in php -r
        $phpCommand = "php -r '$command'";

        // Explicitly remove the dirty file to ensure reliable UI state
        // This is robust against cases where the php command implies a clean but doesn't remove the file in the way we expect.
        $cmd = "$phpCommand && rm /var/run/{$subsystem}.dirty";

        return $this->diagnosticsCommandPrompt($cmd);
    }

    /**
     * @deprecated Use applySubsystemChanges('sysctl')
     */
    public function applySystemTunables()
    {
        return $this->applySubsystemChanges('sysctl');
    }

    /**
     * @deprecated Use markSubsystemDirty('sysctl')
     */
    public function markSysctlDirty()
    {
        return $this->markSubsystemDirty('sysctl');
    }

    // Routing - Gateways
    public function getRoutingGateways()
    {
        return $this->get('/routing/gateways');
    }

    public function createRoutingGateway(array $data)
    {
        return $this->post('/routing/gateway', $data);
    }

    public function updateRoutingGateway(array $data)
    {
        return $this->patch('/routing/gateway', $data);
    }

    public function deleteRoutingGateway(string $id)
    {
        return $this->delete('/routing/gateway', ['id' => $id]);
    }

    // Routing - Static Routes
    public function getRoutingStaticRoutes()
    {
        return $this->get('/routing/static_routes');
    }

    public function createRoutingStaticRoute(array $data)
    {
        return $this->post('/routing/static_route', $data);
    }

    public function updateRoutingStaticRoute(array $data)
    {
        return $this->patch('/routing/static_route', $data);
    }

    public function deleteRoutingStaticRoute(string $id)
    {
        return $this->delete('/routing/static_route', ['id' => $id]);
    }

    // Routing - Gateway Groups
    public function getRoutingGatewayGroups()
    {
        return $this->get('/routing/gateway/groups');
    }

    public function createRoutingGatewayGroup(array $data)
    {
        return $this->post('/routing/gateway/group', $data);
    }

    public function updateRoutingGatewayGroup(array $data)
    {
        return $this->patch('/routing/gateway/group', $data);
    }

    public function deleteRoutingGatewayGroup(string $id)
    {
        return $this->delete('/routing/gateway/group', ['id' => $id]);
    }

    // System - Package Manager
    public function getSystemPackages()
    {
        return $this->get('/system/packages');
    }

    public function getSystemAvailablePackages()
    {
        return $this->get('/system/package/available');
    }

    public function installSystemPackage(string $name)
    {
        return $this->post('/system/package', ['name' => $name]);
    }

    public function uninstallSystemPackage(int $id)
    {
        return $this->delete('/system/package', ['id' => $id]);
    }

    // System - User Manager - Users
    public function getSystemUsers()
    {
        return $this->get('/users');
    }

    public function createSystemUser(array $data)
    {
        return $this->post('/user', $data);
    }

    public function updateSystemUser(array $data)
    {
        return $this->patch('/user', $data);
    }

    public function deleteSystemUser(string $id)
    {
        return $this->delete('/user', ['id' => $id]);
    }

    // System - User Manager - Groups
    public function getSystemGroups()
    {
        return $this->get('/user/groups');
    }

    public function createSystemGroup(array $data)
    {
        return $this->post('/user/group', $data);
    }

    public function updateSystemGroup(array $data)
    {
        return $this->patch('/user/group', $data);
    }

    public function deleteSystemGroup(string $id)
    {
        return $this->delete('/user/group', ['id' => $id]);
    }

    // System - User Manager - Auth Servers
    public function getSystemAuthServers()
    {
        return $this->get('/user/auth_servers');
    }

    public function createSystemAuthServer(array $data)
    {
        return $this->post('/user/auth_server', $data);
    }

    public function updateSystemAuthServer(array $data)
    {
        return $this->patch('/user/auth_server', $data);
    }

    public function deleteSystemAuthServer(string $id)
    {
        return $this->delete('/user/auth_server', ['id' => $id]);
    }


    /**
     * Get gateways status
     */
    public function getGateways()
    {
        return $this->get('/status/gateways');
    }

    /**
     * Get interfaces
     */
    public function getInterfaces()
    {
        return $this->get('/interfaces');
    }

    /**
     * Get interfaces status
     */
    /**
     * Get interfaces status
     */
    public function getInterfacesStatus()
    {
        return $this->get('/status/interfaces');
    }

    /**
     * Get available interfaces (ports)
     */
    public function getAvailableInterfaces()
    {
        return $this->get('/interface/available_interfaces');
    }

    /**
     * Create Interface (Assign)
     */
    public function createInterface(array $data)
    {
        return $this->post('/interface', $data);
    }

    /**
     * Delete Interface (Unassign)
     */
    public function deleteInterface(string $id)
    {
        return $this->delete('/interface', ['id' => $id]);
    }



    /**
     * Get firewall rules
     */
    public function getFirewallRules()
    {
        return $this->get('/firewall/rules');
    }

    public function createFirewallRule(array $data)
    {
        // Ensure interface is an array
        if (isset($data['interface']) && !is_array($data['interface'])) {
            $data['interface'] = [$data['interface']];
        }



        $response = $this->post('/firewall/rule', $data);
        $this->markSubsystemDirty('filter');
        return $response;
    }

    public function updateFirewallRule(int $id, array $data)
    {
        $data['id'] = $id;
        $response = $this->patch("/firewall/rule", $data);
        $this->markSubsystemDirty('filter');
        return $response;
    }

    public function deleteFirewallRule(int $id)
    {
        $response = $this->delete('/firewall/rule', ['id' => $id]);
        $this->markSubsystemDirty('filter');
        return $response;
    }

    public function updateInterface(string $id, array $data)
    {
        // The API requires PATCH for updates and expects 'id' in the body
        $data['id'] = $id;
        $response = $this->patch("/interface", $data);
        // Interface changes usually require an interface reload, but often trigger filter reloads too.
        // For now, valid pfSense behavior is to mark interfaces dirty.
        $this->markSubsystemDirty('interfaces');
        return $response;
    }

    public function applyChanges()
    {
        return $this->applySubsystemChanges('filter');
    }

    /**
     * Get services status
     */
    public function getServices()
    {
        return $this->get('/services');
    }

    /**
     * Get NAT Port Forwards
     */
    public function getNatPortForwards()
    {
        return $this->get('/firewall/nat/port_forwards');
    }

    /**
     * Create NAT Port Forward
     */
    public function createNatPortForward(array $data)
    {
        $response = $this->post('/firewall/nat/port_forward', $data);
        $this->markSubsystemDirty('filter');
        return $response;
    }

    /**
     * Update NAT Port Forward
     */
    public function updateNatPortForward(int $id, array $data)
    {
        $data['id'] = $id;
        $response = $this->patch("/firewall/nat/port_forward", $data);
        $this->markSubsystemDirty('filter');
        return $response;
    }

    /**
     * Delete NAT Port Forward
     */
    public function deleteNatPortForward(int $id)
    {
        $response = $this->delete('/firewall/nat/port_forward', ['id' => $id]);
        $this->markSubsystemDirty('filter');
        return $response;
    }

    /**
     * Get NAT Outbound Rules
     */
    public function getNatOutboundRules()
    {
        return $this->get('/firewall/nat/outbound/mappings');
    }

    /**
     * Get NAT Outbound Mode
     */
    public function getNatOutboundMode()
    {
        return $this->get('/firewall/nat/outbound/mode');
    }

    /**
     * Update NAT Outbound Mode
     */
    public function updateNatOutboundMode(string $mode)
    {
        $response = $this->patch('/firewall/nat/outbound/mode', ['mode' => $mode]);
        $this->markSubsystemDirty('filter');
        return $response;
    }

    /**
     * Create NAT Outbound Rule
     */
    public function createNatOutboundRule(array $data)
    {
        $response = $this->post('/firewall/nat/outbound/mapping', $data);
        $this->markSubsystemDirty('filter');
        return $response;
    }

    /**
     * Update NAT Outbound Rule
     */
    public function updateNatOutboundRule(int $id, array $data)
    {
        $data['id'] = $id;
        $response = $this->patch("/firewall/nat/outbound/mapping", $data);
        $this->markSubsystemDirty('filter');
        return $response;
    }

    /**
     * Delete NAT Outbound Rule
     */
    public function deleteNatOutboundRule(int $id)
    {
        $response = $this->delete("/firewall/nat/outbound/mapping?id={$id}");
        $this->markSubsystemDirty('filter');
        return $response;
    }

    /**
     * Get NAT 1:1 Rules
     */
    public function getNatOneToOneRules()
    {
        return $this->get('/firewall/nat/one_to_one/mappings');
    }

    /**
     * Create NAT 1:1 Rule
     */
    public function createNatOneToOneRule(array $data)
    {
        $response = $this->post('/firewall/nat/one_to_one/mapping', $data);
        $this->markSubsystemDirty('filter');
        return $response;
    }

    /**
     * Update NAT 1:1 Rule
     */
    public function updateNatOneToOneRule(int $id, array $data)
    {
        $data['id'] = $id;
        $response = $this->patch("/firewall/nat/one_to_one/mapping", $data);
        $this->markSubsystemDirty('filter');
        return $response;
    }

    /**
     * Delete NAT 1:1 Rule
     */
    public function deleteNatOneToOneRule(int $id)
    {
        $response = $this->delete("/firewall/nat/one_to_one/mapping?id={$id}");
        $this->markSubsystemDirty('filter');
        return $response;
    }

    /**
     * Get Firewall Aliases
     */
    public function getAliases()
    {
        return $this->get('/firewall/aliases');
    }

    /**
     * Create Firewall Alias
     */
    public function createAlias(array $data)
    {
        $response = $this->post('/firewall/alias', $data);
        $this->markSubsystemDirty('filter');
        return $response;
    }

    /**
     * Update Firewall Alias
     */
    public function updateAlias(string $id, array $data)
    {
        $data['id'] = $id;
        $response = $this->patch('/firewall/alias', $data);
        $this->markSubsystemDirty('filter');
        return $response;
    }

    /**
     * Delete Firewall Alias
     */
    public function deleteAlias(string $id)
    {
        $response = $this->delete('/firewall/alias', ['id' => $id]);
        $this->markSubsystemDirty('filter');
        return $response;
    }

    /**
     * Get Firewall Schedules
     */
    public function getSchedules()
    {
        return $this->get('/firewall/schedules');
    }

    /**
     * Create Firewall Schedule
     */
    public function createSchedule(array $data)
    {
        return $this->post('/firewall/schedule', $data);
    }

    /**
     * Update Firewall Schedule
     */
    public function updateSchedule(int $id, array $data)
    {
        $data['id'] = $id;
        return $this->patch("/firewall/schedule?id={$id}", $data);
    }

    /**
     * Delete Firewall Schedule
     */
    public function deleteSchedule(int $id)
    {
        return $this->delete("/firewall/schedule", ['id' => $id]);
    }

    /**
     * Get Traffic Shaper Limiters
     */
    public function getLimiters()
    {
        return $this->get('/firewall/traffic_shaper/limiters');
    }

    /**
     * Create Traffic Shaper Limiter
     */
    public function createLimiter(array $data)
    {
        return $this->post('/firewall/traffic_shaper/limiter', $data);
    }

    /**
     * Update Traffic Shaper Limiter
     */
    public function updateLimiter(int $id, array $data)
    {
        $data['id'] = $id;
        return $this->patch("/firewall/traffic_shaper/limiter", $data);
    }

    /**
     * Delete Traffic Shaper Limiter
     */
    public function deleteLimiter(int $id)
    {
        return $this->delete("/firewall/traffic_shaper/limiter", ['id' => $id]);
    }

    /**
     * Get Virtual IPs
     */
    public function getVirtualIps()
    {
        return $this->get('/firewall/virtual_ips');
    }

    /**
     * Get IPsec Status (SAs)
     */
    public function getIpsecStatus()
    {
        return $this->get('/status/ipsec/sas');
    }

    /**
     * Get OpenVPN Server Status
     */
    public function getOpenVpnServerStatus()
    {
        return $this->get('/status/openvpn/servers');
    }

    /**
     * Get DHCP Leases
     */
    public function getDhcpLeases()
    {
        return $this->get('/status/dhcp_server/leases');
    }



    /**
     * Get Services Status
     */
    public function getServicesStatus()
    {
        return $this->get('/status/services');
    }


    /**
     * Create Virtual IP
     */
    public function createVirtualIp(array $data)
    {
        return $this->post('/firewall/virtual_ip', $data);
    }

    /**
     * Update Virtual IP
     */
    public function updateVirtualIp(int $id, array $data)
    {
        return $this->patch("/firewall/virtual_ip?id={$id}", $data);
    }

    /**
     * Delete Virtual IP
     */
    public function deleteVirtualIp(int $id)
    {
        return $this->delete("/firewall/virtual_ip", ['id' => $id]);
    }
    /**
     * Get VLANs
     */
    public function getVlans()
    {
        return $this->get('/interface/vlans');
    }

    /**
     * Create VLAN
     */
    public function createVlan(array $data)
    {
        return $this->post('/interface/vlan', $data);
    }

    /**
     * Update VLAN
     */
    public function updateVlan(int $id, array $data)
    {
        return $this->patch("/interface/vlan?id={$id}", $data);
    }

    /**
     * Delete VLAN
     */
    public function deleteVlan(int $id)
    {
        return $this->delete("/interface/vlan", ['id' => $id]);
    }

    /**
     * Get OpenVPN Servers
     */
    public function getOpenVpnServers()
    {
        return $this->get('/vpn/openvpn/servers');
    }

    /**
     * Get OpenVPN Clients
     */
    public function getOpenVpnClients()
    {
        return $this->get('/vpn/openvpn/clients');
    }

    /**
     * Create OpenVPN Server
     */
    public function createOpenVpnServer(array $data)
    {
        return $this->post('/vpn/openvpn/server', $data);
    }

    /**
     * Create OpenVPN Client
     */
    public function createOpenVpnClient(array $data)
    {
        return $this->post('/vpn/openvpn/client', $data);
    }

    /**
     * Delete OpenVPN Server
     */
    public function deleteOpenVpnServer(int $id)
    {
        return $this->delete("/vpn/openvpn/server", ['id' => $id]);
    }

    /**
     * Get CARP Status
     */
    public function getCarpStatus()
    {
        return $this->get('/status/carp');
    }

    /**
     * Update CARP Status
     */
    public function updateCarpStatus(array $data)
    {
        return $this->patch('/status/carp', $data);
    }

    /**
     * Delete OpenVPN Client
     */
    public function deleteOpenVpnClient(int $id)
    {
        return $this->delete("/vpn/openvpn/client", ['id' => $id]);
    }

    /**
     * Get Certificate Authorities
     */
    public function getCertificateAuthorities()
    {
        return $this->get('/system/certificate_authorities');
    }

    /**
     * Get Certificates
     */
    public function getCertificates()
    {
        return $this->get('/system/certificates');
    }

    /**
     * Create Certificate Authority (Import)
     */
    public function createCertificateAuthority(array $data)
    {
        return $this->post('/system/certificate_authority', $data);
    }

    /**
     * Generate Certificate Authority (Internal)
     */
    public function generateCertificateAuthority(array $data)
    {
        return $this->post('/system/certificate_authority/generate', $data);
    }

    /**
     * Create Certificate (Import)
     */
    public function createCertificate(array $data)
    {
        return $this->post('/system/certificate', $data);
    }

    /**
     * Get IPsec Phase 1s
     */
    public function getIpsecPhase1s()
    {
        return $this->get('/vpn/ipsec/phase1s', ['limit' => 0]);
    }

    /**
     * Get IPsec Phase 2s
     */
    public function getIpsecPhase2s()
    {
        return $this->get('/vpn/ipsec/phase2s', ['limit' => 0]);
    }

    /**
     * Create IPsec Phase 1
     */
    public function createIpsecPhase1(array $data)
    {
        return $this->post('/vpn/ipsec/phase1', $data);
    }

    /**
     * Delete IPsec Phase 1
     */
    public function deleteIpsecPhase1(int $id)
    {
        return $this->delete("/vpn/ipsec/phase1", ['ikeid' => $id]);
    }

    /**
     * Create IPsec Phase 2
     */
    public function createIpsecPhase2(array $data)
    {
        return $this->post('/vpn/ipsec/phase2', $data);
    }

    /**
     * Delete IPsec Phase 2
     */
    public function deleteIpsecPhase2(string $id)
    {
        return $this->delete("/vpn/ipsec/phase2", ['uniqid' => $id]);
    }

    /**
     * Get DHCP Relay
     */
    public function getDhcpRelay()
    {
        return $this->get('/services/dhcp_relay');
    }

    /**
     * Update DHCP Relay
     */
    public function updateDhcpRelay(array $data)
    {
        return $this->patch('/services/dhcp_relay', $data);
    }



    /**
     * Generate Certificate (Internal)
     */
    public function generateCertificate(array $data)
    {
        return $this->post('/system/certificate/generate', $data);
    }

    /**
     * Delete Certificate Authority
     */
    public function deleteCertificateAuthority(string $id)
    {
        return $this->delete("/system/certificate_authority", ['id' => $id]);
    }

    /**
     * Delete Certificate
     */
    public function deleteCertificate(string $id)
    {
        return $this->delete("/system/certificate", ['id' => $id]);
    }

    /**
     * Execute a shell command
     */
    public function commandPrompt(string $command)
    {
        return $this->post('/diagnostics/command_prompt', ['command' => $command]);
    }

    /**
     * Get DNS Resolver Configuration
     */
    public function getDnsResolver()
    {
        return $this->get('/services/dns_resolver/settings');
    }

    /**
     * Update DNS Resolver Configuration
     */
    public function updateDnsResolver(array $data)
    {
        return $this->patch('/services/dns_resolver/settings', $data);
    }

    /**
     * Get DNS Resolver Host Overrides
     */
    public function getDnsResolverHostOverrides()
    {
        return $this->get('/services/dns_resolver/host_overrides');
    }

    /**
     * Create DNS Resolver Host Override
     */
    public function createDnsResolverHostOverride(array $data)
    {
        return $this->post('/services/dns_resolver/host_override', $data);
    }

    /**
     * Get DHCP Server Configuration
     */
    public function getDhcpServer(string $interface)
    {
        return $this->get('/services/dhcp_server', ['id' => $interface]);
    }

    /**
     * Update DHCP Server Configuration
     */
    public function updateDhcpServer(array $data)
    {
        return $this->patch('/services/dhcp_server', $data);
    }

    public function getWireGuardTunnels()
    {
        return $this->get('/vpn/wireguard/tunnels');
    }

    public function getWireGuardPeers()
    {
        return $this->get('/vpn/wireguard/peers');
    }

    /**
     * Get ARP Table
     */
    public function getArpTable()
    {
        return $this->get('/diagnostics/arp_table');
    }

    /**
     * Get Firewall States
     */
    public function getFirewallStates()
    {
        return $this->get('/firewall/states');
    }

    /**
     * Get System Logs
     *
     * @param string $type system, firewall, dhcp, auth, ipsec, pptp, openvpn, ntp
     */
    public function getSystemLogs(string $type = 'system')
    {
        return $this->get("/status/logs/{$type}");
    }

    /**
     * Diagnostics: Command Prompt
     */
    public function diagnosticsCommandPrompt(string $command)
    {
        return $this->post('/diagnostics/command_prompt', ['command' => $command]);
    }

    /**
     * Diagnostics: Reboot
     */
    public function diagnosticsReboot()
    {
        return $this->post('/diagnostics/reboot');
    }

    /**
     * Diagnostics: Halt System
     */
    public function diagnosticsHalt()
    {
        return $this->post('/diagnostics/halt_system');
    }

    /**
     * Diagnostics: Get Tables
     */
    public function getDiagnosticsTables()
    {
        return $this->get('/diagnostics/tables');
    }

    /**
     * Diagnostics: SMART Status
     */
    public function getSmartStatus()
    {
        return $this->get('/diagnostics/smart_status');
    }

    /**
     * Diagnostics: Sockets
     */
    public function getSockets()
    {
        return $this->get('/diagnostics/sockets');
    }

    /**
     * Diagnostics: States Summary
     */
    public function getStatesSummary()
    {
        return $this->get('/firewall/states/summary');
    }

    /**
     * Diagnostics: Get Table Content
     */
    public function getDiagnosticsTable(string $table)
    {
        return $this->get('/diagnostics/table', ['id' => $table]);
    }

    /**
     * Get Bind Zones
     */
    public function getBindZones()
    {
        return $this->get('/services/bind/zones');
    }

    /**
     * Get Bind Settings
     */
    public function getBindSettings()
    {
        return $this->get('/services/bind/settings');
    }

    /**
     * Get Certificate Revocation Lists (CRLs)
     */
    public function getCRLs()
    {
        return $this->get('/system/crls');
    }

    /**
     * Get a specific CRL
     */
    public function getCRL($id)
    {
        return $this->get('/system/crl', ['id' => $id]);
    }

    /**
     * Create/Import CRL
     */
    public function createCRL(array $data)
    {
        return $this->post('/system/crl', $data);
    }

    /**
     * Delete CRL
     */
    public function deleteCRL($id)
    {
        return $this->delete('/system/crl', ['id' => $id]);
    }

    /**
     * Update CRL (Add/Remove Certs)
     */
    public function updateCRL($id, array $data)
    {
        // Assuming PATCH or PUT for update, typically to add revoked certs
        // Using PATCH as it's a modification
        $data['id'] = $id;
        return $this->patch('/system/crl', $data);
    }

    /**
     * Get Bridges
     */
    public function getBridges()
    {
        return $this->get('/interfaces/bridge');
    }

    /**
     * Get specific Bridge
     */
    public function getBridge($id)
    {
        return $this->get('/interfaces/bridge', ['id' => $id]);
    }

    /**
     * Create Bridge
     */
    public function createBridge(array $data)
    {
        return $this->post('/interfaces/bridge', $data);
    }

    /**
     * Update Bridge
     */
    public function updateBridge($id, array $data)
    {
        $data['id'] = $id;
        return $this->patch('/interfaces/bridge', $data);
    }

    /**
     * Delete Bridge
     */
    public function deleteBridge($id)
    {
        return $this->delete('/interfaces/bridge', ['id' => $id]);
    }

    /*
    |--------------------------------------------------------------------------
    | Interfaces: LAGGs
    |--------------------------------------------------------------------------
    */

    public function getLaggs()
    {
        return $this->get('/interfaces/lagg');
    }

    public function getLagg($id)
    {
        return $this->get('/interfaces/lagg', ['id' => $id]);
    }

    public function createLagg(array $data)
    {
        return $this->post('/interfaces/lagg', $data);
    }

    public function updateLagg($id, array $data)
    {
        $data['id'] = $id;
        return $this->patch('/interfaces/lagg', $data);
    }

    public function deleteLagg($id)
    {
        return $this->delete('/interfaces/lagg', ['id' => $id]);
    }

    /*
    |--------------------------------------------------------------------------
    | Interfaces: GRE
    |--------------------------------------------------------------------------
    */

    public function getGres()
    {
        return $this->get('/interfaces/gre');
    }

    public function getGre($id)
    {
        return $this->get('/interfaces/gre', ['id' => $id]);
    }

    public function createGre(array $data)
    {
        return $this->post('/interfaces/gre', $data);
    }

    public function updateGre($id, array $data)
    {
        $data['id'] = $id;
        return $this->patch('/interfaces/gre', $data);
    }

    public function deleteGre($id)
    {
        return $this->delete('/interfaces/gre', ['id' => $id]);
    }

    /*
    |--------------------------------------------------------------------------
    | Interfaces: Wireless
    |--------------------------------------------------------------------------
    */

    public function getWireless()
    {
        return $this->get('/interfaces/wireless');
    }

    public function getWirelessDevice($id)
    {
        return $this->get('/interfaces/wireless', ['id' => $id]);
    }

    public function createWireless(array $data)
    {
        return $this->post('/interfaces/wireless', $data);
    }

    public function updateWireless($id, array $data)
    {
        $data['id'] = $id;
        return $this->patch('/interfaces/wireless', $data);
    }

    public function deleteWireless($id)
    {
        return $this->delete('/interfaces/wireless', ['id' => $id]);
    }

    /*
    |--------------------------------------------------------------------------
    | Diagnostics: Backup/Restore
    |--------------------------------------------------------------------------
    */

    public function backupConfiguration()
    {
        return $this->get('/api/v1/diagnostics/backup');
    }

    public function restoreConfiguration(array $data)
    {
        return $this->post('/api/v1/diagnostics/restore', $data);
    }

    /*
    |--------------------------------------------------------------------------
    | Diagnostics: Packet Capture
    |--------------------------------------------------------------------------
    */

    public function getPacketCapture()
    {
        return $this->get('/diagnostics/packet_capture');
    }

    public function startPacketCapture(array $data)
    {
        return $this->post('/diagnostics/packet_capture', $data);
    }

    public function stopPacketCapture()
    {
        return $this->post('/diagnostics/packet_capture/stop');
    }

    /*
    |--------------------------------------------------------------------------
    | Diagnostics: Test Port
    |--------------------------------------------------------------------------
    */

    public function testPort(array $data)
    {
        return $this->post('/diagnostics/test_port', $data);
    }

    /*
    |--------------------------------------------------------------------------
    | Services: NTP
    |--------------------------------------------------------------------------
    */
    public function getNtp()
    {
        return $this->get('/api/v1/services/ntp');
    }

    public function updateNtp(array $data)
    {
        return $this->put('/api/v1/services/ntp', $data);
    }

    /*
    |--------------------------------------------------------------------------
    | Services: SNMP
    |--------------------------------------------------------------------------
    */
    public function getSnmp()
    {
        return $this->get('/api/v1/services/snmp');
    }

    public function updateSnmp(array $data)
    {
        return $this->put('/api/v1/services/snmp', $data);
    }

    /*
    |--------------------------------------------------------------------------
    | Services: Captive Portal
    |--------------------------------------------------------------------------
    */
    public function getCaptivePortalZones()
    {
        return $this->get('/api/v1/services/captiveportal');
    }

    /*
    |--------------------------------------------------------------------------
    | Services: UPnP
    |--------------------------------------------------------------------------
    */
    public function getUpnp()
    {
        return $this->get('/api/v1/services/upnp');
    }

    public function updateUpnp(array $data)
    {
        return $this->put('/api/v1/services/upnp', $data);
    }

    /**
     * Get REST API Version
     */
    public function getApiVersion()
    {
        // pkg info -E pfSense-pkg-RESTAPI returns "pfSense-pkg-RESTAPI-1.0.0" (example)
        // or -v for just version if supported, but simple info is safer.
        // pkg query %v pfSense-pkg-RESTAPI is best for just version.
        $command = 'pkg query %v pfSense-pkg-RESTAPI';
        $response = $this->diagnosticsCommandPrompt($command);

        if (isset($response['data']['output'])) {
            // Format version: 2.6_5 -> 2.6.5
            $response['data']['output'] = str_replace('_', '.', $response['data']['output']);
        }

        return $response;
    }

    /**
     * Refreshes the full system status by querying multiple endpoints.
     */
    public function refreshSystemStatus()
    {
        $staticCacheKey = 'firewall_static_info_' . $this->firewall->id;
        $staticInfo = [];

        // 1. Try Live Static Fetch
        try {
            $versionInfo = $this->getSystemVersion();
            if (isset($versionInfo['data'])) {
                $staticInfo = array_merge($staticInfo, $versionInfo['data']);
            }

            try {
                $apiVersionResponse = $this->getApiVersion();
                $apiVersion = $apiVersionResponse['data']['output'] ?? 'Unknown';
                $staticInfo['api_version'] = trim($apiVersion);
            } catch (\Exception $e) {
                $staticInfo['api_version'] = 'N/A';
            }

            // Version Comparison Logic
            $latestVersion = '2.7.2-RELEASE';
            $currentVersion = $staticInfo['product_version'] ?? $staticInfo['version'] ?? null;

            if ($currentVersion) {
                $vCurrent = preg_replace('/[^0-9.]/', '', $currentVersion);
                $vLatest = preg_replace('/[^0-9.]/', '', $latestVersion);

                if (version_compare($vCurrent, $vLatest, '<')) {
                    $staticInfo['update_available'] = true;
                    $staticInfo['latest_available_version'] = $latestVersion;
                } else {
                    $staticInfo['update_available'] = false;
                }
            }

            // API Version Check
            $latestApiVersion = '2.6.9';
            if (isset($staticInfo['api_version']) && $staticInfo['api_version'] !== 'N/A' && $staticInfo['api_version'] !== 'Unknown') {
                $vApiCurrent = preg_replace('/[^0-9.]/', '', $staticInfo['api_version']);
                $vApiLatest = preg_replace('/[^0-9.]/', '', $latestApiVersion);

                if ($vApiCurrent) {
                    if (version_compare($vApiCurrent, $vApiLatest, '<')) {
                        $staticInfo['api_update_available'] = true;
                    } else {
                        $staticInfo['api_update_available'] = false;
                    }
                }
            }

            Cache::put($staticCacheKey, $staticInfo, now()->addDay());

        } catch (\Exception $e) {
            $staticInfo = Cache::get($staticCacheKey, []);
        }

        // 2. Try Live Dynamic Fetch
        $dynamicStatus = $this->getSystemStatus(); // Throws exception if fails, which is handled by caller

        try {
            $interfaces = $this->getInterfacesStatus();
            $dynamicStatus['interfaces'] = $interfaces['data'] ?? [];
        } catch (\Exception $e) {
            $dynamicStatus['interfaces'] = [];
        }

        try {
            $dns = $this->getSystemDns();
            if (isset($dns['data']['dns'])) {
                $dynamicStatus['data']['dns_servers'] = $dns['data']['dns'];
            } elseif (isset($dns['data']['dns_server'])) {
                $dynamicStatus['data']['dns_servers'] = $dns['data']['dns_server'];
            } elseif (isset($dns['data']['dnsserver'])) {
                $dynamicStatus['data']['dns_servers'] = $dns['data']['dnsserver'];
            } else {
                $dynamicStatus['data']['dns_servers'] = [];
            }
        } catch (\Exception $e) {
        }

        try {
            $history = $this->getConfigHistory();
            $revisions = $history['data'] ?? $history ?? [];
            if (!empty($revisions) && is_array($revisions)) {
                $latest = reset($revisions);
                if (isset($latest['time'])) {
                    $dynamicStatus['data']['last_config_change'] = date('Y-m-d H:i:s T', $latest['time']);
                    $dynamicStatus['data']['last_config_change_ts'] = $latest['time'];
                } elseif (isset($latest['date'])) {
                    $dynamicStatus['data']['last_config_change'] = $latest['date'];
                }
            }
        } catch (\Exception $e) {
        }

        try {
            // Optimization: Skip packages for status checks to improve performance
            // $packages = $this->getSystemPackages();
            // ...
            $dynamicStatus['data']['installed_packages_count'] = 'N/A';
        } catch (\Exception $e) {
            $dynamicStatus['data']['installed_packages_count'] = 'N/A';
        }

        try {
            $statusData = [];
            $rawStatus = $this->getGateways();
            if (isset($rawStatus['data']['gateway']) && is_array($rawStatus['data']['gateway'])) {
                $statusData = $rawStatus['data']['gateway'];
            } elseif (isset($rawStatus['data']) && is_array($rawStatus['data'])) {
                $statusData = $rawStatus['data'];
            }

            $configData = [];
            $rawConfig = $this->getRoutingGateways();
            if (isset($rawConfig['data']['gateway']) && is_array($rawConfig['data']['gateway'])) {
                $configData = $rawConfig['data']['gateway'];
            } elseif (isset($rawConfig['data']) && is_array($rawConfig['data'])) {
                $configData = $rawConfig['data'];
            }

            // Fix for Issue #38: Normalize single gateway response to list
            if (!empty($statusData) && array_keys($statusData) !== range(0, count($statusData) - 1)) {
                $statusData = [$statusData];
            }

            if (!empty($configData) && array_keys($configData) !== range(0, count($configData) - 1)) {
                $configData = [$configData];
            }

            foreach ($statusData as &$gateway) {
                $config = collect($configData)->firstWhere('name', $gateway['name']);
                if ($config) {
                    $gateway['descr'] = $config['descr'] ?? '';
                }
            }
            unset($gateway);

            $dynamicStatus['gateways'] = $statusData;

        } catch (\Exception $e) {
            $dynamicStatus['gateways'] = [];
        }

        if (isset($dynamicStatus['data']) && is_array($dynamicStatus['data'])) {
            $flatData = array_merge($dynamicStatus['data'], $staticInfo);
            unset($dynamicStatus['data']);
            $dynamicStatus = array_merge($dynamicStatus, $flatData);
        } else {
            $dynamicStatus = array_merge($dynamicStatus ?? [], $staticInfo);
        }

        // Deep flatten: Look for nested 'stats' or 'system' objects which are common in some API versions
        foreach (['stats', 'system', 'status', 'usage', 'metrics'] as $key) {
            if (isset($dynamicStatus[$key]) && is_array($dynamicStatus[$key]) && !empty($dynamicStatus[$key])) {
                // Only merge keys that don't exist yet to avoid overwriting top-level successes
                foreach ($dynamicStatus[$key] as $k => $v) {
                    if (!isset($dynamicStatus[$k])) {
                        $dynamicStatus[$k] = $v;
                    }
                }
            }
        }

        // Standardize common metrics if they exist under different names or nested forms
        $mappings = [
            'cpu_usage' => ['cpu', 'cpu_load', 'load', 'cpu_used_percent'],
            'mem_usage' => ['memory', 'mem_used_percent', 'memory_usage', 'mem_usage_percent'],
            'swap_usage' => ['swap', 'swap_used_percent', 'swap_percentage', 'memory_swap_percentage', 'swap_percent', 'swap_p', 'swapused_percent'],
            'disk_usage' => ['disk', 'disk_used_percent', 'disk_usage_percent', 'disk_used']
        ];

        foreach ($mappings as $target => $sources) {
            if (!isset($dynamicStatus[$target]) || $dynamicStatus[$target] === null || $dynamicStatus[$target] === 0 || $dynamicStatus[$target] === '0') {
                foreach ($sources as $source) {
                    if (isset($dynamicStatus[$source]) && $dynamicStatus[$source] !== null && $dynamicStatus[$source] !== '') {
                        $dynamicStatus[$target] = $dynamicStatus[$source];
                        if ($dynamicStatus[$target] != 0)
                            break; // If we found a non-zero value, move to next target
                    }
                }
            }
        }

        // Handle string-based percentages (e.g., "5.2%")
        foreach (['cpu_usage', 'mem_usage', 'swap_usage', 'disk_usage'] as $key) {
            if (isset($dynamicStatus[$key]) && is_string($dynamicStatus[$key])) {
                $dynamicStatus[$key] = floatval(preg_replace('/[^0-9.]/', '', $dynamicStatus[$key]));
            }
        }

        // Handle swap objects if present (common in some API forks)
        if (!isset($dynamicStatus['swap_usage']) || $dynamicStatus['swap_usage'] == 0) {
            if (isset($dynamicStatus['swap']) && is_array($dynamicStatus['swap'])) {
                $swap = $dynamicStatus['swap'];
                if (isset($swap['used']) && isset($swap['total']) && $swap['total'] > 0) {
                    $dynamicStatus['swap_usage'] = round(($swap['used'] / $swap['total']) * 100, 1);
                }
            }
        }

        if (isset($dynamicStatus['api_version'])) {
            // Already set from staticInfo or flattened data
        } elseif (isset($staticInfo['api_version'])) {
            $dynamicStatus['api_version'] = $staticInfo['api_version'];
        }

        return $dynamicStatus;
    }
}
