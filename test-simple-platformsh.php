<?php
// Simple test of Platform.sh configuration parsing
require_once 'scripts/parse-platformsh-config.php';

echo "Testing Platform.sh configuration parsing...\n";

// Simulate working from .ddev directory
if (!is_dir('scripts')) {
    echo "Error: Run this from the project root directory\n";
    exit(1);
}

// Test the parsing functions
echo "\n=== Testing Platform.sh Config Parsing ===\n";

$appConfig = parse_platformsh_app_config();
$services = parse_platformsh_services_config();
$routes = parse_platformsh_routes_config();

if ($appConfig) {
    echo "✅ Successfully parsed .platform.app.yaml\n";
    $phpVersion = extract_php_version($appConfig);
    $docroot = extract_docroot($appConfig);
    echo "  PHP Version: {$phpVersion}\n";
    echo "  Document Root: {$docroot}\n";
} else {
    echo "⚠️  No .platform.app.yaml found\n";
}

if (!empty($services)) {
    echo "✅ Successfully parsed .platform/services.yaml\n";
    $databases = extract_database_services($services);
    $otherServices = extract_other_services($services);
    
    echo "  Databases found: " . count($databases) . "\n";
    foreach ($databases as $name => $config) {
        echo "    - {$name}: {$config['type']}:{$config['version']}\n";
    }
    
    echo "  Other services found: " . count($otherServices) . "\n";
    foreach ($otherServices as $name => $config) {
        echo "    - {$name}: {$config['type']}:{$config['version']}\n";
    }
} else {
    echo "⚠️  No services found in .platform/services.yaml\n";
}

if (!empty($routes)) {
    echo "✅ Successfully parsed .platform/routes.yaml\n";
    echo "  Routes found: " . count($routes) . "\n";
} else {
    echo "⚠️  No routes found in .platform/routes.yaml\n";
}

echo "\n=== Test Complete ===\n";
?>