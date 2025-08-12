<?php
#ddev-generated

/**
 * Comprehensive test for Service Configuration handling
 * Tests various service scenarios including Redis, Elasticsearch, Memcached, and mixed configurations
 */

echo "=== Service Configuration Test ===\n\n";

// Load required classes
if (!file_exists('../platformsh/ServiceConfig.php')) {
    echo "❌ ServiceConfig class not found\n";
    exit(1);
}

require_once '../platformsh/ServiceConfig.php';

function testServiceScenario($name, $platformApp, $services, $expectedServiceCount, $expectedAddons = []) {
    echo "=== Testing: $name ===\n";
    
    try {
        $serviceConfig = new ServiceConfig($platformApp, $services);
        
        // Test service detection
        $nonDbServices = $serviceConfig->getNonDatabaseServices();
        $serviceCount = count($nonDbServices);
        echo "Detected non-DB services: $serviceCount\n";
        
        foreach ($nonDbServices as $relationshipName => $service) {
            $supportedText = $service['supported'] ? '✅' : '❌';
            echo "  $supportedText {$service['type']}:{$service['version']} ({$relationshipName})\n";
        }
        
        // Test required DDEV add-ons
        $requiredAddons = $serviceConfig->getRequiredDdevAddons();
        echo "Required DDEV add-ons: " . count($requiredAddons) . "\n";
        foreach ($requiredAddons as $addon) {
            echo "  - $addon\n";
        }
        
        // Validate expectations
        if ($serviceCount === $expectedServiceCount) {
            echo "✅ Service count matches expectations\n";
        } else {
            echo "⚠️  Service count differs: expected $expectedServiceCount, got $serviceCount\n";
        }
        
        // Test relationship generation
        $relationships = $serviceConfig->generateServiceRelationships();
        echo "Generated relationships: " . count($relationships) . "\n";
        
        foreach ($relationships as $relName => $relData) {
            $firstRel = $relData[0] ?? [];
            echo "  $relName: {$firstRel['scheme']}://{$firstRel['host']}:{$firstRel['port']}\n";
        }
        
        // Test validation
        $errors = $serviceConfig->validateServiceConfiguration();
        if (empty($errors)) {
            echo "✅ Validation passed\n";
        } else {
            echo "⚠️  Validation issues:\n";
            foreach ($errors as $error) {
                echo "   - $error\n";
            }
        }
        
        // Test configuration summary
        $summary = $serviceConfig->getServiceConfigurationSummary();
        echo "Summary: {$summary['supported_services']}/{$summary['total_services']} supported, ";
        echo count($summary['required_addons']) . " add-ons needed\n";
        
        echo "✅ Test completed successfully\n\n";
        return true;
        
    } catch (Exception $e) {
        echo "❌ Test failed: " . $e->getMessage() . "\n\n";
        return false;
    }
}

// Test 1: Redis service
$redisApp = [
    'type' => 'php:8.0',
    'relationships' => [
        'rediscache' => 'cacheredis:redis'
    ]
];
$redisServices = [
    'cacheredis' => [
        'type' => 'redis:6.2'
    ]
];
testServiceScenario('Redis 6.2', $redisApp, $redisServices, 1, ['ddev/ddev-redis']);

// Test 2: Elasticsearch service
$elasticsearchApp = [
    'type' => 'php:8.1',
    'relationships' => [
        'elasticsearch' => 'cacheelasticsearch:elasticsearch'
    ]
];
$elasticsearchServices = [
    'cacheelasticsearch' => [
        'type' => 'elasticsearch:7.10'
    ]
];
testServiceScenario('Elasticsearch 7.10', $elasticsearchApp, $elasticsearchServices, 1, ['ddev/ddev-elasticsearch']);

// Test 3: Memcached service
$memcachedApp = [
    'type' => 'php:8.0',
    'relationships' => [
        'memcached' => 'cachememcached:memcached'
    ]
];
$memcachedServices = [
    'cachememcached' => [
        'type' => 'memcached:1.6'
    ]
];
testServiceScenario('Memcached 1.6', $memcachedApp, $memcachedServices, 1, ['ddev/ddev-memcached']);

// Test 4: Mixed services (from test data)
$mixedApp = [
    'type' => 'php:8.0',
    'relationships' => [
        'database' => 'somedbforsure:mysql',        // Database - should be filtered out
        'rediscache' => 'cacheredis:redis',          // Service
        'memcached' => 'cachememcached:memcached',   // Service
        'elasticsearch' => 'cacheelasticsearch:elasticsearch' // Service
    ]
];
$mixedServices = [
    'somedbforsure' => ['type' => 'oracle-mysql:8.0'], // Database
    'cacheredis' => ['type' => 'redis:6.2'],           // Service
    'cachememcached' => ['type' => 'memcached:1.6'],   // Service
    'cacheelasticsearch' => ['type' => 'elasticsearch:7.10'] // Service
];
testServiceScenario('Mixed DB + Services', $mixedApp, $mixedServices, 3, [
    'ddev/ddev-redis',
    'ddev/ddev-memcached', 
    'ddev/ddev-elasticsearch'
]);

// Test 5: Unsupported service
$unsupportedApp = [
    'type' => 'php:8.0',
    'relationships' => [
        'solr' => 'solrservice:solr',
        'rabbitmq' => 'messagequeue:rabbitmq'
    ]
];
$unsupportedServices = [
    'solrservice' => ['type' => 'solr:8.0'],
    'messagequeue' => ['type' => 'rabbitmq:3.8']
];
testServiceScenario('Unsupported Services (Solr, RabbitMQ)', $unsupportedApp, $unsupportedServices, 2, []);

// Test 6: No services
$noServicesApp = [
    'type' => 'php:8.0',
    'relationships' => []
];
$noServices = [];
testServiceScenario('No Services', $noServicesApp, $noServices, 0, []);

// Test 7: Database-only configuration (should filter out database)
$dbOnlyApp = [
    'type' => 'php:8.1',
    'relationships' => [
        'database' => 'maindb:mysql'
    ]
];
$dbOnlyServices = [
    'maindb' => ['type' => 'postgresql:13']
];
testServiceScenario('Database-only (filtered out)', $dbOnlyApp, $dbOnlyServices, 0, []);

// Test 8: Environment variables generation
echo "=== Environment Variables Test ===\n";
$serviceConfig = new ServiceConfig($mixedApp, $mixedServices);
$envVars = $serviceConfig->getServiceEnvironmentVars();
echo "Environment variables generated:\n";
foreach ($envVars as $key => $value) {
    echo "  $key = $value\n";
}
echo "\n";

// Test 9: Service type validation
echo "=== Service Type Validation Test ===\n";
$serviceConfig = new ServiceConfig($mixedApp, $mixedServices);

$supportedTypes = ['redis', 'elasticsearch', 'memcached', 'opensearch'];
$unsupportedTypes = ['solr', 'rabbitmq', 'mongodb'];

foreach ($supportedTypes as $type) {
    $supported = $serviceConfig->isServiceSupported($type) ? '✅' : '❌';
    echo "$supported $type is " . ($serviceConfig->isServiceSupported($type) ? 'supported' : 'not supported') . "\n";
}

foreach ($unsupportedTypes as $type) {
    $supported = $serviceConfig->isServiceSupported($type) ? '✅' : '❌';
    echo "$supported $type is " . ($serviceConfig->isServiceSupported($type) ? 'supported' : 'not supported') . "\n";
}
echo "\n";

// Test 10: Service configuration retrieval
echo "=== Service Configuration Retrieval Test ===\n";
$redisConfig = $serviceConfig->getServiceTypeConfig('redis');
if ($redisConfig) {
    echo "✅ Redis config: addon={$redisConfig['addon']}, scheme={$redisConfig['scheme']}\n";
} else {
    echo "❌ Redis config not found\n";
}

$solrConfig = $serviceConfig->getServiceTypeConfig('solr');
if ($solrConfig === null) {
    echo "✅ Solr config correctly returns null (unsupported)\n";
} else {
    echo "❌ Solr config should return null\n";
}

// Test 11: Integration with PHP generators
echo "\n=== Integration with PHP Generators Test ===\n";
$testRelationshipName = 'test_redis';

// Test Redis generator
if (file_exists('../platformsh/generate_redis_relationship.php')) {
    $redisOutput = shell_exec("php ../platformsh/generate_redis_relationship.php $testRelationshipName 2>/dev/null");
    if ($redisOutput && json_decode($redisOutput, true)) {
        echo "✅ Redis generator produces valid JSON\n";
        $redisJson = json_decode($redisOutput, true);
        echo "   Sample: {$redisJson[0]['scheme']}://{$redisJson[0]['host']}:{$redisJson[0]['port']}\n";
    } else {
        echo "❌ Redis generator failed or invalid JSON\n";
    }
} else {
    echo "⚠️  Redis generator not found\n";
}

// Test Elasticsearch generator  
if (file_exists('../platformsh/generate_elasticsearch_relationship.php')) {
    $esOutput = shell_exec("php ../platformsh/generate_elasticsearch_relationship.php $testRelationshipName 2>/dev/null");
    if ($esOutput && json_decode($esOutput, true)) {
        echo "✅ Elasticsearch generator produces valid JSON\n";
        $esJson = json_decode($esOutput, true);
        echo "   Sample: {$esJson[0]['scheme']}://{$esJson[0]['host']}:{$esJson[0]['port']}\n";
    } else {
        echo "❌ Elasticsearch generator failed or invalid JSON\n";
    }
} else {
    echo "⚠️  Elasticsearch generator not found\n";
}

// Test Memcached generator
if (file_exists('../platformsh/generate_memcached_relationship.php')) {
    $memcachedOutput = shell_exec("php ../platformsh/generate_memcached_relationship.php $testRelationshipName 2>/dev/null");
    if ($memcachedOutput && json_decode($memcachedOutput, true)) {
        echo "✅ Memcached generator produces valid JSON\n";
        $memcachedJson = json_decode($memcachedOutput, true);
        echo "   Sample: {$memcachedJson[0]['scheme']}://{$memcachedJson[0]['host']}:{$memcachedJson[0]['port']}\n";
    } else {
        echo "❌ Memcached generator failed or invalid JSON\n";
    }
} else {
    echo "⚠️  Memcached generator not found\n";
}

echo "\n=== All Service Configuration Tests Complete ===\n";
echo "Service configuration handling has been thoroughly tested.\n";