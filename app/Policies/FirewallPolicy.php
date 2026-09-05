<?php

namespace App\Policies;

use App\Models\Firewall;
use App\Models\User;

class FirewallPolicy
{
    /**
     * Determine whether the user can view any firewalls.
     */
    public function viewAny(User $user): bool
    {
        return $user->isGlobalAdmin() || !is_null($user->company_id);
    }

    /**
     * Determine whether the user can view the firewall.
     */
    public function view(User $user, Firewall $firewall): bool
    {
        if ($user->isGlobalAdmin()) {
            return true;
        }

        return !is_null($user->company_id) && (int) $user->company_id === (int) $firewall->company_id;
    }

    /**
     * Determine whether the user can create firewalls.
     */
    public function create(User $user): bool
    {
        // Only global admin or company admin can create firewalls
        return $user->isGlobalAdmin() || $user->isCompanyAdmin();
    }

    /**
     * Determine whether the user can update the firewall.
     */
    public function update(User $user, Firewall $firewall): bool
    {
        if ($user->isGlobalAdmin()) {
            return true;
        }

        if ($user->isCompanyAdmin()) {
            return (int) $user->company_id === (int) $firewall->company_id;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the firewall.
     */
    public function delete(User $user, Firewall $firewall): bool
    {
        if ($user->isGlobalAdmin()) {
            return true;
        }

        if ($user->isCompanyAdmin()) {
            return (int) $user->company_id === (int) $firewall->company_id;
        }

        return false;
    }

    /**
     * Determine whether the user can reassign the firewall to a different company.
     */
    public function reassignCompany(User $user, Firewall $firewall): bool
    {
        // Strictly global admin only
        return $user->isGlobalAdmin();
    }
}
