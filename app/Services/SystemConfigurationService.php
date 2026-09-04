<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class SystemConfigurationService
{
    /**
     * Update the environment file with the given key-value pairs.
     *
     * @param array $data
     * @return bool
     */
    public function updateEnv(array $data): bool
    {
        $envPath = base_path('.env');

        if (!file_exists($envPath)) {
            Log::error('SystemConfigurationService: .env file not found.');
            return false;
        }

        $envContent = file_get_contents($envPath);
        $updated = false;

        foreach ($data as $key => $value) {
            // Sanitize key: only allow env-safe characters (letters, digits, underscores)
            if (!preg_match('/^[A-Z_][A-Z0-9_]*$/i', $key)) {
                Log::warning("SystemConfigurationService: Rejected unsafe env key: {$key}");
                continue;
            }

            // Sanitize value: reject newlines that could inject new env variables
            $value = str_replace(["\r", "\n"], '', (string) $value);

            // value escaping for safety
            if (preg_match('/\s/', $value) && strpos($value, '"') === false && strpos($value, "'") === false) {
                $value = '"' . $value . '"';
            }

            // Use preg_quote to prevent regex injection from key names
            $escapedKey = preg_quote($key, '/');

            // Check if key exists
            if (preg_match("/^{$escapedKey}=.*/m", $envContent)) {
                // Update existing key
                $envContent = preg_replace("/^{$escapedKey}=.*/m", "{$key}={$value}", $envContent);
                $updated = true;
            } else {
                // Add new key
                $envContent .= PHP_EOL . "{$key}={$value}";
                $updated = true;
            }
        }

        if ($updated) {
            file_put_contents($envPath, $envContent);

            // Clear config cache to apply changes immediately
            Artisan::call('config:clear');

            return true;
        }

        return false;
    }

    /**
     * Append a value to a comma-separated list in the environment file.
     *
     * @param array $data key => value to append
     * @return bool
     */
    public function appendEnv(array $data): bool
    {
        $envPath = base_path('.env');
        if (!file_exists($envPath)) {
            Log::error('SystemConfigurationService: .env file not found.');
            return false;
        }

        $envContent = file_get_contents($envPath);
        $updated = false;

        foreach ($data as $key => $valueToAdd) {
            // Sanitize key: only allow env-safe characters
            if (!preg_match('/^[A-Z_][A-Z0-9_]*$/i', $key)) {
                Log::warning("SystemConfigurationService: Rejected unsafe env key in appendEnv: {$key}");
                continue;
            }

            // Sanitize value: reject newlines
            $valueToAdd = str_replace(["\r", "\n"], '', (string) $valueToAdd);

            $escapedKey = preg_quote($key, '/');

            // Find existing line
            if (preg_match("/^{$escapedKey}=(.*)$/m", $envContent, $matches)) {
                $currentValue = $matches[1];
                // Remove quotes if present
                $currentValue = trim($currentValue, '"\'');

                $values = array_map('trim', explode(',', $currentValue));

                // Add new value if not exists
                if (!in_array($valueToAdd, $values)) {
                    $values[] = $valueToAdd;
                    $newValue = implode(',', $values);

                    // Quote if contains spaces (unlikely for domains but safe practice)
                    if (preg_match('/\s/', $newValue)) {
                        $newValue = '"' . $newValue . '"';
                    }

                    $envContent = preg_replace("/^{$escapedKey}=.*/m", "{$key}={$newValue}", $envContent);
                    $updated = true;
                }
            } else {
                // Key doesn't exist, just add it
                $envContent .= PHP_EOL . "{$key}={$valueToAdd}";
                $updated = true;
            }
        }

        if ($updated) {
            file_put_contents($envPath, $envContent);
            Artisan::call('config:clear');
            return true;
        }

        return false;
    }

    /**
     * Update Nginx configuration for the hostname.
     * 
     * @param string $hostname
     * @return bool
     */
    public function updateNginxHostname(string $hostname): bool
    {
        $nginxPath = storage_path('app/admixcentral.nginx.conf');

        if (!file_exists($nginxPath)) {
            Log::warning('SystemConfigurationService: Nginx config file not found at ' . $nginxPath);
            return false;
        }

        $content = file_get_contents($nginxPath);

        // Update server_name directive
        // This regex looks for 'server_name' followed by whitespace and then NOT a semicolon, replacing it.
        // It's a basic replacement and assumes standard formatting.
        $newContent = preg_replace('/server_name\s+[\w\.\-\s]+;/', "server_name 10.100.200.152 {$hostname};", $content);

        if ($newContent !== $content) {
            file_put_contents($nginxPath, $newContent);
            Log::info("SystemConfigurationService: Updated Nginx server_name to {$hostname}");
            return true;
        }

        return false;
    }

    /**
     * Apply hostname changes to the system.
     * 
     * @param string $hostname
     * @param string $scheme http or https
     * @return void
     */
    public function updateSystemHostname(string $hostname, string $scheme = 'https'): void
    {
        $appUrl = "{$scheme}://{$hostname}";

        $port = $scheme === 'https' ? '443' : '80';

        // Update strictly replaced variables
        $this->updateEnv([
            'APP_URL' => $appUrl,
            'REVERB_HOST' => $hostname,
            'REVERB_PORT' => $port,
            'REVERB_SCHEME' => $scheme,
            'VITE_REVERB_HOST' => $hostname,
            'VITE_REVERB_PORT' => $port,
            'VITE_REVERB_SCHEME' => $scheme,
        ]);

        // Smart append for Sanctum to prevent lockout from local access
        $this->appendEnv([
            'SANCTUM_STATEFUL_DOMAINS' => $hostname,
        ]);

        // Update Nginx
        $this->updateNginxHostname($hostname);
    }
}
