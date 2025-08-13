<?php
#ddev-generated

// Generate DDEV configuration files from Platform.sh config
// Following ddev-redis-php pattern: use plain text generation instead of yaml_emit()

require_once 'parse-platformsh-config.php';
require_once 'generate-platform-relationships.php';

function generate_ddev_database_config($databases) {
    if (empty($databases)) {
        return;
    }
    
    // Use first database as primary
    $primaryDb = array_values($databases)[0];
    $primaryName = array_keys($databases)[0];
    
    echo "Configuring DDEV for database: {$primaryName} ({$primaryDb['type']}:{$primaryDb['version']})\n";
    
    // Generate database configuration for DDEV's config
    // Apply Platform.sh to DDEV database type mapping (matches bash add-on logic)
    $ddevDbType = $primaryDb['type'];
    if ($primaryDb['type'] === 'mysql') {
        $ddevDbType = 'mariadb';
    } elseif ($primaryDb['type'] === 'oracle-mysql') {
        $ddevDbType = 'mysql';  // oracle-mysql in Platform.sh becomes mysql in DDEV
    } elseif ($primaryDb['type'] === 'postgresql') {
        $ddevDbType = 'postgres';
    }
    $yamlContent = "#ddev-generated
# Platform.sh database configuration - sets primary database
database:
  type: {$ddevDbType}
  version: \"{$primaryDb['version']}\"
";
    
    file_put_contents('config.platformsh-db.yaml', $yamlContent);
    echo "Generated database config: {$ddevDbType}:{$primaryDb['version']}\n";
    
    // Note: Additional databases could be handled with docker-compose if needed in the future
    // For now, DDEV's config.yaml database setting handles the primary database correctly
}

function generate_ddev_services_config($services) {
    if (empty($services)) {
        return;
    }
    
    echo "Configuring DDEV services:\n";
    
    // Generate docker-compose.platformsh-services.yaml using plain text
    $yamlContent = "#ddev-generated
# Platform.sh services configuration
services:";
    
    foreach ($services as $serviceName => $serviceConfig) {
        echo "- {$serviceName}: {$serviceConfig['type']}:{$serviceConfig['version']}\n";
        
        // Use service type as container name to match DDEV add-on expectations
        $containerName = $serviceConfig['type'];
        
        switch ($serviceConfig['type']) {
            case 'redis':
                $yamlContent .= "
  {$containerName}:
    container_name: ddev-\${DDEV_SITENAME}-{$containerName}
    image: redis:{$serviceConfig['version']}-alpine
    labels:
      com.ddev.site-name: \${DDEV_SITENAME}
      com.ddev.approot: \${DDEV_APPROOT}
    restart: \"no\"
    expose:
      - 6379
    volumes:
      - \"ddev-global-cache:/mnt/ddev-global-cache\"
    command: redis-server --appendonly yes";
                break;
                
            case 'elasticsearch':
                $yamlContent .= "
  {$containerName}:
    container_name: ddev-\${DDEV_SITENAME}-{$containerName}
    image: elasticsearch:{$serviceConfig['version']}
    labels:
      com.ddev.site-name: \${DDEV_SITENAME}
      com.ddev.approot: \${DDEV_APPROOT}
    restart: \"no\"
    environment:
      - discovery.type=single-node
      - \"ES_JAVA_OPTS=-Xms512m -Xmx512m\"
    expose:
      - 9200
      - 9300
    volumes:
      - \"ddev-global-cache:/mnt/ddev-global-cache\"";
                break;
                
            case 'solr':
                $yamlContent .= "
  {$containerName}:
    container_name: ddev-\${DDEV_SITENAME}-{$containerName}
    image: solr:{$serviceConfig['version']}-alpine
    labels:
      com.ddev.site-name: \${DDEV_SITENAME}
      com.ddev.approot: \${DDEV_APPROOT}
    restart: \"no\"
    expose:
      - 8983
    volumes:
      - \"ddev-global-cache:/mnt/ddev-global-cache\"
    command: solr-precreate default";
                break;
                
            case 'memcached':
                $yamlContent .= "
  {$containerName}:
    container_name: ddev-\${DDEV_SITENAME}-{$containerName}
    image: memcached:{$serviceConfig['version']}-alpine
    labels:
      com.ddev.site-name: \${DDEV_SITENAME}
      com.ddev.approot: \${DDEV_APPROOT}
    restart: \"no\"
    expose:
      - 11211
    volumes:
      - \"ddev-global-cache:/mnt/ddev-global-cache\"";
                break;
        }
    }
    
    if (!empty($services)) {
        file_put_contents('docker-compose.platformsh-services.yaml', $yamlContent);
        echo "Generated docker-compose.platformsh-services.yaml\n";
    }
}

function generate_ddev_php_config($phpVersion, $docroot, $composerVersion = '2') {
    echo "Configuring DDEV for PHP {$phpVersion} with docroot '{$docroot}' and Composer {$composerVersion}\n";
    
    // Generate Platform.sh environment variables
    $relationships = generate_all_platform_relationships();
    $routes = generate_platform_routes();
    
    // Generate config.platformsh.yaml to override DDEV settings
    $yamlContent = "#ddev-generated
# Platform.sh PHP configuration - overrides project settings
disable_settings_management: true
php_version: \"{$phpVersion}\"
docroot: \"{$docroot}\"
composer_version: \"{$composerVersion}\"
webimage_extra_packages:
  - figlet
web_environment:
  - \"PLATFORM_RELATIONSHIPS={$relationships}\"
  - \"PLATFORM_ROUTES={$routes}\"
  - \"PLATFORM_APP_DIR=/var/www/html\"
  - \"PLATFORM_PROJECT_ENTROPY=ddev-entropy-12345\"
  - \"PLATFORM_TREE_ID=main\"
  - \"PLATFORM_CACHE_DIR=/mnt/ddev-global-cache/ddev-platformsh/\${DDEV_PROJECT}\"
  - \"PLATFORM_VARIABLES=e30=\"
  - \"PLATFORM_APPLICATION_NAME=app\"
  - \"PLATFORM_ENVIRONMENT=main\"
  - \"PLATFORM_BRANCH=main\"
  - \"PLATFORM_PROJECT=ddev-project\"
  - \"PLATFORM_DOCUMENT_ROOT=/var/www/html/{$docroot}\"
  - \"DB_HOST=db\"
  - \"DB_NAME=db\"
  - \"DB_USER=db\"
  - \"DB_PASSWORD=db\"
hooks:
  post-start:
    - exec: mkdir -p \${PLATFORM_CACHE_DIR} || true
    - composer: install
";
    
    file_put_contents('config.platformsh.yaml', $yamlContent);
    echo "Generated config.platformsh.yaml\n";
}

function generate_ddev_dockerfile($appConfig) {
    if (!$appConfig || !isset($appConfig['dependencies']['php'])) {
        return;
    }
    
    $phpDeps = $appConfig['dependencies']['php'];
    $dockerfileContent = "#ddev-generated\nRUN ln -sf /var/www/html /app\n";
    
    $hasComposerDeps = false;
    foreach ($phpDeps as $pkg => $version) {
        if ($pkg !== 'composer/composer') {
            $hasComposerDeps = true;
            break;
        }
    }
    
    if ($hasComposerDeps) {
        echo "Installing PHP dependencies via Composer\n";
        $dockerfileContent .= "\n# Install PHP dependencies from Platform.sh configuration\n";
        $dockerfileContent .= "ENV COMPOSER_HOME=/usr/local/composer\n";
        $dockerfileContent .= "RUN echo \"export PATH=\${PATH}:\${COMPOSER_HOME}/vendor/bin\" >/etc/bashrc/composerpath.bashrc\n";
        
        foreach ($phpDeps as $pkg => $version) {
            if ($pkg !== 'composer/composer') {
                $versionSpec = ($version !== '*') ? ":{$version}" : '';
                $dockerfileContent .= "RUN composer global require {$pkg}{$versionSpec}\n";
                echo "- {$pkg}{$versionSpec}\n";
            }
        }
    }
    
    file_put_contents('web-build/Dockerfile.platformsh', $dockerfileContent);
    echo "Generated web-build/Dockerfile.platformsh\n";
}

function generate_all_ddev_config() {
    echo "Generating DDEV configuration from Platform.sh files...\n";
    
    // Parse Platform.sh configuration
    $appConfig = parse_platformsh_app_config();
    $services = parse_platformsh_services_config();
    $routes = parse_platformsh_routes_config();
    
    if (!$appConfig && empty($services)) {
        echo "No Platform.sh configuration found\n";
        return 1;
    }
    
    // Print summary
    print_parsed_config($appConfig, $services, $routes);
    
    // Generate DDEV configurations
    if ($appConfig) {
        $phpVersion = extract_php_version($appConfig);
        $docroot = extract_docroot($appConfig);
        $composerVersion = extract_composer_version($appConfig);
        generate_ddev_php_config($phpVersion, $docroot, $composerVersion);
        generate_ddev_dockerfile($appConfig);
    }
    
    if (!empty($services)) {
        $databases = extract_database_services($services);
        $otherServices = extract_other_services($services);
        
        generate_ddev_database_config($databases);
        generate_ddev_services_config($otherServices);
    }
    
    echo "DDEV configuration generation complete!\n";
    return 0;
}
?>