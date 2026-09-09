# Deploying the security patches and trusting native firewall certificates

The security changes are in the local source tree. They have not been deployed, and neither the real database nor managed firewalls were modified. Review the [audit and remaining remediation work](/home/baga/Code/admixcentral/docs/SECURITY_AUDIT_2026-09-09.md) before production rollout.

## Native self-signed HTTPS certificates

Both pfSense and OPNsense can continue using their built-in certificates. On **Add Firewall** or **Edit Firewall**, use **Trust a native or self-signed HTTPS certificate** to upload the current HTTPS **server certificate** in public PEM/CRT form. Central derives and stores a public-key fingerprint. It does not need the firewall's private key, and it does not require a public CA.

Use a trusted administrative connection to export the certificate actually selected for the firewall's web interface. pfSense provides certificate export under System → Certificates; older releases may label this Cert. Manager. OPNsense provides its certificate controls under System → Trust → Certificates. Select the public certificate export, not a private-key or PKCS#12 export. See [pfSense certificate management](https://docs.netgate.com/pfsense/en/latest/certificates/certificate.html) and [OPNsense trust documentation](https://docs.opnsense.org/manual/certificates.html).

For an existing firewall, re-enter its API credentials when adding/changing/removing the trusted key. This prevents a saved credential being silently redirected to a newly trusted identity. The form will explain missing credentials; an invalid upload is rejected before any network connection. Existing self-signed firewalls will fail connection checks until their keys are enrolled; plan enrollment during the rollout. There is no automatic first-connection trust acceptance.

The advanced fingerprint field accepts `sha256//<base64 SHA256 of SubjectPublicKeyInfo>=`. Uploading the certificate fills the same stored value automatically. A valid PEM certificate is required; DER files must first be converted to PEM.

| Configuration | Behavior |
|---|---|
| Enrolled firewall public key | Accept that key over HTTPS, including a native self-signed certificate and access by IP with a different certificate hostname. |
| Same key, renewed certificate | Continues working. |
| Changed/replaced key | Connection fails before API credentials are sent; verify the replacement independently and enroll it with credentials. |
| No enrolled key | Normal certificate-chain and hostname validation against the system CA store or configured CA bundle. |
| HTTP URL | Rejected; enable HTTPS on the firewall and use its final HTTPS URL. |
| HTTP redirect from the API | Not followed. Configure the actual management URL/port. |

Public-key trust deliberately replaces CA, hostname and certificate-validity-date checks for that firewall. A certificate with the same enrolled key can remain accepted after expiry. Identity depends on possession of the trusted private key; keep it protected and rotate it after compromise. Enroll the public certificate from an independently trusted source, not from an unverified network fetch. cURL verifies the pin during TLS before application data, independently of CA verification. See [cURL public-key pinning documentation](https://curl.se/libcurl/c/CURLOPT_PINNEDPUBLICKEY.html).

The PHP runtime serving web requests **and** queue workers must have cURL with public-key pinning support and OpenSSL. A runtime without that support rejects pinned connections. The local test used PHP cURL/OpenSSL; test your production runtime too.

## Using the firewall's private CA instead

If certificates have correct names/IP subject alternatives and valid chains, you can use their private CA in the normal verification mode. Install a PEM CA bundle readable by the runtime and set:

```dotenv
FIREWALL_CA_BUNDLE=/etc/admixcentral/firewall-ca-bundle.pem
```

The bundle should contain all CA roots needed for unpinned firewall connections, including public roots if those are also used. It replaces the default CA file for these clients. Rebuild the configuration cache and restart workers after changing this setting. This option still checks hostname and expiry, and it allows certificate/key renewal under the trusted CA without per-key enrollment. Uploading a CA certificate into the **per-firewall server-certificate** field is not equivalent: that field pins the uploaded certificate's own public key.

## SSH backups

pfSense backups using SFTP now require the separate **SSH host key fingerprint** in firewall settings. Verify it through the firewall console or an already trusted administration channel, then enter the SHA256 fingerprint for the host key selected during SSH negotiation. The format is `SHA256:<base64 fingerprint without trailing =>`. It is different from the HTTPS pin.

A console command such as `ssh-keygen -lf /path/to/the/selected_ssh_host_key.pub -E sha256` prints an SSH host-key fingerprint. Choose the actual key file/algorithm offered by that firewall. Merely obtaining a key with an unverified network key scan does not establish trust. On a mismatch, confirm the offered algorithm and actual host identity before replacing the fingerprint. The check occurs before SSH password authentication, following the [phpseclib host-key verification pattern](https://phpseclib.com/docs/ssh2/connect).

Changing the firewall URL, SSH port, SSH username or SSH fingerprint clears a saved SSH password unless you supply it again. Existing backups with no enrolled SSH key will report a setup error until enrolled. OPNsense API-based backup downloads use the HTTPS trust configuration.

## Rollout sequence

1. Back up the database and existing deployment configuration using your established protected backup process. Preserve the existing `APP_KEY`.
2. Prepare trusted HTTPS certificate exports and SSH fingerprints for affected firewalls. Review R01–R06 in the audit, particularly root sudo grants, updater containment, egress restrictions and production debug settings.
3. In the deployment checkout, install the reviewed source and run `php artisan migrate --force`. The two new migrations add nullable `ssh_host_key_fingerprint` and `tls_public_key_pin` columns. They do not auto-trust discovered certificates or rotate credentials.
4. Set the production environment correctly: `APP_DEBUG=false`, `SESSION_ENCRYPT=true`, `SESSION_SECURE_COOKIE=true`, and `APP_URL=https://<canonical-central-host>`. Set the optional CA bundle only if using CA trust. Protect `.env` and cached configuration with appropriate ownership and permissions.
5. Run `php artisan config:cache` and `php artisan view:cache`, build assets with `npm ci --ignore-scripts` then `npm run build` in the build environment, and restart long-lived queue workers/FPM through your normal service/deployment mechanism. These commands are instructions for deployment; they were not run against the live checkout's database/configuration during the audit.
6. Enroll each existing native HTTPS certificate and re-enter the API credentials. For pfSense SFTP backups, enroll the SSH fingerprint and re-enter the SSH password if its destination/trust details changed.
7. Update the existing central nginx configuration to redirect HTTP to canonical HTTPS while retaining the static ACME challenge path. The patched SSL template applies only when generated; editing source does not rewrite `/etc/nginx`. Validate with `nginx -t` before an operator reload.
8. On staging, verify pfSense and OPNsense status, one reversible configuration change, backup retrieval, expected rejection on a wrong key, and key rotation. Verify read-only users cannot modify limiters/virtual IPs or receive credentials, and verify cross-tenant access is denied.

Only `storage` and `bootstrap/cache` should normally be writable by the web runtime; keep executable application code owned by the deployment account. This is incompatible with an in-place web updater, which is another reason to contain that updater until a signed deployment design is implemented. Keep the document root pointed at `public`, prevent uploaded files from executing as PHP, and restrict central administration to the intended management network.
