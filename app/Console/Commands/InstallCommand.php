<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class InstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'install';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install and configure AdmixCentral application';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('    ___       __           _      ______            __             __     ');
        $this->info('   /   | ____/ /___ ___   (_)  __/ ____/___  ____  / /__________ _/ /     ');
        $this->info('  / /| |/ __  / __ `__ \ / / |/_/ /   / _ \/ __ \/ __/ ___/ __ `/ /      ');
        $this->info(' / ___ / /_/ / / / / / // />  </ /___/  __/ / / / /_/ /  / /_/ / /       ');
        $this->info('/_/  |_\__,_/_/ /_/ /_/_/_/|_/ \____/\___/_/ /_/\__/_/   \__,_/_/        ');
        $this->info('');
        $this->info('Welcome to the AdmixCentral Installation Wizard!');
        $this->newLine();

        // 1. Environment File Check
        if (!File::exists(base_path('.env'))) {
            if (File::exists(base_path('.env.example'))) {
                $this->comment('Creating .env file from .env.example...');
                File::copy(base_path('.env.example'), base_path('.env'));
            } else {
                $this->error('.env.example not found! Cannot proceed.');
                return 1;
            }
        }

        // 2. Application Configuration
        $this->info('Step 1: Application Configuration');
        $appUrl = $this->ask('Application URL', env('APP_URL', 'http://localhost'));
        $this->updateEnvFile(['APP_URL' => $appUrl]);

        // 3. Database Configuration
        $this->newLine();
        $this->info('Step 2: Database Configuration (MySQL)');

        $this->configureDatabase();

        // 4. Key Generation
        $this->newLine();
        $this->info('Step 3: Generating Application Key');
        $this->call('key:generate');

        // 5. Migrations
        $this->newLine();
        $this->info('Step 4: Running Database Migrations');
        if ($this->confirm('This will migrate the database. Proceed?', true)) {
            $this->call('migrate', ['--force' => true]);
        }

        // 6. Storage Linking
        $this->newLine();
        $this->info('Step 5: Linking Storage');
        if (!File::exists(public_path('storage'))) {
            $this->call('storage:link');
        } else {
            $this->comment('Storage link already exists.');
        }

        // 7. Clear Caches & Finalize
        $this->newLine();
        $this->info('Step 6: Finalizing Installation');
        $this->comment('Running package discovery and clearing caches...');

        // We use system call for 'composer run' to ensure it runs in the right environment, 
        // or we can call the artisan commands directly if we want to avoid composer dependency in production (though composer is usually there).
        // Since we defined 'admix:finalize' in composer.json, let's try to run that, or fallback to manual artisan calls.

        $this->call('config:clear');
        $this->call('view:clear');
        $this->call('route:clear');

        // Package discovery is tricky from within artisan if it was skipped. 
        // But since we are running 'php artisan install', we effectively booted the app.
        // However, we skipped package auto-discovery in composer install.
        // So we should run it now.
        $this->info('Runing package discovery...');
        passthru('php artisan package:discover --ansi');

        $this->newLine();
        $this->info('----------------------------------------------------');
        $this->info(' Installation Complete! ');
        $this->info('----------------------------------------------------');
        $this->comment('Next Steps:');
        $this->comment('1. Run "npm install && npm run build" to compile frontend assets.');
        $this->comment('2. Configure your web server (Nginx/Apache) to serve the "public" directory.');
        $this->comment('3. Access ' . $appUrl . ' to complete the Admin Setup via the web wizard.');
        $this->newLine();

        return 0;
    }

    protected function configureDatabase()
    {
        $existingHost = env('DB_HOST', '127.0.0.1');
        $existingPort = env('DB_PORT', '3306');
        $existingDb = env('DB_DATABASE', 'admixcentral');
        $existingUser = env('DB_USERNAME', 'root');
        $existingPass = env('DB_PASSWORD', '');

        if (!empty($existingDb) && !empty($existingUser)) {
            try {
                $dsn = "mysql:host={$existingHost};port={$existingPort};dbname={$existingDb}";
                $pdo = new \PDO($dsn, $existingUser, $existingPass, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_TIMEOUT => 3]);
                $this->info('Database connection verified using existing environment settings.');
                $this->updateEnvFile(['APP_INSTALLED' => 'true']);
                return;
            } catch (\Exception $e) {
                // If not connected, proceed to prompt or check if non-interactive
            }
        }

        if (!$this->input->isInteractive()) {
            $this->warn('Non-interactive mode: unable to prompt for database credentials.');
            return;
        }

        $connected = false;

        while (!$connected) {
            $host = $this->ask('Database Host', env('DB_HOST', '127.0.0.1'));
            $port = $this->ask('Database Port', env('DB_PORT', '3306'));
            $database = $this->ask('Database Name', env('DB_DATABASE', 'admixcentral'));
            $username = $this->ask('Database Username', env('DB_USERNAME', 'root'));
            $password = $this->secret('Database Password (hidden)') ?: env('DB_PASSWORD', '');

            $this->comment('Testing database connection...');

            try {
                // Determine driver to use (assuming mysql per instructions, but respecting config if possible)
                // For connection testing we use raw PDO to avoid Laravel caching the old config
                $dsn = "mysql:host={$host};port={$port};dbname={$database}";
                $pdo = new \PDO($dsn, $username, $password, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_TIMEOUT => 5]);

                $this->info('Connection successful!');
                $connected = true;

                // Update .env
                $this->updateEnvFile([
                    'DB_HOST' => $host,
                    'DB_PORT' => $port,
                    'DB_DATABASE' => $database,
                    'DB_USERNAME' => $username,
                    'DB_PASSWORD' => $password,
                    'APP_INSTALLED' => 'true',
                ]);

            } catch (\Exception $e) {
                $this->error('Connection failed: ' . $e->getMessage());
                if (!$this->confirm('Try again?', true)) {
                    $this->warn('Keeping existing configuration/skipping database setup.');
                    return;
                }
            }
        }
    }

    protected function updateEnvFile(array $data)
    {
        $path = base_path('.env');

        if (!File::exists($path)) {
            return;
        }

        $content = File::get($path);

        foreach ($data as $key => $value) {
            // Handle values with spaces by quoting them
            $value = (strpos($value, ' ') !== false && strpos($value, '"') === false) ? '"' . $value . '"' : $value;
            // Handle null/empty
            $value = $value ?? '';

            // Escape special regex characters in the new value if we use it in replacement (not needed here as we use string concat)
            // But we need to escape key for regex

            // If the key exists
            if (preg_match("/^{$key}=.*/m", $content)) {
                // Replace the line
                $content = preg_replace("/^{$key}=.*/m", "{$key}={$value}", $content);
            } else {
                // Append key if not found (though usually it should be in .env.example)
                $content .= "\n{$key}={$value}";
            }
        }

        File::put($path, $content);
    }
}
