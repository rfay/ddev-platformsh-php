<?php
#ddev-generated

/**
 * Main Platform.sh to DDEV Configuration Script
 * 
 * This script orchestrates the complete Platform.sh configuration translation process:
 * 1. Loads and validates Platform.sh configuration files
 * 2. Generates DDEV database and service configurations
 * 3. Creates Platform.sh environment variables
 * 4. Writes config.platformsh.yaml with all necessary settings
 * 5. Handles project-specific customizations (Laravel, etc.)
 */

// Load all required classes
$requiredClasses = [
    'platformsh/Logger.php',
    'platformsh/PlatformshConfig.php',
    'platformsh/DatabaseConfig.php', 
    'platformsh/ServiceConfig.php',
    'platformsh/EnvironmentConfig.php',
    'platformsh/FileOperations.php'
];

foreach ($requiredClasses as $classFile) {
    if (!file_exists($classFile)) {
        fwrite(STDERR, "❌ Required class not found: $classFile\n");
        exit(1);
    }
    require_once $classFile;
}

// Initialize logging
$logger = Logger::getInstance();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    $logger->startOperation("Platform.sh to DDEV configuration translation");
    
    // Step 1: Load Platform.sh configuration
    $logger->startOperation("Loading Platform.sh configuration files");
    
    $platformshConfig = new PlatformshConfig();
    
    // Validate configuration files exist
    $configSummary = $platformshConfig->getConfigurationSummary();
    $logger->logConfigurationSummary($configSummary);
    $logger->completeOperation("Loading Platform.sh configuration files");
    
    // Step 2: Validate application compatibility
    $logger->startOperation("Validating application compatibility");
    
    $validationErrors = $platformshConfig->validateConfigurationFiles();
    $logger->logValidationResults($validationErrors);
    
    if (!empty($validationErrors)) {
        $logger->failOperation("Validating application compatibility", "Configuration validation failed");
        exit(2);
    }
    
    $logger->completeOperation("Validating application compatibility");
    
    // Step 3: Check database compatibility
    echo "\n💾 Checking database compatibility...\n";
    
    $currentDbVersion = null;
    
    // Get current database version if available
    $getCurrentDbCmd = 'ddev debug get-volume-db-version 2>/dev/null';
    $dbVersionOutput = shell_exec($getCurrentDbCmd);
    if ($dbVersionOutput && !str_contains($dbVersionOutput, 'No database')) {
        // Clean up color codes from output  
        $currentDbVersion = preg_replace('/\x1b\[[0-9;]*m/', '', trim($dbVersionOutput));
    }
    
    // Use the higher-level configuration methods instead of raw access
    $databaseConfig = $platformshConfig->getDatabaseConfig();
    $dbCompatibility = $databaseConfig->checkDatabaseCompatibility($currentDbVersion);
    
    if (!$dbCompatibility['compatible']) {
        echo "❌ Database compatibility issue:\n";
        echo "   {$dbCompatibility['message']}\n";
        echo "\n💡 Suggestions:\n";
        echo "   - Use 'ddev delete' to remove existing database and retry\n";
        echo "   - Try 'ddev debug migrate-database {$dbCompatibility['expected_version']}'\n";
        exit(3);
    }
    
    if ($currentDbVersion) {
        echo "   ✅ Database compatible: $currentDbVersion\n";
    } else {
        echo "   ✅ No existing database, will create: {$dbCompatibility['expected_version']}\n";
    }
    
    // Step 4: Install required DDEV add-ons
    echo "\n🔧 Installing required DDEV add-ons...\n";
    
    $requiredAddons = $platformshConfig->getRequiredAddons();
    
    foreach ($requiredAddons as $addon) {
        echo "   📦 Installing $addon...\n";
        $installCmd = "ddev add-on get $addon 2>&1";
        $installOutput = shell_exec($installCmd);
        
        if ($installOutput && str_contains($installOutput, 'successfully')) {
            echo "   ✅ $addon installed successfully\n";
        } else {
            echo "   ⚠️  $addon installation may have issues: " . trim(substr($installOutput ?? '', 0, 100)) . "\n";
        }
    }
    
    if (empty($requiredAddons)) {
        echo "   ✅ No additional DDEV add-ons required\n";
    }
    
    // Step 5: Generate DDEV configuration
    echo "\n⚙️  Generating DDEV configuration...\n";
    
    $ddevConfig = $platformshConfig->generateDdevConfiguration();
    
    echo "   📝 Configuration details:\n";
    echo "      - PHP version: {$ddevConfig['php_version']}\n";
    echo "      - Composer version: {$ddevConfig['composer_version']}\n";
    echo "      - Database: {$ddevConfig['database']['type']}:{$ddevConfig['database']['version']}\n";
    echo "      - Document root: {$ddevConfig['docroot']}\n";
    echo "      - PHP extensions: " . count($ddevConfig['webimage_extra_packages'] ?? []) . "\n";
    echo "      - Environment variables: " . count($ddevConfig['web_environment'] ?? []) . "\n";
    echo "      - Post-start hooks: " . count($ddevConfig['hooks']['post-start'] ?? []) . "\n";
    
    // Step 6: Handle project-specific customizations
    echo "\n🎯 Applying project-specific customizations...\n";
    
    // Get project type from ddev
    $projectTypeCmd = 'ddev describe -j 2>/dev/null';
    $projectInfoJson = shell_exec($projectTypeCmd);
    $projectType = 'unknown';
    
    if ($projectInfoJson) {
        $projectInfo = json_decode($projectInfoJson, true);
        $projectType = $projectInfo['raw']['type'] ?? 'unknown';
    }
    
    echo "   🏷️  Detected project type: $projectType\n";
    
    // Handle Laravel-specific configuration
    if ($projectType === 'laravel') {
        echo "   🅻 Applying Laravel-specific configuration...\n";
        
        $primaryUrl = $_ENV['DDEV_PRIMARY_URL'] ?? 'https://example.ddev.site';
        
        if (file_exists('../.env.example')) {
            $envExampleContent = file_get_contents('../.env.example');
            
            // Update APP_URL and database configuration
            $envContent = preg_replace('/APP_URL=.*/', "APP_URL=$primaryUrl", $envExampleContent);
            $envContent = preg_replace('/DB_(HOST|DATABASE|USERNAME|PASSWORD)=.*/', 'DB_\\1=db', $envContent);
            
            FileOperations::writeFileAtomic('../.env', $envContent);
            echo "      ✅ Laravel .env file updated\n";
        }
    }
    
    // Handle Drupal directory creation
    if (is_dir('../drush') && !is_dir('../.drush')) {
        FileOperations::ensureDirectoryExists('../.drush');
        echo "   🔷 Created .drush directory for Drupal\n";
    }
    
    // Step 7: Handle .environment file
    if (file_exists('../.environment')) {
        FileOperations::ensureDirectoryExists('web-entrypoint.d');
        FileOperations::copyFile('../.environment', 'web-entrypoint.d/environment.sh');
        echo "   🌍 Platform.sh .environment file copied to web-entrypoint.d/\n";
    }
    
    // Step 8: Write final configuration
    echo "\n💾 Writing final DDEV configuration...\n";
    
    // Check for existing config and back it up if it's not generated by us
    $configFile = 'config.platformsh.yaml';
    if (file_exists($configFile)) {
        $existingConfig = file_get_contents($configFile);
        if (!str_contains($existingConfig, '#ddev-generated')) {
            echo "   ❌ Existing config.platformsh.yaml does not have #ddev-generated marker\n";
            echo "   Cannot safely update existing configuration file.\n";
            exit(4);
        }
    }
    
    // Generate YAML configuration
    $yamlConfig = FileOperations::generateDdevConfig($ddevConfig);
    
    // Write configuration with backup
    FileOperations::writeFileWithBackup($configFile, $yamlConfig, 0644);
    
    echo "   ✅ config.platformsh.yaml written successfully\n";
    echo "   📊 Configuration file size: " . strlen($yamlConfig) . " bytes\n";
    
    // Step 9: Dependencies and extensions are handled by the generateDdevConfiguration method
    echo "   🔗 Dependencies and extensions configured automatically\n";
    
    // Step 10: Summary and completion
    echo "\n🎉 Platform.sh to DDEV configuration completed successfully!\n\n";
    echo "📋 Summary:\n";
    echo "   • Application: {$configSummary['app_type']}\n";
    echo "   • PHP version: {$ddevConfig['php_version']}\n";
    echo "   • Database: {$ddevConfig['database']['type']}:{$ddevConfig['database']['version']}\n";
    echo "   • Services: " . count($requiredAddons) . " add-ons installed\n";
    echo "   • Environment variables: " . count($ddevConfig['web_environment'] ?? []) . "\n";
    echo "   • Hooks: " . count($ddevConfig['hooks']['post-start'] ?? []) . " post-start actions\n";
    
    // Environment configuration is handled within the DDEV configuration generation
    echo "   • Platform relationships configured automatically\n";
    
    echo "\n✨ Next steps:\n";
    echo "   1. Run 'ddev start' to apply the new configuration\n";
    echo "   2. Run 'ddev launch' to open your application\n";
    echo "   3. Check logs with 'ddev logs' if needed\n";
    
    echo "\n🔍 For troubleshooting:\n";
    echo "   • Configuration file: $configFile\n";
    echo "   • Environment variables are available in the web container\n";
    echo "   • Use 'ddev exec env | grep PLATFORM' to see Platform.sh variables\n";
    
} catch (Exception $e) {
    $logger->logException($e, "Platform.sh configuration translation");
    
    // Generate troubleshooting report
    echo "\n" . $logger->generateTroubleshootingReport();
    
    echo "\n💡 For additional help:\n";
    echo "   • Enable debug mode: export DDEV_PLATFORMSH_DEBUG=1\n";
    echo "   • Check Platform.sh configuration files syntax\n";
    echo "   • Verify all required services are supported\n";
    echo "   • Report issues: https://github.com/ddev/ddev-platformsh/issues\n";
    
    exit(5);
}

echo "\n🏁 Platform.sh configuration translation completed.\n";