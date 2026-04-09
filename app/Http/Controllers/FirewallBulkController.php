<?php

namespace App\Http\Controllers;

use App\Models\Firewall;
use App\Services\PfSenseApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class FirewallBulkController extends Controller
{
    public function handle(Request $request)
    {
        // Release session lock — loops over firewalls making sequential pfSense API calls
        session_write_close();

        $request->validate([
            'firewall_ids' => 'required|array',
            'firewall_ids.*' => 'exists:firewalls,id',
            'action' => 'required|string',
        ]);

        $firewalls = Firewall::find($request->firewall_ids);
        $action = $request->action;
        $results = [];
        $successCount = 0;
        $failureCount = 0;

        foreach ($firewalls as $firewall) {
            // Apply scoped access check manually just in case, though index selection implies visibility
            if (!auth()->user()->isGlobalAdmin() && $firewall->company_id !== auth()->user()->company_id) {
                continue;
            }

            try {
                $api = new PfSenseApiService($firewall);

                if ($action === 'reboot') {
                    // Call API to reboot
                    // Assuming PfSenseApiService has a reboot method or we call diag_reboot.php via exec or specific endpoint
                    // Let's check Service capability. For now, we can use an exec command or specific endpoint.
                    // pfSense API v2 has /api/v2/diagnostics/reboot
                    $api->post('diagnostics/reboot');
                    $results[] = "{$firewall->name}: Reboot command sent.";
                    $successCount++;
                } elseif ($action === 'update') {
                    // Run pfSense-upgrade command for system update
                    $api->post('diagnostics/command_prompt', ['command' => '/usr/sbin/pfSense-upgrade -y']);
                    $results[] = "{$firewall->name}: System update initiated (pfSense-upgrade -y).";
                    $successCount++;
                } else {
                    $results[] = "{$firewall->name}: Unknown action.";
                    $failureCount++;
                }

            } catch (\Exception $e) {
                $results[] = "{$firewall->name}: Failed - " . $e->getMessage();
                $failureCount++;
            }
        }

        return redirect()->route('firewalls.index')->with([
            'bulk_results' => $results,
            'success' => "Processed {$successCount} firewalls. Errors: {$failureCount}.",
        ]);
    }

    public function create(Request $request, $type)
    {
        $request->validate([
            'firewall_ids' => 'required|array',
        ]);

        // Package type has its own dedicated handler
        if ($type === 'package') {
            return $this->createPackage($request);
        }

        $ids = implode(',', $request->firewall_ids);

        return view('firewalls.bulk.create', [
            'type' => $type,
            'firewall_ids' => $ids
        ]);
    }

    public function store(Request $request, $type)
    {
        // Release session lock — loops over firewalls making sequential pfSense API calls
        session_write_close();

        // Package type has its own dedicated handler
        if ($type === 'package') {
            return $this->storePackage($request);
        }

        $request->validate([
            'firewall_ids' => 'required|string', // Comma separated
        ]);

        $firewall_ids = explode(',', $request->firewall_ids);
        $firewalls = Firewall::find($firewall_ids);

        $successCount = 0;
        $failureCount = 0;
        $results = [];

        foreach ($firewalls as $firewall) {
            if (!auth()->user()->isGlobalAdmin() && $firewall->company_id !== auth()->user()->company_id) {
                continue;
            }

            try {
                $api = new PfSenseApiService($firewall);

                if ($type === 'update') {
                    // System Update - Issue pfSense-upgrade command
                    $api->commandPrompt('pfSense-upgrade -y');
                    $results[] = "{$firewall->name}: pfSense-upgrade command issued.";
                    $successCount++;
                    continue;

                } elseif ($type === 'alias') {
                    // Validate
                    $data = $request->validate([
                        'name' => 'required',
                        'type' => 'required',
                        'address' => 'nullable',
                        'descr' => 'nullable'
                    ]);
                    // Add logic to parse address into details if needed by API? 
                    // API expects 'address' as array or string. Usually array for multiple.
                    // Textarea input is space/comma separated.
                    if (!empty($data['address'])) {
                        $data['address'] = preg_split('/[\s,]+/', $data['address'], -1, PREG_SPLIT_NO_EMPTY);
                    }

                    $api->createAlias($data);

                } elseif ($type === 'nat') {
                    // Validate
                    $validated = $request->validate([
                        'dstport' => 'required',
                        'target' => 'required',
                        'local_port' => 'required',
                        'dst' => 'required',
                        'src' => 'nullable',
                        'srcport' => 'nullable',
                        'protocol' => 'nullable',
                        'interface' => 'nullable',
                        'descr' => 'nullable',
                        'associated_rule' => 'nullable',
                        'natreflection' => 'nullable',
                    ]);

                    $data = [
                        'interface' => $validated['interface'] ?? 'wan',
                        'protocol' => $validated['protocol'] ?? 'tcp',
                        'destination_port' => $validated['dstport'],
                        'dstport' => $validated['dstport'],
                        'target' => $validated['target'],
                        'local_port' => $validated['local_port'],
                        'local-port' => $validated['local_port'],
                        'descr' => $validated['descr'] ?? '',
                        'associated_rule_id' => $validated['associated_rule'] ?? 'new',
                        'natreflection' => $validated['natreflection'] ?? 'system-default',
                    ];

                    // Handle Source
                    $src = $validated['src'] ?? 'any';
                    $data['source'] = ($src === 'any') ? 'any' : $src;

                    if (!empty($validated['srcport']) && $validated['srcport'] !== 'any') {
                        $data['source_port'] = $validated['srcport'];
                        $data['srcport'] = $validated['srcport'];
                    }

                    // Handle Destination
                    $dst = $validated['dst'];
                    $data['destination'] = ($dst === 'any') ? 'any' : $dst;

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
                                    // Ignore silently on bulk failure
                                }
                                break;
                            }
                        }
                    }
                } elseif ($type === 'rule') {
                    // Validate
                    $validated = $request->validate([
                        'type' => 'required',
                        'interface' => 'required',
                        'ipprotocol' => 'required',
                        'protocol' => 'required',
                        'src' => 'nullable',
                        'srcport' => 'nullable',
                        'dst' => 'nullable',
                        'dstport' => 'nullable',
                        'descr' => 'nullable',
                    ]);

                    $data = [
                        'type' => $validated['type'],
                        'interface' => $validated['interface'],
                        'ipprotocol' => $validated['ipprotocol'],
                        'protocol' => $validated['protocol'],
                        'src' => $validated['src'] ?? 'any',
                        'srcport' => $validated['srcport'] ?? '',
                        'dst' => $validated['dst'] ?? 'any',
                        'dstport' => $validated['dstport'] ?? '',
                        'descr' => $validated['descr'] ?? '',
                    ];

                    $api->createFirewallRule($data);

                } elseif ($type === 'ipsec') {
                    $validated = $request->validate([
                        'iketype' => 'required|in:ikev1,ikev2,auto',
                        'protocol' => 'required|in:inet,inet6',
                        'interface' => 'required|string',
                        'remote_gateway' => 'required|string',
                        'descr' => 'nullable|string',
                        'authentication_method' => 'required|in:pre_shared_key',
                        'pre_shared_key' => 'required|string',
                        'myid_type' => 'required|string',
                        'myid_data' => 'nullable|string',
                        'peerid_type' => 'required|string',
                        'peerid_data' => 'nullable|string',
                        'encryption_algorithm_name' => 'required|string',
                        'encryption_algorithm_keylen' => 'required_if:encryption_algorithm_name,aes',
                        'hash_algorithm' => 'required|string',
                        'dhgroup' => 'required|integer',
                        'lifetime' => 'nullable|integer',
                    ]);

                    $data = [
                        'iketype' => $validated['iketype'],
                        'protocol' => $validated['protocol'],
                        'interface' => $validated['interface'],
                        'remote_gateway' => $validated['remote_gateway'],
                        'descr' => $validated['descr'] ?? '',
                        'authentication_method' => $validated['authentication_method'],
                        'pre_shared_key' => $validated['pre_shared_key'],
                        'myid_type' => $validated['myid_type'],
                        'myid_data' => $validated['myid_data'] ?? null,
                        'peerid_type' => $validated['peerid_type'],
                        'peerid_data' => $validated['peerid_data'] ?? null,
                        'encryption' => [
                            [
                                'encryption_algorithm_name' => $validated['encryption_algorithm_name'],
                                'encryption_algorithm_keylen' => (int) ($validated['encryption_algorithm_keylen'] ?? 'auto'),
                                'hash_algorithm' => $validated['hash_algorithm'],
                                'dhgroup' => (int) $validated['dhgroup'],
                            ]
                        ],
                        'lifetime' => (int) ($validated['lifetime'] ?? 28800),
                    ];

                    $p1Response = $api->createIpsecPhase1($data);

                    // Phase 2 Logic
                    if ($request->has('enable_phase2')) {
                        // Validate Phase 2
                        $p2Validated = $request->validate([
                            'p2_mode' => 'required',
                            'p2_protocol' => 'required',
                            'p2_local_network' => 'required',
                            'p2_local_network_custom' => 'nullable|string',
                            'p2_remote_network' => 'required|string',
                            'p2_encryption' => 'required',
                            'p2_keylen' => 'nullable',
                            'p2_hash' => 'required|array',
                            'p2_pfsgroup' => 'required',
                            'p2_lifetime' => 'nullable|integer',
                        ]);

                        $ikeid = $p1Response['data']['ikeid'] ?? null;

                        if ($ikeid) {
                            // Prepare Local Network
                            $localType = $p2Validated['p2_local_network'];
                            $localAddress = null;
                            $localNetbits = null;

                            if ($localType === 'network' && !empty($p2Validated['p2_local_network_custom'])) {
                                // Parse CIDR
                                $parts = explode('/', $p2Validated['p2_local_network_custom']);
                                $localAddress = $parts[0] ?? '';
                                $localNetbits = $parts[1] ?? 32;
                            }

                            // Prepare Remote Network (Always CIDR in bulk form for simplicity)
                            $parts = explode('/', $p2Validated['p2_remote_network']);
                            $remoteAddress = $parts[0] ?? '';
                            $remoteNetbits = $parts[1] ?? 32;

                            $p2Data = [
                                'ikeid' => $ikeid,
                                'mode' => $p2Validated['p2_mode'],
                                'protocol' => $p2Validated['p2_protocol'],
                                'localid_type' => $localType,
                                'localid_address' => $localAddress,
                                'localid_netbits' => (int) $localNetbits,
                                'remoteid_type' => 'network',
                                'remoteid_address' => $remoteAddress,
                                'remoteid_netbits' => (int) $remoteNetbits,
                                'encryption_algorithm_option' => [
                                    [
                                        'name' => $p2Validated['p2_encryption'],
                                        'keylen' => ($p2Validated['p2_encryption'] === 'aes') ? ($p2Validated['p2_keylen'] ?? 'auto') : 'auto',
                                    ]
                                ],
                                'hash_algorithm_option' => $p2Validated['p2_hash'],
                                'pfsgroup' => (int) $p2Validated['p2_pfsgroup'],
                                'lifetime' => (int) ($p2Validated['p2_lifetime'] ?? 3600),
                                'descr' => ($validated['descr'] ?? '') . ' (P2)',
                            ];

                            $api->createIpsecPhase2($p2Data);
                            $results[] = "{$firewall->name}: Phase 2 created.";
                        } else {
                            $results[] = "{$firewall->name}: Phase 1 created, but could not retrieve IKE ID for Phase 2.";
                        }
                    }
                }

                $successCount++;
                $results[] = "{$firewall->name}: Configuration pushed.";

            } catch (\Exception $e) {
                $failureCount++;
                $results[] = "{$firewall->name}: Failed - " . $e->getMessage();
            }
        }

        return redirect()->route('firewalls.index')->with([
            'bulk_results' => $results,
            'success' => "Config pushed to {$successCount} firewalls.",
        ]);
    }

    /**
     * Show package selection form for bulk installation
     */
    public function createPackage(Request $request)
    {
        $request->validate([
            'firewall_ids' => 'required|array',
        ]);

        $ids = implode(',', $request->firewall_ids);
        $packages = [];
        $error = null;

        // Use first firewall to get available packages list
        $firstFirewallId = $request->firewall_ids[0];
        $firstFirewall = Firewall::find($firstFirewallId);

        if ($firstFirewall) {
            try {
                $api = new PfSenseApiService($firstFirewall);
                $packages = $api->getSystemAvailablePackages()['data'] ?? [];
            } catch (\Exception $e) {
                $error = "Could not fetch available packages from {$firstFirewall->name}: " . $e->getMessage();
            }
        }

        return view('firewalls.bulk.package', [
            'firewall_ids' => $ids,
            'packages' => $packages,
            'error' => $error,
        ]);
    }

    /**
     * Install selected package on all firewalls (skipping those that already have it)
     */
    public function storePackage(Request $request)
    {
        $request->validate([
            'firewall_ids' => 'required|string',
            'package' => 'required|string',
        ]);

        $firewall_ids = explode(',', $request->firewall_ids);
        $firewalls = Firewall::find($firewall_ids);
        $packageName = $request->package;

        $successCount = 0;
        $skippedCount = 0;
        $failureCount = 0;
        $results = [];

        foreach ($firewalls as $firewall) {
            if (!auth()->user()->isGlobalAdmin() && $firewall->company_id !== auth()->user()->company_id) {
                continue;
            }

            try {
                $api = new PfSenseApiService($firewall);

                // Check if package is already installed
                $installedPackages = $api->getSystemPackages()['data'] ?? [];
                $alreadyInstalled = false;

                foreach ($installedPackages as $pkg) {
                    $name = $pkg['name'] ?? '';
                    $shortname = $pkg['shortname'] ?? '';
                    if ($name === $packageName || $shortname === $packageName) {
                        $alreadyInstalled = true;
                        break;
                    }
                }

                if ($alreadyInstalled) {
                    $results[] = "{$firewall->name}: Package already installed - skipped.";
                    $skippedCount++;
                    continue;
                }

                // Install the package
                $api->installSystemPackage($packageName);
                $results[] = "{$firewall->name}: Package installation started.";
                $successCount++;

            } catch (\Exception $e) {
                $results[] = "{$firewall->name}: Failed - " . $e->getMessage();
                $failureCount++;
            }
        }

        return redirect()->route('firewalls.index')->with([
            'bulk_results' => $results,
            'success' => "Package '{$packageName}' deployed to {$successCount} firewalls. Skipped: {$skippedCount}. Errors: {$failureCount}.",
        ]);
    }
}
