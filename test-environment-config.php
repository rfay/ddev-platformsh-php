<?php
#ddev-generated

/**
 * Comprehensive test for Environment Configuration handling
 * Tests Platform.sh environment variable generation, relationships, routes, and DDEV integration
 */

echo "=== Environment Configuration Test ===\n\n";

// Load required classes
if (!file_exists('../platformsh/EnvironmentConfig.php') || 
    !file_exists('../platformsh/DatabaseConfig.php') || 
    !file_exists('../platformsh/ServiceConfig.php')) {
    echo "❌ Required configuration classes not found\n";
    exit(1);
}

require_once '../platformsh/EnvironmentConfig.php';
require_once '../platformsh/DatabaseConfig.php';
require_once '../platformsh/ServiceConfig.php';

function testEnvironmentScenario($name, $platformApp, $services, $routes = []) {
    echo "=== Testing: $name ===\n";
    
    try {
        $envConfig = new EnvironmentConfig($platformApp, $services, $routes);
        
        // Test PLATFORM_RELATIONSHIPS generation
        echo "Generating PLATFORM_RELATIONSHIPS...\n";
        $platformRelationships = $envConfig->generatePlatformRelationships();
        
        if (!empty($platformRelationships)) {
            echo "✅ PLATFORM_RELATIONSHIPS generated (" . strlen($platformRelationships) . " chars base64)\n";
            
            // Test parsing the relationships back
            $parsedRelationships = $envConfig->parsePlatformRelationships($platformRelationships);
            $relationshipCount = count($parsedRelationships);
            echo "   Parsed back to $relationshipCount relationships\n";
            
            // Show sample relationships
            if ($relationshipCount > 0) {
                foreach (array_slice($parsedRelationships, 0, 2) as $relName => $relData) {
                    $firstRel = $relData[0] ?? [];
                    $scheme = $firstRel['scheme'] ?? 'unknown';
                    $host = $firstRel['host'] ?? 'unknown';
                    $port = $firstRel['port'] ?? 'unknown';
                    echo "   Sample: $relName -> {$scheme}://{$host}:{$port}\n";
                }
            }
        } else {
            echo "⚠️  No PLATFORM_RELATIONSHIPS generated\n";
        }
        
        // Test PLATFORM_ROUTES generation
        echo "Generating PLATFORM_ROUTES...\n";
        $platformRoutes = $envConfig->generatePlatformRoutes();
        
        if (!empty($platformRoutes)) {
            echo "✅ PLATFORM_ROUTES generated (" . strlen($platformRoutes) . " chars base64)\n";
            $decodedRoutes = json_decode(base64_decode($platformRoutes), true);
            if ($decodedRoutes) {
                echo "   Contains " . count($decodedRoutes) . " routes\n";
            }
        } else {
            echo "⚠️  Empty PLATFORM_ROUTES generated\n";
        }
        
        // Test project entropy generation
        $entropy = $envConfig->generatePlatformProjectEntropy();
        echo "Generated entropy: " . substr($entropy, 0, 16) . "... ✅\n";
        
        // Test all environment variables
        echo "Generating all Platform.sh environment variables...\n";
        $allEnvVars = $envConfig->generateAllPlatformEnvironmentVariables();
        echo "Generated " . count($allEnvVars) . " environment variables:\n";
        
        foreach ($allEnvVars as $envVar) {
            $parts = explode('=', $envVar, 2);
            $key = $parts[0];
            $value = $parts[1] ?? '';
            
            // Show truncated values for readability
            $displayValue = strlen($value) > 50 ? substr($value, 0, 50) . '...' : $value;
            echo "   $key = $displayValue\n";
        }
        
        // Test DDEV hooks generation
        echo "Generating DDEV hooks configuration...\n";
        $hooks = $envConfig->generateDdevHooksConfig();
        $postStartCount = count($hooks['post-start'] ?? []);
        echo "Generated $postStartCount post-start hooks ✅\n";
        
        foreach ($hooks['post-start'] ?? [] as $index => $hook) {
            if (isset($hook['exec'])) {
                $command = strlen($hook['exec']) > 60 ? substr($hook['exec'], 0, 60) . '...' : $hook['exec'];
                echo "   Hook " . ($index + 1) . ": exec '$command'\n";
            } elseif (isset($hook['composer'])) {
                echo "   Hook " . ($index + 1) . ": composer {$hook['composer']}\n";
            }
        }
        
        // Test configuration summary
        echo "Environment configuration summary...\n";
        $summary = $envConfig->getEnvironmentConfigurationSummary();
        echo "   Total relationships: {$summary['total_relationships']}\n";
        echo "   Database relationships: {$summary['database_relationships']}\n";
        echo "   Service relationships: {$summary['service_relationships']}\n";
        echo "   Custom env vars: {$summary['custom_env_vars']}\n";
        echo "   Build hooks: " . ($summary['has_build_hooks'] ? 'Yes' : 'No') . "\n";
        echo "   Deploy hooks: " . ($summary['has_deploy_hooks'] ? 'Yes' : 'No') . "\n";
        echo "   PHP extensions: " . implode(', ', $summary['php_extensions']) . "\n";
        
        // Test validation
        $errors = $envConfig->validateEnvironmentConfiguration();
        if (empty($errors)) {
            echo "✅ Environment configuration validation passed\n";
        } else {
            echo "⚠️  Environment configuration validation issues:\n";
            foreach ($errors as $error) {
                echo "   - $error\n";
            }
        }
        
        echo "✅ Test completed successfully\n\n";
        return true;
        
    } catch (Exception $e) {
        echo "❌ Test failed: " . $e->getMessage() . "\n\n";
        return false;
    }
}

// Set up mock DDEV environment variables for testing
$_ENV['DDEV_PRIMARY_URL'] = 'https://test.ddev.site';
$_ENV['DDEV_PROJECT'] = 'test-project';

// Test 1: Simple database + service configuration
$simpleApp = [
    'type' => 'php:8.1',
    'relationships' => [
        'database' => 'maindb:mysql',
        'redis' => 'cacheredis:redis'
    ],
    'variables' => [
        'env' => [
            'APP_ENV' => 'production',
            'DEBUG' => 'false'
        ]
    ],
    'build' => [
        'flavor' => 'composer'
    ],
    'hooks' => [
        'build' => 'composer install --no-dev',
        'deploy' => "php artisan migrate\nphp artisan cache:clear"
    ],
    'runtime' => [
        'extensions' => ['redis', 'pdo_mysql']
    ]
];

$simpleServices = [
    'maindb' => ['type' => 'mysql:8.0'],
    'cacheredis' => ['type' => 'redis:6.2']
];

$simpleRoutes = [
    'https://{default}/' => [
        'id' => '',
        'production_url' => 'https://example.com/',
        'upstream' => 'app:http',
        'type' => 'upstream',
        'original_url' => 'https://{default}/'
    ]
];

testEnvironmentScenario('Simple DB + Redis + Routes', $simpleApp, $simpleServices, $simpleRoutes);

// Test 2: Complex multi-service configuration  
$complexApp = [
    'type' => 'php:8.2',
    'relationships' => [
        'database' => 'maindb:mysql',
        'readdb' => 'readonlydb:postgresql', 
        'redis' => 'cacheredis:redis',
        'elasticsearch' => 'searchengine:elasticsearch',
        'memcached' => 'fastcache:memcached'
    ],
    'variables' => [
        'env' => [
            'APP_ENV' => 'staging',
            'ELASTICSEARCH_URL' => '{elasticsearch_url}',
            'REDIS_URL' => '{redis_url}',
            'CUSTOM_VAR' => 'custom_value'
        ]
    ],
    'build' => [
        'flavor' => 'composer'
    ],
    'hooks' => [
        'build' => 'npm run build',
        'deploy' => 'php artisan migrate --force',
        'post_deploy' => 'php artisan queue:restart'
    ],
    'runtime' => [
        'extensions' => ['redis', 'blackfire', 'pdo_mysql', 'pdo_pgsql']
    ]
];

$complexServices = [
    'maindb' => ['type' => 'mysql:8.0'],
    'readonlydb' => ['type' => 'postgresql:13'],
    'cacheredis' => ['type' => 'redis:6.2'],
    'searchengine' => ['type' => 'elasticsearch:7.10'],
    'fastcache' => ['type' => 'memcached:1.6']
];

$complexRoutes = [
    'https://{default}/' => [
        'id' => 'main',
        'production_url' => 'https://example.com/',
        'upstream' => 'app:http',
        'type' => 'upstream',
        'original_url' => 'https://{default}/'
    ],
    'https://api.{default}/' => [
        'id' => 'api',
        'production_url' => 'https://api.example.com/',
        'upstream' => 'app:http',
        'type' => 'upstream', 
        'original_url' => 'https://api.{default}/'
    ]
];

testEnvironmentScenario('Complex Multi-Service + Multiple Routes', $complexApp, $complexServices, $complexRoutes);

// Test 3: Database-only configuration
$dbOnlyApp = [
    'type' => 'php:8.0',
    'relationships' => [
        'database' => 'db:mysql'
    ],
    'variables' => [
        'env' => []
    ],
    'build' => [
        'flavor' => 'composer'
    ],
    'hooks' => [],
    'runtime' => [
        'extensions' => []
    ]
];

$dbOnlyServices = [
    'db' => ['type' => 'mariadb:10.6']
];

testEnvironmentScenario('Database-only Configuration', $dbOnlyApp, $dbOnlyServices, []);

// Test 4: No services configuration
$noServicesApp = [
    'type' => 'php:8.1',
    'relationships' => [],
    'variables' => [
        'env' => [
            'STATIC_SITE' => 'true'
        ]
    ],
    'build' => [
        'flavor' => 'none'
    ],
    'hooks' => [
        'build' => 'npm run build-static'
    ],
    'runtime' => [
        'extensions' => ['gd', 'intl']
    ]
];

testEnvironmentScenario('No Services (Static Site)', $noServicesApp, [], []);

// Test 5: PLATFORM_RELATIONSHIPS parsing
echo "=== PLATFORM_RELATIONSHIPS Parsing Test ===\n";

// Create a mock base64-encoded relationships string
$mockRelationships = [
    'database' => [
        [
            'scheme' => 'mysql',
            'username' => 'user',
            'password' => 'password',
            'host' => 'database',
            'port' => 3306,
            'path' => 'main'
        ]
    ],
    'redis' => [
        [
            'scheme' => 'redis',
            'username' => '',
            'password' => '',
            'host' => 'redis',
            'port' => 6379,
            'path' => ''
        ]
    ]
];

$mockRelationshipsJson = json_encode($mockRelationships, JSON_UNESCAPED_SLASHES);
$mockRelationshipsBase64 = base64_encode($mockRelationshipsJson);

$envConfig = new EnvironmentConfig([], []);
$parsedRelationships = $envConfig->parsePlatformRelationships($mockRelationshipsBase64);

if (count($parsedRelationships) === 2) {
    echo "✅ PLATFORM_RELATIONSHIPS parsing successful\n";
    echo "   Parsed " . count($parsedRelationships) . " relationships\n";
    
    foreach ($parsedRelationships as $name => $data) {
        $rel = $data[0];
        echo "   $name: {$rel['scheme']}://{$rel['host']}:{$rel['port']}\n";
    }
} else {
    echo "❌ PLATFORM_RELATIONSHIPS parsing failed\n";
}

// Test invalid base64
$invalidParsed = $envConfig->parsePlatformRelationships('invalid-base64');
if (empty($invalidParsed)) {
    echo "✅ Invalid base64 handling works correctly\n";
} else {
    echo "❌ Invalid base64 should return empty array\n";
}

echo "\n=== Environment Variables Integration Test ===\n";

// Test integration with DatabaseConfig and ServiceConfig
$integrationApp = [
    'type' => 'php:8.1',
    'relationships' => [
        'database' => 'maindb:mysql',
        'redis' => 'cache:redis'
    ]
];

$integrationServices = [
    'maindb' => ['type' => 'mysql:8.0'],
    'cache' => ['type' => 'redis:6.0']
];

$envConfig = new EnvironmentConfig($integrationApp, $integrationServices);

// Test database environment variables
$dbEnvVars = $envConfig->generateDatabaseEnvironmentVariables();
echo "Database environment variables: " . count($dbEnvVars) . "\n";
foreach ($dbEnvVars as $key => $value) {
    echo "  $key = $value\n";
}

// Test service environment variables  
$serviceEnvVars = $envConfig->generateServiceEnvironmentVariables();
echo "Service environment variables: " . count($serviceEnvVars) . "\n";
foreach ($serviceEnvVars as $key => $value) {
    echo "  $key = $value\n";
}

echo "\n=== All Environment Configuration Tests Complete ===\n";
echo "Environment variable handling has been thoroughly tested.\n";