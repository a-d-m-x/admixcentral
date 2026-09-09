<?php

namespace App\Services;

use App\Models\Firewall;

/**
 * Factory that instantiates the correct API service for a given firewall.
 *
 * - OPNsense firewalls → OpnSenseApiService (direct, no delegation layer)
 * - pfSense firewalls  → PfSenseApiService
 *
 * Both services expose the same status surface:
 *   refreshSystemStatus(): array
 *   setApiTimeout(int $seconds): static
 */
class FirewallApiFactory
{
    /**
     * @return OpnSenseApiService|PfSenseApiService
     */
    public static function make(Firewall $firewall): OpnSenseApiService|PfSenseApiService
    {
        if ($firewall->isOpnSense()) {
            return new OpnSenseApiService($firewall);
        }

        return new PfSenseApiService($firewall);
    }
}
