<?php

namespace App\Services;

use phpseclib3\Net\SFTP;

class SshHostKeyVerifier
{
    public static function verify(SFTP $sftp, string $expected): bool
    {
        // phpseclib verifies the server's signature, but the application must
        // compare its identity with a key obtained through a trusted channel.
        if (!preg_match('/\ASHA256:[A-Za-z0-9+\/]{43}\z/', $expected)) {
            return false;
        }

        $key = $sftp->getServerPublicHostKey();
        if (!is_string($key)) {
            return false;
        }

        $parts = preg_split('/\s+/', trim($key));
        $raw = isset($parts[1]) ? base64_decode($parts[1], true) : false;
        if ($raw === false || $raw === '') {
            return false;
        }

        $actual = 'SHA256:' . rtrim(base64_encode(hash('sha256', $raw, true)), '=');

        return hash_equals($expected, $actual);
    }
}
