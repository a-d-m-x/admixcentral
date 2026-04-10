<?php

namespace App\Traits;

use Illuminate\Support\Collection;

trait NormalizesInterfaceData
{
    /**
     * Build a lowercase reverse map from physical device names and IDs to pfSense interface IDs.
     * pfSense may store rules using either the interface ID ('wan') or the physical NIC
     * name ('igb0', 'vmx0', 'igc0', etc.). Rules may also use uppercase ('WAN', 'LAN').
     * This map covers all variants so comparisons work regardless of casing or naming style.
     *
     * @param array $interfaces Raw interfaces array from pfSense API
     * @return array  e.g. ['igc0' => 'wan', 'wan' => 'wan', 'igc1' => 'lan', 'lan' => 'lan']
     */
    protected function buildIfNameToId(array $interfaces): array
    {
        $map = [];
        foreach ($interfaces as $iface) {
            $id   = strtolower($iface['id']   ?? '');
            $if   = strtolower($iface['if']   ?? '');
            $descr = strtolower($iface['descr'] ?? '');

            if ($id) {
                $map[$id] = $id;       // 'wan'   -> 'wan'
            }
            if ($if && $id) {
                $map[$if] = $id;       // 'igc0'  -> 'wan'
            }
            if ($descr && $id) {
                $map[$descr] = $id;    // 'wan' (from descr) -> 'wan'
            }
        }
        return $map;
    }

    /**
     * Normalize a pfSense interface value (string or array, any case) to a lowercase string.
     * When the value is an array (pfSense v2 sometimes wraps in array), the first element is used.
     *
     * @param mixed $value
     * @return string
     */
    protected function normalizeInterface(mixed $value): string
    {
        if (is_array($value)) {
            $value = reset($value) ?: '';
        }
        return strtolower((string) $value);
    }

    /**
     * Filter a rules array by the selected interface, resolving both physical device
     * names and case variations.
     *
     * @param array  $rules            Raw rules from pfSense API
     * @param string $selectedInterface The interface ID to filter by (e.g. 'wan')
     * @param array  $ifNameToId       Map from buildIfNameToId()
     * @return Collection
     */
    protected function filterRulesByInterface(array $rules, string $selectedInterface, array $ifNameToId): Collection
    {
        $selectedLower = strtolower($selectedInterface);

        return collect($rules)->filter(function ($rule) use ($selectedLower, $ifNameToId) {
            if (!isset($rule['interface'])) {
                return false;
            }

            $ruleIfaces = is_array($rule['interface']) ? $rule['interface'] : [$rule['interface']];

            foreach ($ruleIfaces as $ri) {
                $normalized = $ifNameToId[strtolower((string) $ri)] ?? strtolower((string) $ri);
                if ($normalized === $selectedLower) {
                    return true;
                }
            }

            return false;
        })->values();
    }
}
