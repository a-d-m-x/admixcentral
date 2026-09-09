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
            'encryption_algorithm_keylen' => 'nullable',
            'hash_algorithm' => 'required|string',
            'dhgroup' => 'required|integer',
            'lifetime' => 'nullable|integer|min:60',
        ]);

        $algo = $validated['encryption_algorithm_name'];
        $encItem = [
            'encryption_algorithm_name' => $algo,
            'hash_algorithm' => $validated['hash_algorithm'],
            'dhgroup' => (int) $validated['dhgroup'],
        ];

        // Key length is only relevant for algorithms with selectable key lengths
        if (in_array($algo, ['aes', 'aes128gcm', 'aes192gcm', 'aes256gcm'])) {
            $rawKeylen = $request->input('encryption_algorithm_keylen');
            $encItem['encryption_algorithm_keylen'] = !empty($rawKeylen) ? (int) $rawKeylen : 128;
        }

        $data = [
            'iketype' => $validated['iketype'],
            'protocol' => $validated['protocol'],
            'interface' => $validated['interface'],
            'remote_gateway' => $validated['remote_gateway'],
            'descr' => $validated['descr'] ?? '',
            'authentication_method' => $validated['authentication_method'],
            'myid_type' => $validated['myid_type'],
            'peerid_type' => $validated['peerid_type'],
            'encryption' => [$encItem],
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
            'encryption_algorithm_keylen' => 'nullable',
            'hash_algorithm' => 'required|array',
            'pfsgroup' => 'required|integer',
            'lifetime' => 'nullable|integer',
        ]);

        $algo = $validated['encryption_algorithm_name'];
        $rawKeylen = $request->input('encryption_algorithm_keylen');
        // pfSense RESTAPI requires integer 0 for auto, or integer keylen. NEVER the string 'auto'!
        $keylen = ($rawKeylen === 'auto' || $rawKeylen === '0' || empty($rawKeylen)) ? 0 : (int) $rawKeylen;

        $encOption = [
            'name' => $algo,
        ];
        if (in_array($algo, ['aes', 'aes128gcm', 'aes192gcm', 'aes256gcm', 'blowfish'])) {
            $encOption['keylen'] = $keylen;
        }

        $localAddress = in_array($validated['localid_type'], ['address', 'network']) ? ($validated['localid_address'] ?? null) : null;
        $localNetbits = ($validated['localid_type'] === 'network' && isset($validated['localid_netbits']) && $validated['localid_netbits'] !== '')
            ? (int) $validated['localid_netbits'] : null;

        $remoteAddress = in_array($validated['remoteid_type'], ['address', 'network']) ? ($validated['remoteid_address'] ?? null) : null;
        $remoteNetbits = ($validated['remoteid_type'] === 'network' && isset($validated['remoteid_netbits']) && $validated['remoteid_netbits'] !== '')
            ? (int) $validated['remoteid_netbits'] : null;

        $data = [
            'ikeid' => is_numeric($phase1Id) ? (int) $phase1Id : (string) $phase1Id,
            'descr' => $validated['descr'] ?? '',
            'mode' => $validated['mode'],
            'localid_type' => $validated['localid_type'],
            'localid_address' => $localAddress,
            'localid_netbits' => $localNetbits,
            'remoteid_type' => $validated['remoteid_type'],
            'remoteid_address' => $remoteAddress,
            'remoteid_netbits' => $remoteNetbits,
            'protocol' => $validated['protocol'],
            'encryption_algorithm_option' => [$encOption],
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
