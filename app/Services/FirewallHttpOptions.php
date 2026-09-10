<?php

namespace App\Services;

class FirewallHttpOptions
{
    public static function get(?string $publicKeyPin = null, ?string $url = null): array
    {
        if ($url !== null && strtolower(parse_url($url, PHP_URL_SCHEME) ?? '') !== 'https') {
            throw new \RuntimeException('Firewall management requires HTTPS. Enable HTTPS on the firewall and update its URL.');
        }

        $options = [
            'verify'          => config('services.firewall.ca_bundle') ?: true,
            'allow_redirects' => false,
        ];

        if ($publicKeyPin !== null && $publicKeyPin !== '') {
            if (!preg_match('~\Asha256//[A-Za-z0-9+/]{43}=\z~', $publicKeyPin)) {
                throw new \InvalidArgumentException('Invalid firewall TLS public-key pin.');
            }
            if (!extension_loaded('curl') || !defined('CURLOPT_PINNEDPUBLICKEY')) {
                throw new \RuntimeException('PHP cURL with public-key pinning support is required for a self-signed firewall certificate.');
            }

            // For an explicitly enrolled self-signed certificate:
            //   • Skip CA-chain verification  — the enrolled public-key pin IS the trust anchor.
            //   • Allow access by IP/mismatched name — default pfSense/OPNsense certs lack IP SANs.
            //   • Enforce the pinned key      — cURL rejects the handshake if the key differs.
            //
            // We pass cURL options directly because Guzzle's `verify => false` shorthand maps to
            // CURLOPT_SSL_VERIFYPEER=0 AND CURLOPT_SSL_VERIFYHOST=0.
            $options['verify'] = false; // Guzzle: disables VERIFYPEER — handled below via curl
            $options['curl']   = [
                CURLOPT_SSL_VERIFYPEER  => false, // don't require a publicly trusted CA chain
                CURLOPT_SSL_VERIFYHOST  => 0,     // allow native self-signed certs accessed by IP (pinned key is the trust anchor)
                CURLOPT_PINNEDPUBLICKEY => $publicKeyPin,
            ];
        }

        return $options;
    }

    public static function pinFromCertificate(string $certificate): string
    {
        if (str_contains($certificate, 'PRIVATE KEY')) {
            throw new \InvalidArgumentException('Upload only the public HTTPS server certificate, without its private key.');
        }
        $x509 = @openssl_x509_read($certificate);
        if ($x509 === false) {
            throw new \InvalidArgumentException('Upload a valid PEM-encoded HTTPS server certificate (.pem or .crt).');
        }
        $key = openssl_pkey_get_public($x509);
        $details = $key === false ? false : openssl_pkey_get_details($key);
        if ($details === false || empty($details['key'])) {
            throw new \InvalidArgumentException('The certificate does not contain a supported public key.');
        }
        $der = base64_decode(preg_replace('/-----[^-]+-----|\s+/', '', $details['key']), true);
        if ($der === false || $der === '') {
            throw new \InvalidArgumentException('Unable to read the certificate public key.');
        }

        return 'sha256//' . base64_encode(hash('sha256', $der, true));
    }
}
