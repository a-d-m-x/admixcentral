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
    public function install(string $domain, string $email, string $method = 'http', ?string $cfToken = null): array
    {
        // Strict domain validation to prevent Nginx config injection
        if (strlen($domain) > 253 || !preg_match('/\A(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z](?:[a-z0-9-]{0,61}[a-z0-9])?\z/i', $domain)) {
            throw new \Exception("Invalid domain format.");
        }

        try {
            // 1. Request Certificate
            if ($method === 'cloudflare') {
                if (!$cfToken) {
                    throw new \Exception('A Cloudflare API token is required for DNS-01 verification.');
                }
                $this->requestCertificateViaCloudflareDns($domain, $email, $cfToken);
            } else {
                $this->requestCertificate($domain, $email);
            }

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
            // The web interface redirects HTTP to HTTPS; Reverb also uses TLS.
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
        // Run via the narrow wrapper — NOT `sudo certbot` directly.
        // The wrapper hardcodes all certbot flags and accepts NO hook arguments,
        // preventing --deploy-hook injection if the web app is ever compromised.
        $result = Process::run(
            ['sudo', '/usr/local/bin/admixcentral-request-cert', $domain, $email, 'http']
        );

        if ($result->failed()) {
            throw new \Exception("Certbot (HTTP) failed: " . $result->errorOutput());
        }
    }

    protected function requestCertificateViaCloudflareDns(string $domain, string $email, string $cfToken): void
    {
        // Write temp credentials file — chmod 600 immediately, deleted in finally block
        $credPath = storage_path('app/cf-credentials-' . uniqid() . '.ini');
        file_put_contents($credPath, "dns_cloudflare_api_token = {$cfToken}\n");
        chmod($credPath, 0600);

        try {
            // Run via the narrow wrapper — NOT `sudo certbot` directly.
            // The wrapper validates that the credentials file is inside storage/app/
            // and hardcodes all other certbot arguments.
            $result = Process::run(
                ['sudo', '/usr/local/bin/admixcentral-request-cert', $domain, $email, 'cloudflare', $credPath]
            );

            if ($result->failed()) {
                throw new \Exception("Certbot (DNS-01) failed: " . $result->errorOutput());
            }
        } finally {
            // Always remove the credentials file — even on failure
            if (file_exists($credPath)) {
                unlink($credPath);
            }
        }
    }

    public function deleteCertificate(string $domain): void
    {
        $result = Process::run(
            ['sudo', '/usr/local/bin/admixcentral-cert-delete', $domain]
        );

        if ($result->failed()) {
            Log::warning("Failed to delete certificate for {$domain}: " . $result->errorOutput());
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
        // Write to system path via wrapper (hardcoded destination — no path injection possible)
        $source = storage_path($this->nginxConfigPath);
        $write = Process::run("cat " . escapeshellarg($source) . " | sudo /usr/local/bin/admixcentral-nginx-config-write");
        if ($write->failed()) {
            throw new \Exception("Failed to write Nginx config: " . $write->errorOutput());
        }

        // Test config via wrapper (no arguments accepted — cannot load arbitrary config)
        $test = Process::run(['sudo', '/usr/local/bin/admixcentral-nginx-test']);
        if ($test->failed()) {
            throw new \Exception("Nginx config test failed: " . $test->errorOutput());
        }

        // Reload via wrapper (no arguments — only reloads nginx, cannot stop/restart)
        $reload = Process::run(['sudo', '/usr/local/bin/admixcentral-nginx-reload']);
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
    server_name {$domain};
    root /var/www/admixcentral/public;

    location ^~ /.well-known/acme-challenge/ {
        default_type text/plain;
        try_files \$uri =404;
    }

    location / {
        return 301 https://{$domain}\$request_uri;
    }
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;

    server_name {$domain};

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

    location ~ ^/index\.php(/|$) {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ \.php$ {
        return 404;
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

    location ~ ^/index\.php(/|$) {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ \.php$ {
        return 404;
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
