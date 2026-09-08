<?php

namespace App\Http\Controllers;

use App\Models\Firewall;
use App\Services\PfSenseApiService;
use Illuminate\Http\Request;

class PackageManagerController extends Controller
{
    public function index(Firewall $firewall, Request $request)
    {
        $api = new PfSenseApiService($firewall);
        $tab = $request->query('tab', 'installed');

        $data = [];
        try {
            if ($tab === 'installed') {
                $data['packages'] = $api->getSystemPackages()['data'] ?? [];
            } elseif ($tab === 'available') {
                // Fetch both installed and available packages
                $installedPackages = $api->getSystemPackages()['data'] ?? [];
                $availablePackages = $api->getSystemAvailablePackages()['data'] ?? [];

                // Build a list of installed package names/shortnames for filtering
                $installedNames = [];
                foreach ($installedPackages as $pkg) {
                    if (!empty($pkg['name'])) {
                        $installedNames[] = $pkg['name'];
                    }
                    if (!empty($pkg['shortname'])) {
                        $installedNames[] = $pkg['shortname'];
                    }
                }

                // Filter out installed packages from available
                $data['packages'] = array_filter($availablePackages, function ($pkg) use ($installedNames) {
                    $name = $pkg['name'] ?? '';
                    $shortname = $pkg['shortname'] ?? '';
                    return !in_array($name, $installedNames) && !in_array($shortname, $installedNames);
                });
                // Re-index the array
                $data['packages'] = array_values($data['packages']);
            }
        } catch (\Exception $e) {
            // Handle API errors gracefully, maybe the endpoint is slow or times out
            \Illuminate\Support\Facades\Log::error('PackageManager Error', [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'class' => get_class($e),
                'tab' => $tab,
            ]);
            $errorMsg = $e->getMessage();
            if (empty($errorMsg)) {
                $errorMsg = 'API request failed (possibly timeout or connection issue). ' . get_class($e);
            }
            $data['error'] = $errorMsg;
            $data['packages'] = [];
        }

        return view('system.package_manager.index', compact('firewall', 'tab', 'data'));
    }

    public function install(Firewall $firewall, Request $request)
    {
        $api = new PfSenseApiService($firewall);
        $name = $request->input('name');

        try {
            $api->installSystemPackage($name);
            return redirect()->route('system.package_manager.index', ['firewall' => $firewall, 'tab' => 'installed'])
                ->with('success', "Package '$name' installation started. It may take a few minutes to appear.");
        } catch (\Exception $e) {
            return back()->withErrors(['error' => "Failed to install package '$name': " . $e->getMessage()]);
        }
    }

    public function uninstall(Firewall $firewall, Request $request)
    {
        $api = new PfSenseApiService($firewall);
        $id = $request->input('id');
        $name = $request->input('name', 'package');

        try {
            $api->uninstallSystemPackage($id, $name);
            return redirect()->route('system.package_manager.index', ['firewall' => $firewall, 'tab' => 'installed'])
                ->with('success', "Package '$name' uninstallation started.");
        } catch (\Exception $e) {
            return back()->withErrors(['error' => "Failed to uninstall package '$name': " . $e->getMessage()]);
        }
    }

    public function reinstall(Firewall $firewall, Request $request)
    {
        $api = new PfSenseApiService($firewall);
        $name = $request->input('name');

        try {
            $api->reinstallSystemPackage($name);
            return redirect()->route('system.package_manager.index', ['firewall' => $firewall, 'tab' => 'installed'])
                ->with('success', "Package '$name' reinstallation started.");
        } catch (\Exception $e) {
            return back()->withErrors(['error' => "Failed to reinstall package '$name': " . $e->getMessage()]);
        }
    }

    public function lock(Firewall $firewall, Request $request)
    {
        $api = new PfSenseApiService($firewall);
        $name = $request->input('name');

        try {
            $api->lockSystemPackage($name);
            return redirect()->route('system.package_manager.index', ['firewall' => $firewall, 'tab' => 'installed'])
                ->with('success', "Package '$name' locked.");
        } catch (\Exception $e) {
            return back()->withErrors(['error' => "Failed to lock package '$name': " . $e->getMessage()]);
        }
    }

    public function unlock(Firewall $firewall, Request $request)
    {
        $api = new PfSenseApiService($firewall);
        $name = $request->input('name');

        try {
            $api->unlockSystemPackage($name);
            return redirect()->route('system.package_manager.index', ['firewall' => $firewall, 'tab' => 'installed'])
                ->with('success', "Package '$name' unlocked.");
        } catch (\Exception $e) {
            return back()->withErrors(['error' => "Failed to unlock package '$name': " . $e->getMessage()]);
        }
    }
}
