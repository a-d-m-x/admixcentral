<?php

namespace App\Http\Controllers;

use App\Models\Firewall;
use App\Services\PfSenseApiService;
use Illuminate\Http\Request;

class InterfacesController extends Controller
{
    public function assignments(Firewall $firewall)
    {
        $api = new PfSenseApiService($firewall);
        $interfaces = [];
        $availablePorts = [];
        $error = null;

        try {
            $interfaces = $api->getInterfaces()['data'] ?? [];
            $availablePorts = $this->normalizeAvailablePorts($api->getAvailableInterfaces()['data'] ?? []);
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }

        return view('interfaces.assignments', compact('firewall', 'interfaces', 'availablePorts', 'error'));
    }

    public function storeAssignment(Firewall $firewall, Request $request)
    {
        $api = new PfSenseApiService($firewall);
        $data = $request->validate([
            'if' => 'required|string',
            'descr' => 'nullable|string',
        ]);

        try {
            $api->createInterface($data);
            return redirect()->route('interfaces.assignments', $firewall)->with('success', 'Interface assigned successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to assign interface: ' . $e->getMessage());
        }
    }

    public function destroyAssignment(Firewall $firewall, string $id)
    {
        $api = new PfSenseApiService($firewall);
        try {
            $api->deleteInterface($id);
            return redirect()->route('interfaces.assignments', $firewall)->with('success', 'Interface unassigned successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to unassign interface: ' . $e->getMessage());
        }
    }

    public function vlans(Firewall $firewall)
    {
        $api = new PfSenseApiService($firewall);
        $vlans = [];
        $error = null;

        try {
            $vlans = $api->getVlans()['data'] ?? [];
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }

        return view('interfaces.vlans.index', compact('firewall', 'vlans', 'error'));
    }

    public function createVlan(Firewall $firewall)
    {
        $api = new PfSenseApiService($firewall);
        $interfaces = [];
        try {
            $interfaces = $this->normalizeAvailablePorts($api->getAvailableInterfaces()['data'] ?? []);
        } catch (\Exception $e) {
            // Ignore
        }
        return view('interfaces.vlans.create', compact('firewall', 'interfaces'));
    }

    public function storeVlan(Firewall $firewall, Request $request)
    {
        $api = new PfSenseApiService($firewall);
        $data = $request->validate([
            'if' => 'required|string',
            'tag' => 'required|integer',
            'descr' => 'nullable|string',
            'pcp' => 'nullable|integer',
        ]);

        // Explicitly cast to integer for API
        $data['tag'] = (int) $data['tag'];
        if (isset($data['pcp'])) {
            $data['pcp'] = (int) $data['pcp'];
        }

        try {
            $api->createVlan($data);
            return redirect()->route('interfaces.vlans.index', $firewall)->with('success', 'VLAN created successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to create VLAN: ' . $e->getMessage())->withInput();
        }
    }

    public function editVlan(Firewall $firewall, string $id)
    {
        $api = new PfSenseApiService($firewall);
        $vlan = null;
        $interfaces = [];
        try {
            $vlans = $api->getVlans()['data'] ?? [];
            foreach ($vlans as $v) {
                if ((string) $v['vlanif'] === $id) { // Assuming vlanif is the ID or we use index?
                    // Wait, API usually uses 'id' or 'vlanif'.
                    // Let's check getVlans response structure if possible.
                    // For now assume we find it by matching ID.
                    $vlan = $v;
                    break;
                }
            }
            // If not found by vlanif, maybe $id is the index?
            // The API deleteVlan takes 'id'.

            $interfaces = $this->normalizeAvailablePorts($api->getAvailableInterfaces()['data'] ?? []);
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to fetch VLAN data: ' . $e->getMessage());
        }

        if (!$vlan) {
            // Fallback: try to find by array index if ID is numeric?
            // Or just pass the ID and let the view handle it if we had a direct getVlan($id).
            // But we don't.
            // Let's assume $id passed in URL is the 'vlanif' (e.g. 'vlan0').
            // If we can't find it, we can't edit it.
            // Actually, usually we edit by ID (0, 1, 2...).
            // Let's assume ID is the index or ID returned by API.
            // I'll update logic to look for 'id' key if present, or match 'vlanif'.
            foreach ($vlans as $index => $v) {
                if ((string) $index === $id) {
                    $vlan = $v;
                    break;
                }
                if (isset($v['id']) && (string) $v['id'] === $id) {
                    $vlan = $v;
                    break;
                }
                if (isset($v['vlanif']) && $v['vlanif'] === $id) {
                    $vlan = $v;
                    break;
                }
            }
        }

        return view('interfaces.vlans.edit', compact('firewall', 'vlan', 'interfaces', 'id'));
    }

    public function updateVlan(Firewall $firewall, string $id, Request $request)
    {
        $api = new PfSenseApiService($firewall);
        $data = $request->validate([
            'if' => 'required|string',
            'tag' => 'required|integer',
            'descr' => 'nullable|string',
            'pcp' => 'nullable|integer',
        ]);

        // Explicitly cast to integer for API
        $data['tag'] = (int) $data['tag'];
        if (isset($data['pcp'])) {
            $data['pcp'] = (int) $data['pcp'];
        }

        // API updateVlan expects ID.
        // If ID is numeric index, we pass it.
        // If ID is vlanif, we might need to pass that.
        // Existing service method: updateVlan(int $id, array $data) -> patch("/interface/vlan?id={$id}", $data)
        // So it expects an INT id.

        try {
            $api->updateVlan((int) $id, $data);
            return redirect()->route('interfaces.vlans.index', $firewall)->with('success', 'VLAN updated successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to update VLAN: ' . $e->getMessage())->withInput();
        }
    }

    public function destroyVlan(Firewall $firewall, string $id)
    {
        $api = new PfSenseApiService($firewall);
        try {
            $api->deleteVlan((int) $id);
            return redirect()->route('interfaces.vlans.index', $firewall)->with('success', 'VLAN deleted successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to delete VLAN: ' . $e->getMessage());
        }
    }

    private function normalizeAvailablePorts(array $ports): array
    {
        $normalized = [];
        foreach ($ports as $k => $v) {
            $ifName = (is_array($v) && isset($v['if'])) ? $v['if'] : (is_string($k) ? $k : null);
            if ($ifName) {
                $normalized[$ifName] = is_array($v) ? $v : [];
                $normalized[$ifName]['if'] = $ifName;
            }
        }
        return $normalized;
    }
}
