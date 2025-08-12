<?php
#ddev-generated

/**
 * Proof-of-concept test for php-yaml functionality
 * This script tests the basic PHP add-on capabilities
 */

echo "=== DDEV PHP Add-on Test ===\n\n";

// Test 1: Check php-yaml extension
if (!extension_loaded('yaml')) {
    echo "❌ php-yaml extension not loaded\n";
    exit(1);
} else {
    echo "✅ php-yaml extension is available\n";
}

// Test 2: Check DDEV environment variables
$ddevVars = [
    'DDEV_PROJECT' => $_ENV['DDEV_PROJECT'] ?? null,
    'DDEV_DOCROOT' => $_ENV['DDEV_DOCROOT'] ?? null,
    'DDEV_PROJECT_TYPE' => $_ENV['DDEV_PROJECT_TYPE'] ?? null,
    'DDEV_APPROOT' => $_ENV['DDEV_APPROOT'] ?? null,
    'DDEV_PHP_VERSION' => $_ENV['DDEV_PHP_VERSION'] ?? null,
    'DDEV_DATABASE' => $_ENV['DDEV_DATABASE'] ?? null,
];

echo "\n=== DDEV Environment Variables ===\n";
foreach ($ddevVars as $var => $value) {
    if ($value) {
        echo "✅ $var = $value\n";
    } else {
        echo "⚠️  $var = (not set)\n";
    }
}

// Test 3: Check working directory
echo "\n=== Working Directory ===\n";
echo "Current directory: " . getcwd() . "\n";
echo "Expected: /var/www/html/.ddev\n";

// Test 4: Check file access
echo "\n=== File Access ===\n";

$testFiles = [
    'config.yaml' => 'DDEV project config',
    '../.platform.app.yaml' => 'Platform.sh app config', 
    '../.platform/services.yaml' => 'Platform.sh services config',
    '../.platform/routes.yaml' => 'Platform.sh routes config'
];

foreach ($testFiles as $file => $desc) {
    if (file_exists($file)) {
        echo "✅ Found $desc: $file\n";
    } else {
        echo "⚠️  Missing $desc: $file\n";
    }
}

// Test 5: Test YAML parsing
echo "\n=== YAML Processing Test ===\n";

$testData = [
    'type' => 'php:8.1',
    'dependencies' => [
        'php' => [
            'composer/composer' => '^2'
        ]
    ],
    'relationships' => [
        'database' => 'database:mysql'
    ]
];

$yamlString = yaml_emit($testData);
echo "✅ YAML generation successful:\n";
echo $yamlString;

$parsed = yaml_parse($yamlString);
if ($parsed === $testData) {
    echo "✅ YAML parsing successful\n";
} else {
    echo "❌ YAML parsing failed\n";
}

// Test 6: Test JSON generation for relationships
echo "\n=== JSON Generation Test ===\n";

$relationship = [
    'database' => [
        [
            'username' => 'db',
            'hostname' => 'db', 
            'port' => 3306,
            'type' => 'mariadb:10.4'
        ]
    ]
];

$json = json_encode($relationship, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
echo "✅ JSON generation successful:\n";
echo $json . "\n";

// Test 7: Test PlatformshConfig class if it exists
echo "\n=== PlatformshConfig Class Test ===\n";

if (file_exists('platformsh/PlatformshConfig.php')) {
    require_once 'platformsh/PlatformshConfig.php';
    
    try {
        $config = new PlatformshConfig();
        echo "✅ PlatformshConfig class instantiated successfully\n";
        echo "PHP Version: " . $config->getPhpVersion() . "\n";
        echo "Composer Version: " . $config->getComposerVersion() . "\n";
        echo "Document Root: " . $config->getDocumentRoot() . "\n";
    } catch (Exception $e) {
        echo "❌ PlatformshConfig error: " . $e->getMessage() . "\n";
    }
} else {
    echo "⚠️  PlatformshConfig class not found\n";
}

echo "\n=== Test Complete ===\n";