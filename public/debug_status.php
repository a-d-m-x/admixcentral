<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Check latest update record
$update = \App\Models\SystemUpdate::latest()->first();

echo "<h1>Latest SystemUpdate Record</h1>";
if ($update) {
    echo "<pre>";
    print_r($update->toArray());
    echo "</pre>";
} else {
    echo "No records found.";
}

// Check Log file
echo "<h1>Last 50 Log Entries</h1>";
$logFile = storage_path('logs/laravel.log');
if (file_exists($logFile)) {
    $lines = file($logFile);
    $lastLines = array_slice($lines, -50);
    echo "<pre>" . htmlspecialchars(implode("", $lastLines)) . "</pre>";
} else {
    echo "Log file not found.";
}
