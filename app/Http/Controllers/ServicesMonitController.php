<?php

namespace App\Http\Controllers;

use App\Models\Firewall;
use Illuminate\Http\Request;

class ServicesMonitController extends Controller
{
    public function index(Firewall $firewall)
    {
        if (!$firewall->isOpnSense()) {
            abort(404, 'Monit is only supported for OPNsense firewalls.');
        }

        $api = $firewall->opnsense();
        $status = [];
        $settings = [];
        $error = null;

        try {
            $status = $api->getMonitStatus();
            $settings = $api->getMonitSettings()['monit']['general'] ?? [];
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }

        return view('services.monit.index', compact('firewall', 'status', 'settings', 'error'));
    }

    public function serviceAction(Firewall $firewall, string $action)
    {
        if (!$firewall->isOpnSense()) {
            abort(404);
        }

        $api = $firewall->opnsense();

        try {
            switch ($action) {
                case 'restart':
                    $api->restartMonitService();
                    break;
                default:
                    return back()->with('error', 'Invalid service action.');
            }

            return back()->with('success', "Monit service {$action}ed successfully.");
        } catch (\Exception $e) {
            return back()->with('error', "Failed to {$action} Monit service: " . $e->getMessage());
        }
    }
}
