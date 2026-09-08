<?php

namespace App\Http\Controllers;

use App\Models\Firewall;
use App\Services\PfSenseApiService;
use Illuminate\Http\Request;

class SystemController extends Controller
{
    /**
     * Display the Advanced System Settings page.
     *
     * Fetches different data sets based on the selected 'tab' (admin, firewall, notifications, etc.).
     *
     * @param Firewall $firewall
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function advanced(Firewall $firewall, Request $request)
    {
        $api = new PfSenseApiService($firewall);
        $tab = $request->query('tab', 'admin');

        $data = [
            'tunables' => [], // Default to empty array
        ];
        try {
            switch ($tab) {
                case 'admin':
                    $data['webgui'] = $api->getSystemWebGui()['data'] ?? [];
                    $data['ssh'] = $api->getSystemSsh()['data'] ?? [];
                    $data['console'] = $api->getSystemConsole()['data'] ?? [];
                    break;
                case 'firewall':
                    $data['firewall'] = $api->getSystemFirewallAdvanced()['data'] ?? [];
                    // Fix for API returning PHP_INT_MAX (9223372036854775807) when value is unset
                    if (isset($data['firewall']['aliasesresolveinterval']) && (string) $data['firewall']['aliasesresolveinterval'] == '9223372036854775807') {
                        $data['firewall']['aliasesresolveinterval'] = null;
                    }
                    break;
                case 'notifications':
                    $data['notifications'] = $api->getSystemNotifications()['data'] ?? [];
                    break;
                case 'tunables':
                    try {
                        $data['tunables'] = $api->getSystemTunables()['data'] ?? [];
                    } catch (\Exception $e) {
                        \Illuminate\Support\Facades\Log::error("Failed to fetch tunables: " . $e->getMessage());
                        $data['tunables'] = [];
                    }

                    // Sync dirty state with firewall
                    try {
                        $isDirty = $api->getDirtyState();

                        // Sync only if changed
                        if ($firewall->is_dirty !== $isDirty) {
                            $firewall->is_dirty = $isDirty;
                            $firewall->save();
                            \Illuminate\Support\Facades\Log::info("Synced dirty state for Firewall {$firewall->id} to " . ($isDirty ? 'true' : 'false'));
                        }
                    } catch (\Exception $e) {
                        \Illuminate\Support\Facades\Log::warning("Failed to sync dirty state: " . $e->getMessage());
                    }
                    break;
                case 'networking':
                case 'miscellaneous':
                    // Not supported by API yet
                    break;
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Failed to fetch data for tab {$tab}: " . $e->getMessage());
            // If the API call fails (e.g. 404), we probably want to show the 'Not Supported' view
            // or at least not crash.
            // For now, let's allow the view to render with empty data, but maybe pass an error flag.
            // Or better, if it's a 404, specifically handle it.
            if ($e->getCode() == 404) {
                return view('system.advanced-not-supported', compact('firewall', 'tab'));
            }
        }

        return view('system.advanced', compact('firewall', 'tab', 'data'));
    }

    /**
     * Update Advanced System Settings.
     *
     * Handles form submissions for various tabs (Admin Access, Firewall/NAT, Notifications).
     * Delegates to specific API methods based on the tab.
     */
    public function updateAdvanced(Firewall $firewall, Request $request)
    {
        $api = new PfSenseApiService($firewall);
        $tab = $request->input('tab', 'admin');

        try {
            switch ($tab) {
                case 'admin':
                    $validated = $request->validate([
                        'protocol' => 'required|in:http,https',
                        'port' => 'nullable|integer',
                        'sslcertref' => 'nullable|string',
                        'ssh_port' => 'nullable|integer',
                    ]);
                    $webGuiData = [
                        'protocol' => $validated['protocol'],
                        'port' => $validated['port'],
                        'sslcertref' => $validated['sslcertref'] ?? '',
                    ];

                    // Handle checkboxes/booleans if needed, though API inspection showed strings/empty.
                    // SSH
                    $sshData = [
                        'enable' => $request->has('ssh_enable') ? '1' : '', // API returned '1' or empty
                        'port' => $request->input('ssh_port'),
                        'sshdkeyonly' => $request->has('sshdkeyonly') ? 'enabled' : '', // Guessing 'enabled' or '1' based on common patterns, need to verify. API returned empty.
                        'sshdagentforwarding' => $request->has('sshdagentforwarding') ? 'enabled' : '',
                    ];
                    // Console
                    $consoleData = [
                        'passwd_protect_console' => $request->has('passwd_protect_console') ? '1' : '',
                    ];

                    $api->updateSystemWebGui($webGuiData);
                    $api->updateSystemSsh($sshData);
                    $api->updateSystemConsole($consoleData);
                    break;

                case 'firewall':
                    $firewallData = [
                        'aliasesresolveinterval' => (int) $request->input('aliasesresolveinterval'),
                        'checkaliasesurlcert' => $request->has('checkaliasesurlcert'),
                    ];
                    $api->updateSystemFirewallAdvanced($firewallData);
                    break;

                case 'notifications':
                    // Identify which sub-section we are saving based on hidden 'type' field or unique keys
                    $type = $request->input('notification_type', 'smtp');

                    if ($type === 'smtp') {
                        $notifyData = $request->except(['_token', '_method', 'tab', 'notification_type']);
                        // Checkboxes handling
                        $checkboxes = ['ssl', 'sslvalidate'];
                        foreach ($checkboxes as $chk) {
                            if (!$request->has($chk)) {
                                $notifyData[$chk] = false;
                            } else {
                                $notifyData[$chk] = true;
                            }
                        }

                        // Enforce LOGIN mechanism if username is provided
                        if (!empty($notifyData['username']) && empty($notifyData['authentication_mechanism'])) {
                            $notifyData['authentication_mechanism'] = 'LOGIN';
                        } elseif (!empty($notifyData['username']) && ($notifyData['authentication_mechanism'] ?? '') === 'PLAIN') {
                            $notifyData['authentication_mechanism'] = 'LOGIN';
                        }

                        $api->updateSystemNotifications($notifyData);

                    } elseif ($type === 'telegram') {
                        $telegramData = [
                            'enable' => $request->has('telegram_enable'),
                            'api' => $request->input('telegram_api'),
                            'chatid' => $request->input('telegram_chatid'),
                        ];
                        $api->updateSystemNotificationsTelegram($telegramData);

                    } elseif ($type === 'pushover') {
                        $pushoverData = [
                            'enable' => $request->has('pushover_enable'),
                            'apikey' => $request->input('pushover_apikey'),
                            'userkey' => $request->input('pushover_userkey'),
                            'sound' => $request->input('pushover_sound'),
                            'priority' => $request->input('pushover_priority'),
                        ];
                        $api->updateSystemNotificationsPushover($pushoverData);

                    } elseif ($type === 'slack') {
                        $slackData = [
                            'enable' => $request->has('slack_enable'),
                            'api' => $request->input('slack_api'),
                            'channel' => $request->input('slack_channel'),
                        ];
                        $api->updateSystemNotificationsSlack($slackData);

                    } elseif ($type === 'sounds') {
                        $soundsData = [
                            'disablebeep' => $request->has('disablebeep'),
                        ];
                        $api->updateSystemNotificationsSounds($soundsData);
                    }
                    break;
            }

            $firewall->update(['is_dirty' => true]);

            return redirect()->route('system.advanced', ['firewall' => $firewall, 'tab' => $tab])
                ->with('success', 'System settings updated successfully.');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('System Settings Update Failed: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Failed to update system settings. Please check logs for details.']);
        }
    }

    public function storeTunable(Request $request, Firewall $firewall)
    {
        $request->validate([
            'tunable' => 'required|string',
            'value' => 'required|string',
            'descr' => 'nullable|string',
        ]);

        try {
            $api = new PfSenseApiService($firewall);
            $api->createSystemTunable($request->only(['tunable', 'value', 'descr']));
            $firewall->update(['is_dirty' => true]);

            return redirect()->route('system.advanced', ['firewall' => $firewall, 'tab' => 'tunables'])
                ->with('success', 'Tunable created successfully.');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Tunable Creation Failed: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Failed to create tunable.']);
        }
    }

    public function updateTunable(Request $request, Firewall $firewall, string $id)
    {
        $request->validate([
            'tunable' => 'required|string',
            'value' => 'required|string',
            'descr' => 'nullable|string',
        ]);

        try {
            $api = new PfSenseApiService($firewall);
            // The API update method usually takes the ID as a parameter or part of the data.
            // Based on PfSenseApiService::updateSystemTunable(array $data), it likely expects the ID in the data or as a query param.
            // Let's check the service method signature again. It was updateSystemTunable(array $data).
            // Usually for updates we need to pass the ID. Let's assume the ID needs to be in the data array or passed separately.
            // Looking at other update methods in this project, they often take $data.
            // Let's assume we need to pass the ID in the data array for now, or check if the service handles it.
            // Actually, for a specific item update, we often need the ID.
            // Let's pass the ID in the data array as 'id' or similar if the API requires it.
            // Re-reading the service definition: updateSystemTunable(array $data).
            // It's likely we need to merge the ID into the data.
            $data = $request->only(['tunable', 'value', 'descr']);
            $data['id'] = $id;
            $api->updateSystemTunable($data);
            $firewall->update(['is_dirty' => true]);

            return redirect()->route('system.advanced', ['firewall' => $firewall, 'tab' => 'tunables'])
                ->with('success', 'Tunable updated successfully.');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Tunable Update Failed: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Failed to update tunable.']);
        }
    }

    public function destroyTunable(Firewall $firewall, string $id)
    {
        try {
            $api = new PfSenseApiService($firewall);
            $api->deleteSystemTunable($id);
            $firewall->update(['is_dirty' => true]);

            return redirect()->route('system.advanced', ['firewall' => $firewall, 'tab' => 'tunables'])
                ->with('success', 'Tunable deleted successfully.');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Tunable Deletion Failed: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Failed to delete tunable.']);
        }
    }

    public function applyTunables(Firewall $firewall)
    {
        try {
            $api = new PfSenseApiService($firewall);
            $api->applySystemTunables();

            $firewall->is_dirty = false;
            $firewall->save();

            \Illuminate\Support\Facades\Log::info("Applied tunables for Firewall ID: {$firewall->id}, is_dirty cleared.");

            return redirect()->route('system.advanced', ['firewall' => $firewall, 'tab' => 'tunables'])
                ->with('success', 'Tunable changes applied successfully.');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Tunable Application Failed: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Failed to apply changes.']);
        }
    }

    public function generalSetup(Firewall $firewall)
    {
        try {
            $api = new \App\Services\PfSenseApiService($firewall);
            $hostname = $api->getSystemHostname()['data'] ?? [];
            $timezone = $api->getSystemTimezone()['data'] ?? [];
            $dns = $api->getSystemDns()['data'] ?? [];
        } catch (\Exception $e) {
            $hostname = [];
            $timezone = [];
            $dns = [];
            session()->flash('error', 'Failed to fetch system settings: ' . $e->getMessage());
        }



        $timezones = \DateTimeZone::listIdentifiers();

        return view('system.general', compact('firewall', 'hostname', 'timezone', 'dns', 'timezones'));
    }

    /**
     * Update General Setup (Hostname, Timezone, DNS).
     */
    public function updateGeneralSetup(Request $request, Firewall $firewall)
    {
        try {
            $api = new \App\Services\PfSenseApiService($firewall);

            // Update Hostname
            if ($request->has('hostname') || $request->has('domain')) {
                $validated = $request->validate([
                    'hostname' => 'nullable|string|max:63',
                    'domain' => 'nullable|string|max:255',
                ]);
                $api->updateSystemHostname($request->only(['hostname', 'domain']));
            }

            // Update Timezone
            if ($request->has('timezone')) {
                $api->updateSystemTimezone($request->only(['timezone']));
            }

            // Update DNS
            // Note: dnsserver is usually an array, but form might send it differently.
            // Assuming form sends array of servers.
            $dnsData = [
                'dnsserver' => array_filter($request->input('dnsserver', [])),
                'dnsallowoverride' => $request->has('dnsallowoverride'),
                'dnslocalhost' => $request->has('dnslocalhost') ? 'remote' : 'local',
            ];
            $api->updateSystemDns($dnsData);

            $firewall->update(['is_dirty' => true]);

            return redirect()->route('system.general-setup', $firewall)->with('success', 'System settings updated successfully.');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('General Setup Update Failed: ' . $e->getMessage());
            return back()->with('error', 'Failed to update system settings.');
        }
    }

    public function highAvailSync(Firewall $firewall)
    {
        return view('system.high-avail-sync', compact('firewall'));
    }

    public function packageManager(Firewall $firewall)
    {
        return view('system.package-manager', compact('firewall'));
    }

    public function routing(Firewall $firewall)
    {
        return view('system.routing', compact('firewall'));
    }

    public function update(Firewall $firewall)
    {
        $api = new \App\Services\PfSenseApiService($firewall);
        $version = [];
        $firmwareStatus = [];
        $upgradeStatus = [];

        try {
            $version = $api->getSystemVersion()['data'] ?? [];
            if ($firewall->isOpnSense()) {
                $firmwareStatus = $api->getFirmwareStatus();
                $upgradeStatus = $api->getFirmwareUpgradeStatus();
            }
        } catch (\Exception $e) {
            // Log error
        }

        return view('system.update', compact('firewall', 'version', 'firmwareStatus', 'upgradeStatus'));
    }

    public function checkFirmware(Firewall $firewall)
    {
        try {
            $api = new \App\Services\PfSenseApiService($firewall);
            $res = $api->checkFirmwareUpdates();
            return back()->with('success', 'Firmware update check initiated: ' . ($res['status'] ?? 'ok'));
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to check firmware updates: ' . $e->getMessage());
        }
    }

    public function auditFirmware(Firewall $firewall)
    {
        try {
            $api = new \App\Services\PfSenseApiService($firewall);
            $res = $api->auditFirmware();
            return back()->with('success', 'Security audit initiated: ' . ($res['status'] ?? 'ok'));
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to initiate security audit: ' . $e->getMessage());
        }
    }

    public function upgradeFirmware(Firewall $firewall)
    {
        try {
            $api = new \App\Services\PfSenseApiService($firewall);
            $res = $api->upgradeFirmware();
            return back()->with('success', 'Firmware upgrade initiated: ' . ($res['status'] ?? 'ok'));
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to initiate firmware upgrade: ' . $e->getMessage());
        }
    }

    public function getFirmwareStatusJson(Firewall $firewall)
    {
        try {
            $api = new \App\Services\PfSenseApiService($firewall);
            $status = $api->getFirmwareUpgradeStatus();
            return response()->json($status);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'log' => $e->getMessage()], 500);
        }
    }

    public function userManager(Firewall $firewall)
    {
        $api = new \App\Services\PfSenseApiService($firewall);
        $users = [];
        $groups = [];

        try {
            $users = $api->getUsers()['data'] ?? [];
            $groups = $api->getGroups()['data'] ?? [];
        } catch (\Exception $e) {
            // Log error
        }

        return view('system.user-manager', compact('firewall', 'users', 'groups'));
    }

    public function certificateManager(Firewall $firewall)
    {
        $api = new \App\Services\PfSenseApiService($firewall);
        $cas = [];
        $certs = [];

        try {
            $cas = $api->getCertificateAuthorities()['data'] ?? [];
            $certs = $api->getCertificates()['data'] ?? [];
        } catch (\Exception $e) {
            // Log error
        }

        return view('system.certificate-manager', compact('firewall', 'cas', 'certs'));
    }

    /**
     * Test Notification Settings (Custom SMTP Tester)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function testNotifications(Request $request, Firewall $firewall)
    {
        // ... (validation logic remains same)
        $validated = $request->validate([
            'ipaddress' => 'required|string',
            'port' => 'required|integer',
            'ssl' => 'boolean', // In UI it's 'ssl', checks box
            'fromaddress' => 'required|email',
            'notifyemailaddress' => 'required|email',
            'username' => 'nullable|string',
            'password' => 'nullable|string',
        ]);

        $transportConfig = [
            'transport' => 'smtp',
            'host' => $validated['ipaddress'],
            'port' => $validated['port'],
            'username' => $validated['username'] ?? null,
            'password' => $validated['password'] ?? null,
            'timeout' => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN'),
        ];

        // Determine encryption
        if ($request->boolean('ssl')) {
            if ($validated['port'] == 465) {
                $transportConfig['encryption'] = 'ssl';
            } else {
                $transportConfig['encryption'] = 'tls';
            }
        } else {
            $transportConfig['encryption'] = null;
        }

        $fromEmail = $validated['fromaddress'];
        $toEmail = $validated['notifyemailaddress'];

        // Prepare the temporary mailer config
        config(['mail.mailers.smtp_test' => $transportConfig]);

        // We set it globally for this request context
        config(['mail.from.address' => $fromEmail]);
        config(['mail.from.name' => $fromEmail]);

        // Gather context info
        $firewallName = $firewall->name;
        $customerName = $firewall->company->name ?? 'Unknown Customer';

        try {
            \Illuminate\Support\Facades\Mail::mailer('smtp_test')->raw(
                "This is a test email from {$fromEmail}.\n\nFirewall: {$firewallName}\nCustomer: {$customerName}\n\nIf you received this, your SMTP settings are correct.",
                function ($message) use ($toEmail, $fromEmail, $firewallName, $customerName) {
                    $message->to($toEmail)
                        ->from($fromEmail, $fromEmail)
                        ->subject("SMTP Test - {$customerName} - {$firewallName}");
                }
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Status: Test email sent successfully to ' . $toEmail
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Connection Failed: ' . $e->getMessage()
            ], 500);
        }
    }
}
