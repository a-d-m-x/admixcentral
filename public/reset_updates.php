<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    \App\Models\SystemUpdate::truncate();
    echo "System updates table truncated. UI should be reset.";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage();
}
