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
        $services = [];
        $alerts = [];
        $tests = [];
        $error = null;

        try {
            $status = $api->getMonitServiceStatus();
            $settingsData = $api->getMonitSettings();
            $settings = $settingsData['monit']['general'] ?? [];
            $services = $api->getMonitServices()['data'] ?? [];
            $alerts = $api->getMonitAlerts()['data'] ?? [];
            $tests = $api->getMonitTests()['data'] ?? [];
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        if (auth()->user()->isReadOnly()) {
            unset($settings['password']);
        }

        return view('services.monit.index', compact('firewall', 'status', 'settings', 'services', 'alerts', 'tests', 'error'));
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
                    $api->startMonitService();
                    break;
                case 'stop':
                    $api->stopMonitService();
                    break;
                case 'restart':
                    $api->restartMonitService();
                    break;
                case 'reconfigure':
                    $api->reconfigureMonitService();
                    break;
                default:
                    return back()->with('error', 'Invalid service action.');
            }

            $label = $action === 'reconfigure' ? 'reconfigured' : "{$action}ed";
            return back()->with('success', "Monit service {$label} successfully.");
        } catch (\Throwable $e) {
            return back()->with('error', "Failed to {$action} Monit service: " . $e->getMessage());
        }
    }

    public function updateSettings(Request $request, Firewall $firewall)
    {
        if (!$firewall->isOpnSense()) {
            abort(404);
        }

        $request->validate([
            'interval' => 'required|integer|min:1',
            'startdelay' => 'required|integer|min:0',
            'mailserver' => 'nullable|string',
            'port' => 'nullable|integer',
            'username' => 'nullable|string',
            'password' => 'nullable|string',
            'httpdPort' => 'nullable|integer',
        ]);

        $api = $firewall->opnsense();

        try {
            $payload = [
                'enabled' => $request->has('enabled') ? '1' : '0',
                'interval' => (string) $request->input('interval', '120'),
                'startdelay' => (string) $request->input('startdelay', '120'),
                'mailserver' => (string) ($request->input('mailserver') ?? ''),
                'port' => (string) ($request->input('port') ?? '25'),
                'username' => (string) ($request->input('username') ?? ''),
                'password' => (string) ($request->input('password') ?? ''),
                'ssl' => $request->has('ssl') ? '1' : '0',
                'sslverify' => $request->has('sslverify') ? '1' : '0',
                'httpdEnabled' => $request->has('httpdEnabled') ? '1' : '0',
                'httpdPort' => (string) ($request->input('httpdPort') ?? '2812'),
            ];

            $api->updateMonitSettings($payload);
            $api->reconfigureMonitService();

            return back()->with('success', 'Monit general settings updated successfully.');
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Failed to update Monit settings: ' . $e->getMessage());
        }
    }

    public function storeService(Request $request, Firewall $firewall)
    {
        if (!$firewall->isOpnSense()) {
            abort(404);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string',
            'description' => 'nullable|string',
            'pidfile' => 'nullable|string',
            'path' => 'nullable|string',
            'address' => 'nullable|string',
        ]);

        $api = $firewall->opnsense();

        try {
            $tests = $request->input('tests', []);
            if (is_array($tests)) {
                $tests = implode(',', array_filter($tests));
            }

            $payload = [
                'enabled' => $request->has('enabled') ? '1' : '0',
                'name' => $request->input('name'),
                'type' => $request->input('type'),
                'description' => (string) ($request->input('description') ?? ''),
                'pidfile' => (string) ($request->input('pidfile') ?? ''),
                'path' => (string) ($request->input('path') ?? ''),
                'address' => (string) ($request->input('address') ?? ''),
                'tests' => (string) ($tests ?? ''),
            ];

            $api->createMonitService($payload);
            $api->reconfigureMonitService();

            return back()->with('success', "Monit service '{$request->input('name')}' created successfully.");
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Failed to create Monit service: ' . $e->getMessage());
        }
    }

    public function toggleService(Firewall $firewall, string $uuid)
    {
        if (!$firewall->isOpnSense()) {
            abort(404);
        }

        $api = $firewall->opnsense();

        try {
            $res = $api->toggleMonitService($uuid);
            $api->reconfigureMonitService();

            return back()->with('success', 'Monit service toggled: ' . ($res['result'] ?? 'Success'));
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed to toggle Monit service: ' . $e->getMessage());
        }
    }

    public function destroyService(Firewall $firewall, string $uuid)
    {
        if (!$firewall->isOpnSense()) {
            abort(404);
        }

        $api = $firewall->opnsense();

        try {
            $api->deleteMonitService($uuid);
            $api->reconfigureMonitService();

            return back()->with('success', 'Monit service deleted successfully.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed to delete Monit service: ' . $e->getMessage());
        }
    }

    public function storeAlert(Request $request, Firewall $firewall)
    {
        if (!$firewall->isOpnSense()) {
            abort(404);
        }

        $request->validate([
            'recipient' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
        ]);

        $api = $firewall->opnsense();

        try {
            $payload = [
                'enabled' => $request->has('enabled') ? '1' : '0',
                'recipient' => $request->input('recipient'),
                'description' => (string) ($request->input('description') ?? ''),
                'noton' => $request->has('noton') ? '1' : '0',
            ];

            $api->createMonitAlert($payload);
            $api->reconfigureMonitService();

            return back()->with('success', "Monit alert recipient '{$request->input('recipient')}' added successfully.");
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Failed to create Monit alert: ' . $e->getMessage());
        }
    }

    public function toggleAlert(Firewall $firewall, string $uuid)
    {
        if (!$firewall->isOpnSense()) {
            abort(404);
        }

        $api = $firewall->opnsense();

        try {
            $res = $api->toggleMonitAlert($uuid);
            $api->reconfigureMonitService();

            return back()->with('success', 'Monit alert toggled: ' . ($res['result'] ?? 'Success'));
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed to toggle Monit alert: ' . $e->getMessage());
        }
    }

    public function destroyAlert(Firewall $firewall, string $uuid)
    {
        if (!$firewall->isOpnSense()) {
            abort(404);
        }

        $api = $firewall->opnsense();

        try {
            $api->deleteMonitAlert($uuid);
            $api->reconfigureMonitService();

            return back()->with('success', 'Monit alert deleted successfully.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed to delete Monit alert: ' . $e->getMessage());
        }
    }
}
