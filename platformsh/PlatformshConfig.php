<?php
#ddev-generated

/**
 * Platform.sh Configuration Parser for DDEV PHP Add-on
 * 
 * Handles parsing of Platform.sh configuration files and translation
 * to DDEV-compatible configuration structures.
 */
class PlatformshConfig
{
    private array $platformApp = [];
    private array $services = [];
    private array $routes = [];
    private ?DatabaseConfig $databaseConfig = null;
    
    // DDEV environment variables
    private string $projectName;
    private string $docroot;
    private string $projectType;
    private string $appRoot;
    
    public function __construct()
    {
        // Initialize from DDEV environment variables
        $this->projectName = $_ENV['DDEV_PROJECT'] ?? '';
        $this->docroot = $_ENV['DDEV_DOCROOT'] ?? 'web';
        $this->projectType = $_ENV['DDEV_PROJECT_TYPE'] ?? 'php';
        $this->appRoot = $_ENV['DDEV_APPROOT'] ?? '/var/www/html';
        
        // Load Platform.sh configuration files
        $this->loadPlatformshConfig();
        
        // Initialize database configuration handler
        $this->initializeDatabaseConfig();
    }
    
    /**
     * Load and parse Platform.sh configuration files
     */
    private function loadPlatformshConfig(): void
    {
        // Load .platform.app.yaml from project root
        $appConfigPath = $this->appRoot . '/.platform.app.yaml';
        if (file_exists($appConfigPath)) {
            $this->platformApp = yaml_parse_file($appConfigPath) ?: [];
        }
        
        // Load .platform/services.yaml
        $servicesPath = $this->appRoot . '/.platform/services.yaml';
        if (file_exists($servicesPath)) {
            $this->services = yaml_parse_file($servicesPath) ?: [];
        }
        
        // Load .platform/routes.yaml  
        $routesPath = $this->appRoot . '/.platform/routes.yaml';
        if (file_exists($routesPath)) {
            $this->routes = yaml_parse_file($routesPath) ?: [];
        }
    }
    
    /**
     * Initialize database configuration handler
     */
    private function initializeDatabaseConfig(): void
    {
        require_once __DIR__ . '/DatabaseConfig.php';
        $this->databaseConfig = new DatabaseConfig($this->platformApp, $this->services);
    }
    
    /**
     * Get database configuration handler
     */
    public function getDatabaseConfig(): DatabaseConfig
    {
        return $this->databaseConfig;
    }
    
    /**
     * Get PHP version from Platform.sh app configuration
     */
    public function getPhpVersion(): string
    {
        $type = $this->platformApp['type'] ?? 'php:8.1';
        return str_replace('php:', '', $type);
    }
    
    /**
     * Get Composer version from dependencies
     */
    public function getComposerVersion(): string
    {
        $dependencies = $this->platformApp['dependencies']['php'] ?? [];
        
        foreach ($dependencies as $package => $version) {
            if ($package === 'composer/composer') {
                return str_replace('^', '', $version);
            }
        }
        
        return '2'; // Default to Composer 2
    }
    
    /**
     * Get document root from Platform.sh configuration
     */
    public function getDocumentRoot(): string
    {
        return $this->platformApp['web']['locations']['/']['root'] ?? $this->docroot;
    }
    
    /**
     * Get PHP extensions from Platform.sh configuration
     */
    public function getPhpExtensions(): array
    {
        $extensions = $this->platformApp['runtime']['extensions'] ?? [];
        $phpVersion = $this->getPhpVersion();
        
        // Filter out extensions that are handled differently in DDEV
        $filtered = array_diff($extensions, ['blackfire', 'pdo_pgsql', 'sodium']);
        
        // Convert to DDEV package names
        $packages = [];
        foreach ($filtered as $extension) {
            $packages[] = "php{$phpVersion}-{$extension}";
        }
        
        // Handle sodium separately (different package name)
        if (in_array('sodium', $extensions)) {
            $packages[] = 'php-sodium';
        }
        
        return $packages;
    }
    
    /**
     * Get environment variables from Platform.sh configuration
     */
    public function getEnvironmentVariables(): array
    {
        return $this->platformApp['variables']['env'] ?? [];
    }
    
    /**
     * Generate relationships data for PLATFORM_RELATIONSHIPS (legacy method)
     * Use generateServiceRelationships() for non-database services
     */
    public function generateRelationships(): array
    {
        // Combine database and service relationships
        return array_merge(
            $this->databaseConfig->generateDatabaseRelationships(),
            $this->generateServiceRelationships()
        );
    }
    
    /**
     * Generate non-database service relationships
     */
    private function generateServiceRelationships(): array
    {
        $relationships = [];
        $platformRelationships = $this->platformApp['relationships'] ?? [];
        
        foreach ($platformRelationships as $relationshipName => $relationshipDef) {
            $serviceName = explode(':', $relationshipDef)[0];
            $serviceDef = $this->services[$serviceName] ?? null;
            
            if (!$serviceDef) {
                continue;
            }
            
            [$serviceType, $serviceVersion] = explode(':', $serviceDef['type'] ?? 'unknown:latest');
            
            // Skip database services (handled by DatabaseConfig)
            if (in_array($serviceType, ['mysql', 'mariadb', 'oracle-mysql', 'postgresql', 'postgres'])) {
                continue;
            }
            
            // Handle non-database services
            if (in_array($serviceType, ['redis', 'redis-persistent', 'memcached', 'elasticsearch', 'opensearch'])) {
                $relationships[$relationshipName] = $this->generateServiceRelationship($serviceType, $relationshipName);
            }
        }
        
        return $relationships;
    }
    
    /**
     * Generate routes data for PLATFORM_ROUTES
     */
    public function generateRoutes(): array
    {
        $routeData = [];
        $primaryUrl = $_ENV['DDEV_PRIMARY_URL'] ?? "https://{$this->projectName}.ddev.site";
        
        foreach ($this->routes as $routePattern => $routeConfig) {
            $routeData[$primaryUrl . '/'] = [
                'primary' => true,
                'id' => $routeConfig['id'] ?? null,
                'production_url' => $routeConfig['production_url'] ?? '',
                'attributes' => new stdClass(),
                'upstream' => $routeConfig['upstream'] ?? '',
                'type' => $routeConfig['type'] ?? '',
                'original_url' => $routePattern
            ];
        }
        
        return $routeData;
    }
    
    /**
     * Map Platform.sh service types to DDEV equivalents
     */
    private function mapServiceType(string $serviceType): string
    {
        return match($serviceType) {
            'mysql' => 'mariadb',
            'oracle-mysql' => 'mysql', 
            'postgresql' => 'postgres',
            default => $serviceType
        };
    }
    
    /**
     * Generate database relationship structure
     */
    private function generateDatabaseRelationship(string $serviceName, string $dbType, string $relationshipName): array
    {
        [$type, $version] = explode(':', $dbType);
        
        $scheme = match($type) {
            'postgres' => 'pgsql',
            default => 'mysql'
        };
        
        $port = match($type) {
            'postgres' => 5432,
            default => 3306
        };
        
        return [
            [
                'username' => 'db',
                'scheme' => $scheme,
                'service' => $serviceName,
                'fragment' => null,
                'ip' => '255.255.255.255',
                'hostname' => 'db',
                'public' => false,
                'cluster' => 'ddev-dummy-cluster',
                'host' => 'db',
                'rel' => $scheme === 'pgsql' ? 'pgsql' : 'mysql',
                'query' => ['is_master' => true],
                'path' => 'db',
                'password' => 'db',
                'type' => $dbType,
                'port' => $port,
                'host_mapped' => false
            ]
        ];
    }
    
    /**
     * Generate service relationship structure
     */
    private function generateServiceRelationship(string $serviceType, string $relationshipName): array
    {
        return match($serviceType) {
            'redis', 'redis-persistent' => [
                [
                    'username' => null,
                    'scheme' => 'redis',
                    'service' => 'cache',
                    'fragment' => null,
                    'ip' => '255.255.255.255',
                    'hostname' => 'redis',
                    'public' => false,
                    'cluster' => 'ddev-dummy-cluster',
                    'host' => 'redis',
                    'rel' => 'redis',
                    'query' => new stdClass(),
                    'path' => null,
                    'password' => null,
                    'type' => 'redis:6.0',
                    'port' => 6379,
                    'host_mapped' => false
                ]
            ],
            'elasticsearch', 'opensearch' => [
                [
                    'username' => null,
                    'scheme' => 'http',
                    'service' => 'search',
                    'fragment' => null,
                    'ip' => '255.255.255.255',
                    'hostname' => 'elasticsearch',
                    'public' => false,
                    'cluster' => 'ddev-dummy-cluster',
                    'host' => 'elasticsearch',
                    'rel' => 'elasticsearch',
                    'query' => new stdClass(),
                    'path' => null,
                    'password' => null,
                    'type' => 'elasticsearch:7.5',
                    'port' => 9200,
                    'host_mapped' => false
                ]
            ],
            'memcached' => [
                [
                    'service' => 'memcached',
                    'ip' => '255.255.255.255',
                    'hostname' => 'memcached',
                    'cluster' => 'ddev-dummy-cluster',
                    'host' => 'memcached',
                    'rel' => 'memcached',
                    'scheme' => 'memcached',
                    'type' => 'memcached:1.6',
                    'port' => 11211
                ]
            ],
            default => []
        };
    }
    
    /**
     * Get hooks from Platform.sh configuration
     */
    public function getHooks(): array
    {
        $hooks = [];
        
        // Add composer install if needed
        if (($this->platformApp['build']['flavor'] ?? '') === 'composer') {
            $hooks[] = ['composer' => 'install'];
        }
        
        // Add build hooks
        if (!empty($this->platformApp['hooks']['build'])) {
            $buildCommands = trim($this->platformApp['hooks']['build']);
            $buildCommands = preg_replace('/\n\n+/', "\n", $buildCommands);
            $hooks[] = ['exec' => $buildCommands];
        }
        
        // Add deploy hooks
        if (!empty($this->platformApp['hooks']['deploy'])) {
            $deployCommands = trim($this->platformApp['hooks']['deploy']);
            $deployCommands = preg_replace('/\n\n+/', "\n", $deployCommands);
            $hooks[] = ['exec' => $deployCommands];
        }
        
        // Add post_deploy hooks
        if (!empty($this->platformApp['hooks']['post_deploy'])) {
            $postDeployCommands = trim($this->platformApp['hooks']['post_deploy']);
            $postDeployCommands = preg_replace('/\n\n+/', "\n", $postDeployCommands);
            $hooks[] = ['exec' => $postDeployCommands];
        }
        
        // Add blackfire if needed
        if (in_array('blackfire', $this->platformApp['runtime']['extensions'] ?? [])) {
            $hooks[] = ['exec' => 'phpenmod blackfire'];
        }
        
        return $hooks;
    }
    
    /**
     * Check if application type is supported (PHP only)
     */
    public function validateApplicationType(): bool
    {
        $type = $this->platformApp['type'] ?? '';
        return str_starts_with($type, 'php');
    }
    
    /**
     * Get application type for error messages
     */
    public function getApplicationType(): string
    {
        return $this->platformApp['type'] ?? 'unknown';
    }
    
    /**
     * Validate Platform.sh configuration files exist and are readable
     */
    public function validateConfigurationFiles(): array
    {
        $errors = [];
        
        $appConfigPath = $this->appRoot . '/.platform.app.yaml';
        if (!file_exists($appConfigPath)) {
            $errors[] = "Missing required file: .platform.app.yaml";
        } elseif (!is_readable($appConfigPath)) {
            $errors[] = "Cannot read .platform.app.yaml";
        }
        
        // Services and routes are optional but should be readable if they exist
        $servicesPath = $this->appRoot . '/.platform/services.yaml';
        if (file_exists($servicesPath) && !is_readable($servicesPath)) {
            $errors[] = "Cannot read .platform/services.yaml";
        }
        
        $routesPath = $this->appRoot . '/.platform/routes.yaml';
        if (file_exists($routesPath) && !is_readable($routesPath)) {
            $errors[] = "Cannot read .platform/routes.yaml";
        }
        
        return $errors;
    }
    
    /**
     * Validate parsed YAML structure has required fields
     */
    public function validatePlatformConfiguration(): array
    {
        $errors = [];
        
        if (empty($this->platformApp)) {
            $errors[] = "Platform.sh application configuration is empty or invalid";
            return $errors;
        }
        
        if (!isset($this->platformApp['type'])) {
            $errors[] = "Missing required field: type in .platform.app.yaml";
        }
        
        if (!isset($this->platformApp['name'])) {
            $errors[] = "Missing required field: name in .platform.app.yaml";
        }
        
        // Validate relationships reference existing services
        $relationships = $this->platformApp['relationships'] ?? [];
        foreach ($relationships as $relationshipName => $relationshipDef) {
            $serviceName = explode(':', $relationshipDef)[0];
            if (!empty($this->services) && !isset($this->services[$serviceName])) {
                $errors[] = "Relationship '$relationshipName' references unknown service '$serviceName'";
            }
        }
        
        return $errors;
    }
    
    /**
     * Get database type and version from services for DDEV configuration
     */
    public function getDatabaseConfiguration(): array
    {
        $primaryDb = $this->databaseConfig->getPrimaryDatabase();
        
        return [
            'type' => $primaryDb['ddev_type'],
            'version' => $primaryDb['ddev_version'],
            'service_name' => $primaryDb['service_name'],
            'relationship_name' => $primaryDb['relationship_name']
        ];
    }
    
    /**
     * Get required DDEV add-ons based on Platform.sh services
     */
    public function getRequiredAddons(): array
    {
        $addons = [];
        $supportedServices = [
            'redis' => 'ddev/ddev-redis',
            'redis-persistent' => 'ddev/ddev-redis',
            'memcached' => 'ddev/ddev-memcached',
            'elasticsearch' => 'ddev/ddev-elasticsearch',
            'opensearch' => 'ddev/ddev-elasticsearch'
        ];
        
        $relationships = $this->platformApp['relationships'] ?? [];
        
        foreach ($relationships as $relationshipName => $relationshipDef) {
            $serviceName = explode(':', $relationshipDef)[0];
            $serviceDef = $this->services[$serviceName] ?? null;
            
            if (!$serviceDef) {
                continue;
            }
            
            [$serviceType] = explode(':', $serviceDef['type'] ?? '');
            
            if (isset($supportedServices[$serviceType])) {
                $addons[] = $supportedServices[$serviceType];
            }
        }
        
        return array_unique($addons);
    }
    
    /**
     * Generate complete DDEV configuration array
     */
    public function generateDdevConfiguration(): array
    {
        $ddevDbConfig = $this->databaseConfig->generateDdevDatabaseConfig();
        
        $config = [
            'disable_settings_management' => true,
            'php_version' => $this->getPhpVersion(),
            'composer_version' => $this->getComposerVersion(),
            'database' => $ddevDbConfig,
            'docroot' => $this->getDocumentRoot()
        ];
        
        // Add PHP extensions packages
        $extensions = $this->getPhpExtensions();
        if (!empty($extensions)) {
            $config['webimage_extra_packages'] = array_merge(['figlet'], $extensions);
        }
        
        // Add Python pip if needed
        if (!empty($this->platformApp['dependencies']['python3'])) {
            $config['webimage_extra_packages'][] = 'python3-pip';
        }
        
        // Add environment variables 
        $allRelationships = array_merge(
            $this->databaseConfig->generateDatabaseRelationships(),
            $this->generateServiceRelationships()
        );
        $platformRelationships = base64_encode(json_encode($allRelationships));
        $platformRoutes = base64_encode(json_encode($this->generateRoutes()));
        $platformProjectEntropy = hash('sha256', random_bytes(32));
        
        $webEnvironment = [
            "PLATFORM_RELATIONSHIPS={$platformRelationships}",
            "PLATFORM_APP_DIR=/var/www/html",
            "PLATFORM_PROJECT_ENTROPY={$platformProjectEntropy}",
            "PLATFORM_TREE_ID=2dc356f2fea13ef683f9adc5fc5bd28e05ad992a",
            "PLATFORM_CACHE_DIR=/mnt/ddev-global-cache/ddev-platformsh/{$this->projectName}",
            "PLATFORM_ROUTES={$platformRoutes}",
            "PLATFORM_VARIABLES=e30="
        ];
        
        // Add custom environment variables
        $customEnvVars = $this->getEnvironmentVariables();
        foreach ($customEnvVars as $key => $value) {
            $webEnvironment[] = "{$key}={$value}";
        }
        
        $config['web_environment'] = $webEnvironment;
        
        // Add hooks
        $hooks = $this->getHooks();
        if (!empty($hooks)) {
            $config['hooks'] = [
                'post-start' => array_merge([
                    ['exec' => 'mkdir -p ${PLATFORM_CACHE_DIR} || true'],
                    ['exec' => '[ ! -z "${PLATFORMSH_CLI_TOKEN:-}" ] && (platform ssh-cert:load -y || true)']
                ], $hooks)
            ];
        }
        
        return $config;
    }
    
    /**
     * Get supported DDEV add-ons mapping
     */
    public function getSupportedServices(): array
    {
        return [
            'redis' => 'ddev/ddev-redis',
            'redis-persistent' => 'ddev/ddev-redis',
            'memcached' => 'ddev/ddev-memcached',
            'elasticsearch' => 'ddev/ddev-elasticsearch',
            'opensearch' => 'ddev/ddev-elasticsearch'
        ];
    }
    
    /**
     * Check if a current database version matches the Platform.sh configuration
     */
    public function checkDatabaseCompatibility(?string $currentDbVersion): array
    {
        return $this->databaseConfig->checkDatabaseCompatibility($currentDbVersion);
    }
    
    /**
     * Get configuration summary for reporting and validation
     */
    public function getConfigurationSummary(): array
    {
        return [
            'app_type' => $this->getApplicationType(),
            'app_name' => $this->platformApp['name'] ?? 'unnamed',
            'php_version' => $this->getPhpVersion(),
            'total_services' => count($this->services),
            'total_routes' => count($this->routes),
            'total_relationships' => count($this->platformApp['relationships'] ?? []),
            'build_flavor' => $this->platformApp['build']['flavor'] ?? 'none',
            'has_hooks' => !empty($this->platformApp['hooks']),
            'php_extensions' => $this->getPhpExtensions(),
            'dependencies' => [
                'php' => count($this->platformApp['dependencies']['php'] ?? []),
                'python3' => count($this->platformApp['dependencies']['python3'] ?? [])
            ]
        ];
    }
}