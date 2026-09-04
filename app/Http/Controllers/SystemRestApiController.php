<?php

namespace App\Http\Controllers;

use App\Models\Firewall;
use App\Services\PfSenseApiService;
use Illuminate\Http\Request;

class SystemRestApiController extends Controller
{
    public function index(Firewall $firewall)
    {
        $api = new PfSenseApiService($firewall);
        $installedVersion = 'Unknown';
        $latestVersion = 'Unknown';
        $releaseDate = 'Unknown';
        $updateAvailable = false;

        // Fetch Installed Version
        try {
            $versionResponse = $api->getApiVersion();
            $installedVersion = $versionResponse['data']['output'] ?? 'Unknown';
            // Clean up version string
            $installedVersion = trim($installedVersion);
        } catch (\Exception $e) {
            // Keep default
        }

        // Fetch All Releases from GitHub
        $availableVersions = [];
        try {
            $response = \Illuminate\Support\Facades\Http::get('https://api.github.com/repos/jaredhendrickson13/pfsense-api/releases');
            if ($response->successful()) {
                $releases = $response->json();
                foreach ($releases as $release) {
                    $availableVersions[] = [
                        'tag' => $release['tag_name'] ?? 'Unknown',
                        'version' => str_replace('v', '', $release['tag_name'] ?? 'Unknown'),
                        'published_at' => \Carbon\Carbon::parse($release['published_at'])->format('M d, Y'),
                        'name' => $release['name'] ?? $release['tag_name'],
                        'body' => $release['body'] ?? '',
                    ];
                }
            }
        } catch (\Exception $e) {
            // Keep empty
        }

        // Set latest version from the first item in the list if available
        if (!empty($availableVersions)) {
            $latestVersion = $availableVersions[0]['version'];
            $releaseDate = $availableVersions[0]['published_at'];
        }

        // Compare Versions
        if ($installedVersion !== 'Unknown' && $latestVersion !== 'Unknown') {
            $updateAvailable = version_compare($installedVersion, $latestVersion, '<');
        }

        return view('system.rest-api.index', compact('firewall', 'installedVersion', 'latestVersion', 'releaseDate', 'updateAvailable', 'availableVersions'));
    }

    public function update(Firewall $firewall)
    {
        $api = new PfSenseApiService($firewall);

        try {
            // Repo update logic switched to using the dedicated update command
            $command = "pfsense-restapi update";

            // Re-using the command prompt capability
            $response = $api->commandPrompt($command);

            $output = $response['data']['output'] ?? 'Command executed.';

            return back()->with('success', 'REST API update initiated. Output: ' . substr($output, 0, 200) . '...');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to update REST API: ' . $e->getMessage());
        }
    }

    public function revert(Firewall $firewall, Request $request)
    {
        $request->validate([
            'version' => ['required', 'string', 'regex:/^[a-zA-Z0-9._-]+$/'],
        ]);

        $version = $request->input('version');
        $safeVersion = escapeshellarg($version);
        $api = new PfSenseApiService($firewall);

        try {
            // Command: pfsense-restapi revert <version>
            $command = "pfsense-restapi revert {$safeVersion}";

            $response = $api->commandPrompt($command);
            $output = $response['data']['output'] ?? 'Command executed.';

            return back()->with('success', "Revert to {$version} initiated. Output: " . substr($output, 0, 200) . '...');
        } catch (\Exception $e) {
            return back()->with('error', "Failed to revert to {$version}: " . $e->getMessage());
        }
    }
}
