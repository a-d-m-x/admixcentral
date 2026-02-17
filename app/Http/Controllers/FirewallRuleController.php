<?php

namespace App\Http\Controllers;

use App\Models\Firewall;
use App\Services\PfSenseApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FirewallRuleController extends Controller
{
    public function index(Request $request, Firewall $firewall)
    {
        try {
            $api = new PfSenseApiService($firewall);
            $rulesResponse = $api->getFirewallRules();
            $interfacesResponse = $api->getInterfaces();

            $rules = $rulesResponse['data'] ?? [];
            $interfaces = $interfacesResponse['data'] ?? [];

            // Filter rules by interface if requested, otherwise default to 'wan'
            $selectedInterface = $request->query('interface', 'wan');

            // Filter logic: pfSense API v2 returns all rules. We need to filter them in the controller
            // or rely on the API if it supports filtering (it usually returns all).
            // The 'interface' field in the rule object matches the interface ID (e.g., 'wan', 'lan').

            $filteredRules = collect($rules)->filter(function ($rule) use ($selectedInterface) {
                if (!isset($rule['interface'])) {
                    return false;
                }

                if (is_array($rule['interface'])) {
                    return in_array($selectedInterface, $rule['interface']);
                }

                return $rule['interface'] === $selectedInterface;
            })->values();

            if (request()->wantsJson()) {
                return response()->json($filteredRules);
            }

            return view('firewall.rules.index', compact('firewall', 'interfaces', 'filteredRules', 'selectedInterface'));
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to fetch firewall rules: ' . $e->getMessage());
        }
    }

    public function create(Firewall $firewall)
    {

        try {
            $api = new PfSenseApiService($firewall);
            $interfacesResponse = $api->getInterfaces();
            $interfaces = $interfacesResponse['data'] ?? [];


            // Empty rule for create with defaults
            $rule = [
                'type' => 'pass',
                'interface' => 'wan',
                'ipprotocol' => 'inet',
                'protocol' => 'tcp',
                'source' => ['any' => true],
                'destination' => ['any' => true],
                'log' => false,
                'descr' => '',
                'disabled' => false,
            ];

            return view('firewall.rules.edit', compact('firewall', 'rule', 'interfaces'));
        } catch (\Exception $e) {

            return back()->with('error', 'Failed to prepare rule creation: ' . $e->getMessage());
        }
    }

    public function store(Request $request, Firewall $firewall)
    {
        $data = $this->prepareRuleData($request);



        try {
            $api = new PfSenseApiService($firewall);
            $response = $api->createFirewallRule($data);



            $firewall->update(['is_dirty' => true]);

            return redirect()->route('firewall.rules.index', ['firewall' => $firewall, 'interface' => $data['interface']])
                ->with('success', 'Firewall rule created successfully. Please apply changes.');
        } catch (\Exception $e) {


            return back()->with('error', 'Failed to create rule: ' . $e->getMessage())->withInput();
        }
    }

    public function edit(Firewall $firewall, string $tracker)
    {
        // We need to find the rule by 'tracker' ID
        try {
            $api = new PfSenseApiService($firewall);
            $rulesResponse = $api->getFirewallRules();
            $rules = $rulesResponse['data'] ?? [];

            $rule = collect($rules)->firstWhere('tracker', $tracker);

            if (!$rule) {
                return back()->with('error', 'Firewall rule not found.');
            }

            $interfacesResponse = $api->getInterfaces();
            $interfaces = $interfacesResponse['data'] ?? [];

            return view('firewall.rules.edit', compact('firewall', 'rule', 'interfaces'));
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to fetch rule details: ' . $e->getMessage());
        }
    }

    public function update(Request $request, Firewall $firewall, $tracker)
    {
        $data = $this->prepareRuleData($request);

        // Add tracker to data if not present (though API usually needs index)
        $data['tracker'] = $tracker;

        try {
            $api = new PfSenseApiService($firewall);

            // Find the index of the rule with this tracker
            $index = $this->getRuleIndexByTracker($api, $tracker);

            if ($index === null) {
                return back()->with('error', 'Rule not found.');
            }

            // Update the rule at the specific index
            $response = $api->updateFirewallRule($index, $data);

            $firewall->update(['is_dirty' => true]);

            return redirect()->route('firewall.rules.index', ['firewall' => $firewall, 'interface' => $data['interface']])
                ->with('success', 'Firewall rule updated successfully. Please apply changes.');
        } catch (\Exception $e) {

            return back()->with('error', 'Failed to update rule: ' . $e->getMessage())->withInput();
        }
    }

    public function destroy(Request $request, Firewall $firewall, $tracker)
    {
        try {
            $api = new PfSenseApiService($firewall);

            $index = $this->getRuleIndexByTracker($api, $tracker);

            if ($index === null) {
                return back()->with('error', 'Rule not found.');
            }

            $api->deleteFirewallRule($index);
            $firewall->update(['is_dirty' => true]);

            $interface = $request->input('interface', 'wan');

            return redirect()->route('firewall.rules.index', ['firewall' => $firewall, 'interface' => $interface])
                ->with('success', 'Firewall rule deleted successfully. Please apply changes.');
        } catch (\Exception $e) {

            return back()->with('error', 'Failed to delete rule: ' . $e->getMessage());
        }
    }

    public function bulkAction(Request $request, Firewall $firewall)
    {
        $request->validate([
            'action' => 'required|in:enable,disable,delete',
            'trackers' => 'required|array',
            'trackers.*' => 'required|numeric',
        ]);

        $action = $request->input('action');
        $trackers = $request->input('trackers');
        $count = 0;

        try {
            $api = new PfSenseApiService($firewall);
            $rulesResponse = $api->getFirewallRules();
            $rules = $rulesResponse['data'] ?? [];

            // Map trackers to indices
            $trackerToIndex = [];
            foreach ($rules as $index => $rule) {
                if (isset($rule['tracker']) && in_array($rule['tracker'], $trackers)) {
                    $trackerToIndex[$rule['tracker']] = $index;
                }
            }

            if ($action === 'delete') {
                // Sort indices in descending order to avoid shifting issues when deleting
                arsort($trackerToIndex);

                foreach ($trackerToIndex as $tracker => $index) {
                    $api->deleteFirewallRule($index);
                    $count++;
                }
            } elseif ($action === 'enable' || $action === 'disable') {
                foreach ($trackerToIndex as $tracker => $index) {
                    $ruleData = $rules[$index];

                    if ($action === 'enable') {
                        // Enable if disabled is true
                        if (isset($ruleData['disabled']) && $ruleData['disabled'] === true) {
                            $ruleData['disabled'] = false;
                            $api->updateFirewallRule($index, $ruleData);
                            $count++;
                        }
                    } else { // disable
                        // Disable if disabled is not set or false
                        if (!isset($ruleData['disabled']) || $ruleData['disabled'] === false) {
                            $ruleData['disabled'] = true;
                            $api->updateFirewallRule($index, $ruleData);
                            $count++;
                        }
                    }
                }
            }

            $firewall->update(['is_dirty' => true]);

            $interface = $request->input('interface', 'wan');

            return redirect()->route('firewall.rules.index', ['firewall' => $firewall, 'interface' => $interface])
                ->with('success', "Successfully processed {$count} rules. Please apply changes.");

        } catch (\Exception $e) {

            return back()->with('error', 'Bulk action failed: ' . $e->getMessage());
        }
    }

    public function move(Request $request, Firewall $firewall, $tracker)
    {
        $request->validate([
            'direction' => 'required|in:up,down',
            'interface' => 'required|string',
        ]);

        $direction = $request->input('direction');
        $interface = $request->input('interface');

        try {
            $api = new PfSenseApiService($firewall);
            $rulesResponse = $api->getFirewallRules();
            $rules = $rulesResponse['data'] ?? [];

            // Filter rules by interface to match UI order
            $filteredIndices = [];
            foreach ($rules as $index => $rule) {
                // Check if rule belongs to interface
                // Rule interface can be string or array
                $ruleIf = $rule['interface'] ?? '';
                $matches = false;
                if (is_array($ruleIf)) {
                    $matches = in_array($interface, $ruleIf);
                } else {
                    $matches = $ruleIf === $interface;
                }

                if ($matches) {
                    $filteredIndices[] = $index;
                }
            }

            // Find position of current tracker in filtered list
            $currentPos = -1;
            $globalIndex = -1;

            foreach ($filteredIndices as $pos => $idx) {
                if (isset($rules[$idx]['tracker']) && (string) $rules[$idx]['tracker'] === (string) $tracker) {
                    $currentPos = $pos;
                    $globalIndex = $idx;
                    break;
                }
            }

            if ($currentPos === -1) {
                return back()->with('error', 'Rule not found in current interface list.');
            }

            // Determine target position
            $targetPos = $direction === 'up' ? $currentPos - 1 : $currentPos + 1;

            if ($targetPos < 0 || $targetPos >= count($filteredIndices)) {
                return back()->with('error', 'Cannot move rule further in that direction.');
            }

            $targetGlobalIndex = $filteredIndices[$targetPos];

            // Swap data
            $sourceData = $rules[$globalIndex];
            $targetData = $rules[$targetGlobalIndex];

            // Remove immutable metadata fields to prevent API errors
            $immutableFields = [
                'tracker',
                'created',
                'updated',
                'created_time',
                'created_username',
                'created_by',
                'updated_time',
                'updated_username',
                'updated_by'
            ];

            foreach ($immutableFields as $field) {
                unset($sourceData[$field]);
                unset($targetData[$field]);
            }

            // Update source index with target data
            $api->updateFirewallRule($globalIndex, $targetData);

            // Update target index with source data
            $api->updateFirewallRule($targetGlobalIndex, $sourceData);

            $firewall->update(['is_dirty' => true]);

            return redirect()->route('firewall.rules.index', ['firewall' => $firewall, 'interface' => $interface])
                ->with('success', 'Rule moved successfully. Please apply changes.');

        } catch (\Exception $e) {

            return back()->with('error', 'Failed to move rule: ' . $e->getMessage());
        }
    }



    protected function prepareRuleData(Request $request)
    {
        $data = [
            'type' => $request->input('type'),
            'interface' => $request->input('interface'),
            'ipprotocol' => $request->input('ipprotocol'),
            'protocol' => $request->input('protocol'),
            'descr' => $request->input('descr'),
            'disabled' => $request->has('disabled'),
            'log' => $request->has('log'),
            'statetype' => $request->input('statetype'),
            'os' => $request->input('os'),
            'nosync' => $request->has('nosync'),
            'sched' => $request->input('sched'),
            'gateway' => $request->input('gateway'),
            'dnpipe' => $request->input('dnpipe'),
            'pdnpipe' => $request->input('pdnpipe'),
            'ackqueue' => $request->input('ackqueue'),
            'defaultqueue' => $request->input('defaultqueue'),
        ];

        // Source
        if ($request->input('source_type') === 'any') {
            $data['source'] = 'any';
        } else {
            $src = [];
            if ($request->input('source_type') === 'network') {
                $src['network'] = $request->input('source_address');
            } elseif ($request->input('source_type') === 'address') {
                $src['address'] = $request->input('source_address');
            } else {
                // Handle special types like wanip, lanip
                $src['network'] = $request->input('source_type');
            }

            if ($request->has('source_invert')) {
                $src['not'] = true;
            }

            if ($request->filled('source_port_from')) {
                $src['port'] = $request->input('source_port_from');
                if ($request->filled('source_port_to')) {
                    $src['port'] .= '-' . $request->input('source_port_to');
                }
            }
            $data['source'] = $src;
        }

        // Destination
        if ($request->input('destination_type') === 'any') {
            $data['destination'] = 'any';
        } else {
            $dst = [];
            if ($request->input('destination_type') === 'network') {
                $dst['network'] = $request->input('destination_address');
            } elseif ($request->input('destination_type') === 'address') {
                $dst['address'] = $request->input('destination_address');
            } else {
                // Handle special types like wanip, lanip
                $dst['network'] = $request->input('destination_type');
            }

            if ($request->has('destination_invert')) {
                $dst['not'] = true;
            }

            if ($request->filled('destination_port_from')) {
                $dst['port'] = $request->input('destination_port_from');
                if ($request->filled('destination_port_to')) {
                    $dst['port'] .= '-' . $request->input('destination_port_to');
                }
            }
            $data['destination'] = $dst;
        }

        // TCP Flags
        if ($request->filled('tcp_flags_set')) {
            $data['tcpflags_set'] = implode(',', $request->input('tcp_flags_set'));
        }
        if ($request->filled('tcp_flags_out_of')) {
            $data['tcpflags_any'] = implode(',', $request->input('tcp_flags_out_of'));
        }

        return $data;
    }

    protected function getRuleIndexByTracker(PfSenseApiService $api, string $tracker): ?int
    {
        $rulesResponse = $api->getFirewallRules();
        $rules = $rulesResponse['data'] ?? [];

        foreach ($rules as $index => $rule) {
            if (isset($rule['tracker']) && (string) $rule['tracker'] === $tracker) {
                return $index;
            }
        }

        return null;
    }

    public function toggle(Firewall $firewall, $tracker)
    {
        try {
            $api = new PfSenseApiService($firewall);

            // Find the index of the rule with this tracker
            $index = $this->getRuleIndexByTracker($api, $tracker);

            if ($index === null) {
                return back()->with('error', 'Rule not found.');
            }

            $rulesResponse = $api->getFirewallRules();
            $rules = $rulesResponse['data'] ?? [];
            $ruleData = $rules[$index];

            // Determine new state (strict boolean)
            $isCurrentlyDisabled = !empty($ruleData['disabled']);
            $newState = !$isCurrentlyDisabled;

            // Update local data
            $ruleData['disabled'] = $newState;

            // Update the rule at the specific index
            $api->updateFirewallRule($index, $ruleData);

            $firewall->update(['is_dirty' => true]);

            return back()->with('success', 'Firewall rule status toggled successfully.');
        } catch (\Exception $e) {
            // Log::error('FirewallRuleController@toggle failed: ' . $e->getMessage());
            return back()->with('error', 'Failed to toggle rule: ' . $e->getMessage());
        }
    }
}
