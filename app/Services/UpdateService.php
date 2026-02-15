<?php

namespace App\Services;

use App\Models\SystemUpdate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UpdateService
{
    /**
     * The GitHub repository "owner/repo"
     */
    protected string $repository;

    public function __construct()
    {
        $this->repository = config('services.github.repository', 'a-d-m-x/admixcentral');
    }

    /**
     * Fetch the latest release from GitHub.
     * Uses Mode 1: Public GitHub API.
     */
    public function checkForUpdates(): ?array
    {
        // Use authenticated request if token is available to avoid rate limits
        $token = config('services.github.token');
        $headers = [
            'Accept' => 'application/vnd.github.v3+json',
        ];

        if ($token) {
            $headers['Authorization'] = 'token ' . $token;
        }

        try {
            // Fetch releases (not just latest, to avoid drafts/prereleases if needed)
            $response = Http::withHeaders($headers)
                ->get("https://api.github.com/repos/{$this->repository}/releases");

            if ($response->failed()) {
                Log::error('UpdateService: Failed to fetch releases from GitHub.', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            $releases = $response->json();

            if (empty($releases)) {
                return null;
            }

            // Filter for the latest stable release (not draft, not prerelease)
            // GitHub returns them in chronological order, so the first one that matches is the latest.
            $latestRelease = null;
            foreach ($releases as $release) {
                if (!$release['draft'] && !$release['prerelease']) {
                    $latestRelease = $release;
                    break;
                }
            }

            if (!$latestRelease) {
                return null;
            }

            return $latestRelease; // Contains 'tag_name', 'assets', 'body' (notes), etc.

        } catch (\Exception $e) {
            Log::error('UpdateService: Exception while checking for updates.', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Compare available version with current version.
     * Returns true if a new version is available.
     */
    public function isNewVersionAvailable(string $currentVersion, string $latestVersion): bool
    {
        // Normalize versions by removing 'v' prefix if present
        $current = ltrim($currentVersion, 'v');
        $latest = ltrim($latestVersion, 'v');

        return version_compare($latest, $current, '>');
    }
}
