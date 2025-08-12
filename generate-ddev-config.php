<?php
#ddev-generated

/**
 * Generate complete DDEV configuration from Platform.sh files
 * Demonstrates the full translation process
 */

require_once 'platformsh/PlatformshConfig.php';

echo "=== DDEV Configuration Generator ===\n\n";

try {
    // Initialize Platform.sh configuration parser
    $platformConfig = new PlatformshConfig();
    
    // Validate configuration
    echo "1. Validating Platform.sh configuration...\n";
    $fileErrors = $platformConfig->validateConfigurationFiles();
    $configErrors = $platformConfig->validatePlatformConfiguration();
    
    if (!empty($fileErrors) || !empty($configErrors)) {
        echo "❌ Configuration validation failed:\n";
        foreach (array_merge($fileErrors, $configErrors) as $error) {
            echo "   - $error\n";
        }
        exit(1);
    }
    echo "✅ Configuration validation passed\n\n";
    
    // Check application type
    echo "2. Checking application compatibility...\n";
    if (!$platformConfig->validateApplicationType()) {
        echo "❌ Unsupported application type: " . $platformConfig->getApplicationType() . "\n";
        echo "Only PHP applications are currently supported.\n";
        exit(5);
    }
    echo "✅ Application type supported: " . $platformConfig->getApplicationType() . "\n\n";
    
    // Check database compatibility (simulate existing database check)
    echo "3. Checking database compatibility...\n";
    $currentDbVersion = $_ENV['CURRENT_DB_VERSION'] ?? null; // Would come from `ddev debug get-volume-db-version`
    $dbCompatibility = $platformConfig->checkDatabaseCompatibility($currentDbVersion);
    
    if (!$dbCompatibility['compatible']) {
        echo "❌ Database compatibility issue:\n";
        echo $dbCompatibility['message'] . "\n";
        exit(1);
    }
    echo "✅ Database compatibility check passed\n\n";
    
    // Show required add-ons
    echo "4. Identifying required DDEV add-ons...\n";
    $requiredAddons = $platformConfig->getRequiredAddons();
    if (empty($requiredAddons)) {
        echo "No additional DDEV add-ons required\n";
    } else {
        echo "Required DDEV add-ons:\n";
        foreach ($requiredAddons as $addon) {
            echo "  - $addon\n";
            // In real implementation, would run: ddev add-on get $addon
        }
    }
    echo "\n";
    
    // Generate complete DDEV configuration
    echo "5. Generating DDEV configuration...\n";
    $ddevConfig = $platformConfig->generateDdevConfiguration();
    
    // Add the #ddev-generated comment and project description
    $configWithComments = [
        '# #ddev-generated',
        '# Generated configuration based on platform.sh project configuration',
    ];
    
    // Convert to YAML
    $yamlConfig = yaml_emit($ddevConfig);
    
    // Write to config.platformsh.yaml
    $configFile = 'config.platformsh.yaml';
    $fullConfig = implode("\n", $configWithComments) . "\n" . $yamlConfig;
    
    file_put_contents($configFile, $fullConfig);
    
    echo "✅ DDEV configuration generated successfully\n";
    echo "Configuration written to: $configFile\n\n";
    
    // Show configuration summary
    echo "6. Configuration Summary:\n";
    echo "   PHP Version: " . $ddevConfig['php_version'] . "\n";
    echo "   Composer Version: " . $ddevConfig['composer_version'] . "\n";
    
    $dbConfig = $ddevConfig['database'];
    echo "   Database: {$dbConfig['type']}:{$dbConfig['version']}\n";
    echo "   Document Root: " . $ddevConfig['docroot'] . "\n";
    
    if (isset($ddevConfig['webimage_extra_packages'])) {
        echo "   Extra Packages: " . count($ddevConfig['webimage_extra_packages']) . " packages\n";
    }
    
    echo "   Environment Variables: " . count($ddevConfig['web_environment']) . " variables\n";
    
    if (isset($ddevConfig['hooks']['post-start'])) {
        echo "   Post-start Hooks: " . count($ddevConfig['hooks']['post-start']) . " hooks\n";
    }
    
    // Show sample environment variables
    echo "\n7. Platform.sh Environment Variables (sample):\n";
    foreach ($ddevConfig['web_environment'] as $envVar) {
        if (str_starts_with($envVar, 'PLATFORM_')) {
            $parts = explode('=', $envVar, 2);
            $varName = $parts[0];
            $varValue = $parts[1] ?? '';
            
            if (in_array($varName, ['PLATFORM_RELATIONSHIPS', 'PLATFORM_ROUTES'])) {
                echo "   $varName: " . strlen($varValue) . " bytes (base64 encoded)\n";
            } else {
                echo "   $envVar\n";
            }
        }
    }
    
    echo "\n✅ Platform.sh to DDEV translation completed successfully!\n";
    echo "\nNext steps:\n";
    echo "1. Install required DDEV add-ons (shown above)\n";
    echo "2. Run 'ddev start' to apply the new configuration\n";
    echo "3. Your Platform.sh project environment will be replicated in DDEV\n";
    
} catch (Exception $e) {
    echo "❌ Error during configuration generation: " . $e->getMessage() . "\n";
    exit(1);
}