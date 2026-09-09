# AdmixCentral

AdmixCentral is a centralized firewall management dashboard engineered for managing multiple **pfSense®** and **OPNsense®** instances from a single pane of glass. It provides a unified, mobile-first interface for system administrators and MSPs to manage firewalls, companies, and users across hybrid firewall deployments.

## Features

### 🌐 Multi-Platform Firewall Support
- **Dual Engine Architecture**: Full compatibility with both **pfSense** (via [pfRest / pfSense-pkg-RESTAPI](https://pfrest.org/)) and **OPNsense** (via native modular REST API with API Key & Secret authentication).
- **Intelligent Platform Adaptation**: Automatic detection of firewall OS type (`pfSense` vs `OPNsense`) with tailored workflows, interface normalization, and feature guards.
- **Visual Platform Badges**: Color-coded badges (`PFSENSE` and `OPNSENSE`) across cards, headers, and tables for instant fleet identification.

### 🛡️ Enterprise Security
- **Two-Factor Authentication (2FA)**: TOTP-based 2FA (Google Authenticator, Authy) with recovery codes and password confirmation for sensitive actions.
- **Secure Architecture**: Isolated multi-tenancy scopes, secure session management, and CSRF protection.
- **Role-Based Access**: Granular control over company and user permissions (Superadmin, Company Admin, Read-Only).

### 📱 Mobile-First Experience
- **Progressive Web App (PWA)**: Installable on iOS/Android for a native app-like experience.
- **Responsive Design**: "Glanceable" mobile dashboard with stacked card layouts, auto-minimizing sidebars, and touch-optimized navigation.
- **Dark Mode**: Fully integrated dark mode support that respects system preferences.

### ⚡ Real-Time Operations
- **WebSocket Updates**: Live dashboard status updates (CPU, RAM, Traffic) without refreshing.
- **Live Diagnostics**: Real-time ping, traceroute, and system activity logs.

### 🔧 Core Management
- **Unified Dashboard**: Centralized view of health status, resource usage, and alerts across all managed instances.
- **Automated SSL/Hostname**: Built-in lifecycle management for Let's Encrypt SSL certificates and dynamic hostname handling.
- **System Customization**:
    - **Branding**: Dynamic Logo and Favicon uploading.
    - **Theming**: "Indigo Standard" unified design system.

### 🔥 Firewall Management
- **Rules & Aliases**: Full CRUD management of firewall rules (interface-specific and floating), IP/port aliases, and NAT rules (Port Forward, 1:1, Outbound / SNAT) with drag-and-drop rule reordering.
- **Tunables (sysctl)**: Full management of system tunables with reliable "Apply Changes" orchestration.
- **Virtual IPs**: IP Alias, CARP, and Proxy ARP management across physical interfaces and VLANs.
- **High Availability & CARP**: Real-time CARP status monitoring, demotion levels, and XMLRPC/pfsync High Availability synchronization.
- **VPN Management**:
    - **OpenVPN**: Server/client instances, cryptographic settings, and exportable client configurations.
    - **IPSec**: Phase 1 / Phase 2 tunnels, mobile clients, Security Policy Database (SPD), and Security Association Database (SAD).
    - **WireGuard**: Modern, high-performance tunnel and peer management with QR code generation and instant client config downloads.
- **Services**: DHCP Server & Leases, Unbound DNS Resolver, Dnsmasq DNS Forwarder, NTP, Syslog, HAProxy, and ACME certificate integration.
- **Security & Traffic Management**:
    - **Intrusion Detection (IDS/IPS)**: Suricata rule category inspection and alert logging.
    - **Traffic Shaper**: Pipe and queue bandwidth management.
    - **Captive Portal**: Zone administration and active session monitoring.
    - **Monit**: Service monitoring and process health oversight.
- **Backup & Restore**: Automated and on-demand configuration backups with AES-256-GCM encryption, SSH credentials preservation, and one-click restore points.

## Screenshots
<p align="center">
  <a href="https://admixcentralmedia.admix.cloud/AdmixC-Card.png">
    <img src="https://admixcentralmedia.admix.cloud/AdmixC-Card.png" width="30%" alt="Dashboard Card View">
  </a>

  <a href="https://admixcentralmedia.admix.cloud/AdmixC-Compact.png">
    <img src="https://admixcentralmedia.admix.cloud/AdmixC-Compact.png" width="30%" alt="Dashboard Compact View">
  </a>

  <a href="https://admixcentralmedia.admix.cloud/AdmixC-Add-Company.png">
    <img src="https://admixcentralmedia.admix.cloud/AdmixC-Add-Company.png" width="30%" alt="Add Company">
  </a>
</p>

<p align="center">
  <a href="https://admixcentralmedia.admix.cloud/AdmixC-Add-User.png">
    <img src="https://admixcentralmedia.admix.cloud/AdmixC-Add-User.png" width="30%" alt="Add User">
  </a>

  <a href="https://admixcentralmedia.admix.cloud/AdmixC-Firewall-Dashboard-1.png">
    <img src="https://admixcentralmedia.admix.cloud/AdmixC-Firewall-Dashboard-1.png" width="30%" alt="Firewall Dashboard">
  </a>

  <a href="https://admixcentralmedia.admix.cloud/AdmixC-Firewall-Dashboard-2.png">
    <img src="https://admixcentralmedia.admix.cloud/AdmixC-Firewall-Dashboard-2.png" width="30%" alt="Firewall Dashboard">
  </a>
</p>

<p align="center">
  <a href="https://admixcentralmedia.admix.cloud/AdmixC-Firewall-NAT-1.png">
    <img src="https://admixcentralmedia.admix.cloud/AdmixC-Firewall-NAT-1.png" width="30%" alt="Firewall NAT">
  </a>

  <a href="https://admixcentralmedia.admix.cloud/AdmixC-Firewall-Rules-1.png">
    <img src="https://admixcentralmedia.admix.cloud/AdmixC-Firewall-Rules-1.png" width="30%" alt="Firewall Rules">
  </a>

  <a href="https://admixcentralmedia.admix.cloud/AdmixC-Firewall-System-1.png">
    <img src="https://admixcentralmedia.admix.cloud/AdmixC-Firewall-System-1.png" width="30%" alt="Firewall System">
  </a>
</p>

<p align="center">
  <a href="https://admixcentralmedia.admix.cloud/AdmixC-Settings-1.png">
    <img src="https://admixcentralmedia.admix.cloud/AdmixC-Settings-1.png" width="30%" alt="Settings">
  </a>

  <a href="https://admixcentralmedia.admix.cloud/AdmixC-Settings-2.png">
    <img src="https://admixcentralmedia.admix.cloud/AdmixC-Settings-2.png" width="30%" alt="Settings">
  </a>
</p>

## Tech Stack

- **Framework**: [Laravel 11.x](https://laravel.com) (PHP 8.2+)
- **Security**: Laravel Fortify (2FA, Authentication), Argon2id password hashing, encrypted credential storage
- **Frontend**: Blade, Tailwind CSS (Custom Utility Framework), Alpine.js
- **Real-Time**: Laravel Reverb / WebSockets
- **Database**: MySQL 8.0+ (Primary), MariaDB, PostgreSQL supported
- **API Integrations**:
    - **pfSense**: Custom service layer interacting with [pfRest (pfSense REST API)](https://pfrest.org/) - [GitHub](https://github.com/pfrest/pfSense-pkg-RESTAPI)
    - **OPNsense**: Native modular REST API integration across 30+ core & plugin endpoints (`/api/*`) using key/secret token authentication

---

## Architecture Evolution (v0.x to v1.0)

This release marks a major architectural shift from the initial prototype. The following specification changes have been implemented to support enterprise-grade reliability and mobile usability:

| System Area | Legacy Specification (v0.x) | Modern Specification (v1.0) |
| :--- | :--- | :--- |
| **Data Transport** | REST Polling (Waterfall requests) | **WebSockets (Laravel Reverb)** + Optimistic UI |
| **Database** | SQLite (File-based) | **MySQL 8.0+** (Transactional, Scalable) |
| **Authentication** | Basic Session Auth | **2FA (TOTP)** via Laravel Fortify + Password Confirmation |
| **Frontend Strategy** | Desktop-centric Dashboard | **Mobile-First PWA** (Installable, Responsive, Touch-optimized) |
| **Resilience** | UI froze on connection loss | **Offline Resilience** (Cached state, Blur overlays, Auto-reconnect) |
| **Design System** | Ad-hoc Utility Classes | **"Indigo Standard"** (Unified colors, typography, and modal systems) |
| **Multi-Tenancy** | Basic scope filtration | **Strict Scope Isolation** with Role-Based Access Control |

---

## Installation

AdmixCentral can be installed automatically using the included installation script or manually for administrators who prefer to configure each component themselves.

### Supported Operating Systems

The automated installer is currently tested on:

- Ubuntu 24.04 LTS
- Fedora 42+
- Arch Linux (experimental)

Other Linux distributions may work but are not officially tested.

---

## Automatic Installation (Recommended)

The included installer automates the entire deployment process, including:

- Nginx installation and configuration
- PHP 8.3+ installation and configuration
- MySQL/MariaDB installation and configuration
- Redis installation and configuration
- Supervisor installation and configuration
- Composer dependency installation
- Frontend asset compilation
- Laravel queue worker setup
- Laravel Reverb setup
- Scheduled task configuration
- Permission and ownership configuration

### Install AdmixCentral

```bash
wget https://raw.githubusercontent.com/a-d-m-x/admixcentral/main/install_admixcentral.sh
chmod +x install_admixcentral.sh
sudo ./install_admixcentral.sh
```

During installation, you will be prompted for:

- Database username
- Database password
- Application URL
- Additional application settings

After installation completes, browse to:

```text
http://your-server-ip
```

to access AdmixCentral.

---

## Manual Installation

For administrators who prefer to install and configure all components manually.

### 1. Install Required Software

Install the following packages using your distribution's package manager:

- PHP 8.3 or newer
- Composer
- Node.js 20 or newer
- Nginx
- MySQL or MariaDB
- Redis
- Supervisor

Required PHP extensions:

- bcmath
- curl
- gd
- intl
- mbstring
- mysqli/mysql
- redis
- xml
- zip

---

### 2. Clone the Repository

```bash
git clone https://github.com/admxlz/admixcentral.git
cd admixcentral
```

---

### 3. Install PHP Dependencies

```bash
composer install --no-dev --optimize-autoloader
```

---

### 4. Create Environment File

```bash
cp .env.example .env
```

Edit `.env` and configure the application:

```env
APP_NAME="AdmixCentral"
APP_ENV=production
APP_DEBUG=false
APP_URL=http://your-server-ip

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=admixcentral
DB_USERNAME=admixcentral
DB_PASSWORD=your_password

CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

---

### 5. Generate Application Key

```bash
php artisan key:generate
```

---

### 6. Run the Installation Wizard

```bash
php artisan install
```

The installation wizard will:

- Configure application settings
- Verify database connectivity
- Create required database tables
- Seed default data
- Generate required application secrets

---

### 7. Install Frontend Dependencies

```bash
npm install
npm run build
```

---

### 8. Configure Storage

```bash
php artisan storage:link
```

---

### 9. Configure Queue Workers

Create a Supervisor configuration similar to:

```ini
[program:admix-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/admixcentral/artisan queue:work redis --sleep=3 --tries=3
autostart=true
autorestart=true
user=www-data
numprocs=8
redirect_stderr=true
stdout_logfile=/var/www/admixcentral/storage/logs/worker.log
```

Reload Supervisor:

```bash
supervisorctl reread
supervisorctl update
```

---

### 10. Configure Laravel Reverb

Create a Supervisor configuration similar to:

```ini
[program:admix-reverb]
command=php /var/www/admixcentral/artisan reverb:start --host=0.0.0.0 --port=8080
directory=/var/www/admixcentral
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/admixcentral/storage/logs/reverb.log
```

Reload Supervisor:

```bash
supervisorctl reread
supervisorctl update
```

---

### 11. Configure the Scheduler

Add the following cron entry:

```cron
* * * * * php /var/www/admixcentral/artisan schedule:run >/dev/null 2>&1
```

---

### 12. Configure Permissions

```bash
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
```

Adjust the web server user as required for your distribution.

---

### 13. Configure Nginx

Set the web root to:

```text
/var/www/admixcentral/public
```

Example configuration:

```nginx
server {
    listen 80;
    server_name _;

    root /var/www/admixcentral/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php-fpm/www.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

Restart Nginx and PHP-FPM after configuration.

---

## Updating AdmixCentral

To update an existing installation:

```bash
cd /var/www/admixcentral

git pull

composer install --no-dev --optimize-autoloader

php artisan migrate --force

npm install
npm run build

php artisan optimize:clear

supervisorctl restart all
systemctl reload nginx
```

## Adding Your First Firewall

AdmixCentral supports both pfSense and OPNsense firewalls with platform-native workflows.

### Adding a pfSense Firewall

To manage a pfSense firewall, ensure the [pfSense REST API (pfRest)](https://github.com/pfrest/pfSense-pkg-RESTAPI) package is installed on the target pfSense machine.

1. Log in to AdmixCentral as an administrator.
2. Navigate to **Firewalls > Add Firewall**.
3. Select **pfSense** as the firewall type.
4. Enter the **Firewall Name**, **URL** (e.g. `https://192.168.1.1`), and **API Credentials** (Username/Password or API Token).
5. Click **Connect**. AdmixCentral will verify connectivity, fetch system info and interfaces, and register the firewall.

### Adding an OPNsense Firewall

OPNsense includes a built-in REST API out of the box — no third-party packages are required.

1. In your OPNsense Web GUI:
   - Navigate to **System > Access > Users**.
   - Edit an administrative user (or create a dedicated API user with appropriate permissions).
   - Under the **API keys** section, click the `+` icon.
   - Your browser will automatically download an `apikey.txt` file containing your `key` and `secret`.
2. In AdmixCentral:
   - Navigate to **Firewalls > Add Firewall**.
   - Select **OPNsense** as the firewall type.
   - Enter the **Firewall Name**, **URL** (e.g. `https://192.168.240.11`), **API Key**, and **API Secret**.
3. Click **Connect**. AdmixCentral will verify connectivity via `/api/core/firmware/status`, retrieve interface configurations, and register the firewall.

---

## Troubleshooting

### "403 Forbidden" on Uploaded Images (Logo/Favicon)
If you encounter a 403 error for uploaded images, it usually means the webserver cannot find the file in the `public/storage` directory, or it is looking in the wrong place.

**Common Causes:**
1.  **Missing Symlink**: The `public/storage` symbolic link is missing.
    *   **Fix**: Run `php artisan storage:link`.
2.  **Wrong Disk**: The application was saving files to `storage/app/private` (default) instead of `storage/app/public`.
    *   **Fix**: Update `SystemCustomizationController.php` to use `store('path', 'public')` (This is fixed in the latest codebase).
3.  **Permissions**: The webserver user (e.g., `www-data` or `nginx`) does not have permission to read the storage folder.
    *   **Fix**: `chmod -R 775 storage/app/public` and ensure ownership is correct.

**Verification:**
Check if the file actually exists where the symlink points:
```bash
ls -la storage/app/public/customization/
```
If the file is missing there but you have a URL, the upload process likely failed or saved to the wrong disk.

## Disclaimers

> [!CAUTION]
> This project is an independent open-source tool and is not affiliated with, sponsored by, or endorsed by Netgate, Electric Sheep Fencing LLC (pfSense®), or Deciso B.V. (OPNsense®). pfSense is a registered trademark of Electric Sheep Fencing LLC. OPNsense is a registered trademark of Deciso B.V.
