<?php
// Run ONLY as the application runtime user. No providers, jobs or migrations are booted.
// Output is deliberately allowlisted: never return URLs, credentials or exception messages.
error_reporting(0);
ini_set('display_errors', '0');
ob_start();
try {
    $base = $argv[1];
    chdir($base);
    require $base.'/vendor/autoload.php';
    $app = require $base.'/bootstrap/app.php';
    (new Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables)->bootstrap($app);
    (new Illuminate\Foundation\Bootstrap\LoadConfiguration)->bootstrap($app);
    $config = $app->make('config');
    $redis = $config->get('database.redis', []);
    $local = true;
    foreach (['default', 'cache'] as $name) {
        $connection = $redis[$name] ?? [];
        $host = $connection['host'] ?? '';
        if (!empty($connection['url'])) {
            $host = parse_url($connection['url'], PHP_URL_HOST);
        }
        $local = $local && in_array($host, ['127.0.0.1', 'localhost', '::1'], true);
    }
    $report = [
        'ok' => true,
        'php' => PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION,
        'modules' => get_loaded_extensions(),
        'config_cached' => $app->configurationIsCached(),
        'debug' => (bool) $config->get('app.debug'),
        'https' => parse_url((string) $config->get('app.url'), PHP_URL_SCHEME) === 'https',
        'session_secure' => (bool) $config->get('session.secure'),
        'session_encrypt' => (bool) $config->get('session.encrypt'),
        'cache' => $config->get('cache.default'),
        'session' => $config->get('session.driver'),
        'queue' => $config->get('queue.default'),
        'database' => $config->get('database.default'),
        'reverb_loopback' => in_array($config->get('reverb.servers.reverb.host'), ['127.0.0.1', '::1', 'localhost'], true),
        'redis_local' => $local,
        'redis_client' => $redis['client'] ?? '',
        'writable_storage' => is_writable($base.'/storage/logs') && is_writable($base.'/storage/framework'),
        'writable_cache' => is_writable($base.'/bootstrap/cache'),
        'ca_readable' => !$config->get('services.firewall.ca_bundle') || is_readable($config->get('services.firewall.ca_bundle')),
        'curl_pinning' => extension_loaded('curl') && defined('CURLOPT_PINNEDPUBLICKEY'),
        'redis_ping' => [],
    ];
    if (($argv[2] ?? '') === 'ping') {
        // Use Laravel's URL/ACL/TLS/database handling, preserving all existing options.
        foreach (['default', 'cache'] as $name) {
            try {
                $settings = $redis;
                $settings[$name]['timeout'] = 3;
                $settings[$name]['read_timeout'] = 3;
                $settings[$name]['max_retries'] = 0;
                $manager = new Illuminate\Redis\RedisManager($app, $settings['client'] ?? 'phpredis', $settings);
                $pong = $manager->connection($name)->ping();
                $report['redis_ping'][$name] = in_array(strtoupper((string) $pong), ['1', 'PONG', '+PONG'], true);
                $manager->purge($name);
            } catch (Throwable $e) {
                $report['redis_ping'][$name] = false;
            }
        }
    }
    ob_end_clean();
    echo json_encode($report, JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    ob_end_clean();
    echo '{"ok":false}';
    exit(1);
}
