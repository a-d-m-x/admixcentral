<?php

namespace App\Http\Controllers;

use App\Models\Firewall;
use Illuminate\Http\Request;

class ServicesIdsController extends Controller
{
    public function index(Firewall $firewall)
    {
        if (!$firewall->isOpnSense()) {
            abort(404, 'Intrusion Detection is only supported for OPNsense firewalls.');
        }

        $api = $firewall->opnsense();
        $status = [];
        $settings = [];
        $alerts = [];
        $error = null;

        try {
            $status = $api->getIdsStatus();
            $settings = $api->getIdsSettings()['ids']['general'] ?? [];
            $alerts = $api->getIdsAlerts();
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }

        return view('services.ids.index', compact('firewall', 'status', 'settings', 'alerts', 'error'));
    }

    public function serviceAction(Firewall $firewall, string $action)
    {
        if (!$firewall->isOpnSense()) {
            abort(404);
        }

        $api = $firewall->opnsense();

        try {
            switch ($action) {
                case 'start':
                    $api->startIdsService();
                    break;
                case 'stop':
                    $api->stopIdsService();
                    break;
                case 'restart':
                    $api->restartIdsService();
                    break;
                default:
                    return back()->with('error', 'Invalid service action.');
            }

            return back()->with('success', "Intrusion Detection service {$action}ed successfully.");
        } catch (\Exception $e) {
            return back()->with('error', "Failed to {$action} Intrusion Detection service: " . $e->getMessage());
        }
    }
}
