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
    
    // Keep database type as-is for relationships - don't convert mysql to mariadb
    $ddevDbType = $dbType;
    
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
    
    if (!empty($services) && $appConfig) {
        $appRelationships = extract_relationships($appConfig);
        $databases = extract_database_services($services);
        $otherServices = extract_other_services($services);
        
        // Generate relationships based on app config relationships section
        foreach ($appRelationships as $relationshipName => $relationshipConfig) {
            $serviceName = $relationshipConfig['service'];
            $endpoint = $relationshipConfig['endpoint'];
            
            // Check if it's a database relationship
            if (isset($databases[$serviceName])) {
                $dbConfig = $databases[$serviceName];
                $dbType = $dbConfig['type'] . ':' . $dbConfig['version'];
                $dbRel = generate_database_relationship($serviceName, $dbType, $relationshipName);
                $relationships = array_merge($relationships, $dbRel);
                echo "Added database relationship: {$relationshipName} -> {$serviceName} ({$dbType})\n";
            }
            // Check if it's an other service relationship  
            elseif (isset($otherServices[$serviceName])) {
                $serviceConfig = $otherServices[$serviceName];
                $serviceType = $serviceConfig['type'] . ':' . $serviceConfig['version'];
                $serviceRel = generate_service_relationship($serviceName, $serviceType, $relationshipName);
                $relationships = array_merge($relationships, $serviceRel);
                echo "Added service relationship: {$relationshipName} -> {$serviceName} ({$serviceType})\n";
            }
        }
        
        // Fallback: Generate relationships for services without explicit app relationships
        if (empty($appRelationships)) {
            foreach ($databases as $serviceName => $dbConfig) {
                $dbType = $dbConfig['type'] . ':' . $dbConfig['version'];
                $dbRel = generate_database_relationship($serviceName, $dbType, 'database');
                $relationships = array_merge($relationships, $dbRel);
                echo "Added database relationship: {$serviceName} ({$dbType})\n";
            }
            
            foreach ($otherServices as $serviceName => $serviceConfig) {
                $serviceType = $serviceConfig['type'] . ':' . $serviceConfig['version'];
                $serviceRel = generate_service_relationship($serviceName, $serviceType, $serviceName);
                $relationships = array_merge($relationships, $serviceRel);
                echo "Added service relationship: {$serviceName} ({$serviceType})\n";
            }
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

function generate_platform_routes() {
    $projectName = $_ENV['DDEV_SITENAME'] ?? 'ddev-project';
    
    // Generate basic route configuration for DDEV site
    $routes = [
        "https://{$projectName}.ddev.site/" => [
            "type" => "upstream",
            "upstream" => "app:http",
            "cache" => [
                "enabled" => false
            ]
        ]
    ];
    
    $jsonRoutes = json_encode($routes, JSON_UNESCAPED_SLASHES);
    $base64Routes = base64_encode($jsonRoutes);
    
    echo "Generated PLATFORM_ROUTES for site: https://{$projectName}.ddev.site/\n";
    
    return $base64Routes;
}

function generate_basic_environment_file() {
    // Generate minimal environment file for template mode (no Platform.sh config)
    $appRoot = $_ENV['DDEV_APPROOT'] ?? '/var/www/html';
    $environmentFile = "{$appRoot}/.environment";
    
    $envContent = "#ddev-generated
# Basic environment variables for template mode (no Platform.sh config)
# WordPress-specific database environment variables
export DB_HOST=\"db\"
export DB_USER=\"db\"
export DB_PASSWORD=\"db\"
export DB_NAME=\"db\"
export DB_PORT=\"3306\"

# Basic Platform.sh-like environment variables
export PLATFORM_APPLICATION_NAME=\"app\"
export PLATFORM_ENVIRONMENT=\"main\"
export PLATFORM_BRANCH=\"main\"
export PLATFORM_APP_DIR=\"{$appRoot}\"
export PLATFORM_DOCUMENT_ROOT=\"{$appRoot}/" . ($_ENV['DDEV_DOCROOT'] ?? 'web') . "\"
export PLATFORM_CACHE_DIR=\"{$appRoot}/tmp/cache\"
";
    
    file_put_contents($environmentFile, $envContent);
    echo "Generated basic environment file for template mode: .environment\n";
    
    // Also generate DDEV web environment config for PHP access
    $webEnvYaml = "#ddev-generated
# WordPress environment variables available to PHP
web_environment:
  - DB_HOST=db
  - DB_USER=db
  - DB_PASSWORD=db
  - DB_NAME=db
  - DB_PORT=3306
  - PLATFORM_APPLICATION_NAME=app
  - PLATFORM_ENVIRONMENT=main
  - PLATFORM_BRANCH=main
";
    
    file_put_contents('config.platformsh-env.yaml', $webEnvYaml);
    echo "Generated DDEV web environment config: config.platformsh-env.yaml\n";
    
    // Generate basic DDEV config for template mode to ensure proper docroot
    $basicConfigYaml = "#ddev-generated
# Basic DDEV config for template mode (no Platform.sh config)
docroot: web
";
    
    file_put_contents('config.platformsh-basic.yaml', $basicConfigYaml);
    echo "Generated basic DDEV config: config.platformsh-basic.yaml\n";
}

function generate_platform_environment_file() {
    $relationships = generate_all_platform_relationships();
    $routes = generate_platform_routes();
    
    // Generate .environment file for Platform.sh environment variables
    $appRoot = $_ENV['DDEV_APPROOT'] ?? '/var/www/html';
    $environmentFile = "{$appRoot}/.environment";
    
    $envContent = "#ddev-generated
# Platform.sh environment variables for DDEV
export PLATFORM_RELATIONSHIPS=\"{$relationships}\"
export PLATFORM_ROUTES=\"{$routes}\"
export PLATFORM_APPLICATION_NAME=\"app\"
export PLATFORM_ENVIRONMENT=\"main\"
export PLATFORM_BRANCH=\"main\"
export PLATFORM_TREE_ID=\"main\"
export PLATFORM_PROJECT=\"ddev-project\"
export PLATFORM_PROJECT_ENTROPY=\"ddev-entropy-12345\"
export PLATFORM_APP_DIR=\"{$appRoot}\"
export PLATFORM_DOCUMENT_ROOT=\"{$appRoot}/" . ($_ENV['DDEV_DOCROOT'] ?? 'web') . "\"
export PLATFORM_CACHE_DIR=\"{$appRoot}/tmp/cache\"
export PLATFORM_VARIABLES=\"\"
export PLATFORM_SMTP_HOST=\"\"

# WordPress-specific database environment variables (common in Platform.sh WordPress templates)
export DB_HOST=\"db\"
export DB_USER=\"db\"
export DB_PASSWORD=\"db\"
export DB_NAME=\"db\"
export DB_PORT=\"3306\"
";
    
    file_put_contents($environmentFile, $envContent);
    echo "Generated Platform.sh environment file: .environment\n";
    
    // Also generate DDEV web environment config for PHP access
    $webEnvYaml = "#ddev-generated
# Platform.sh environment variables available to PHP
web_environment:
  - PLATFORM_RELATIONSHIPS={$relationships}
  - PLATFORM_ROUTES={$routes}
  - PLATFORM_APPLICATION_NAME=app
  - PLATFORM_ENVIRONMENT=main
  - PLATFORM_BRANCH=main
  - PLATFORM_PROJECT=ddev-project
  - DB_HOST=db
  - DB_USER=db
  - DB_PASSWORD=db
  - DB_NAME=db
  - DB_PORT=3306
";
    
    file_put_contents('config.platformsh-env.yaml', $webEnvYaml);
    echo "Generated DDEV web environment config: config.platformsh-env.yaml\n";
}
?>