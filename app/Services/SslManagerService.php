<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use App\Services\SystemConfigurationService;

class SslManagerService
{
    protected $nginxConfigPath = 'app/admixcentral.nginx.conf'; // Relative to storage/
    protected $systemConfigPath = '/etc/nginx/sites-available/admixcentral';


    public function __construct(
        protected SystemConfigurationService $configService
    ) {
    }

    /**
     * Install SSL certificate for the given domain
     */
    public function install(string $domain, string $email): array
    {
        // Strict domain validation to prevent Nginx config injection
        if (!preg_match('/^(?!:\/\/)(?=.{1,255}$)((.{1,63}\.){1,127}(?![0-9]*$)[a-z0-9-]+\.?)$/i', $domain) || preg_match('/[;{}\n\r]/', $domain)) {
            throw new \Exception("Invalid domain format.");
        }

        try {
            // 1. Request Certificate
            $this->requestCertificate($domain, $email);

            // 2. Safely Apply Nginx Configuration
            // We use a separate try-catch to rollback if applying config fails
            try {
                $this->updateNginxConfig($domain);
                $this->applyNginxConfig();
            } catch (\Exception $e) {
                // Verification failed or reload failed. Revert immediately to HTTP.
                Log::warning("SSL Configuration failed, reverting to HTTP: " . $e->getMessage());

                // Restore HTTP config
                $this->updateNginxConfigToHttp($domain);
                $this->applyNginxConfig();

                throw new \Exception("SSL verification failed. Reverted to HTTP. Error: " . $e->getMessage());
            }

            // 4. Update System Environment
            $this->configService->updateSystemHostname($domain, 'https');

            // 5. Enable Secure Cookies and Update Websockets
            // Note: We keep REVERB on 443/wss for SSL, but the main site is dual-stack.
            // However, Mixed Content issues might arise if user visits via HTTP but Reverb tries WSS.
            // For now, aligning Reverb with the primary access method (HTTPS).
            $this->configService->updateEnv([
                'SESSION_SECURE_COOKIE' => 'true',
                'REVERB_PORT' => '443',
                'REVERB_SCHEME' => 'https',
                'VITE_REVERB_PORT' => '443',
                'VITE_REVERB_SCHEME' => 'https',
            ]);

            return ['success' => true, 'message' => 'SSL installed successfully. Please use HTTPS.'];
        } catch (\Exception $e) {
            Log::error("SSL Installation Failed: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    protected function requestCertificate(string $domain, string $email): void
    {
        // Check if certbot exists
        $check = Process::run('which certbot');
        if ($check->failed()) {
            throw new \Exception('Certbot is not installed. Please run the installer script.');
        }

        // Run certbot safely
        // --webroot using public dir as root for challenges
        $cmd = "sudo certbot certonly --webroot -w " . escapeshellarg(public_path()) .
            " -d " . escapeshellarg($domain) . " --non-interactive --agree-tos -m " . escapeshellarg($email) . " --deploy-hook ''";

        $result = Process::run($cmd);

        if ($result->failed()) {
            throw new \Exception("Certbot failed: " . $result->errorOutput());
        }

        // Verify certificates exist (skipped due to permissions - nginx -t will verify later)
        // Note: www-data cannot read /etc/letsencrypt/live directly, causing false negatives.
    }

    public function deleteCertificate(string $domain): void
    {
        // Delete certificate using certbot safely
        // --cert-name matches the domain by default when creating standard certs
        $cmd = "sudo certbot delete --cert-name " . escapeshellarg($domain) . " --non-interactive";

        $result = Process::run($cmd);

        if ($result->failed()) {
            // Log but don't throw, as the critical part (Nginx cleanup) is already done
            Log::warning("Failed to delete certificate files for {$domain}: " . $result->errorOutput());
        }
    }

    protected function updateNginxConfig(string $domain): void
    {
        $stub = $this->getNginxSslStub($domain);
        file_put_contents(storage_path($this->nginxConfigPath), $stub);
    }

    protected function updateNginxConfigToHttp(string $domain): void
    {
        $stub = $this->getNginxHttpStub($domain);
        file_put_contents(storage_path($this->nginxConfigPath), $stub);
    }

    protected function applyNginxConfig(): void
    {
        // Write to system path using sudo tee
        $source = storage_path($this->nginxConfigPath);
        $cmd = "cat {$source} | sudo tee {$this->systemConfigPath}";

        $write = Process::run($cmd);
        if ($write->failed()) {
            throw new \Exception("Failed to write Nginx config: " . $write->errorOutput());
        }

        // Test config
        $test = Process::run("sudo nginx -t");
        if ($test->failed()) {
            throw new \Exception("Nginx config test failed: " . $test->errorOutput());
        }

        // Reload Nginx
        $reload = Process::run("sudo systemctl reload nginx");
        if ($reload->failed()) {
            throw new \Exception("Failed to reload Nginx: " . $reload->errorOutput());
        }
    }

    public function uninstall(string $domain): array
    {
        try {
            // 1. Generate HTTP Config
            $stub = $this->getNginxHttpStub($domain);
            file_put_contents(storage_path($this->nginxConfigPath), $stub);

            // 2. Apply Nginx Config
            $this->applyNginxConfig();

            // 3. Update System Configuration
            $this->configService->updateSystemHostname($domain, 'http');

            // 4. Disable Secure Cookies and Revert Websockets
            $this->configService->updateEnv([
                'SESSION_SECURE_COOKIE' => 'false',
                'REVERB_PORT' => '80',
                'REVERB_SCHEME' => 'http',
                'VITE_REVERB_PORT' => '80',
                'VITE_REVERB_SCHEME' => 'http',
            ]);

            // 5. Delete Certificate Files
            $this->deleteCertificate($domain);

            return ['success' => true, 'message' => 'SSL uninstalled successfully. Reverted to HTTP.'];
        } catch (\Exception $e) {
            Log::error("SSL Uninstall Failed: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    protected function getNginxSslStub(string $domain): string
    {
        return <<<NGINX
server {
    listen 80;
    listen [::]:80;
    listen 443 ssl http2;
    listen [::]:443 ssl http2;

    server_name _;

    root /var/www/admixcentral/public;

    ssl_certificate /etc/letsencrypt/live/{$domain}/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/{$domain}/privkey.pem;

    # Optional: Enable HSTS (commented out by default to avoid accidental lockout)
    # add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    # WebSocket Proxy for Reverb
    location /app {
        proxy_http_version 1.1;
        proxy_set_header Host \$http_host;
        proxy_set_header Scheme \$scheme;
        proxy_set_header SERVER_PORT \$server_port;
        proxy_set_header REMOTE_ADDR \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection "Upgrade";

        proxy_pass http://127.0.0.1:8080;
    }
}
NGINX;
    }

    protected function getNginxHttpStub(string $domain): string
    {
        return <<<NGINX
server {
    listen 80;
    listen [::]:80;
    server_name _;

    root /var/www/admixcentral/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    # WebSocket Proxy for Reverb
    location /app {
        proxy_http_version 1.1;
        proxy_set_header Host \$http_host;
        proxy_set_header Scheme \$scheme;
        proxy_set_header SERVER_PORT \$server_port;
        proxy_set_header REMOTE_ADDR \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection "Upgrade";

        proxy_pass http://127.0.0.1:8080;
    }
}
NGINX;
    }
}
