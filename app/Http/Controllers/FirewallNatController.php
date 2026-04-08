<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Firewall;
use App\Services\PfSenseApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FirewallNatController extends Controller
{
    // Port Forward
    public function portForward(Firewall $firewall)
    {
        try {
            $api = new PfSenseApiService($firewall);
            $response = $api->getNatPortForwards();

            $rules = $response['data'] ?? [];

            // Get interfaces for dropdown
            $interfacesResponse = $api->getInterfaces();
            $interfaces = $interfacesResponse['data'] ?? [];

            // Get Aliases for highlighting
            $aliasesResponse = $api->getAliases();
            $aliasesData = $aliasesResponse['data'] ?? [];
            $aliasMap = collect($aliasesData)->mapWithKeys(function ($item, $key) {
                return [
                    $item['name'] => [
                        'type' => $item['type'] ?? 'unknown',
                        'descr' => $item['descr'] ?? '',
                        'id' => $item['id'] ?? $key
                    ]
                ];
            })->toArray();

            return view('firewall.nat.port-forward', compact('firewall', 'rules', 'interfaces', 'aliasMap'));
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to fetch port forward rules: ' . $e->getMessage());
        }
    }

    public function storePortForward(Request $request, Firewall $firewall)
    {
        Log::info("storePortForward called", $request->all());

        $validated = $request->validate([
            'interface' => 'required|string',
            'ipprotocol' => 'nullable|in:inet,inet6',
            'protocol' => 'required|in:tcp,udp,tcp/udp,icmp,esp,ah,gre,ipv6,igmp,pim,ospf,sctp,any',
            'src_type' => 'nullable|string',
            'src' => 'nullable|string',
            'srcport' => 'nullable|string',
            'dst_type' => 'nullable|string',
            'dst' => 'nullable|string',
            'dstport' => 'required|string',
            'target' => 'required|string',
            'local_port' => 'required|string',
            'descr' => 'nullable|string',
            'disabled' => 'nullable|boolean',
            'natreflection' => 'nullable|in:enable,disable,purenat,system-default',
            'associated_rule_id' => 'nullable|string',
        ]);

        $interfaceValue = $validated['interface'];
        $natreflection = ($validated['natreflection'] ?? 'system-default') === 'system-default'
            ? null
            : $validated['natreflection'];

        // Build source from type + address + invert
        $srcType = $validated['src_type'] ?? 'any';
        if ($srcType === 'any') {
            $source = 'any';
        } elseif (in_array($srcType, ['address', 'network'])) {
            $addr = trim($validated['src'] ?? '');
            $source = $addr ?: 'any';
        } else {
            $source = $srcType; // fixed: 'wan:ip', 'lan:ip', 'wan', 'lan', etc.
        }
        if ($source !== 'any' && $request->boolean('src_not')) {
            $source = '!' . $source;
        }

        // Build destination from type + address + invert
        $dstType = $validated['dst_type'] ?? 'wan:ip';
        if ($dstType === 'any') {
            $destination = 'any';
        } elseif (in_array($dstType, ['address', 'network'])) {
            $addr = trim($validated['dst'] ?? '');
            $destination = $addr ?: 'any';
        } else {
            $destination = $dstType;
        }
        if ($destination !== 'any' && $request->boolean('dst_not')) {
            $destination = '!' . $destination;
        }

        $data = [
            'interface' => $interfaceValue,
            'protocol' => $validated['protocol'],
            'ipprotocol' => $validated['ipprotocol'] ?? 'inet',
            'source' => $source,
            'destination' => $destination,
            'destination_port' => $validated['dstport'],
            'dstport' => $validated['dstport'],
            'target' => $validated['target'],
            'local_port' => $validated['local_port'],
            'local-port' => $validated['local_port'],
            'descr' => $validated['descr'] ?? '',
        ];

        if ($natreflection !== null) {
            $data['natreflection'] = $natreflection;
        }


        if (isset($validated['associated_rule_id'])) {
            $val = $validated['associated_rule_id'];
            if ($val === 'linked') {
                $data['associated_rule_id'] = 'new';
            } elseif ($val === 'pass') {
                $data['associated_rule_id'] = 'pass';
            } elseif ($val === '') {
                $data['associated_rule_id'] = '';
            } else {
                $data['associated_rule_id'] = $val;
            }
        }

        if (isset($validated['srcport']) && $validated['srcport'] !== 'any') {
            $data['source_port'] = $validated['srcport'];
            $data['srcport'] = $validated['srcport'];
        }

        if ($request->has('disabled') && $request->boolean('disabled')) {
            $data['disabled'] = true;
        }

        try {
            $api = new PfSenseApiService($firewall);
            $response = $api->createNatPortForward($data);

            // Fix pfSense API issue where dynamically generated filter rules lack destination ports
            if (($data['associated_rule_id'] ?? '') === 'new' && isset($response['data']['associated_rule_id'])) {
                $trackerId = $response['data']['associated_rule_id'];
                $rules = $api->getFirewallRules()['data'] ?? [];
                foreach ($rules as $rule) {
                    if (($rule['associated_rule_id'] ?? '') === $trackerId) {
                        try {
                            $api->updateFirewallRule($rule['id'], [
                                'dstport' => $validated['local_port'],
                            ]);
                        } catch (\Exception $e) {
                            Log::error('Failed to update associated filter rule port: ' . $e->getMessage());
                        }
                        break;
                    }
                }
            }

            $firewall->update(['is_dirty' => true]);

            return redirect()->route('firewall.nat.port-forward', $firewall)
                ->with('success', 'Port forward rule created successfully. Please apply changes.');
        } catch (\Exception $e) {
            Log::error('PfSense API Error in storePortForward: ' . $e->getMessage());
            return back()->withInput()->with('error', 'Failed to create port forward: ' . $e->getMessage());
        }
    }

    public function updatePortForward(Request $request, Firewall $firewall, $id)
    {
        $validated = $request->validate([
            'interface' => 'required|string',
            'ipprotocol' => 'nullable|in:inet,inet6',
            'protocol' => 'required|in:tcp,udp,tcp/udp,icmp,esp,ah,gre,ipv6,igmp,pim,ospf,sctp,any',
            'src_type' => 'nullable|string',
            'src' => 'nullable|string',
            'srcport' => 'nullable|string',
            'dst_type' => 'nullable|string',
            'dst' => 'nullable|string',
            'dstport' => 'required|string',
            'target' => 'required|string',
            'local_port' => 'required|string',
            'descr' => 'nullable|string',
            'disabled' => 'nullable|boolean',
            'natreflection' => 'nullable|in:enable,disable,purenat,system-default',
            'associated_rule_id' => 'nullable|string',
        ]);

        $interfaceValue = $validated['interface'];
        $natreflection = ($validated['natreflection'] ?? 'system-default') === 'system-default'
            ? null
            : $validated['natreflection'];

        // Build source from type + address + invert
        $srcType = $validated['src_type'] ?? 'any';
        if ($srcType === 'any') {
            $source = 'any';
        } elseif (in_array($srcType, ['address', 'network'])) {
            $addr = trim($validated['src'] ?? '');
            $source = $addr ?: 'any';
        } else {
            $source = $srcType;
        }
        if ($source !== 'any' && $request->boolean('src_not')) {
            $source = '!' . $source;
        }

        // Build destination from type + address + invert
        $dstType = $validated['dst_type'] ?? 'wan:ip';
        if ($dstType === 'any') {
            $destination = 'any';
        } elseif (in_array($dstType, ['address', 'network'])) {
            $addr = trim($validated['dst'] ?? '');
            $destination = $addr ?: 'any';
        } else {
            $destination = $dstType;
        }
        if ($destination !== 'any' && $request->boolean('dst_not')) {
            $destination = '!' . $destination;
        }

        $data = [
            'interface' => $interfaceValue,
            'protocol' => $validated['protocol'],
            'ipprotocol' => $validated['ipprotocol'] ?? 'inet',
            'source' => $source,
            'destination' => $destination,
            'destination_port' => $validated['dstport'],
            'dstport' => $validated['dstport'],
            'target' => $validated['target'],
            'local_port' => $validated['local_port'],
            'local-port' => $validated['local_port'],
            'descr' => $validated['descr'] ?? '',
        ];

        if ($natreflection !== null) {
            $data['natreflection'] = $natreflection;
        }

        if (isset($validated['associated_rule_id'])) {
            $val = $validated['associated_rule_id'];
            if ($val === 'linked') {
                $data['associated_rule_id'] = 'new';
            } elseif ($val === 'pass') {
                $data['associated_rule_id'] = 'pass';
            } elseif ($val === '') {
                $data['associated_rule_id'] = '';
            } else {
                $data['associated_rule_id'] = $val;
            }
        }

        if (isset($validated['srcport']) && $validated['srcport'] !== 'any') {
            $data['source_port'] = $validated['srcport'];
            $data['srcport'] = $validated['srcport'];
        }

        if ($request->has('disabled') && $request->boolean('disabled')) {
            $data['disabled'] = true;
        }


        try {
            $api = new PfSenseApiService($firewall);
            $response = $api->updateNatPortForward((int) $id, $data);

            // Fix pfSense API issue where dynamically generated filter rules lack destination ports
            if (($data['associated_rule_id'] ?? '') === 'new' && isset($response['data']['associated_rule_id'])) {
                $trackerId = $response['data']['associated_rule_id'];
                $rules = $api->getFirewallRules()['data'] ?? [];
                foreach ($rules as $rule) {
                    if (($rule['associated_rule_id'] ?? '') === $trackerId) {
                        try {
                            $api->updateFirewallRule($rule['id'], [
                                'dstport' => $validated['local_port'],
                            ]);
                        } catch (\Exception $e) {
                            Log::error('Failed to update associated filter rule port: ' . $e->getMessage());
                        }
                        break;
                    }
                }
            }

            $firewall->update(['is_dirty' => true]);

            return redirect()->route('firewall.nat.port-forward', $firewall)
                ->with('success', 'Port forward rule updated successfully. Please apply changes.');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Failed to update port forward: ' . $e->getMessage());
        }
    }

    public function destroyPortForward(Firewall $firewall, $id)
    {
        try {
            $api = new PfSenseApiService($firewall);

            // Fetch the rule to check for associated rule
            $rules = $api->getNatPortForwards()['data'] ?? [];
            $rule = $rules[$id] ?? null;

            if ($rule) {
                // Check for associated rule ID (could be hyphen or underscore)
                $associatedRuleId = $rule['associated-rule-id'] ?? $rule['associated_rule_id'] ?? null;


                // If it's a specific ID (not a keyword), try to find and delete the linked firewall rule
                if ($associatedRuleId && !in_array($associatedRuleId, ['pass', 'block', 'reject', 'none'])) {
                    $firewallRules = $api->getFirewallRules()['data'] ?? [];

                    // Clean up ID if it has 'nat_' prefix for comparison
                    $cleanId = str_replace('nat_', '', $associatedRuleId);


                    foreach ($firewallRules as $fwIndex => $fwRule) {
                        if (isset($fwRule['tracker']) && (string) $fwRule['tracker'] === (string) $cleanId) {
                            try {
                                $api->deleteFirewallRule($fwIndex);

                            } catch (\Exception $e) {

                            }
                            break;
                        }
                    }
                }
            }

            $api->deleteNatPortForward((int) $id);
            $firewall->update(['is_dirty' => true]);

            return redirect()->route('firewall.nat.port-forward', $firewall)
                ->with('success', 'Port forward rule deleted successfully. Please apply changes.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to delete port forward: ' . $e->getMessage());
        }
    }

    public function togglePortForward(Firewall $firewall, $id)
    {
        try {
            $api = new PfSenseApiService($firewall);
            $rules = $api->getNatPortForwards()['data'] ?? [];
            if (!isset($rules[$id])) {
                return back()->with('error', 'Port forward rule not found.');
            }
            $isCurrentlyDisabled = !empty($rules[$id]['disabled']);
            $response = $api->updateNatPortForward((int) $id, ['disabled' => !$isCurrentlyDisabled]);
            $firewall->update(['is_dirty' => true]);
            if (isset($response['status']) && $response['status'] === 'error') {
                throw new \Exception($response['message'] ?? 'Unknown API error');
            }
            return back()->with('success', 'Port forward rule status toggled successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to toggle port forward rule: ' . $e->getMessage());
        }
    }

    public function bulkAction(Request $request, Firewall $firewall)
    {
        $request->validate([
            'action' => 'required|in:enable,disable,delete',
            'ids'    => 'required|array',
            'ids.*'  => 'integer|min:0',
        ]);

        $action = $request->input('action');
        $ids    = $request->input('ids');
        rsort($ids); // descending so deletes don't shift indexes

        try {
            $api = new PfSenseApiService($firewall);

            if ($action === 'delete') {
                foreach ($ids as $id) {
                    try {
                        $rules = $api->getNatPortForwards()['data'] ?? [];
                        $rule  = $rules[$id] ?? null;
                        if ($rule) {
                            $associatedRuleId = $rule['associated-rule-id'] ?? $rule['associated_rule_id'] ?? null;
                            if ($associatedRuleId && !in_array($associatedRuleId, ['pass', 'block', 'reject', 'none'])) {
                                $firewallRules = $api->getFirewallRules()['data'] ?? [];
                                $cleanId = str_replace('nat_', '', $associatedRuleId);
                                foreach ($firewallRules as $fwIndex => $fwRule) {
                                    if (isset($fwRule['tracker']) && (string) $fwRule['tracker'] === (string) $cleanId) {
                                        try { $api->deleteFirewallRule($fwIndex); } catch (\Exception $e) {}
                                        break;
                                    }
                                }
                            }
                        }
                        $api->deleteNatPortForward((int) $id);
                    } catch (\Exception $e) {
                        Log::warning("Bulk NAT delete failed for id={$id}: " . $e->getMessage());
                    }
                }
            } else {
                $disabled = ($action === 'disable');
                foreach ($ids as $id) {
                    try {
                        $api->updateNatPortForward((int) $id, ['disabled' => $disabled]);
                    } catch (\Exception $e) {
                        Log::warning("Bulk NAT {$action} failed for id={$id}: " . $e->getMessage());
                    }
                }
            }

            $firewall->update(['is_dirty' => true]);
            $label = ['enable' => 'Enabled', 'disable' => 'Disabled', 'delete' => 'Deleted'][$action] ?? ucfirst($action);
            return redirect()->route('firewall.nat.port-forward', $firewall)
                ->with('success', "Selected rules {$label} successfully. Please apply changes.");
        } catch (\Exception $e) {
            return back()->with('error', 'Bulk action failed: ' . $e->getMessage());
        }
    }

    // Outbound NAT
    public function outbound(Firewall $firewall)
    {
        try {
            $api = new PfSenseApiService($firewall);
            $modeResponse = $api->getNatOutboundMode();
            $rulesResponse = $api->getNatOutboundRules();
            $interfacesResponse = $api->getInterfaces();

            $mode = $modeResponse['data']['mode'] ?? 'automatic';
            $rules = $rulesResponse['data'] ?? [];
            $interfaces = $interfacesResponse['data'] ?? [];

            return view('firewall.nat.outbound', compact('firewall', 'mode', 'rules', 'interfaces'));
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to fetch outbound NAT configuration: ' . $e->getMessage());
        }
    }

    public function updateOutboundMode(Request $request, Firewall $firewall)
    {
        $validated = $request->validate([
            'mode' => 'required|in:automatic,hybrid,advanced,disabled',
        ]);

        try {
            $api = new PfSenseApiService($firewall);
            $api->updateNatOutboundMode($validated['mode']);
            $firewall->update(['is_dirty' => true]);

            return back()->with('success', 'Outbound NAT mode updated successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to update outbound NAT mode: ' . $e->getMessage());
        }
    }

    public function storeOutbound(Request $request, Firewall $firewall)
    {
        $validated = $request->validate([
            'interface' => 'required|string',
            'protocol' => 'required|in:tcp,udp,tcp/udp,icmp,esp,ah,gre,ipv6,igmp,any',
            'src' => 'nullable|string',
            'srcport' => 'nullable|string',
            'dst' => 'nullable|string',
            'dstport' => 'nullable|string',
            'target' => 'nullable|string', // Translation address
            'target_subnet' => 'nullable|integer',
            'descr' => 'nullable|string',
            'disabled' => 'nullable|boolean',
            'nonat' => 'nullable|boolean',
            'staticnatport' => 'nullable|boolean',
        ]);

        $interfaceValue = $validated['interface'];

        $data = [
            'interface' => $interfaceValue,
            'protocol' => $validated['protocol'],
            'source' => ($validated['src'] ?? 'any') === 'any' ? 'any' : $validated['src'],
            'destination' => ($validated['dst'] ?? 'any') === 'any' ? 'any' : $validated['dst'],
            'target' => $validated['target'] ?? null,
            'target_subnet' => isset($validated['target_subnet']) ? (int) $validated['target_subnet'] : 32, // Default to 32 for single IP
            'descr' => $validated['descr'] ?? '',
            'nonat' => $request->boolean('nonat'),
            'staticnatport' => $request->boolean('staticnatport'),
        ];

        if (isset($validated['srcport']) && $validated['srcport'] !== 'any') {
            $data['source_port'] = $validated['srcport'];
        }
        if (isset($validated['dstport']) && $validated['dstport'] !== 'any') {
            $data['destination_port'] = $validated['dstport'];
        }
        if ($request->has('disabled') && $request->boolean('disabled')) {
            $data['disabled'] = true;
        }

        try {
            $api = new PfSenseApiService($firewall);
            $api->createNatOutboundRule($data);
            $firewall->update(['is_dirty' => true]);

            return redirect()->route('firewall.nat.outbound', $firewall)
                ->with('success', 'Outbound NAT rule created successfully. Please apply changes.');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Failed to create outbound NAT rule: ' . $e->getMessage());
        }
    }

    public function updateOutbound(Request $request, Firewall $firewall, $id)
    {
        $validated = $request->validate([
            'interface' => 'required|string',
            'protocol' => 'required|in:tcp,udp,tcp/udp,icmp,esp,ah,gre,ipv6,igmp,any',
            'src' => 'nullable|string',
            'srcport' => 'nullable|string',
            'dst' => 'nullable|string',
            'dstport' => 'nullable|string',
            'target' => 'nullable|string',
            'target_subnet' => 'nullable|integer',
            'descr' => 'nullable|string',
            'disabled' => 'nullable|boolean',
            'nonat' => 'nullable|boolean',
            'staticnatport' => 'nullable|boolean',
        ]);

        $interfaceValue = $validated['interface'];

        $data = [
            'interface' => $interfaceValue,
            'protocol' => $validated['protocol'],
            'source' => ($validated['src'] ?? 'any') === 'any' ? 'any' : $validated['src'],
            'destination' => ($validated['dst'] ?? 'any') === 'any' ? 'any' : $validated['dst'],
            'target' => $validated['target'] ?? null,
            'target_subnet' => isset($validated['target_subnet']) ? (int) $validated['target_subnet'] : 32,
            'descr' => $validated['descr'] ?? '',
            'nonat' => $request->boolean('nonat'),
            'staticnatport' => $request->boolean('staticnatport'),
        ];

        if (isset($validated['srcport']) && $validated['srcport'] !== 'any') {
            $data['source_port'] = $validated['srcport'];
        }
        if (isset($validated['dstport']) && $validated['dstport'] !== 'any') {
            $data['destination_port'] = $validated['dstport'];
        }
        if ($request->has('disabled') && $request->boolean('disabled')) {
            $data['disabled'] = true;
        }

        try {
            $api = new PfSenseApiService($firewall);
            $api->updateNatOutboundRule((int) $id, $data);
            $firewall->update(['is_dirty' => true]);

            return redirect()->route('firewall.nat.outbound', $firewall)
                ->with('success', 'Outbound NAT rule updated successfully. Please apply changes.');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Failed to update outbound NAT rule: ' . $e->getMessage());
        }
    }

    public function destroyOutbound(Firewall $firewall, $id)
    {
        try {
            $api = new PfSenseApiService($firewall);
            $api->deleteNatOutboundRule((int) $id);
            $firewall->update(['is_dirty' => true]);

            return redirect()->route('firewall.nat.outbound', $firewall)
                ->with('success', 'Outbound NAT rule deleted successfully. Please apply changes.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to delete outbound NAT rule: ' . $e->getMessage());
        }
    }

    public function editOutbound(Firewall $firewall, $id)
    {
        try {
            $api = new PfSenseApiService($firewall);
            $rules = $api->getNatOutboundRules()['data'] ?? [];

            if (!isset($rules[$id])) {
                return response()->json(['error' => 'Rule not found'], 404);
            }

            return response()->json($rules[$id]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function toggleOutbound(Firewall $firewall, $id)
    {
        try {
            $api = new PfSenseApiService($firewall);
            $rules = $api->getNatOutboundRules()['data'] ?? [];

            if (!isset($rules[$id])) {
                return back()->with('error', 'Outbound NAT rule not found.');
            }

            $currentRule = $rules[$id];

            // Determine new state (strict boolean)
            $isCurrentlyDisabled = !empty($currentRule['disabled']);
            $newState = !$isCurrentlyDisabled;

            // Construct minimal PATCH payload
            $payload = [
                'disabled' => $newState
            ];

            // Add ID if the API method requires it in payload (though updateNatOutboundRule takes ID as arg)
            $payload['id'] = (int) $id;



            $api->updateNatOutboundRule((int) $id, $payload);
            $firewall->update(['is_dirty' => true]);

            return back()->with('success', 'Outbound NAT rule status toggled successfully.');
        } catch (\Exception $e) {

            return back()->with('error', 'Failed to toggle outbound NAT rule: ' . $e->getMessage());
        }
    }

    // 1:1 NAT
    public function oneToOne(Firewall $firewall)
    {
        try {
            $api = new PfSenseApiService($firewall);
            $response = $api->getNatOneToOneRules();
            $interfacesResponse = $api->getInterfaces();

            $rules = $response['data'] ?? [];
            $interfaces = $interfacesResponse['data'] ?? [];

            return view('firewall.nat.one-to-one', compact('firewall', 'rules', 'interfaces'));
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to fetch 1:1 NAT rules: ' . $e->getMessage());
        }
    }

    public function storeOneToOne(Request $request, Firewall $firewall)
    {
        $validated = $request->validate([
            'interface' => 'required|string',
            'external' => 'required|string', // External IP
            'src' => 'nullable|string', // Internal IP (Source)
            'dst' => 'nullable|string', // Destination IP
            'descr' => 'nullable|string',
            'disabled' => 'nullable|boolean',
            'natreflection' => 'nullable|in:enable,disable',
        ]);

        $interfaceValue = $validated['interface'];

        $data = [
            'interface' => $interfaceValue,
            'external' => $validated['external'],
            'source' => ($validated['src'] ?? 'any') === 'any' ? 'any' : $validated['src'],
            'destination' => ($validated['dst'] ?? 'any') === 'any' ? 'any' : $validated['dst'],
            'descr' => $validated['descr'] ?? '',
            'natreflection' => $validated['natreflection'] ?? null,
        ];

        if ($request->has('disabled') && $request->boolean('disabled')) {
            $data['disabled'] = true;
        }

        try {
            $api = new PfSenseApiService($firewall);
            $api->createNatOneToOneRule($data);
            $firewall->update(['is_dirty' => true]);

            return redirect()->route('firewall.nat.one-to-one', $firewall)
                ->with('success', '1:1 NAT rule created successfully. Please apply changes.');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Failed to create 1:1 NAT rule: ' . $e->getMessage());
        }
    }

    public function updateOneToOne(Request $request, Firewall $firewall, $id)
    {
        $validated = $request->validate([
            'interface' => 'required|string',
            'external' => 'required|string',
            'src' => 'nullable|string',
            'dst' => 'nullable|string',
            'descr' => 'nullable|string',
            'disabled' => 'nullable|boolean',
            'natreflection' => 'nullable|in:enable,disable',
        ]);

        $interfaceValue = $validated['interface'];

        $data = [
            'interface' => $interfaceValue,
            'external' => $validated['external'],
            'source' => ($validated['src'] ?? 'any') === 'any' ? 'any' : $validated['src'],
            'destination' => ($validated['dst'] ?? 'any') === 'any' ? 'any' : $validated['dst'],
            'descr' => $validated['descr'] ?? '',
            'natreflection' => $validated['natreflection'] ?? null,
        ];

        if ($request->has('disabled') && $request->boolean('disabled')) {
            $data['disabled'] = true;
        }

        try {
            $api = new PfSenseApiService($firewall);
            $api->updateNatOneToOneRule((int) $id, $data);
            $firewall->update(['is_dirty' => true]);

            return redirect()->route('firewall.nat.one-to-one', $firewall)
                ->with('success', '1:1 NAT rule updated successfully. Please apply changes.');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Failed to update 1:1 NAT rule: ' . $e->getMessage());
        }
    }

    public function destroyOneToOne(Firewall $firewall, $id)
    {
        try {
            $api = new PfSenseApiService($firewall);
            $api->deleteNatOneToOneRule((int) $id);
            $firewall->update(['is_dirty' => true]);

            return redirect()->route('firewall.nat.one-to-one', $firewall)
                ->with('success', '1:1 NAT rule deleted successfully. Please apply changes.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to delete 1:1 NAT rule: ' . $e->getMessage());
        }
    }

    public function toggleOneToOne(Firewall $firewall, $id)
    {
        try {
            $api = new PfSenseApiService($firewall);
            $rules = $api->getNatOneToOneRules()['data'] ?? [];

            if (!isset($rules[$id])) {
                return back()->with('error', '1:1 NAT rule not found.');
            }

            $currentRule = $rules[$id];

            // Determine new state (strict boolean)
            $isCurrentlyDisabled = !empty($currentRule['disabled']);
            $newState = !$isCurrentlyDisabled;

            // Construct minimal PATCH payload
            $payload = [
                'disabled' => $newState
            ];
            // ID is required for updateNatOneToOneRule method logic but typically patching by ID in URL handled by service
            // The service method: updateNatOneToOneRule(int $id, array $data) -> patch("/firewall/nat/one_to_one/mapping", $data + ['id' => $id])
            // So we just pass the payload.

            $api->updateNatOneToOneRule((int) $id, $payload);
            $firewall->update(['is_dirty' => true]);

            return back()->with('success', '1:1 NAT rule status toggled successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to toggle 1:1 NAT rule: ' . $e->getMessage());
        }
    }
}
