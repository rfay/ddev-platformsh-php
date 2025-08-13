<?php
#ddev-generated

// Main Platform.sh to DDEV configuration setup script
// Following ddev-redis-php pattern: simple, direct approach using environment variables

require_once 'generate-ddev-config.php';
require_once 'generate-platform-relationships.php';

// Use DDEV environment variables for context
$projectType = $_ENV['DDEV_PROJECT_TYPE'] ?? 'php';
$projectName = $_ENV['DDEV_PROJECT'] ?? 'unknown';
$appRoot = $_ENV['DDEV_APPROOT'] ?? '/var/www/html';
$docroot = $_ENV['DDEV_DOCROOT'] ?? 'web';

echo "Platform.sh DDEV Configuration Setup\n";
echo "Project: {$projectName} (Type: {$projectType})\n";
echo "Working directory: " . getcwd() . "\n";

// Generate DDEV configuration from Platform.sh files (if they exist)
$result = generate_all_ddev_config();

if ($result === 0) {
    // Generate Platform.sh environment variables
    generate_platform_environment_file();
    
    // Note: Composer dependencies will be installed via DDEV hooks in post-start
    
    echo "✅ Platform.sh configuration setup complete\n";
    echo "🔄 Please run 'ddev restart' to apply the new configuration\n";
} else {
    echo "❌ Failed to convert Platform.sh configuration\n";
    return 1;
}

?>