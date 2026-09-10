<?php

use App\Models\Firewall;
use App\Services\FirewallHttpOptions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

return new class extends Migration {
    /**
     * Automatically discover and enroll public key pins for existing firewalls
     * using native self-signed certificates, preventing connection failures on update.
     */
    public function up(): void
    {
        try {
            $firewalls = Firewall::whereNull('tls_public_key_pin')
                ->where('url', 'like', 'https://%')
                ->get();

            foreach ($firewalls as $firewall) {
                try {
                    if (FirewallHttpOptions::requiresPin($firewall->url, 2)) {
                        $pin = FirewallHttpOptions::fetchPinFromUrl($firewall->url, 2);
                        if ($pin) {
                            $firewall->update(['tls_public_key_pin' => $pin]);
                            Log::info("Migration: Auto-enrolled TLS public key pin for firewall [{$firewall->id}] ({$firewall->name}): {$pin}");
                        }
                    }
                } catch (\Throwable $e) {
                    Log::warning("Migration: Could not auto-enroll TLS pin for firewall [{$firewall->id}]: " . $e->getMessage());
                }
            }
        } catch (\Throwable $e) {
            Log::warning("Migration: Auto-enrollment query failed: " . $e->getMessage());
        }
    }

    public function down(): void
    {
        // No-op: preserve enrolled pins
    }
};
