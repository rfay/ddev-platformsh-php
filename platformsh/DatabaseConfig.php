<?php
#ddev-generated

/**
 * Database Configuration Handler for Platform.sh to DDEV Translation
 * 
 * Handles all database-related configuration logic including:
 * - Database service detection and type mapping
 * - Version compatibility checking
 * - DDEV database configuration generation
 * - Multi-database scenario handling
 */
class DatabaseConfig
{
    private array $platformApp;
    private array $services;
    private array $supportedDatabases;
    
    public function __construct(array $platformApp, array $services)
    {
        $this->platformApp = $platformApp;
        $this->services = $services;
        
        // Define supported database types and their DDEV mappings
        $this->supportedDatabases = [
            'mysql' => [
                'ddev_type' => 'mysql',
                'default_version' => '8.0',
                'port' => 3306,
                'scheme' => 'mysql'
            ],
            'mariadb' => [
                'ddev_type' => 'mariadb', 
                'default_version' => '10.4',
                'port' => 3306,
                'scheme' => 'mysql'
            ],
            'oracle-mysql' => [
                'ddev_type' => 'mysql',
                'default_version' => '8.0', 
                'port' => 3306,
                'scheme' => 'mysql'
            ],
            'postgresql' => [
                'ddev_type' => 'postgres',
                'default_version' => '13',
                'port' => 5432,
                'scheme' => 'pgsql'
            ],
            'postgres' => [
                'ddev_type' => 'postgres',
                'default_version' => '13',
                'port' => 5432,
                'scheme' => 'pgsql'
            ]
        ];
    }
    
    /**
     * Get all database services from Platform.sh configuration
     */
    public function getDatabaseServices(): array
    {
        $databases = [];
        $relationships = $this->platformApp['relationships'] ?? [];
        
        foreach ($relationships as $relationshipName => $relationshipDef) {
            $serviceName = explode(':', $relationshipDef)[0];
            $serviceDef = $this->services[$serviceName] ?? null;
            
            if (!$serviceDef) {
                continue;
            }
            
            [$serviceType, $serviceVersion] = $this->parseServiceType($serviceDef['type'] ?? '');
            
            if ($this->isDatabaseService($serviceType)) {
                $databases[] = [
                    'relationship_name' => $relationshipName,
                    'service_name' => $serviceName,
                    'platform_type' => $serviceType,
                    'platform_version' => $serviceVersion,
                    'ddev_type' => $this->mapToDdevType($serviceType),
                    'ddev_version' => $this->mapToDdevVersion($serviceType, $serviceVersion),
                    'port' => $this->getServicePort($serviceType),
                    'scheme' => $this->getServiceScheme($serviceType),
                    'service_definition' => $serviceDef
                ];
            }
        }
        
        // If no databases found, return default MariaDB configuration
        if (empty($databases)) {
            return [[
                'relationship_name' => 'database',
                'service_name' => 'database', 
                'platform_type' => 'mariadb',
                'platform_version' => '10.4',
                'ddev_type' => 'mariadb',
                'ddev_version' => '10.4',
                'port' => 3306,
                'scheme' => 'mysql',
                'service_definition' => ['type' => 'mariadb:10.4']
            ]];
        }
        
        return $databases;
    }
    
    /**
     * Get primary database configuration for DDEV
     */
    public function getPrimaryDatabase(): array
    {
        $databases = $this->getDatabaseServices();
        return $databases[0]; // Return first database as primary
    }
    
    /**
     * Check if current database version matches Platform.sh configuration
     */
    public function checkDatabaseCompatibility(?string $currentDbVersion): array
    {
        $primaryDb = $this->getPrimaryDatabase();
        $expectedVersion = "{$primaryDb['ddev_type']}:{$primaryDb['ddev_version']}";
        
        if (empty($currentDbVersion)) {
            return [
                'compatible' => true,
                'message' => '',
                'expected_version' => $expectedVersion
            ];
        }
        
        // Remove color escape sequences from current version
        $cleanCurrentVersion = preg_replace('/\x1b\[[0-9;]*m?/', '', $currentDbVersion);
        
        if ($cleanCurrentVersion !== $expectedVersion) {
            return [
                'compatible' => false,
                'expected_version' => $expectedVersion,
                'current_version' => $cleanCurrentVersion,
                'message' => "There is an existing database in this project that doesn't match the upstream database type.\n" .
                           "Expected: {$expectedVersion}, Found: {$cleanCurrentVersion}\n" .
                           "Please use 'ddev delete' to delete the existing database and retry, or try " .
                           "'ddev debug migrate-database {$expectedVersion}' to migrate the database."
            ];
        }
        
        return [
            'compatible' => true,
            'message' => '',
            'expected_version' => $expectedVersion,
            'current_version' => $cleanCurrentVersion
        ];
    }
    
    /**
     * Generate DDEV database configuration
     */
    public function generateDdevDatabaseConfig(): array
    {
        $primaryDb = $this->getPrimaryDatabase();
        
        return [
            'type' => $primaryDb['ddev_type'],
            'version' => $primaryDb['ddev_version']
        ];
    }
    
    /**
     * Generate database relationships for PLATFORM_RELATIONSHIPS
     */
    public function generateDatabaseRelationships(): array
    {
        $relationships = [];
        $databases = $this->getDatabaseServices();
        
        foreach ($databases as $db) {
            $relationships[$db['relationship_name']] = [[
                'username' => 'db',
                'scheme' => $db['scheme'],
                'service' => $db['service_name'],
                'fragment' => null,
                'ip' => '255.255.255.255',
                'hostname' => 'db',
                'public' => false,
                'cluster' => 'ddev-dummy-cluster',
                'host' => 'db',
                'rel' => $db['scheme'] === 'pgsql' ? 'pgsql' : 'mysql',
                'query' => [
                    'is_master' => true
                ],
                'path' => 'db',
                'password' => 'db',
                'type' => "{$db['platform_type']}:{$db['platform_version']}",
                'port' => (int)$db['port'],
                'host_mapped' => false
            ]];
        }
        
        return $relationships;
    }
    
    /**
     * Validate database configuration and return any errors
     */
    public function validateDatabaseConfiguration(): array
    {
        $errors = [];
        $databases = $this->getDatabaseServices();
        
        foreach ($databases as $db) {
            // Check if database type is supported
            if (!$this->isDatabaseService($db['platform_type'])) {
                $errors[] = "Unsupported database type: {$db['platform_type']}";
            }
            
            // Check if version is reasonable
            if (!$this->isValidDatabaseVersion($db['platform_type'], $db['platform_version'])) {
                $errors[] = "Potentially unsupported database version: {$db['platform_type']}:{$db['platform_version']}";
            }
            
            // Check service definition
            if (empty($db['service_definition'])) {
                $errors[] = "Database service '{$db['service_name']}' referenced in relationships but not defined in services.yaml";
            }
        }
        
        return $errors;
    }
    
    /**
     * Get database configuration environment variables
     */
    public function getDatabaseEnvironmentVars(): array
    {
        $primaryDb = $this->getPrimaryDatabase();
        
        return [
            'DBTYPE' => $primaryDb['ddev_type'],
            'DBVERSION' => $primaryDb['ddev_version'],
            'DB_PLATFORM_TYPE' => $primaryDb['platform_type'],
            'DB_PLATFORM_VERSION' => $primaryDb['platform_version']
        ];
    }
    
    /**
     * Check if multiple databases are configured
     */
    public function hasMultipleDatabases(): bool
    {
        return count($this->getDatabaseServices()) > 1;
    }
    
    /**
     * Get information about multiple database setup
     */
    public function getMultiDatabaseInfo(): array
    {
        $databases = $this->getDatabaseServices();
        
        if (!$this->hasMultipleDatabases()) {
            return [];
        }
        
        $info = [
            'primary' => $databases[0],
            'additional' => array_slice($databases, 1),
            'total_count' => count($databases),
            'warnings' => []
        ];
        
        // Add warnings about DDEV limitations
        $info['warnings'][] = "DDEV currently supports only one database service. Additional databases will be available through relationships but not as separate DDEV services.";
        
        return $info;
    }
    
    /**
     * Parse service type into type and version components
     */
    private function parseServiceType(string $serviceTypeString): array
    {
        $parts = explode(':', $serviceTypeString);
        $type = $parts[0] ?? '';
        $version = $parts[1] ?? $this->supportedDatabases[$type]['default_version'] ?? 'latest';
        
        return [$type, $version];
    }
    
    /**
     * Check if a service type is a database
     */
    private function isDatabaseService(string $serviceType): bool
    {
        return array_key_exists($serviceType, $this->supportedDatabases);
    }
    
    /**
     * Map Platform.sh database type to DDEV type
     */
    private function mapToDdevType(string $platformType): string
    {
        return $this->supportedDatabases[$platformType]['ddev_type'] ?? 'mariadb';
    }
    
    /**
     * Map Platform.sh database version to DDEV version
     */
    private function mapToDdevVersion(string $platformType, string $platformVersion): string
    {
        // Use the Platform.sh version directly, or fall back to default
        return $platformVersion ?: ($this->supportedDatabases[$platformType]['default_version'] ?? '10.4');
    }
    
    /**
     * Get service port for database type
     */
    private function getServicePort(string $serviceType): int
    {
        return $this->supportedDatabases[$serviceType]['port'] ?? 3306;
    }
    
    /**
     * Get service scheme for database type
     */
    private function getServiceScheme(string $serviceType): string
    {
        return $this->supportedDatabases[$serviceType]['scheme'] ?? 'mysql';
    }
    
    /**
     * Validate database version format
     */
    private function isValidDatabaseVersion(string $type, string $version): bool
    {
        // Basic version format validation
        if (empty($version) || $version === 'latest') {
            return true;
        }
        
        // Check for reasonable version numbers
        switch ($type) {
            case 'mysql':
            case 'oracle-mysql':
                return preg_match('/^[5-9]\.[0-9]$/', $version) === 1;
            case 'mariadb':
                return preg_match('/^10\.[0-9]+$/', $version) === 1;
            case 'postgresql':
            case 'postgres':
                return preg_match('/^1[0-9]$/', $version) === 1 || preg_match('/^[9]\\.[0-9]$/', $version) === 1;
            default:
                return true;
        }
    }
    
    /**
     * Get supported database types
     */
    public function getSupportedDatabaseTypes(): array
    {
        return array_keys($this->supportedDatabases);
    }
    
    /**
     * Get database configuration summary for display
     */
    public function getDatabaseConfigurationSummary(): array
    {
        $databases = $this->getDatabaseServices();
        $primary = $this->getPrimaryDatabase();
        
        return [
            'primary_database' => [
                'type' => $primary['ddev_type'],
                'version' => $primary['ddev_version'],
                'platform_source' => "{$primary['platform_type']}:{$primary['platform_version']}"
            ],
            'total_databases' => count($databases),
            'multiple_databases' => $this->hasMultipleDatabases(),
            'all_databases' => array_map(function($db) {
                return [
                    'name' => $db['relationship_name'],
                    'type' => $db['ddev_type'],
                    'version' => $db['ddev_version'],
                    'platform_source' => "{$db['platform_type']}:{$db['platform_version']}"
                ];
            }, $databases)
        ];
    }
}