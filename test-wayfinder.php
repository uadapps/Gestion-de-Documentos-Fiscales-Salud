<?php

// Simple test script to check if wayfinder:generate command is available
require __DIR__ . '/vendor/autoload.php';

use Illuminate\Foundation\Application;

$app = require_once __DIR__ . '/bootstrap/app.php';

$artisan = $app->make('Illuminate\Contracts\Console\Kernel');

echo "Available commands containing 'wayfinder':\n";
exec('php artisan list | findstr wayfinder', $output, $return_var);

if (empty($output)) {
    echo "No wayfinder commands found.\n";
    echo "Return code: $return_var\n";

    // Try to manually check if service provider is loaded
    echo "\nChecking service providers...\n";
    $providers = $app->getLoadedProviders();

    foreach ($providers as $provider => $loaded) {
        if (strpos($provider, 'Wayfinder') !== false) {
            echo "Found provider: $provider - " . ($loaded ? "LOADED" : "NOT LOADED") . "\n";
        }
    }

    if (!isset($providers['Laravel\\Wayfinder\\WayfinderServiceProvider'])) {
        echo "WayfinderServiceProvider is NOT loaded!\n";
        echo "Try running: php artisan config:clear\n";
        echo "Then: php artisan cache:clear\n";
    }
} else {
    echo implode("\n", $output) . "\n";
    echo "Commands found successfully!\n";
}
