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

// Check if Platform.sh configuration exists
if (!file_exists('../.platform.app.yaml')) {
    echo "✅ No Platform.sh configuration found - installation successful for template mode\n";
    exit(0);
}

echo "📝 Found Platform.sh configuration files\n";

// Generate DDEV configuration from Platform.sh files
$result = generate_all_ddev_config();

if ($result === 0) {
    // Install required DDEV add-ons based on services
    $services = parse_platformsh_services_config();
    install_required_addons($services);
    
    // Generate Platform.sh environment variables
    generate_platform_environment_file();
    
    // Install Composer dependencies if composer.json exists
    if (file_exists('../composer.json')) {
        echo "📦 Installing Composer dependencies...\n";
        $composerOutput = shell_exec('cd .. && composer install --no-interaction --no-progress --quiet 2>&1');
        if ($composerOutput && trim($composerOutput) !== '') {
            echo "Composer: {$composerOutput}\n";
        }
        echo "✅ Composer dependencies installed\n";
    }
    
    echo "✅ Platform.sh configuration successfully converted to DDEV\n";
    echo "🔄 Please run 'ddev restart' to apply the new configuration\n";
} else {
    echo "❌ Failed to convert Platform.sh configuration\n";
    exit(1);
}

function install_required_addons($services) {
    if (empty($services)) {
        return;
    }
    
    $supportedAddons = [
        'redis' => 'ddev/ddev-redis',
        'redis-persistent' => 'ddev/ddev-redis', 
        'memcached' => 'ddev/ddev-memcached',
        'elasticsearch' => 'ddev/ddev-elasticsearch',
        'opensearch' => 'ddev/ddev-elasticsearch'
    ];
    
    $otherServices = extract_other_services($services);
    
    foreach ($otherServices as $serviceName => $serviceConfig) {
        $serviceType = $serviceConfig['type'];
        
        if (isset($supportedAddons[$serviceType])) {
            $addon = $supportedAddons[$serviceType];
            echo "📦 Installing DDEV add-on for {$serviceType}: {$addon}\n";
            
            // Run ddev add-on get command
            $output = shell_exec("cd .. && ddev add-on get {$addon} 2>&1");
            if ($output) {
                echo "Add-on output: {$output}\n";
            }
            echo "✅ Installed {$addon}\n";
        }
    }
}
?>