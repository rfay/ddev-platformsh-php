<?php
#ddev-generated

// Simple Platform.sh configuration parsing using yaml_parse_file() and environment variables
// Working directory: /var/www/html/.ddev

function parse_platformsh_app_config() {
    $appConfigFile = '../.platform.app.yaml';
    
    if (!file_exists($appConfigFile)) {
        return null;
    }
    
    $config = yaml_parse_file($appConfigFile);
    if ($config === false) {
        echo "Error: Failed to parse .platform.app.yaml\n";
        return null;
    }
    
    return $config;
}

function parse_platformsh_services_config() {
    $servicesConfigFile = '../.platform/services.yaml';
    
    if (!file_exists($servicesConfigFile)) {
        return [];
    }
    
    $config = yaml_parse_file($servicesConfigFile);
    if ($config === false) {
        echo "Error: Failed to parse .platform/services.yaml\n";
        return [];
    }
    
    return $config;
}

function parse_platformsh_routes_config() {
    $routesConfigFile = '../.platform/routes.yaml';
    
    if (!file_exists($routesConfigFile)) {
        return [];
    }
    
    $config = yaml_parse_file($routesConfigFile);
    if ($config === false) {
        echo "Error: Failed to parse .platform/routes.yaml\n";
        return [];
    }
    
    return $config;
}

function extract_database_services($services) {
    $databases = [];
    
    if (empty($services) || !is_array($services)) {
        return $databases;
    }
    
    foreach ($services as $serviceName => $serviceConfig) {
        if (isset($serviceConfig['type'])) {
            $type = $serviceConfig['type'];
            
            // Check for database types - preserve oracle-mysql vs mysql distinction
            if (strpos($type, 'mysql') !== false || strpos($type, 'mariadb') !== false) {
                // Extract the base type (before the colon) to preserve oracle-mysql vs mysql
                $baseType = explode(':', $type)[0];
                $databases[$serviceName] = [
                    'type' => $baseType,  // Keep original: mysql, oracle-mysql, or mariadb
                    'version' => extract_version_from_type($type),
                    'disk' => $serviceConfig['disk'] ?? 1024
                ];
            } elseif (strpos($type, 'postgresql') !== false) {
                $databases[$serviceName] = [
                    'type' => 'postgres',
                    'version' => extract_version_from_type($type),
                    'disk' => $serviceConfig['disk'] ?? 1024
                ];
            }
        }
    }
    
    return $databases;
}

function extract_other_services($services) {
    $otherServices = [];
    
    if (empty($services) || !is_array($services)) {
        return $otherServices;
    }
    
    foreach ($services as $serviceName => $serviceConfig) {
        if (isset($serviceConfig['type'])) {
            $type = $serviceConfig['type'];
            
            // Check for non-database services
            if (strpos($type, 'redis') !== false) {
                $otherServices[$serviceName] = [
                    'type' => 'redis',
                    'version' => extract_version_from_type($type)
                ];
            } elseif (strpos($type, 'elasticsearch') !== false) {
                $otherServices[$serviceName] = [
                    'type' => 'elasticsearch',
                    'version' => extract_version_from_type($type)
                ];
            } elseif (strpos($type, 'solr') !== false) {
                $otherServices[$serviceName] = [
                    'type' => 'solr',
                    'version' => extract_version_from_type($type)
                ];
            } elseif (strpos($type, 'memcached') !== false) {
                $otherServices[$serviceName] = [
                    'type' => 'memcached',
                    'version' => extract_version_from_type($type)
                ];
            }
        }
    }
    
    return $otherServices;
}

function extract_version_from_type($type) {
    // Extract version from types like "mysql:8.0", "redis:6.0", etc.
    if (strpos($type, ':') !== false) {
        return explode(':', $type)[1];
    }
    return 'latest';
}

function extract_php_version($appConfig) {
    if (isset($appConfig['type']) && strpos($appConfig['type'], 'php:') === 0) {
        return str_replace('php:', '', $appConfig['type']);
    }
    return '8.1'; // Default PHP version
}

function extract_docroot($appConfig) {
    return $appConfig['web']['locations']['/']['root'] ?? 'web';
}

function extract_composer_version($appConfig) {
    if (isset($appConfig['dependencies']['php']['composer/composer'])) {
        $composerVersion = $appConfig['dependencies']['php']['composer/composer'];
        
        // Handle caret versions like '^1' -> '1', '^2' -> '2'
        if (strpos($composerVersion, '^') === 0) {
            return substr($composerVersion, 1, 1);
        }
        
        // Handle explicit versions like '2.5.6' -> '2.5.6', '1.10.22' -> '1.10.22'
        if (preg_match('/^\d+\.\d+(\.\d+)?$/', $composerVersion)) {
            return $composerVersion;
        }
        
        // Fallback: extract major version for other cases
        if (preg_match('/^(\d+)\./', $composerVersion, $matches)) {
            return $matches[1];
        }
    }
    
    // Default to Composer 2 if not specified
    return '2';
}

function extract_relationships($appConfig) {
    if (!isset($appConfig['relationships'])) {
        return [];
    }
    
    $relationships = [];
    foreach ($appConfig['relationships'] as $relationshipName => $serviceMapping) {
        // Parse service mapping like 'db:mysql' or 'cache:redis'
        if (strpos($serviceMapping, ':') !== false) {
            list($serviceName, $endpoint) = explode(':', $serviceMapping, 2);
            $relationships[$relationshipName] = [
                'service' => $serviceName,
                'endpoint' => $endpoint
            ];
        }
    }
    
    return $relationships;
}

// Utility function to print parsed configuration for debugging
function print_parsed_config($appConfig, $services, $routes) {
    echo "Platform.sh Configuration Summary:\n";
    
    if ($appConfig) {
        echo "- PHP Version: " . extract_php_version($appConfig) . "\n";
        echo "- Document Root: " . extract_docroot($appConfig) . "\n";
        echo "- Composer Version: " . extract_composer_version($appConfig) . "\n";
    }
    
    $databases = extract_database_services($services);
    if (!empty($databases)) {
        echo "- Databases:\n";
        foreach ($databases as $name => $config) {
            echo "  * {$name}: {$config['type']}:{$config['version']}\n";
        }
    }
    
    $otherServices = extract_other_services($services);
    if (!empty($otherServices)) {
        echo "- Other Services:\n";
        foreach ($otherServices as $name => $config) {
            echo "  * {$name}: {$config['type']}:{$config['version']}\n";
        }
    }
}
?>