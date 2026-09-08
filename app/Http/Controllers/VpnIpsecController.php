<?php

namespace App\Http\Controllers;

use App\Models\Firewall;
use App\Services\PfSenseApiService;
use Illuminate\Http\Request;

class VpnIpsecController extends Controller
{
    public function tunnels(Firewall $firewall)
    {
        try {
            $api = new PfSenseApiService($firewall);
            $phase1s = $api->getIpsecPhase1s()['data'] ?? [];
            $phase2s = $api->getIpsecPhase2s()['data'] ?? [];
            $interfaces = $api->get('/interfaces')['data'] ?? [];
            \Illuminate\Support\Facades\Log::info('Interfaces:', $interfaces);
            return view('vpn.ipsec', compact('firewall', 'phase1s', 'phase2s', 'interfaces'));
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to fetch IPsec tunnels: ' . $e->getMessage());
        }
    }

    public function createPhase1(Firewall $firewall)
    {
        // ... (keep existing or remove if using modal)
        // For now, we'll keep it but we are moving to modal in index
        return view('vpn.ipsec.edit-phase1', compact('firewall'));
    }

    public function storePhase1(Request $request, Firewall $firewall)
    {
        $validated = $request->validate([
            'iketype' => 'required|in:ikev1,ikev2,auto',
            'protocol' => 'required|in:inet,inet6',
            'interface' => 'required|string',
            'remote_gateway' => 'required|string',
            'descr' => 'nullable|string',
            'authentication_method' => 'required|in:pre_shared_key,rsasig',
            'pre_shared_key' => 'required_if:authentication_method,pre_shared_key',
            'myid_type' => 'required|string',
            'peerid_type' => 'required|string',
            'encryption_algorithm_name' => 'required|string',
            'encryption_algorithm_keylen' => 'required_if:encryption_algorithm_name,aes|integer',
            'hash_algorithm' => 'required|string',
            'dhgroup' => 'required|integer',
            'lifetime' => 'nullable|integer|min:60',
        ]);

        $data = [
            'iketype' => $validated['iketype'],
            'protocol' => $validated['protocol'],
            'interface' => $validated['interface'],
            'remote_gateway' => $validated['remote_gateway'],
            'descr' => $validated['descr'] ?? '',
            'authentication_method' => $validated['authentication_method'],
            'myid_type' => $validated['myid_type'],
            'peerid_type' => $validated['peerid_type'],
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

        if ($validated['authentication_method'] === 'pre_shared_key') {
            $data['pre_shared_key'] = $validated['pre_shared_key'];
        }

        try {
            $api = new PfSenseApiService($firewall);
            $api->createIpsecPhase1($data);

            return redirect()->route('vpn.ipsec', $firewall)
                ->with('success', 'IPsec tunnel created successfully.');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Failed to create IPsec tunnel: ' . $e->getMessage());
        }
    }

    public function destroyPhase1(Firewall $firewall, string|int $id)
    {
        try {
            $api = new PfSenseApiService($firewall);
            $api->deleteIpsecPhase1($id);

            return redirect()->route('vpn.ipsec', $firewall)
                ->with('success', 'IPsec tunnel deleted successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to delete IPsec tunnel: ' . $e->getMessage());
        }
    }

    public function phase2(Firewall $firewall, string $phase1Id)
    {
        try {
            $api = new PfSenseApiService($firewall);
            $response = $api->getIpsecPhase2s();
            \Illuminate\Support\Facades\Log::info('Phase 2 Response:', $response);

            $ikeid = is_numeric($phase1Id) ? (int) $phase1Id : (string) $phase1Id;
            $phase2List = collect($response['data'] ?? [])->where('ikeid', $ikeid);
            \Illuminate\Support\Facades\Log::info('Filtered Phase 2 List:', $phase2List->toArray());

            return view('vpn.ipsec.phase2', compact('firewall', 'phase1Id', 'phase2List'));
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to fetch Phase 2 entries: ' . $e->getMessage());
        }
    }

    public function storePhase2(Request $request, Firewall $firewall, string $phase1Id)
    {
        $validated = $request->validate([
            'descr' => 'nullable|string',
            'mode' => 'required|string',
            'localid_type' => 'required|string',
            'localid_address' => 'nullable|string',
            'localid_netbits' => 'nullable|integer',
            'remoteid_type' => 'required|string',
            'remoteid_address' => 'nullable|string',
            'remoteid_netbits' => 'nullable|integer',
            'protocol' => 'required|string',
            'encryption_algorithm_name' => 'required|string',
            'encryption_algorithm_keylen' => 'required|string',
            'hash_algorithm' => 'required|array',
            'pfsgroup' => 'required|string',
            'lifetime' => 'nullable|integer',
        ]);

        $data = [
            'ikeid' => is_numeric($phase1Id) ? (int) $phase1Id : (string) $phase1Id,
            'descr' => $validated['descr'] ?? '',
            'mode' => $validated['mode'],
            'localid_type' => $validated['localid_type'],
            'localid_address' => $validated['localid_address'],
            'localid_netbits' => isset($validated['localid_netbits']) ? (int) $validated['localid_netbits'] : null,
            'remoteid_type' => $validated['remoteid_type'],
            'remoteid_address' => $validated['remoteid_address'],
            'remoteid_netbits' => isset($validated['remoteid_netbits']) ? (int) $validated['remoteid_netbits'] : null,
            'protocol' => $validated['protocol'],
            'encryption_algorithm_option' => [
                [
                    'name' => $validated['encryption_algorithm_name'],
                    'keylen' => $validated['encryption_algorithm_keylen'] === 'auto' ? 'auto' : (int) $validated['encryption_algorithm_keylen'],
                ]
            ],
            'hash_algorithm_option' => $validated['hash_algorithm'],
            'pfsgroup' => (int) $validated['pfsgroup'],
            'lifetime' => (int) ($validated['lifetime'] ?? 3600),
        ];

        try {
            $api = new PfSenseApiService($firewall);
            $api->createIpsecPhase2($data);

            return redirect()->route('vpn.ipsec.phase2', [$firewall, $phase1Id])
                ->with('success', 'IPsec Phase 2 tunnel created successfully.');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Failed to create IPsec Phase 2 tunnel: ' . $e->getMessage());
        }
    }

    public function destroyPhase2(Firewall $firewall, string $phase1Id, string $uniqid)
    {
        try {
            $api = new PfSenseApiService($firewall);
            $api->deleteIpsecPhase2($uniqid);

            return redirect()->route('vpn.ipsec.phase2', [$firewall, $phase1Id])
                ->with('success', 'IPsec Phase 2 tunnel deleted successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to delete IPsec Phase 2 tunnel: ' . $e->getMessage());
        }
    }
}
