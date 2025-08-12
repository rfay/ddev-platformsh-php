<?php
#ddev-generated

/**
 * EnvironmentConfig class for handling Platform.sh environment variables
 * 
 * This class generates Platform.sh-compatible environment variables for DDEV,
 * including PLATFORM_RELATIONSHIPS, PLATFORM_ROUTES, and other standard variables.
 */

class EnvironmentConfig 
{
    private array $platformApp;
    private array $services;
    private array $routes;
    private DatabaseConfig $databaseConfig;
    private ServiceConfig $serviceConfig;
    
    public function __construct(array $platformApp, array $services, array $routes = []) 
    {
        $this->platformApp = $platformApp;
        $this->services = $services;
        $this->routes = $routes;
        $this->databaseConfig = new DatabaseConfig($platformApp, $services);
        $this->serviceConfig = new ServiceConfig($platformApp, $services);
    }
    
    /**
     * Generate PLATFORM_RELATIONSHIPS environment variable
     * This combines database and service relationships into base64-encoded JSON
     */
    public function generatePlatformRelationships(): string 
    {
        $allRelationships = [];
        
        // Add database relationships
        $dbRelationships = $this->databaseConfig->generateDatabaseRelationships();
        $allRelationships = array_merge($allRelationships, $dbRelationships);
        
        // Add service relationships  
        $serviceRelationships = $this->serviceConfig->generateServiceRelationships();
        $allRelationships = array_merge($allRelationships, $serviceRelationships);
        
        // Convert to JSON and base64 encode
        $json = json_encode($allRelationships, JSON_UNESCAPED_SLASHES);
        return base64_encode($json);
    }
    
    /**
     * Generate PLATFORM_ROUTES environment variable
     * This processes route configurations into base64-encoded JSON
     */
    public function generatePlatformRoutes(): string 
    {
        $routeRelationships = [];
        $ddevPrimaryUrl = $_ENV['DDEV_PRIMARY_URL'] ?? 'https://example.ddev.site';
        
        foreach ($this->routes as $routeKey => $routeConfig) {
            // Generate route relationship using existing PHP generator
            $routeId = $routeConfig['id'] ?? '';
            $productionUrl = $routeConfig['production_url'] ?? '';
            $upstream = $routeConfig['upstream'] ?? '';
            $type = $routeConfig['type'] ?? '';
            $originalUrl = $routeConfig['original_url'] ?? $routeKey;
            
            // This would call the PHP equivalent of generate_route.sh
            $routeData = $this->generateRouteRelationship(
                $ddevPrimaryUrl . '/',
                $routeId,
                $productionUrl,
                $upstream, 
                $type,
                $originalUrl
            );
            
            if ($routeData) {
                $routeRelationships[] = $routeData;
            }
        }
        
        // Convert routes array to JSON object and base64 encode
        $routesObject = new stdClass();
        foreach ($routeRelationships as $index => $route) {
            $routesObject->{$route['original_url'] ?? "route_$index"} = $route;
        }
        
        $json = json_encode($routesObject, JSON_UNESCAPED_SLASHES);
        return base64_encode($json);
    }
    
    /**
     * Generate route relationship data structure
     * Uses existing generate_route.php script
     */
    private function generateRouteRelationship(string $ddevUrl, string $id, string $productionUrl, string $upstream, string $type, string $originalUrl): ?array 
    {
        $scriptPath = __DIR__ . '/generate_route.php';
        if (!file_exists($scriptPath)) {
            return null;
        }
        
        // Call the existing PHP generator
        $command = sprintf(
            'php %s %s %s %s %s %s %s 2>/dev/null',
            escapeshellarg($scriptPath),
            escapeshellarg($ddevUrl),
            escapeshellarg($id),
            escapeshellarg($productionUrl),
            escapeshellarg($upstream),
            escapeshellarg($type),
            escapeshellarg($originalUrl)
        );
        
        $output = shell_exec($command);
        if ($output) {
            $routeData = json_decode($output, true);
            return $routeData ?: null;
        }
        
        return null;
    }
    
    /**
     * Generate PLATFORM_PROJECT_ENTROPY 
     * Creates a random hash for security purposes
     */
    public function generatePlatformProjectEntropy(): string 
    {
        // Generate random hash similar to bash: echo $RANDOM | shasum -a 256
        $randomValue = random_int(0, 32767); // $RANDOM equivalent
        return hash('sha256', (string)$randomValue);
    }
    
    /**
     * Generate all Platform.sh environment variables for DDEV config
     */
    public function generateAllPlatformEnvironmentVariables(): array 
    {
        $ddevProject = $_ENV['DDEV_PROJECT'] ?? 'default-project';
        $platformRelationships = $this->generatePlatformRelationships();
        $platformRoutes = $this->generatePlatformRoutes();
        $platformProjectEntropy = $this->generatePlatformProjectEntropy();
        
        $envVars = [
            "PLATFORM_RELATIONSHIPS={$platformRelationships}",
            "PLATFORM_APP_DIR=/var/www/html",
            "PLATFORM_PROJECT_ENTROPY={$platformProjectEntropy}",
            "PLATFORM_TREE_ID=2dc356f2fea13ef683f9adc5fc5bd28e05ad992a", // Consider using git commit hash
            "PLATFORM_CACHE_DIR=/mnt/ddev-global-cache/ddev-platformsh/{$ddevProject}",
            "PLATFORM_ROUTES={$platformRoutes}",
            "PLATFORM_VARIABLES=e30=" // Base64 encoded empty JSON object {}
        ];
        
        // Add custom environment variables from platform app configuration
        if (isset($this->platformApp['variables']['env'])) {
            foreach ($this->platformApp['variables']['env'] as $key => $value) {
                $envVars[] = "{$key}={$value}";
            }
        }
        
        return $envVars;
    }
    
    /**
     * Parse existing PLATFORM_RELATIONSHIPS environment variable
     * Useful for validating or debugging relationship configurations
     */
    public function parsePlatformRelationships(?string $platformRelationships = null): array 
    {
        if ($platformRelationships === null) {
            $platformRelationships = $_ENV['PLATFORM_RELATIONSHIPS'] ?? '';
        }
        
        if (empty($platformRelationships)) {
            return [];
        }
        
        $decodedJson = base64_decode($platformRelationships);
        if (!$decodedJson) {
            return [];
        }
        
        $relationships = json_decode($decodedJson, true);
        return is_array($relationships) ? $relationships : [];
    }
    
    /**
     * Generate environment variables for database connections
     * These are used by applications to connect to services
     */
    public function generateDatabaseEnvironmentVariables(): array 
    {
        return $this->databaseConfig->getDatabaseEnvironmentVars();
    }
    
    /**
     * Generate environment variables for service connections
     * These are used by applications to connect to services like Redis, Elasticsearch
     */
    public function generateServiceEnvironmentVariables(): array 
    {
        return $this->serviceConfig->getServiceEnvironmentVars();
    }
    
    /**
     * Generate DDEV hooks configuration
     * Converts Platform.sh hooks to DDEV-compatible format
     */
    public function generateDdevHooksConfig(): array 
    {
        $hooks = [
            'post-start' => []
        ];
        
        // Standard Platform.sh setup hooks
        $hooks['post-start'][] = [
            'exec' => 'mkdir -p ${PLATFORM_CACHE_DIR} || true'
        ];
        
        $hooks['post-start'][] = [
            'exec' => '[ ! -z "${PLATFORMSH_CLI_TOKEN:-}" ] && (platform ssh-cert:load -y || true)'
        ];
        
        // Add composer install if needed
        $buildFlavor = $this->platformApp['build']['flavor'] ?? '';
        if ($buildFlavor === 'composer') {
            $hooks['post-start'][] = [
                'composer' => 'install'
            ];
        }
        
        // Convert Platform.sh build hooks
        if (!empty($this->platformApp['hooks']['build'])) {
            $buildCommands = $this->cleanHookCommands($this->platformApp['hooks']['build']);
            $hooks['post-start'][] = [
                'exec' => $buildCommands
            ];
        }
        
        // Convert Platform.sh deploy hooks
        if (!empty($this->platformApp['hooks']['deploy'])) {
            $deployCommands = $this->cleanHookCommands($this->platformApp['hooks']['deploy']);
            $hooks['post-start'][] = [
                'exec' => $deployCommands
            ];
        }
        
        // Convert Platform.sh post_deploy hooks
        if (!empty($this->platformApp['hooks']['post_deploy'])) {
            $postDeployCommands = $this->cleanHookCommands($this->platformApp['hooks']['post_deploy']);
            $hooks['post-start'][] = [
                'exec' => $postDeployCommands
            ];
        }
        
        // Enable blackfire if needed
        $extensions = $this->platformApp['runtime']['extensions'] ?? [];
        if (in_array('blackfire', $extensions)) {
            $hooks['post-start'][] = [
                'exec' => 'phpenmod blackfire'
            ];
        }
        
        return $hooks;
    }
    
    /**
     * Clean hook commands by removing extra newlines
     * PHP equivalent of Go template regexReplaceAll "\n\n*" with "\n"
     */
    private function cleanHookCommands(string $commands): string 
    {
        // Remove multiple consecutive newlines, keeping single ones
        return preg_replace('/\n+/', "\n", trim($commands));
    }
    
    /**
     * Get Platform.sh project configuration summary for environment setup
     */
    public function getEnvironmentConfigurationSummary(): array 
    {
        $relationships = $this->parsePlatformRelationships($this->generatePlatformRelationships());
        $dbSummary = $this->databaseConfig->getDatabaseConfigurationSummary();
        $serviceSummary = $this->serviceConfig->getServiceConfigurationSummary();
        
        return [
            'total_relationships' => count($relationships),
            'database_relationships' => $dbSummary['total_databases'],
            'service_relationships' => $serviceSummary['total_services'],
            'custom_env_vars' => count($this->platformApp['variables']['env'] ?? []),
            'has_build_hooks' => !empty($this->platformApp['hooks']['build']),
            'has_deploy_hooks' => !empty($this->platformapp['hooks']['deploy']),
            'has_post_deploy_hooks' => !empty($this->platformApp['hooks']['post_deploy']),
            'php_extensions' => $this->platformApp['runtime']['extensions'] ?? [],
            'build_flavor' => $this->platformApp['build']['flavor'] ?? 'none'
        ];
    }
    
    /**
     * Validate environment configuration for common issues
     */
    public function validateEnvironmentConfiguration(): array 
    {
        $errors = [];
        
        // Validate relationships can be generated
        try {
            $this->generatePlatformRelationships();
        } catch (Exception $e) {
            $errors[] = "Failed to generate PLATFORM_RELATIONSHIPS: " . $e->getMessage();
        }
        
        // Validate routes can be generated
        try {
            $this->generatePlatformRoutes();
        } catch (Exception $e) {
            $errors[] = "Failed to generate PLATFORM_ROUTES: " . $e->getMessage();
        }
        
        // Check for required environment variables in hooks
        $allHooks = [
            $this->platformApp['hooks']['build'] ?? '',
            $this->platformApp['hooks']['deploy'] ?? '',
            $this->platformApp['hooks']['post_deploy'] ?? ''
        ];
        
        foreach ($allHooks as $hookContent) {
            if (str_contains($hookContent, '${') && !str_contains($hookContent, 'PLATFORM_')) {
                $errors[] = "Hook contains environment variable that may not be available in DDEV context";
            }
        }
        
        return $errors;
    }
}