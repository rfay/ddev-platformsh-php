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

// Check for database compatibility before proceeding
$appConfig = parse_platformsh_app_config();
$services = parse_platformsh_services_config();

if (!check_database_compatibility($appConfig, $services)) {
    return 1;
}

// Generate DDEV configuration from Platform.sh files (if they exist)
$result = generate_all_ddev_config();

if ($result === 0) {
    // Install required DDEV add-ons based on services (if any)
    install_required_addons($services);
    
    // Generate Platform.sh environment variables
    generate_platform_environment_file();
    
    // Note: Composer dependencies will be installed via DDEV hooks in post-start
    
    echo "✅ Platform.sh configuration setup complete\n";
    echo "🔄 Please run 'ddev restart' to apply the new configuration\n";
} else {
    echo "❌ Failed to convert Platform.sh configuration\n";
    return 1;
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

function check_database_compatibility($appConfig, $services) {
    // Get current DDEV database configuration
    $currentDbVersion = get_current_ddev_database_version();
    
    // Get expected database configuration from Platform.sh
    $expectedDb = get_expected_database_config($appConfig, $services);
    
    if (!$expectedDb) {
        // No database configuration found, allow to proceed
        return true;
    }
    
    $expectedVersion = "{$expectedDb['ddev_type']}:{$expectedDb['version']}";
    
    if (empty($currentDbVersion)) {
        // No current database, allow to proceed
        return true;
    }
    
    // Remove color escape sequences from current version
    $cleanCurrentVersion = preg_replace('/\x1b\[[0-9;]*m?/', '', $currentDbVersion);
    
    if ($cleanCurrentVersion !== $expectedVersion) {
        echo "There is an existing database in this project that doesn't match the upstream database type.\n";
        echo "Expected: {$expectedVersion}, Found: {$cleanCurrentVersion}\n";
        echo "Please use 'ddev delete' to delete the existing database and retry, or try\n";
        echo "'ddev debug migrate-database {$expectedVersion}' to migrate the database.\n";
        return false;
    }
    
    return true;
}

function get_current_ddev_database_version() {
    // Get current DDEV database configuration
    $ddevConfigPath = '../.ddev/config.yaml';
    
    if (!file_exists($ddevConfigPath)) {
        return null;
    }
    
    $config = yaml_parse_file($ddevConfigPath);
    if (!$config || !isset($config['database'])) {
        return null;
    }
    
    $dbType = $config['database']['type'] ?? 'mariadb';
    $dbVersion = $config['database']['version'] ?? '10.4';
    
    return "{$dbType}:{$dbVersion}";
}

function get_expected_database_config($appConfig, $services) {
    $databases = extract_database_services($services);
    
    if (empty($databases)) {
        return null;
    }
    
    // Use first database as primary
    $primaryDb = array_values($databases)[0];
    
    // Apply Platform.sh to DDEV database type mapping
    $ddevDbType = $primaryDb['type'];
    if ($primaryDb['type'] === 'mysql') {
        $ddevDbType = 'mariadb';
    } elseif ($primaryDb['type'] === 'oracle-mysql') {
        $ddevDbType = 'mysql';
    } elseif ($primaryDb['type'] === 'postgresql') {
        $ddevDbType = 'postgres';
    }
    
    return [
        'ddev_type' => $ddevDbType,
        'version' => $primaryDb['version']
    ];
}
?>