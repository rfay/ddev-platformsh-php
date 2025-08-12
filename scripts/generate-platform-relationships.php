<?php
#ddev-generated

// Generate Platform.sh PLATFORM_RELATIONSHIPS environment variable
// Equivalent to the bash generate_*_relationship.sh scripts

require_once 'parse-platformsh-config.php';

function generate_database_relationship($serviceName, $dbType, $relationshipName) {
    // Map database types to scheme and port
    $scheme = 'mysql';
    $port = 3306;
    $rel = 'mysql';
    
    // Convert Platform.sh mysql to mariadb for DDEV compatibility
    $ddevDbType = $dbType;
    if (strpos($dbType, 'mysql:') === 0) {
        $ddevDbType = str_replace('mysql:', 'mariadb:', $dbType);
    }
    
    if (strpos($dbType, 'postgres') === 0) {
        $scheme = 'pgsql';
        $port = 5432;
        $rel = 'pgsql';
    } elseif (strpos($dbType, 'mariadb') === 0 || strpos($dbType, 'mysql') === 0) {
        $scheme = 'mysql';
        $port = 3306;
        $rel = 'mysql';
    }
    
    return [
        $relationshipName => [
            [
                "username" => "db",
                "scheme" => $scheme,
                "service" => $serviceName,
                "fragment" => null,
                "ip" => "255.255.255.255",
                "hostname" => "db",
                "public" => false,
                "cluster" => "ddev-dummy-cluster",
                "host" => "db",
                "rel" => $rel,
                "query" => [
                    "is_master" => true
                ],
                "path" => "db",
                "password" => "db",
                "type" => $ddevDbType,
                "port" => $port,
                "host_mapped" => false
            ]
        ]
    ];
}

function generate_service_relationship($serviceName, $serviceType, $relationshipName) {
    $port = 6379; // Default Redis port
    $scheme = 'redis';
    
    // Extract service type from version string
    $baseServiceType = explode(':', $serviceType)[0];
    
    if (strpos($serviceType, 'redis') === 0) {
        $port = 6379;
        $scheme = 'redis';
        $hostname = 'redis';  // Use service type as hostname to match container name
    } elseif (strpos($serviceType, 'elasticsearch') === 0) {
        $port = 9200;
        $scheme = 'http';
        $hostname = 'elasticsearch';
    } elseif (strpos($serviceType, 'solr') === 0) {
        $port = 8983;
        $scheme = 'solr';
        $hostname = 'solr';
    } elseif (strpos($serviceType, 'memcached') === 0) {
        $port = 11211;
        $scheme = 'memcached';
        $hostname = 'memcached';
    } else {
        $hostname = $baseServiceType;
    }
    
    return [
        $relationshipName => [
            [
                "username" => null,
                "scheme" => $scheme,
                "service" => $serviceName,  // Keep original service name for Platform.sh compatibility
                "fragment" => null,
                "ip" => "255.255.255.255",
                "hostname" => $hostname,    // Use service type as hostname to match DDEV container name
                "public" => false,
                "cluster" => "ddev-dummy-cluster",
                "host" => $hostname,        // Use service type as host to match DDEV container name
                "rel" => $scheme,
                "query" => [],
                "path" => null,
                "password" => null,
                "type" => $serviceType,
                "port" => $port,
                "host_mapped" => false
            ]
        ]
    ];
}

function generate_all_platform_relationships() {
    echo "Generating Platform.sh PLATFORM_RELATIONSHIPS...\n";
    
    $appConfig = parse_platformsh_app_config();
    $services = parse_platformsh_services_config();
    
    $relationships = [];
    
    if (!empty($services)) {
        $databases = extract_database_services($services);
        $otherServices = extract_other_services($services);
        
        // Generate database relationships
        foreach ($databases as $serviceName => $dbConfig) {
            $dbType = $dbConfig['type'] . ':' . $dbConfig['version'];
            $dbRel = generate_database_relationship($serviceName, $dbType, 'database');
            $relationships = array_merge($relationships, $dbRel);
            echo "Added database relationship: {$serviceName} ({$dbType})\n";
        }
        
        // Generate service relationships
        foreach ($otherServices as $serviceName => $serviceConfig) {
            $serviceType = $serviceConfig['type'] . ':' . $serviceConfig['version'];
            $serviceRel = generate_service_relationship($serviceName, $serviceType, $serviceName);
            $relationships = array_merge($relationships, $serviceRel);
            echo "Added service relationship: {$serviceName} ({$serviceType})\n";
        }
    }
    
    if (empty($relationships)) {
        echo "No Platform.sh services found for relationships\n";
        return '';
    }
    
    // Convert to JSON and base64 encode (like the bash version)
    $jsonRelationships = json_encode($relationships, JSON_UNESCAPED_SLASHES);
    $base64Relationships = base64_encode($jsonRelationships);
    
    echo "Generated PLATFORM_RELATIONSHIPS with " . count($relationships) . " relationship(s)\n";
    
    return $base64Relationships;
}

function generate_platform_environment_file() {
    $relationships = generate_all_platform_relationships();
    
    if (empty($relationships)) {
        return;
    }
    
    // Generate .environment file for Platform.sh environment variables
    $appRoot = $_ENV['DDEV_APPROOT'] ?? '/var/www/html';
    $environmentFile = "{$appRoot}/.environment";
    
    $envContent = "#ddev-generated
# Platform.sh environment variables for DDEV
export PLATFORM_RELATIONSHIPS=\"{$relationships}\"
export PLATFORM_APPLICATION_NAME=\"app\"
export PLATFORM_ENVIRONMENT=\"main\"
export PLATFORM_BRANCH=\"main\"
export PLATFORM_TREE_ID=\"main\"
export PLATFORM_PROJECT=\"ddev-project\"
export PLATFORM_APP_DIR=\"{$appRoot}\"
export PLATFORM_DOCUMENT_ROOT=\"{$appRoot}/" . ($_ENV['DDEV_DOCROOT'] ?? 'web') . "\"
";
    
    file_put_contents($environmentFile, $envContent);
    echo "Generated Platform.sh environment file: .environment\n";
}
?>