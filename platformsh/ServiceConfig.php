<?php
#ddev-generated

/**
 * ServiceConfig class for handling Platform.sh services (Redis, Elasticsearch, etc.)
 * 
 * This class converts Platform.sh service configurations to DDEV service configurations,
 * generates service relationships, and manages DDEV add-on requirements.
 */

class ServiceConfig 
{
    private array $platformApp;
    private array $services;
    private array $supportedServices;
    
    public function __construct(array $platformApp, array $services) 
    {
        $this->platformApp = $platformApp;
        $this->services = $services;
        
        // Define supported Platform.sh services and their DDEV add-on mappings
        $this->supportedServices = [
            'redis' => [
                'addon' => 'ddev/ddev-redis',
                'ports' => ['redis' => 6379],
                'scheme' => 'redis'
            ],
            'redis-persistent' => [
                'addon' => 'ddev/ddev-redis', 
                'ports' => ['redis' => 6379],
                'scheme' => 'redis'
            ],
            'memcached' => [
                'addon' => 'ddev/ddev-memcached',
                'ports' => ['memcached' => 11211],
                'scheme' => 'memcached'  
            ],
            'elasticsearch' => [
                'addon' => 'ddev/ddev-elasticsearch',
                'ports' => ['http' => 9200],
                'scheme' => 'http'
            ],
            'opensearch' => [
                'addon' => 'ddev/ddev-elasticsearch',
                'ports' => ['http' => 9200], 
                'scheme' => 'http'
            ]
        ];
    }
    
    /**
     * Get all non-database services from Platform.sh configuration
     */
    public function getNonDatabaseServices(): array
    {
        $nonDbServices = [];
        $relationships = $this->platformApp['relationships'] ?? [];
        
        foreach ($relationships as $relationshipName => $relationshipDef) {
            $serviceName = explode(':', $relationshipDef)[0];
            
            if (!isset($this->services[$serviceName])) {
                continue;
            }
            
            $serviceConfig = $this->services[$serviceName];
            $serviceType = explode(':', $serviceConfig['type'])[0];
            
            // Skip database services - they're handled by DatabaseConfig
            if (in_array($serviceType, ['mysql', 'mariadb', 'oracle-mysql', 'postgresql'])) {
                continue;
            }
            
            $nonDbServices[$relationshipName] = [
                'name' => $serviceName,
                'relationship_name' => $relationshipName,
                'type' => $serviceType,
                'version' => explode(':', $serviceConfig['type'])[1] ?? 'latest',
                'platform_config' => $serviceConfig,
                'supported' => isset($this->supportedServices[$serviceType])
            ];
        }
        
        return $nonDbServices;
    }
    
    /**
     * Get required DDEV add-ons for all services
     */
    public function getRequiredDdevAddons(): array
    {
        $addons = [];
        $services = $this->getNonDatabaseServices();
        
        foreach ($services as $service) {
            if ($service['supported'] && isset($this->supportedServices[$service['type']]['addon'])) {
                $addon = $this->supportedServices[$service['type']]['addon'];
                if (!in_array($addon, $addons)) {
                    $addons[] = $addon;
                }
            }
        }
        
        return $addons;
    }
    
    /**
     * Generate service relationships for Platform.sh environment variables
     */
    public function generateServiceRelationships(): array
    {
        $relationships = [];
        $services = $this->getNonDatabaseServices();
        
        foreach ($services as $relationshipName => $service) {
            if (!$service['supported']) {
                continue;
            }
            
            $serviceType = $service['type'];
            $serviceConfig = $this->supportedServices[$serviceType];
            
            // Generate relationship data structure matching bash script output
            $relationshipData = [];
            
            foreach ($serviceConfig['ports'] as $portName => $portNumber) {
                $relationshipEntry = [
                    'scheme' => $serviceConfig['scheme'],
                    'username' => '',
                    'password' => '', 
                    'host' => $service['name'],
                    'port' => $portNumber,
                    'path' => '',
                    'query' => [],
                    'fragment' => null,
                    'public' => false,
                    'service' => $service['name'],
                    'ip' => '127.0.0.1',  // DDEV internal
                    'hostname' => $service['name'] . '.ddev.site',
                    'cluster' => 'project-' . ($_ENV['DDEV_PROJECT'] ?? 'default') . '-' . $service['name'],
                    'type' => $service['type'] . ':' . $service['version']
                ];
                
                $relationshipData[] = $relationshipEntry;
            }
            
            $relationships[$relationshipName] = $relationshipData;
        }
        
        return $relationships;
    }
    
    /**
     * Get service configuration warnings and errors
     */
    public function validateServiceConfiguration(): array
    {
        $errors = [];
        $services = $this->getNonDatabaseServices();
        
        foreach ($services as $relationshipName => $service) {
            if (!$service['supported']) {
                $errors[] = "Unsupported service type '{$service['type']}' for relationship '{$relationshipName}'. " .
                           "Supported types: " . implode(', ', array_keys($this->supportedServices));
            }
        }
        
        return $errors;
    }
    
    /**
     * Get summary of service configuration
     */
    public function getServiceConfigurationSummary(): array
    {
        $services = $this->getNonDatabaseServices();
        $supported = array_filter($services, fn($s) => $s['supported']);
        $unsupported = array_filter($services, fn($s) => !$s['supported']);
        
        return [
            'total_services' => count($services),
            'supported_services' => count($supported),
            'unsupported_services' => count($unsupported),
            'required_addons' => $this->getRequiredDdevAddons(),
            'service_types' => array_unique(array_column($services, 'type')),
            'services' => $services
        ];
    }
    
    /**
     * Check if service type is supported
     */
    public function isServiceSupported(string $serviceType): bool
    {
        return isset($this->supportedServices[$serviceType]);
    }
    
    /**
     * Get service configuration for a specific service type
     */
    public function getServiceTypeConfig(string $serviceType): ?array
    {
        return $this->supportedServices[$serviceType] ?? null;
    }
    
    /**
     * Generate DDEV service environment variables
     */
    public function getServiceEnvironmentVars(): array
    {
        $envVars = [];
        $services = $this->getNonDatabaseServices();
        
        foreach ($services as $relationshipName => $service) {
            if (!$service['supported']) {
                continue;
            }
            
            $serviceType = $service['type'];
            $serviceName = $service['name'];
            
            // Add service-specific environment variables
            $envVars["PLATFORM_SERVICE_{$relationshipName}_HOST"] = $serviceName;
            $envVars["PLATFORM_SERVICE_{$relationshipName}_TYPE"] = $service['type'] . ':' . $service['version'];
            
            // Add port information
            $serviceConfig = $this->supportedServices[$serviceType];
            foreach ($serviceConfig['ports'] as $portName => $portNumber) {
                $envVars["PLATFORM_SERVICE_{$relationshipName}_PORT"] = (string)$portNumber;
                break; // Use primary port
            }
        }
        
        return $envVars;
    }
}