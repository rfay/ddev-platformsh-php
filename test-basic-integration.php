<?php
#ddev-generated

echo "=== Basic Integration Test ===\n";

// Test basic class loading
$classes = [
    '../platformsh/PlatformshConfig.php',
    '../platformsh/DatabaseConfig.php',
    '../platformsh/ServiceConfig.php',
    '../platformsh/EnvironmentConfig.php',
    '../platformsh/FileOperations.php'
];

foreach ($classes as $class) {
    if (file_exists($class)) {
        require_once $class;
        echo "✅ Loaded: " . basename($class) . "\n";
    } else {
        echo "❌ Missing: $class\n";
        exit(1);
    }
}

// Create simple test data
$testDir = '/tmp/basic-test-' . uniqid();
mkdir($testDir, 0755, true);

// Simple platform configuration
$platformApp = [
    'name' => 'test-app',
    'type' => 'php:8.1',
    'relationships' => [
        'database' => 'db:mysql'
    ]
];

$services = [
    'db' => [
        'type' => 'mysql:8.0'
    ]
];

// Create temporary config files
file_put_contents("$testDir/.platform.app.yaml", yaml_emit($platformApp));
file_put_contents("$testDir/.platform/services.yaml", yaml_emit($services));
file_put_contents("$testDir/.platform/routes.yaml", yaml_emit([]));

// Change to test directory
$originalDir = getcwd();
chdir($testDir);

try {
    echo "\n=== Testing PlatformshConfig ===\n";
    $config = new PlatformshConfig();
    
    // Test methods
    $appType = $config->getApplicationType();
    echo "App type: $appType\n";
    
    $phpVersion = $config->getPhpVersion();
    echo "PHP version: $phpVersion\n";
    
    $summary = $config->getConfigurationSummary();
    echo "Configuration summary:\n";
    foreach ($summary as $key => $value) {
        if (is_array($value)) {
            echo "  $key: " . json_encode($value) . "\n";
        } else {
            echo "  $key: $value\n";
        }
    }
    
    echo "\n✅ Basic integration test passed!\n";
    
} catch (Exception $e) {
    echo "❌ Test failed: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
} finally {
    chdir($originalDir);
    system("rm -rf " . escapeshellarg($testDir));
}

echo "\n=== Basic Integration Test Complete ===\n";