<?php

use App\Models\Firewall;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;

echo "--- Debugging Cache ---\n";

// 1. Check Setting
$setting = SystemSetting::where('key', 'enable_status_cache')->first();
echo "SystemSetting 'enable_status_cache': " . ($setting ? $setting->value : "NOT SET (Defaults to true)") . "\n";

// 2. Check Firewalls
$firewalls = Firewall::all();
echo "Found " . $firewalls->count() . " firewalls.\n";

foreach ($firewalls as $fw) {
    echo "\nFirewall: {$fw->name} (ID: {$fw->id})\n";
    $key = 'firewall_status_' . $fw->id;
    $hasCache = Cache::has($key);
    echo "Cache Key: $key\n";
    echo "Has Cache: " . ($hasCache ? "YES" : "NO") . "\n";

    if ($hasCache) {
        $data = Cache::get($key);
        echo "Keys in Cache: " . implode(', ', array_keys($data)) . "\n";
        if (isset($data['data'])) {
            echo "Keys in [data]: " . implode(', ', array_keys($data['data'])) . "\n";
            if (isset($data['data']['gateways'])) {
                echo "Gateways Count: " . count($data['data']['gateways']) . "\n";
                print_r($data['data']['gateways']);
            } else {
                echo "No 'gateways' in [data]\n";
            }
        }
    } else {
        // Try to trigger update immediately for this one
        echo "Attempting to refresh status...\n";
        try {
            $api = new \App\Services\PfSenseApiService($fw);
            $status = $api->refreshSystemStatus();
            echo "Refresh Result Keys: " . implode(', ', array_keys($status)) . "\n";
        } catch (\Exception $e) {
            echo "Refresh Failed: " . $e->getMessage() . "\n";
        }
    }
}
echo "\n--- End Debug ---\n";
