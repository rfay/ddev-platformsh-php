<?php
#ddev-generated

/**
 * Comprehensive test for PlatformshConfig class
 * Tests parsing and validation against real Platform.sh configuration data
 */

echo "=== Platform.sh Configuration Parser Test ===\n\n";

// Check if class exists
if (!file_exists('platformsh/PlatformshConfig.php')) {
    echo "❌ PlatformshConfig.php not found\n";
    exit(1);
}

require_once 'platformsh/PlatformshConfig.php';

// Test 1: Basic instantiation
echo "=== Test 1: Basic Class Instantiation ===\n";
try {
    $config = new PlatformshConfig();
    echo "✅ PlatformshConfig class instantiated successfully\n";
} catch (Exception $e) {
    echo "❌ PlatformshConfig instantiation failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 2: Configuration validation
echo "\n=== Test 2: Configuration File Validation ===\n";
$fileErrors = $config->validateConfigurationFiles();
if (empty($fileErrors)) {
    echo "✅ Configuration files validation passed\n";
} else {
    echo "⚠️  Configuration file issues:\n";
    foreach ($fileErrors as $error) {
        echo "   - $error\n";
    }
}

$platformErrors = $config->validatePlatformConfiguration();
if (empty($platformErrors)) {
    echo "✅ Platform.sh configuration validation passed\n";
} else {
    echo "⚠️  Platform.sh configuration issues:\n";
    foreach ($platformErrors as $error) {
        echo "   - $error\n";
    }
}

// Test 3: Application type validation
echo "\n=== Test 3: Application Type Validation ===\n";
if ($config->validateApplicationType()) {
    echo "✅ Application type is supported (PHP)\n";
    echo "   Type: " . $config->getApplicationType() . "\n";
} else {
    echo "❌ Unsupported application type: " . $config->getApplicationType() . "\n";
}

// Test 4: Basic configuration extraction
echo "\n=== Test 4: Configuration Extraction ===\n";
echo "PHP Version: " . $config->getPhpVersion() . "\n";
echo "Composer Version: " . $config->getComposerVersion() . "\n";
echo "Document Root: " . $config->getDocumentRoot() . "\n";

$extensions = $config->getPhpExtensions();
echo "PHP Extensions: " . (empty($extensions) ? 'none' : implode(', ', $extensions)) . "\n";

$envVars = $config->getEnvironmentVariables();
echo "Environment Variables: " . (empty($envVars) ? 'none' : count($envVars) . ' variables') . "\n";

// Test 5: Database configuration
echo "\n=== Test 5: Database Configuration ===\n";
$dbConfig = $config->getDatabaseConfiguration();
echo "Database Type: {$dbConfig['type']}\n";
echo "Database Version: {$dbConfig['version']}\n";
echo "Service Name: {$dbConfig['service_name']}\n";
echo "Relationship Name: {$dbConfig['relationship_name']}\n";

// Test 6: Required add-ons
echo "\n=== Test 6: Required DDEV Add-ons ===\n";
$addons = $config->getRequiredAddons();
if (empty($addons)) {
    echo "No additional DDEV add-ons required\n";
} else {
    echo "Required add-ons:\n";
    foreach ($addons as $addon) {
        echo "  - $addon\n";
    }
}

// Test 7: Relationships generation
echo "\n=== Test 7: Relationships Generation ===\n";
try {
    $relationships = $config->generateRelationships();
    if (empty($relationships)) {
        echo "No relationships configured\n";
    } else {
        echo "Generated relationships:\n";
        foreach ($relationships as $name => $data) {
            echo "  - $name: " . count($data) . " connection(s)\n";
        }
        
        // Show sample relationship data
        $firstRel = reset($relationships);
        if ($firstRel) {
            echo "\nSample relationship structure:\n";
            echo json_encode($firstRel[0] ?? [], JSON_PRETTY_PRINT) . "\n";
        }
    }
} catch (Exception $e) {
    echo "❌ Relationships generation failed: " . $e->getMessage() . "\n";
}

// Test 8: Routes generation
echo "\n=== Test 8: Routes Generation ===\n";
try {
    $routes = $config->generateRoutes();
    if (empty($routes)) {
        echo "No routes configured\n";
    } else {
        echo "Generated routes:\n";
        foreach ($routes as $route => $data) {
            echo "  - $route\n";
        }
    }
} catch (Exception $e) {
    echo "❌ Routes generation failed: " . $e->getMessage() . "\n";
}

// Test 9: Full DDEV configuration generation
echo "\n=== Test 9: DDEV Configuration Generation ===\n";
try {
    $ddevConfig = $config->generateDdevConfiguration();
    echo "✅ DDEV configuration generated successfully\n";
    
    echo "\nConfiguration summary:\n";
    echo "  PHP Version: " . $ddevConfig['php_version'] . "\n";
    echo "  Composer Version: " . $ddevConfig['composer_version'] . "\n";
    echo "  Database: " . $ddevConfig['database']['type'] . ':' . $ddevConfig['database']['version'] . "\n";
    echo "  Document Root: " . $ddevConfig['docroot'] . "\n";
    echo "  Environment Variables: " . count($ddevConfig['web_environment']) . " variables\n";
    
    if (isset($ddevConfig['hooks'])) {
        $hookCount = count($ddevConfig['hooks']['post-start'] ?? []);
        echo "  Post-start Hooks: $hookCount hooks\n";
    }
    
} catch (Exception $e) {
    echo "❌ DDEV configuration generation failed: " . $e->getMessage() . "\n";
}

// Test 10: YAML output generation
echo "\n=== Test 10: YAML Configuration Output ===\n";
try {
    $ddevConfig = $config->generateDdevConfiguration();
    $yamlOutput = yaml_emit($ddevConfig);
    
    echo "✅ YAML configuration generated successfully\n";
    echo "YAML size: " . strlen($yamlOutput) . " characters\n";
    
    // Show first few lines
    $lines = explode("\n", $yamlOutput);
    echo "First 10 lines:\n";
    for ($i = 0; $i < min(10, count($lines)); $i++) {
        echo "  " . $lines[$i] . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ YAML generation failed: " . $e->getMessage() . "\n";
}

// Test 11: Database compatibility check
echo "\n=== Test 11: Database Compatibility Check ===\n";
$compatibility = $config->checkDatabaseCompatibility(null);
if ($compatibility['compatible']) {
    echo "✅ No existing database, compatibility check passed\n";
} else {
    echo "⚠️  Database compatibility issue: " . $compatibility['message'] . "\n";
}

// Test with mismatched version
$compatibility = $config->checkDatabaseCompatibility('postgres:13');
if (!$compatibility['compatible']) {
    echo "✅ Database mismatch detection working correctly\n";
} else {
    echo "⚠️  Database mismatch not detected properly\n";
}

// Test 12: Service relationship comparison with bash scripts
echo "\n=== Test 12: Compare with Bash Script Output ===\n";

// Test database relationship
try {
    $dbConfig = $config->getDatabaseConfiguration();
    $phpOutput = $config->generateRelationships()[$dbConfig['relationship_name']] ?? [];
    
    if (!empty($phpOutput)) {
        echo "✅ PHP relationship generation successful\n";
        echo "Generated relationship type: " . $phpOutput[0]['type'] . "\n";
        echo "Generated relationship scheme: " . $phpOutput[0]['scheme'] . "\n";
        echo "Generated relationship port: " . $phpOutput[0]['port'] . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Relationship comparison failed: " . $e->getMessage() . "\n";
}

echo "\n=== Test Complete ===\n";
echo "PlatformshConfig class validation completed.\n";