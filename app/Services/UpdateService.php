<?php

namespace App\Services;

use App\Models\SystemSetting;
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
     * Resolve whether pre-releases should be surfaced.
     *
     * Priority (highest wins):
     *   1. ALLOW_PRERELEASES=true in .env  — forces on regardless of DB (ideal for staging)
     *   2. `allow_prereleases` DB setting  — user-controlled toggle in Settings UI
     *   3. Default: false                  — stable-only (production default)
     */
    public function allowPrereleases(): bool
    {
        // 1. Env override — takes precedence over everything
        $envValue = config('services.github.allow_prereleases');
        if ($envValue !== null) {
            return filter_var($envValue, FILTER_VALIDATE_BOOLEAN);
        }

        // 2. DB setting
        return SystemSetting::where('key', 'allow_prereleases')->value('value') === '1';
    }

    /**
     * Fetch the latest release from GitHub.
     *
     * Respects allowPrereleases() priority chain:
     *   env ALLOW_PRERELEASES > DB allow_prereleases > stable-only default
     *
     * Drafts are never surfaced regardless of any setting.
     */
    public function checkForUpdates(): ?array
    {
        $allowPrereleases = $this->allowPrereleases();

        // Use authenticated request if token is available to avoid rate limits
        $token = config('services.github.token');
        $headers = [
            'Accept' => 'application/vnd.github.v3+json',
        ];

        if ($token) {
            $headers['Authorization'] = 'token ' . $token;
        }

        try {
            // Fetch releases list (not just /latest, so we can apply our own filter)
            $response = Http::withHeaders($headers)
                ->get("https://api.github.com/repos/{$this->repository}/releases");

            if ($response->failed()) {
                Log::error('UpdateService: Failed to fetch releases from GitHub.', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return null;
            }

            $releases = $response->json();

            if (empty($releases)) {
                return null;
            }

            // GitHub returns releases newest-first.
            // Always skip drafts. Skip pre-releases unless opted in.
            $latestRelease = null;
            foreach ($releases as $release) {
                if ($release['draft']) {
                    continue;
                }
                if ($release['prerelease'] && !$allowPrereleases) {
                    continue;
                }
                $latestRelease = $release;
                break;
            }

            return $latestRelease; // null if nothing matched

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
        $current = ltrim($currentVersion, 'v');
        $latest  = ltrim($latestVersion,  'v');

        return version_compare($latest, $current, '>');
    }
}
