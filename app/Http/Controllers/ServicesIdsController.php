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
        $rulesets = [];
        $userRules = [];
        $error = null;

        try {
            $status = $api->getIdsStatus();
            $settingsData = $api->getIdsSettings();
            $settings = $settingsData['ids']['general'] ?? [];
            $alerts = $api->getIdsAlerts();
            $rulesets = $api->getIdsRulesets()['rows'] ?? [];
            $userRules = $api->getIdsUserRules()['rows'] ?? [];
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        return view('services.ids.index', compact('firewall', 'status', 'settings', 'alerts', 'rulesets', 'userRules', 'error'));
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
                case 'reconfigure':
                    $api->reconfigureIdsService();
                    break;
                case 'update-rules':
                    $api->updateIdsRules();
                    break;
                default:
                    return back()->with('error', 'Invalid service action.');
            }

            $label = $action === 'reconfigure' ? 'reconfigured' : ($action === 'update-rules' ? 'rules updated' : "{$action}ed");
            return back()->with('success', "Intrusion Detection service {$label} successfully.");
        } catch (\Throwable $e) {
            return back()->with('error', "Failed to {$action} Intrusion Detection service: " . $e->getMessage());
        }
    }

    public function updateSettings(Request $request, Firewall $firewall)
    {
        if (!$firewall->isOpnSense()) {
            abort(404);
        }

        $api = $firewall->opnsense();

        try {
            $payload = [
                'enabled' => $request->has('enabled') ? '1' : '0',
                'promisc' => $request->has('promisc') ? '1' : '0',
                'syslog' => $request->has('syslog') ? '1' : '0',
                'syslog_eve' => $request->has('syslog_eve') ? '1' : '0',
                'LogPayload' => $request->has('LogPayload') ? '1' : '0',
            ];

            if ($request->filled('mode')) {
                $payload['mode'] = $request->input('mode');
            }

            $api->updateIdsSettings($payload);
            $api->reconfigureIdsService();

            return back()->with('success', 'Intrusion Detection settings updated successfully.');
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Failed to update IDS settings: ' . $e->getMessage());
        }
    }

    public function toggleRuleset(Firewall $firewall, string $filename)
    {
        if (!$firewall->isOpnSense()) {
            abort(404);
        }

        $api = $firewall->opnsense();

        try {
            $res = $api->toggleIdsRuleset($filename);
            $api->reconfigureIdsService();

            $statusText = ($res['status'] ?? '') === '1' ? 'enabled' : 'disabled';
            return back()->with('success', "Ruleset '{$filename}' {$statusText}.");
        } catch (\Throwable $e) {
            return back()->with('error', "Failed to toggle ruleset: " . $e->getMessage());
        }
    }

    public function storeUserRule(Request $request, Firewall $firewall)
    {
        if (!$firewall->isOpnSense()) {
            abort(404);
        }

        $request->validate([
            'action' => 'required|in:alert,drop,pass',
            'description' => 'nullable|string|max:255',
            'source' => 'nullable|string|max:255',
            'destination' => 'nullable|string|max:255',
            'fingerprint' => 'nullable|string|max:255',
        ]);

        $api = $firewall->opnsense();

        try {
            $payload = [
                'enabled' => $request->has('enabled') ? '1' : '0',
                'action' => $request->input('action'),
                'description' => (string) ($request->input('description') ?? ''),
                'source' => (string) ($request->input('source') ?? ''),
                'destination' => (string) ($request->input('destination') ?? ''),
                'fingerprint' => (string) ($request->input('fingerprint') ?? ''),
            ];

            $api->createIdsUserRule($payload);
            $api->reconfigureIdsService();

            return back()->with('success', "User rule created successfully.");
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Failed to create user rule: ' . $e->getMessage());
        }
    }

    public function toggleUserRule(Firewall $firewall, string $uuid)
    {
        if (!$firewall->isOpnSense()) {
            abort(404);
        }

        $api = $firewall->opnsense();

        try {
            $res = $api->toggleIdsUserRule($uuid);
            $api->reconfigureIdsService();

            return back()->with('success', 'User rule toggled: ' . ($res['result'] ?? 'Success'));
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed to toggle user rule: ' . $e->getMessage());
        }
    }

    public function destroyUserRule(Firewall $firewall, string $uuid)
    {
        if (!$firewall->isOpnSense()) {
            abort(404);
        }

        $api = $firewall->opnsense();

        try {
            $api->deleteIdsUserRule($uuid);
            $api->reconfigureIdsService();

            return back()->with('success', 'User rule deleted successfully.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed to delete user rule: ' . $e->getMessage());
        }
    }
}

