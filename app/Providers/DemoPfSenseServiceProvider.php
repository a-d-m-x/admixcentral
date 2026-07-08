<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Http;

/**
 * DEMO MODE ONLY — active only when MOCK_PFSENSE=true in .env.
 *
 * Intercepts all outbound pfSense REST API calls (PfSenseApiService uses the
 * Http facade) and returns realistic canned data, so the mock firewalls seeded
 * for local exploration behave like real devices across the whole app
 * (IPsec, interfaces, live status, etc.) without a live pfSense box.
 *
 * Production is unaffected: with MOCK_PFSENSE unset, boot() is a no-op.
 */
class DemoPfSenseServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (! env('MOCK_PFSENSE', false)) {
            return;
        }

        Http::fake(function ($request) {
            $url  = $request->url();
            $path = parse_url($url, PHP_URL_PATH) ?? '';
            $host = parse_url($url, PHP_URL_HOST) ?? 'site';
            $site = explode('.', $host)[0]; // e.g. "globex-png"

            $ok = fn ($data) => Http::response([
                'code'        => 200,
                'status'      => 'ok',
                'response_id' => 'SUCCESS',
                'message'     => 'Mock response',
                'data'        => $data,
            ], 200);

            // ── IPsec ───────────────────────────────────────────────────────
            if (str_contains($path, '/status/ipsec')) {
                return $ok($this->ipsecStatus($site));
            }
            if (str_contains($path, '/vpn/ipsec/phase1')) {
                return $ok($this->phase1s($site));
            }
            if (str_contains($path, '/vpn/ipsec/phase2')) {
                return $ok($this->phase2s($site));
            }

            // ── Interfaces (used by IPsec form + status pages) ──────────────
            if (str_contains($path, '/interfaces')) {
                return $ok($this->interfaces());
            }

            // ── Everything else: harmless empty success ─────────────────────
            return $ok([]);
        });
    }

    /**
     * Live IPsec Security Associations (/status/ipsec/sas) — pfSense
     * "Status / IPsec / Overview" shape: IKE SA + connected uptime + child SAs.
     */
    private function ipsecStatus(string $site): array
    {
        return [
            [
                'con_id' => 'con1', 'uniqueid' => 6360, 'descr' => 'HQ — Kuala Lumpur DC',
                'version' => 2, 'role' => 'responder', 'state' => 'ESTABLISHED',
                'local_id' => '102.223.18.11',  'local_host' => '102.223.18.11',  'local_port' => 4500, 'local_spi' => '5b022b0e677cc3c0',
                'remote_id' => '129.151.173.10', 'remote_host' => '129.151.173.10', 'remote_port' => 4500, 'remote_natt' => true, 'remote_spi' => '9628adbcd4317360',
                'rekey' => 9512, 'reauth' => 0, 'established' => 13677,
                'encr' => 'AES_CBC', 'keysize' => 256, 'integ' => 'HMAC_SHA2_256_128', 'prf' => 'PRF_HMAC_SHA2_256', 'dh' => 'MODP_2048',
                'children' => [[
                    'name' => 'con1', 'reqid' => 1, 'state' => 'INSTALLED', 'mode' => 'TUNNEL', 'proto' => 'ESP',
                    'spi_in' => 'cf4a12b7', 'spi_out' => 'a91e77d0', 'encr' => 'AES_GCM_16', 'keysize' => 256,
                    'bytes_in' => 18234880, 'bytes_out' => 9412355, 'packets_in' => 142233, 'packets_out' => 98120,
                    'local_ts' => '10.20.0.0/24', 'remote_ts' => '10.10.0.0/24', 'life' => 3600, 'rekey' => 2400, 'installed' => 1176,
                ]],
            ],
            [
                'con_id' => 'con2', 'uniqueid' => 6412, 'descr' => 'DR Site — Cyberjaya',
                'version' => 2, 'role' => 'responder', 'state' => 'ESTABLISHED',
                'local_id' => '102.223.18.11',  'local_host' => '102.223.18.11',  'local_port' => 500, 'local_spi' => 'd205e3751942eebe',
                'remote_id' => '193.57.230.11', 'remote_host' => '193.57.230.11', 'remote_port' => 500, 'remote_natt' => false, 'remote_spi' => '0200f0fc57b7bda4',
                'rekey' => 13562, 'reauth' => 0, 'established' => 10999,
                'encr' => 'AES_CBC', 'keysize' => 256, 'integ' => 'HMAC_SHA2_256_128', 'prf' => 'PRF_HMAC_SHA2_256', 'dh' => 'MODP_2048',
                'children' => [[
                    'name' => 'con2', 'reqid' => 2, 'state' => 'INSTALLED', 'mode' => 'TUNNEL', 'proto' => 'ESP',
                    'spi_in' => '7b1c04ee', 'spi_out' => 'e0aa9931', 'encr' => 'AES_GCM_16', 'keysize' => 256,
                    'bytes_in' => 5120445, 'bytes_out' => 4402221, 'packets_in' => 41022, 'packets_out' => 39887,
                    'local_ts' => '10.20.0.0/24', 'remote_ts' => '10.30.0.0/24', 'life' => 3600, 'rekey' => 2610, 'installed' => 990,
                ]],
            ],
            [
                'con_id' => 'con3', 'uniqueid' => 6137, 'descr' => 'Partner B2B — Vendor SFTP',
                'version' => 2, 'role' => 'initiator', 'state' => 'ESTABLISHED',
                'local_id' => '197.250.34.205', 'local_host' => '197.250.34.205', 'local_port' => 4500, 'local_spi' => 'e0dd1f184d672779',
                'remote_id' => '129.151.180.80', 'remote_host' => '129.151.180.80', 'remote_port' => 4500, 'remote_natt' => true, 'remote_spi' => '02004c6664f050c5',
                'rekey' => 675, 'reauth' => 0, 'established' => 23343,
                'encr' => 'AES_CBC', 'keysize' => 256, 'integ' => 'HMAC_SHA2_256_128', 'prf' => 'PRF_HMAC_SHA2_256', 'dh' => 'MODP_2048',
                'children' => [[
                    'name' => 'con3', 'reqid' => 3, 'state' => 'INSTALLED', 'mode' => 'TUNNEL', 'proto' => 'ESP',
                    'spi_in' => '3af90b12', 'spi_out' => 'bb17c204', 'encr' => 'AES_GCM_16', 'keysize' => 256,
                    'bytes_in' => 984223, 'bytes_out' => 771904, 'packets_in' => 8123, 'packets_out' => 7440,
                    'local_ts' => '10.20.99.0/24', 'remote_ts' => '172.16.5.10/32', 'life' => 3600, 'rekey' => 300, 'installed' => 2210,
                ]],
            ],
        ];
    }

    private function interfaces(): array
    {
        return [
            ['id' => 'wan',  'descr' => 'WAN',  'if' => 'igb0'],
            ['id' => 'lan',  'descr' => 'LAN',  'if' => 'igb1'],
            ['id' => 'opt1', 'descr' => 'DMZ',  'if' => 'igb2'],
        ];
    }

    private function phase1s(string $site): array
    {
        return [
            [
                'ikeid'          => 1,
                'iketype'        => 'ikev2',
                'protocol'       => 'inet',
                'interface'      => 'wan',
                'remote-gateway' => '203.0.113.10',
                'mode'           => 'main',
                'descr'          => 'HQ — Kuala Lumpur DC',
                'encryption'     => [['encryption-algorithm-name' => 'aes', 'encryption-algorithm-keylen' => 256, 'hash-algorithm' => 'sha256', 'dhgroup' => 14]],
            ],
            [
                'ikeid'          => 2,
                'iketype'        => 'ikev2',
                'protocol'       => 'inet',
                'interface'      => 'wan',
                'remote-gateway' => '198.51.100.24',
                'mode'           => 'main',
                'descr'          => 'DR Site — Cyberjaya',
                'encryption'     => [['encryption-algorithm-name' => 'aes', 'encryption-algorithm-keylen' => 256, 'hash-algorithm' => 'sha512', 'dhgroup' => 14]],
            ],
            [
                'ikeid'          => 3,
                'iketype'        => 'ikev1',
                'protocol'       => 'inet',
                'interface'      => 'wan',
                'remote-gateway' => '192.0.2.77',
                'mode'           => 'aggressive',
                'descr'          => 'Partner B2B — Vendor SFTP',
                'disabled'       => '', // presence of key => shown as Disabled
                'encryption'     => [['encryption-algorithm-name' => 'aes', 'encryption-algorithm-keylen' => 128, 'hash-algorithm' => 'sha1', 'dhgroup' => 2]],
            ],
        ];
    }

    private function phase2s(string $site): array
    {
        return [
            ['ikeid' => 1, 'uniqid' => 'ph2_hq_lan',  'mode' => 'tunnel', 'descr' => 'Branch LAN ↔ HQ LAN',
                'localid'  => ['type' => 'network', 'address' => '10.20.0.0',  'netbits' => 24],
                'remoteid' => ['type' => 'network', 'address' => '10.10.0.0',  'netbits' => 24]],
            ['ikeid' => 1, 'uniqid' => 'ph2_hq_voip', 'mode' => 'tunnel', 'descr' => 'VoIP ↔ HQ PBX',
                'localid'  => ['type' => 'network', 'address' => '10.20.10.0', 'netbits' => 24],
                'remoteid' => ['type' => 'network', 'address' => '10.10.50.0', 'netbits' => 24]],
            ['ikeid' => 2, 'uniqid' => 'ph2_dr_repl', 'mode' => 'tunnel', 'descr' => 'Backup replication ↔ DR',
                'localid'  => ['type' => 'network', 'address' => '10.20.0.0',  'netbits' => 24],
                'remoteid' => ['type' => 'network', 'address' => '10.30.0.0',  'netbits' => 24]],
            ['ikeid' => 3, 'uniqid' => 'ph2_b2b_host', 'mode' => 'tunnel', 'descr' => 'B2B single-host access',
                'localid'  => ['type' => 'network', 'address' => '10.20.99.0', 'netbits' => 24],
                'remoteid' => ['type' => 'address', 'address' => '172.16.5.10', 'netbits' => '']],
        ];
    }
}
