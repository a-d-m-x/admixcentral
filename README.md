# AdmixCentral

AdmixCentral is a centralized firewall management dashboard tailored for managing multiple **pfSense** instances. It leverages the [pfRest API](https://pfrest.org/) to provide a unified interface for system administrators to manage firewalls, companies, and users from a single pane of glass.

## Features

### 🛡️ Enterprise Security
- **Two-Factor Authentication (2FA)**: TOTP-based 2FA (Google Authenticator, Authy) with recovery codes and password confirmation for sensitive actions.
- **Secure Architecture**: Isolated multi-tenancy scopes, secure session management, and CSRF protection.
- **Role-Based Access**: Granular control over company and user permissions.

### 📱 Mobile-First Experience
- **Progressive Web App (PWA)**: Installable on iOS/Android for a native app-like experience.
- **Responsive Design**: "Glanceable" mobile dashboard with stacked card layouts, auto-minimizing sidebars, and touch-optimized navigation.
- **Dark Mode**: Fully integrated dark mode support that respects system preferences.

### ⚡ Real-Time Operations
- **WebSocket Updates**: Live dashboard status updates (CPU, RAM, Traffic) without refreshing.
- **Live Diagnostics**: Real-time ping, traceroute, and system activity logs.

### 🔧 Core Management
- **Unified Dashboard**: centralized view of health status, resource usage, and alerts across all managed instances.
- **Automated SSL/Hostname**: Built-in lifecycle management for Let's Encrypt SSL certificates and dynamic hostname handling.
- **System Customization**:
    - **Branding**: Dynamic Logo and Favicon uploading.
    - **Theming**: "Indigo Standard" unified design system.

### 🔥 Firewall Management
- **Tunables**: Full management of system tunables (sysctls) with reliable "Apply Changes" behavior.
- **Aliases & Rules**: Bulk management of aliases, NAT rules, and firewall rules with drag-and-drop ordering.
- **VPN Management**:
    - **OpenVPN**: Server/Client configuration.
    - **IPSec**: Full IKEv1/v2 tunnel management.
    - **WireGuard**: Modern, high-performance tunnel and peer management.
- **Services**: DHCP, DNS Resolver/Forwarder, HAProxy, and ACME integration.
- **Backup & Restore**: Automated config backups and one-click restore points.

## Tech Stack

- **Framework**: [Laravel 11.x](https://laravel.com) (PHP 8.2+)
- **Security**: Laravel Fortify (2FA, Authentication)
- **Frontend**: Blade, Tailwind CSS (Custom Utility Framework), Alpine.js
- **Real-Time**: Laravel Reverb / WebSockets
- **Database**: MySQL 8.0+ (Primary), PostgreSQL supported
- **API Integration**: Custom service layer interacting with [pfRest (pfSense REST API)](https://pfrest.org/) - [GitHub](https://github.com/pfrest/pfSense-pkg-RESTAPI)

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

## Development & Testing Setup

Use these instructions for setting up a local development environment.

### Steps

1. **Install System Dependencies**
   Ensure your system has the following installed:
   - PHP >= 8.2
   - Composer
   - Node.js & NPM
   - MySQL 8.0+

2. **Clone the Repository**
   ```bash
   git clone https://github.com/admxlz/admixcentral.git
   cd admixcentral
   ```

3. **Install Dependencies**
   ```bash
   composer install
   ```

4. **Run Installation Wizard**
   AdmixCentral includes a guided installer to configure your environment, database, and encryption keys automatically.
   ```bash
   php artisan install
   ```
   *Follow the on-screen prompts to enter your database credentials.*

5. **Build Frontend Assets**
   ```bash
   npm ci && npm run build
   ```

6. **Web Server Configuration**
   You can serve the application locally:
   ```bash
   php artisan serve
   ```
   For production, see the [Production Deployment](#production-deployment-nginx--php-fpm--ssl) section below.

### Troubleshooting

- **"Duplicate column name" error**: This is fixed in the latest version. The installer handles existing columns gracefully.
- **"Connection refused"**: Ensure your MySQL server is running and accessible. The installer will let you retry credentials.
- **Setup Wizard skipped**: If `.env` already exists, the installer might skip some steps. You can run `php artisan install` again or edit `.env` manually if needed.

---

## Production Deployment (Nginx + PHP-FPM + SSL)

For a production environment, it is recommended to use Nginx with PHP-FPM and SSL enabled.

### 1. Requirements
Ensure your server has the following installed:
- Nginx
- PHP 8.2 or higher + FPM (`php8.2-fpm`)
- MySQL 8.0+ or MariaDB 10.5+
- Certbot (for SSL)

### 2. File Ownership & Permissions (Critical)
**NEVER** run Composer or NPM as root. This causes permission issues with the web server.

Run commands as the web server user (usually `www-data`) or your deployment user:

```bash
cd /var/www/admixcentral

# 1. Set ownership to your web user (e.g., www-data)
sudo chown -R www-data:www-data .

# 2. Run commands as that user
sudo -u www-data composer install --no-dev
sudo -u www-data npm ci && sudo -u www-data npm run build

# 3. Create storage link
sudo -u www-data php artisan storage:link

# 4. Set permissions for writeable directories
sudo chmod -R 775 storage bootstrap/cache
```

### 3. Nginx Configuration
Create a new configuration file at `/etc/nginx/sites-available/admixcentral`:

```nginx
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    server_name _;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2 default_server;
    listen [::]:443 ssl http2 default_server;
    server_name _;
    root /var/www/admixcentral/public;

    # SSL Configuration (Let's Encrypt placeholders)
    # ssl_certificate /etc/letsencrypt/live/yourdomain.com/fullchain.pem;
    # ssl_certificate_key /etc/letsencrypt/live/yourdomain.com/privkey.pem;
    # include /etc/letsencrypt/options-ssl-nginx.conf;
    # ssl_dhparam /etc/letsencrypt/ssl-dhparams.pem;

    # ... (rest of config)

    index index.html index.htm index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}

# IMPORTANT: Remove the default Nginx site to avoid conflicts
# sudo rm /etc/nginx/sites-enabled/default
```

Enable the site:
```bash
ln -s /etc/nginx/sites-available/admixcentral /etc/nginx/sites-enabled/
nginx -t
systemctl reload nginx
```

### 4. Enable SSL with Certbot
The easiest way to secure your application is using Certbot (Let's Encrypt):

```bash
# Install Certbot
sudo apt install certbot python3-certbot-nginx

# Obtain and install certificate
sudo certbot --nginx -d dashboard.yourdomain.com
```
Certbot will automatically update your Nginx configuration with the correct SSL paths.

### 5. Configure Queue Workers (Supervisor)
For optimal performance, AdmixCentral uses background workers to process firewall checks in parallel.

1. **Install Supervisor**
   ```bash
   sudo apt-get install supervisor
   ```

2. **Create Configuration**
   Create `/etc/supervisor/conf.d/admix-worker.conf`:
   ```ini
   [program:admix-worker]
   process_name=%(program_name)s_%(process_num)02d
   command=php /var/www/admixcentral/artisan queue:work --sleep=3 --tries=3 --max-time=3600
   autostart=true
   autorestart=true
   user=www-data
   numprocs=20
   redirect_stderr=true
   stdout_logfile=/var/www/admixcentral/storage/logs/worker.log
   stopwaitsecs=3600
   ```
   *Note: Ensure the path `/var/www/admixcentral` matches your installation directory.*

3. **Fix Permissions**
   Ensure the worker process can write to logs:
   ```bash
   sudo chown -R www-data:www-data /var/www/admixcentral/storage
   sudo chown -R www-data:www-data /var/www/admixcentral/bootstrap/cache
   sudo chmod -R 775 /var/www/admixcentral/storage
   ```

4. **Start Workers**
   ```bash
   sudo supervisorctl reread
   sudo supervisorctl update
   sudo supervisorctl start all
   ```

---

## Initial Setup

Upon first installation, AdmixCentral requires you to create a **Global Admin** account via the secure Setup Wizard.

1. Access the application in your browser (e.g., `http://dashboard.yourdomain.com`).
2. You will be automatically redirected to the **Setup Wizard**.
3. Create your admin account details.
4. You will then be logged in and redirected to the Dashboard.

## Firewall Setup

To manage a pfSense firewall, ensure the [pfSense REST API (pfRest)](https://github.com/pfrest/pfSense-pkg-RESTAPI) package is installed on the target pfSense machine.

1. Log in to AdmixCentral.
2. Navigate to **Firewalls > Add Firewall**.
3. Enter the **pfSense URL** and **API Credentials** (Username/Password).
4. Click **Connect**. AdmixCentral will automatically verify the connection and retrieve system details.

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
> This package is not affiliated or supported by Netgate or the pfSense team.
