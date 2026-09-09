# AdmixCentral host preparation and readiness

`upgrade_admixcentral.sh` now prepares the **host environment of an existing installation**. The application’s built-in GitHub release updater continues to handle application code. This wizard does not download releases, run Composer/npm, build assets, run migrations, change application ownership, or move application data.

It offers explained, individually selectable steps for PHP extensions, Redis/Valkey, Supervisor workers, Reverb, cron, PHP-FPM and environment hardening. Every change prompt defaults to **No**. Use a maintenance window: package installation and selected service changes can briefly interrupt requests and background work.

## Run it

Keep these three files together in their repository layout:

- `upgrade_admixcentral.sh`
- `scripts/security_upgrade/wizard.py`
- `scripts/security_upgrade/probe.php`

Run a reviewed copy of the script on the live host. The script itself should come from a trusted deployment checkout; it runs with administrator privileges.

```bash
# Read-only readiness report first; adjust the deployment path if needed.
sudo bash upgrade_admixcentral.sh --check --install-dir /var/www/admixcentral

# Guided preparation. Each proposed change is explained before you select it.
sudo bash upgrade_admixcentral.sh --install-dir /var/www/admixcentral
```

`--dry-run` is an alias for `--check`. The check does not install packages, edit configuration, clear Laravel configuration or restart services. It reads configuration and sends Redis PINGs using the existing application settings; authentication/connection activity may appear in normal service logs. It does not boot application service providers or contact managed firewalls. Root gives the most complete report; without root, unreadable host configuration or inability to assume the application runtime user can stop the check.

Exit codes: **0** means the implemented checks found no outstanding items, **2** means items need attention or were skipped, **1** means an error stopped the wizard, and **130** means the operator interrupted it. A successful report is a readiness check, not a guarantee that an application or host has no vulnerabilities.

For custom PHP/FPM layouts:

```bash
sudo bash upgrade_admixcentral.sh \
  --install-dir /srv/admixcentral \
  --php-bin /usr/bin/php8.3 \
  --fpm-bin /usr/sbin/php-fpm8.3 \
  --fpm-service php8.3-fpm.service \
  --fpm-pool /etc/php/8.3/fpm/pool.d/admixcentral.conf \
  --runtime-user admixcentral
```

`INSTALL_DIR` and `PHP_VER` environment overrides remain available, although explicit options avoid sudo environment filtering. `--php-version 8.3` selects an **already installed** versioned CLI on Ubuntu. The runtime user is detected from the FPM pool, never from the owner of `artisan`; it must be non-root. Paths embedded in service configuration must not contain whitespace or shell/configuration metacharacters.

## Distribution support

The wizard targets conventional, supported **systemd** installations using their configured package repositories. It detects derivatives through `/etc/os-release` `ID_LIKE`, and stops on unknown families rather than guessing a package manager.

| Family | Package tool | New local Redis-compatible server | PHP Redis extension | Typical FPM service/user |
| --- | --- | --- | --- | --- |
| Ubuntu/Debian derivatives | apt-get | redis-server | php&lt;installed-version&gt;-redis | php&lt;version&gt;-fpm / www-data |
| Fedora derivatives | dnf | valkey | php-pecl-redis6 | php-fpm / apache |
| Arch derivatives | pacman | valkey | php-redis | php-fpm / http |

User and service values are discovered, not assumed from this table. Existing Redis installations are preserved; selecting packages does not replace them with Valkey. Fedora and Arch distribute Valkey, which supports the Redis protocol used by phpredis. See the [Fedora extension package](https://packages.fedoraproject.org/pkgs/php-pecl-redis6/php-pecl-redis6/) and [Arch Valkey file list](https://archlinux.org/packages/extra/x86_64/valkey/files/).

PHP 8.2+ within PHP 8 and Python 3.9+ are required. The installed application’s Composer lock file may require a newer PHP version; the read-only PHP probe also checks whether its dependencies can load. On Ubuntu releases with older default PHP, upgrade PHP/the OS through an administrator-approved source first. The wizard does not add a PPA, change Fedora module streams, download PECL builds, or enable third-party repositories.

On Arch, complete the normal full system upgrade (`pacman -Syu`) before preparation if the host is behind. The wizard uses `pacman -S --needed` and never performs a partial `pacman -Sy` upgrade. Available but disabled PHP shared extensions can be enabled in a dedicated `99-admixcentral.ini`; CLI and FPM are validated afterward. See the [Arch PHP Redis package layout](https://archlinux.org/packages/extra/x86_64/php-redis/files/).

Fedora Atomic/immutable hosts require their own image/layering workflow and reboot. Automatic package installation stops on an rpm-ostree boot. Containers and non-systemd distributions need service configuration in their container/orchestration layer instead of this wizard. Third-party PHP stacks need the explicit FPM options and administrator-managed matching extension packages.

## Redis, credentials and data

The wizard preserves `APP_KEY`, database credentials, Redis passwords/usernames/URLs, TLS options, database indices and prefixes. Its probe uses Laravel’s connection parsing and checks both the `default` and `cache` Redis connections without printing secrets.

It also preserves `CACHE_STORE`, `SESSION_DRIVER`, `QUEUE_CONNECTION`, and existing worker connection/queue arguments. Laravel 12 uses **CACHE_STORE**; old installations containing only `CACHE_DRIVER=redis` may still use the default database cache. The readiness report displays the effective configured drivers so this mismatch is visible.

To adopt Redis on a deployment that currently uses another backend:

1. Prepare the server and verify both Redis PING checks. Confirm capacity, authentication, private network access and backups.
2. Schedule a maintenance window. Stop job producers (including the scheduler), drain all ready/delayed/reserved jobs from the old connection and reconcile failed jobs before switching workers. Account for every named queue and any explicit `queue:work database` argument.
3. Update the intended driver settings under your normal application configuration process. Changing the session backend/encryption signs users out. Changing the cache backend affects cached data and coordination locks. Do not move these while old and new workers are using different lock stores.
4. Clear Laravel configuration as the non-root runtime user, gracefully restart workers/Reverb, reload FPM, and verify login, polling and background jobs. Resume producers after verification. Keep the old data until rollback is no longer needed.

The wizard intentionally does not attempt this data migration and never runs FLUSHDB/FLUSHALL or `cache:clear`.

For a **dedicated local** Redis/Valkey instance with a verified current dataset backup, an optional hardening step binds it to loopback, enables protected mode and selects `maxmemory-policy noeviction`. This step restarts the server: data that has not been persisted can be lost, so skip it when durability or backup recovery is uncertain. This prevents silent eviction of queues/sessions, but writes can fail when a configured memory limit is reached; monitor memory and leave capacity for the rest of the host. Loopback does not isolate other local users: use ACLs or an appropriately permissioned socket on multi-user hosts.

Shared, remote, replicated, clustered and TLS instances need administrator-specific settings. Redis configs with active includes are left for manual review, including Fedora layouts that include module configuration files. Apply bind/protected-mode/eviction settings in the effective configuration without breaking remote clients. The wizard identifies the config from the service’s `ExecStart`; it does not guess a custom service file.

**Persistence needs special care.** The wizard reports missing AOF persistence but does not toggle it on an existing dataset. Back up the current dataset, enable AOF on the running server, verify completion/durability, and persist the change before a later restart. Simply editing `appendonly yes` and restarting an existing RDB deployment can lose data. Follow the [Valkey persistence conversion procedure](https://valkey.io/topics/persistence/) or the corresponding procedure for your Redis version. Configuration backups from this wizard do not back up Redis or the application database.

## Workers, Reverb and scheduler

The wizard discovers Supervisor’s actual include glob, including `.conf` and `.ini` layouts. It only updates the selected Admix program group; it does not restart all Supervisor programs. It preserves existing connection, named-queue and Reverb port arguments. Reverb uses one process per listening port, and binding it to loopback is offered only when the operator confirms the reverse proxy is local.

New workers use the configured queue connection. Worker count is selected explicitly (default two for a new program), and PHP-FPM sizing remains unchanged unless the operator supplies a measured limit. The script does not automatically allocate most of the host RAM to PHP.

Existing possible systemd worker services and scheduler cron/timer definitions are preserved for review instead of adding duplicates. Shared/custom Supervisor files are left for manual editing. The discovery is conservative and may identify another Laravel application’s service; verify its target path. User timers and custom wrapper scripts may require manual inspection. A scheduler definition alone does not prove the scheduler is healthy: verify recent scheduled jobs and service logs.

For a new scheduler, the wizard creates `/etc/cron.d/admixcentral` with the detected non-root user, absolute PHP binary and application working directory, then enables cron/crond. Scheduler output remains suppressed as in the original installer; inspect Laravel logs for failures.

## Host security and firewall certificates

The optional environment step can disable debug output, enable secure-only cookies after you confirm working public HTTPS, and enable encrypted sessions after explaining the sign-out effect. It clears **only** Laravel configuration and requests graceful queue/Reverb restarts as the runtime user. It does not run `config:cache`, because this application currently has direct `env()` reads outside configuration files. Canonical HTTPS URLs and reverse-proxy/TLS configuration remain an operator responsibility.

The legacy `/etc/sudoers.d/admixcentral` file can grant the web user root configuration writes. The wizard offers to remove that file’s grants after explaining the effect: web-triggered certificate installation and host tuning may stop working and should then be done by an OS administrator. It validates sudoers syntax before keeping the edit. Other sudoers files/groups may still grant privileges and need review. The GitHub updater and application code ownership are unchanged.

Check permissions without making the whole application root-owned: the built-in updater needs its existing deployment write access. Restrict `.env` to the deployment owner and required runtime group, normally mode `0640` or `0600` when the runtime user owns it. Give the selected runtime user write access to `storage/` and `bootstrap/cache/`; avoid world-writable permissions. The wizard reports access problems rather than recursively changing ownership.

Native pfSense/OPNsense CA certificates and self-signed certificates remain supported by the existing application security patches: use a verified per-firewall TLS public-key pin or the configured trusted CA bundle. CLI and FPM both need cURL/OpenSSL, and the runtime user must be able to read the CA bundle. The wizard never disables TLS verification or overwrites firewall trust records. Recheck trust when a firewall key is replaced.

On Fedora with SELinux enforcing, check AVC denials if PHP can run from the CLI but web requests cannot reach Redis/firewalls or write runtime files. Keep enforcement enabled. An administrator should set persistent file contexts for the deployment, read-only web content and writable runtime directories, then apply them with `restorecon`. Review `httpd_can_network_connect` for PHP outbound firewall-management/Redis connections: enabling it permits broader outbound network access for the web domain, so choose a scoped local policy instead where needed. A successful CLI Redis PING does **not** validate the PHP-FPM SELinux domain. Do not fix this with `setenforce 0`, broad `chmod 777`, or blind generated allow rules. The wizard reports SELinux state and leaves policy changes to the host administrator.

Keep database and Redis listeners private; expose Reverb directly only if that is your deliberate TLS/network design. Review nginx/Apache reverse-proxy rules, host firewall rules, SSH access and OS security updates separately. The wizard does not open network ports, replace web-server configuration, install certificates, or change kernel/sysctl settings.

## Backups, failures and recovery

Interactive changes create a root-only directory under `/var/backups/admixcentral-host/`. An exclusive lock prevents overlapping runs. For each edited file, numbered metadata records its original path, existence, ownership and mode; the corresponding `.original` contains the prior contents. Backup copies and action logs are private and may include secrets; do not publish them.

Files are replaced atomically, retaining existing extended attributes (including SELinux labels). On validation or activation failure, the affected file is restored and service recovery is attempted. Environment changes also snapshot cached Laravel configuration for recovery. Failure or interruption leaves earlier completed steps in place and returns a nonzero exit status. Package installation, data already processed by workers and session invalidation cannot be rolled back by restoring a config file. Service enablement is not automatically undone.

For manual recovery, read the numbered JSON metadata to identify the matching original file. Restore its contents **and the recorded owner/group/mode**, validate the service configuration, and reload the affected service. Do not blindly use `cp -a` on `.original`: backup copies deliberately have private `0600` permissions. For a file whose metadata says `existed: false`, remove only the newly created file after reviewing its purpose. Reapply persistent SELinux labels with `restorecon` when appropriate. Run the read-only check again after recovery.

## Validation performed in this repository

```bash
bash -n upgrade_admixcentral.sh
php -l scripts/security_upgrade/probe.php
python3 -B -m unittest discover -s tests/Upgrade -v
```

Tests cover distro/derivative and package selection, credential preservation, configuration idempotence, file rollback and interruption, read-only control flow, non-root execution, queue/Reverb settings, and Redis ACL/URL parsing against a local simulated RESP server. The PHP integration test needs installed Composer dependencies and phpredis; otherwise it is explicitly skipped. Tests do not install packages or restart real host services. Full Ubuntu/Fedora/Arch VM deployment testing is still required before declaring every OS/repository/custom-host combination validated.
