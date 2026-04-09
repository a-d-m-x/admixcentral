<?php

namespace App\Http\Controllers;

use App\Models\Firewall;
use App\Services\PfSenseApiService;
use Illuminate\Http\Request;

class InterfaceController extends Controller
{
    public function index(Firewall $firewall)
    {
        try {
            $api = new PfSenseApiService($firewall);
            $interfacesConfig = $api->getInterfaces();
            $interfacesStatus = $api->getInterfacesStatus();

            // Merge status into config
            $interfaces = $interfacesConfig;
            if (isset($interfaces['data']) && isset($interfacesStatus['data'])) {
                foreach ($interfaces['data'] as &$interface) {
                    // Find matching status by interface name (id)
                    // The status array is a list, we need to find the one where 'name' matches 'id' (e.g. 'wan')
                    // OR 'name' matches 'descr' (e.g. 'WAN')?
                    // Let's look at the data structure from previous steps.
                    // Config: [{'id' => 'wan', ...}, {'id' => 'lan', ...}]
                    // Status: [{'name' => 'wan', ...}, {'name' => 'lan', ...}]

                    $status = collect($interfacesStatus['data'])->firstWhere('name', $interface['id']);
                    if ($status) {
                        $interface['status_data'] = $status;
                        // Override/Set specific fields for display
                        $interface['ipaddr'] = $status['ipaddr'] ?? $interface['ipaddr'];
                        $interface['macaddr'] = $status['macaddr'] ?? null;
                        $interface['hwif'] = $status['hwif'] ?? null;
                        $interface['status'] = $status['status'] ?? null;
                    }
                }
            }

            return view('interfaces.index', compact('firewall', 'interfaces'));
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to fetch interfaces: ' . $e->getMessage());
        }
    }

    public function show(Firewall $firewall, string $interfaceId)
    {
        try {
            $api = new PfSenseApiService($firewall);
            $allInterfaces = $api->getInterfaces();

            // Find the specific interface
            $interface = collect($allInterfaces['data'] ?? [])->firstWhere('id', $interfaceId)
                ?? collect($allInterfaces['data'] ?? [])->firstWhere('if', $interfaceId);

            if (!$interface) {
                return back()->with('error', 'Interface not found');
            }

            return view('interfaces.show', compact('firewall', 'interface', 'interfaceId'));
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to fetch interface details: ' . $e->getMessage());
        }
    }
    public function edit(Firewall $firewall, string $interfaceId)
    {
        try {
            $api = new PfSenseApiService($firewall);
            $allInterfaces = $api->getInterfaces();

            // Find the specific interface
            $interface = collect($allInterfaces['data'] ?? [])->firstWhere('id', $interfaceId)
                ?? collect($allInterfaces['data'] ?? [])->firstWhere('if', $interfaceId);

            if (!$interface) {
                return back()->with('error', 'Interface not found');
            }

            // Normalize IPv4 type — pfSense may omit 'type' for static interfaces
            // or return 'staticv4'. Derive from ipaddr when type is absent/unknown.
            $ipv4Type = $interface['type'] ?? '';
            if ($ipv4Type === 'staticv4') {
                $ipv4Type = 'static';
            } elseif (empty($ipv4Type)) {
                $ipaddr = $interface['ipaddr'] ?? '';
                if ($ipaddr === 'dhcp')       $ipv4Type = 'dhcp';
                elseif ($ipaddr === 'pppoe')  $ipv4Type = 'pppoe';
                elseif ($ipaddr === 'none')   $ipv4Type = 'none';
                elseif (filter_var($ipaddr, FILTER_VALIDATE_IP)) $ipv4Type = 'static';
                else                          $ipv4Type = 'dhcp'; // safe fallback
            }
            $interface['type'] = $ipv4Type;

            // Normalize IPv6 type — same logic
            $ipv6Type = $interface['type6'] ?? '';
            if (empty($ipv6Type)) {
                $ipaddrv6 = $interface['ipaddrv6'] ?? '';
                if ($ipaddrv6 === 'dhcp6')   $ipv6Type = 'dhcp6';
                elseif ($ipaddrv6 === 'slaac') $ipv6Type = 'slaac';
                elseif ($ipaddrv6 === 'none')  $ipv6Type = 'none';
                elseif (filter_var($ipaddrv6, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) $ipv6Type = 'static6';
                else                           $ipv6Type = 'dhcp6';
            }
            $interface['type6'] = $ipv6Type;

            return view('interfaces.edit', compact('firewall', 'interface', 'interfaceId'));
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to fetch interface details: ' . $e->getMessage());
        }
    }

    public function update(Request $request, Firewall $firewall, string $interfaceId)
    {
        // Handle checkboxes before validation
        $request->merge([
            'enable' => $request->has('enable'),
            'blockpriv' => $request->has('blockpriv'),
            'blockbogon' => $request->has('blockbogon'),
            'dhcp6usev4parent' => $request->has('dhcp6usev4parent'),
            'dhcp6onlyrequestprefix' => $request->has('dhcp6onlyrequestprefix'),
            'dhcp6sendprefixhint' => $request->has('dhcp6sendprefixhint'),
            'dhcp6waitra' => $request->has('dhcp6waitra'),
            'pppoe_dialondemand' => $request->has('pppoe_dialondemand'),
        ]);

        // Cast integer fields and handle empty strings
        if ($request->filled('subnet')) {
            $request->merge(['subnet' => (int) $request->input('subnet')]);
        } else {
            $request->merge(['subnet' => null]);
        }

        if ($request->filled('subnetv6')) {
            $request->merge(['subnetv6' => (int) $request->input('subnetv6')]);
        } else {
            $request->merge(['subnetv6' => null]);
        }

        $data = $request->validate([
            'descr' => 'nullable|string|max:255',
            'enable' => 'nullable|boolean',
            'type' => 'nullable|string',
            'type6' => 'nullable|string',
            'mac' => 'nullable|string',
            'mtu' => 'nullable|integer',
            'mss' => 'nullable|integer',
            'media' => 'nullable|string',
            'ipaddr' => 'nullable|string',
            'subnet' => 'nullable|integer|min:1|max:32',
            'gateway' => 'nullable|string',
            'ipaddrv6' => 'nullable|string',
            'subnetv6' => 'nullable|integer|min:1|max:128',
            'gatewayv6' => 'nullable|string',
            'blockpriv' => 'nullable|boolean',
            'blockbogon' => 'nullable|boolean',
            // DHCP Client Config
            'dhcphostname' => 'nullable|string',
            'alias-address' => 'nullable|string',
            'alias-subnet' => 'nullable|integer',
            'dhcprejectfrom' => 'nullable|string',
            // DHCP6 Client Config
            'dhcp6usev4parent' => 'nullable|boolean',
            'dhcp6onlyrequestprefix' => 'nullable|boolean',
            'dhcp6delegationsize' => 'nullable|integer',
            'dhcp6sendprefixhint' => 'nullable|boolean',
            'dhcp6waitra' => 'nullable|boolean',
            // PPPoE Config
            'pppoe_username' => 'nullable|string',
            'pppoe_password' => 'nullable|string',
            'pppoe_service' => 'nullable|string',
            'pppoe_dialondemand' => 'nullable|boolean',
            'pppoe_idletimeout' => 'nullable|integer',
            'pppoe_periodic_reset' => 'nullable|string',
        ]);

        // API requires the 'if' field to identify the interface.
        // We expect this to be passed as a hidden input from the form, 
        // but for security we should probably verify it matches the route param's underlying interface.
        // For now, we'll trust the hidden input or derive it if missing.
        if (!$request->has('if')) {
            // Fallback: try to find it again (expensive but safe)
            try {
                $api = new PfSenseApiService($firewall);
                $allInterfaces = $api->getInterfaces();
                $interface = collect($allInterfaces['data'] ?? [])->firstWhere('id', $interfaceId)
                    ?? collect($allInterfaces['data'] ?? [])->firstWhere('if', $interfaceId);
                $data['if'] = $interface['if'] ?? $interfaceId;
            } catch (\Exception $e) {
                $data['if'] = $interfaceId; // Best effort
            }
        } else {
            $data['if'] = $request->input('if');
        }

        // API requires subnetv6 to be an integer even if type6 is not static
        $sv6 = $data['subnetv6'] ?? null;
        if (empty($sv6)) {
            $data['subnetv6'] = 64;
        } else {
            $data['subnetv6'] = (int) $sv6;
        }

        \Illuminate\Support\Facades\Log::info('Interface update payload:', $data);

        // Remove null subnet fields to avoid API validation errors
        if (array_key_exists('subnet', $data) && is_null($data['subnet'])) {
            unset($data['subnet']);
        }
        if (array_key_exists('subnetv6', $data) && is_null($data['subnetv6'])) {
            unset($data['subnetv6']);
        }

        try {
            $api = new PfSenseApiService($firewall);
            $api->updateInterface($interfaceId, $data);
            $firewall->update(['is_dirty' => true]);

            return redirect()->route('firewall.interfaces.index', $firewall)
                ->with('success', 'Interface updated successfully. Please apply changes.');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Exception caught in update: ' . $e->getMessage());
            return back()->with('error', 'Failed to update interface: ' . $e->getMessage())
                ->withInput();
        }
    }
}
