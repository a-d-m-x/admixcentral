<?php

use App\Models\Firewall;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

// Device channel - for sending commands to devices
Broadcast::channel('device.{firewallId}', function ($user, $firewallId) {
    // This channel is strictly for devices only, never web users
    if ($user instanceof User) {
        return false;
    }

    if (is_object($user) && isset($user->firewall_id)) {
        return (int) $user->firewall_id === (int) $firewallId;
    }

    return false;
});

// Firewall dashboard channel - for real-time updates to users
Broadcast::channel('firewall.{firewallId}', function ($user, $firewallId) {
    $firewall = Firewall::find($firewallId);

    if (!$firewall) {
        return false;
    }

    // Global admins can access any firewall
    if ($user->isGlobalAdmin()) {
        return true;
    }

    // Tenant users can only access their company's firewalls
    return !is_null($user->company_id) && (int) $user->company_id === (int) $firewall->company_id;
});
