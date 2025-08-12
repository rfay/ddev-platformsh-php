<?php
#ddev-generated

/**
 * FileOperations utility class for safe file and directory operations
 * 
 * Replaces bash file operations with robust PHP implementations including:
 * - Safe file writing with atomic operations
 * - Directory creation and management
 * - File copying and backup/restore capabilities
 * - Configuration file generation
 * - Template processing and substitution
 */

class FileOperations 
{
    private static array $backups = [];
    private static bool $dryRun = false;
    
    /**
     * Enable or disable dry run mode (for testing)
     */
    public static function setDryRun(bool $dryRun): void 
    {
        self::$dryRun = $dryRun;
    }
    
    /**
     * Safely write content to a file with atomic operation
     * Uses temporary file + rename to ensure atomicity
     */
    public static function writeFileAtomic(string $filePath, string $content, int $permissions = 0644): bool 
    {
        if (self::$dryRun) {
            echo "[DRY RUN] Would write " . strlen($content) . " bytes to $filePath\n";
            return true;
        }
        
        $directory = dirname($filePath);
        if (!self::ensureDirectoryExists($directory)) {
            throw new RuntimeException("Failed to create directory: $directory");
        }
        
        // Create temporary file in same directory for atomic operation
        $tempFile = $filePath . '.tmp.' . getmypid() . '.' . uniqid();
        
        try {
            $bytesWritten = file_put_contents($tempFile, $content, LOCK_EX);
            if ($bytesWritten === false) {
                throw new RuntimeException("Failed to write to temporary file: $tempFile");
            }
            
            // Set permissions before rename
            if (!chmod($tempFile, $permissions)) {
                throw new RuntimeException("Failed to set permissions on temporary file: $tempFile");
            }
            
            // Atomic rename
            if (!rename($tempFile, $filePath)) {
                throw new RuntimeException("Failed to rename temporary file to final destination: $filePath");
            }
            
            return true;
            
        } catch (Exception $e) {
            // Clean up temporary file on error
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
            throw $e;
        }
    }
    
    /**
     * Write content to file with backup capability
     */
    public static function writeFileWithBackup(string $filePath, string $content, int $permissions = 0644): bool 
    {
        // Create backup if file exists
        if (file_exists($filePath)) {
            $backupPath = self::createBackup($filePath);
            if ($backupPath) {
                self::$backups[$filePath] = $backupPath;
            }
        }
        
        return self::writeFileAtomic($filePath, $content, $permissions);
    }
    
    /**
     * Append content to file safely
     */
    public static function appendToFile(string $filePath, string $content): bool 
    {
        if (self::$dryRun) {
            echo "[DRY RUN] Would append " . strlen($content) . " bytes to $filePath\n";
            return true;
        }
        
        $directory = dirname($filePath);
        if (!self::ensureDirectoryExists($directory)) {
            return false;
        }
        
        return file_put_contents($filePath, $content, FILE_APPEND | LOCK_EX) !== false;
    }
    
    /**
     * Ensure directory exists with proper permissions
     */
    public static function ensureDirectoryExists(string $dirPath, int $permissions = 0755): bool 
    {
        if (self::$dryRun) {
            if (!is_dir($dirPath)) {
                echo "[DRY RUN] Would create directory: $dirPath\n";
            }
            return true;
        }
        
        if (is_dir($dirPath)) {
            return true;
        }
        
        return mkdir($dirPath, $permissions, true);
    }
    
    /**
     * Copy file with error handling
     */
    public static function copyFile(string $source, string $destination): bool 
    {
        if (self::$dryRun) {
            echo "[DRY RUN] Would copy $source to $destination\n";
            return true;
        }
        
        if (!file_exists($source)) {
            throw new RuntimeException("Source file does not exist: $source");
        }
        
        $directory = dirname($destination);
        if (!self::ensureDirectoryExists($directory)) {
            throw new RuntimeException("Failed to create destination directory: $directory");
        }
        
        return copy($source, $destination);
    }
    
    /**
     * Remove file safely
     */
    public static function removeFile(string $filePath): bool 
    {
        if (self::$dryRun) {
            echo "[DRY RUN] Would remove file: $filePath\n";
            return true;
        }
        
        if (!file_exists($filePath)) {
            return true; // Already removed
        }
        
        return unlink($filePath);
    }
    
    /**
     * Create backup of existing file
     */
    public static function createBackup(string $filePath): ?string 
    {
        if (!file_exists($filePath)) {
            return null;
        }
        
        $timestamp = date('Y-m-d_H-i-s');
        $backupPath = $filePath . '.backup.' . $timestamp;
        
        if (self::$dryRun) {
            echo "[DRY RUN] Would create backup: $filePath -> $backupPath\n";
            return $backupPath;
        }
        
        return copy($filePath, $backupPath) ? $backupPath : null;
    }
    
    /**
     * Restore file from backup
     */
    public static function restoreFromBackup(string $filePath): bool 
    {
        if (!isset(self::$backups[$filePath])) {
            return false;
        }
        
        $backupPath = self::$backups[$filePath];
        
        if (self::$dryRun) {
            echo "[DRY RUN] Would restore: $backupPath -> $filePath\n";
            return true;
        }
        
        if (file_exists($backupPath)) {
            $result = copy($backupPath, $filePath);
            if ($result) {
                unset(self::$backups[$filePath]);
                unlink($backupPath); // Clean up backup
            }
            return $result;
        }
        
        return false;
    }
    
    /**
     * Clean up all backup files
     */
    public static function cleanupBackups(): void 
    {
        foreach (self::$backups as $originalFile => $backupPath) {
            if (file_exists($backupPath) && !self::$dryRun) {
                unlink($backupPath);
            }
        }
        self::$backups = [];
    }
    
    /**
     * Generate DDEV configuration file (config.platformsh.yaml)
     */
    public static function generateDdevConfig(array $config): string 
    {
        $yaml = "# #ddev-generated\n";
        $yaml .= "# Generated configuration based on platform.sh project configuration\n";
        
        foreach ($config as $key => $value) {
            $yaml .= self::arrayToYaml([$key => $value], 0);
        }
        
        return $yaml;
    }
    
    /**
     * Convert PHP array to YAML string (simple implementation)
     */
    private static function arrayToYaml(array $array, int $indent = 0): string 
    {
        $yaml = '';
        $indentStr = str_repeat('  ', $indent);
        
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $yaml .= "$indentStr$key:\n";
                if (empty($value)) {
                    $yaml .= $indentStr . "  []\n";
                } else {
                    // Check if this is a list (numeric keys) or map (string keys)
                    $isList = array_keys($value) === range(0, count($value) - 1);
                    
                    if ($isList) {
                        foreach ($value as $item) {
                            if (is_array($item)) {
                                $yaml .= "$indentStr- \n" . self::arrayToYaml($item, $indent + 1);
                            } else {
                                $yaml .= "$indentStr- " . self::escapeYamlValue($item) . "\n";
                            }
                        }
                    } else {
                        $yaml .= self::arrayToYaml($value, $indent + 1);
                    }
                }
            } else {
                $yaml .= "$indentStr$key: " . self::escapeYamlValue($value) . "\n";
            }
        }
        
        return $yaml;
    }
    
    /**
     * Escape YAML values properly
     */
    private static function escapeYamlValue($value): string 
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        
        if (is_null($value)) {
            return 'null';
        }
        
        if (is_numeric($value)) {
            return (string) $value;
        }
        
        $strValue = (string) $value;
        
        // Quote strings that need quoting
        if (preg_match('/[:#@`|>*&!%{}[\],]/', $strValue) || 
            preg_match('/^\s|\s$/', $strValue) ||
            preg_match('/^(true|false|null|yes|no|on|off)$/i', $strValue)) {
            return '"' . str_replace('"', '\"', $strValue) . '"';
        }
        
        return $strValue;
    }
    
    /**
     * Process template string with variable substitution
     * Simple replacement for Go templating functionality
     */
    public static function processTemplate(string $template, array $variables): string 
    {
        $result = $template;
        
        // Replace ${VAR} and {VAR} patterns
        foreach ($variables as $key => $value) {
            $patterns = [
                '$' . $key,
                '${' . $key . '}',
                '{' . $key . '}'
            ];
            
            foreach ($patterns as $pattern) {
                $result = str_replace($pattern, (string) $value, $result);
            }
        }
        
        return $result;
    }
    
    /**
     * Generate environment file (.env) with proper formatting
     */
    public static function generateEnvironmentFile(array $envVars): string 
    {
        $content = "# Generated by ddev-platformsh PHP add-on\n";
        $content .= "# Platform.sh environment variables for DDEV\n\n";
        
        foreach ($envVars as $key => $value) {
            // Escape value for shell safety
            $escapedValue = self::escapeShellValue($value);
            $content .= "$key=$escapedValue\n";
        }
        
        return $content;
    }
    
    /**
     * Escape shell values for environment files
     */
    private static function escapeShellValue(string $value): string 
    {
        // If value contains special characters, quote it
        if (preg_match('/[ \t\n\r\f\v$`"\'\\\\]/', $value)) {
            return '"' . str_replace(['"', '\\', '$', '`'], ['\"', '\\\\', '\\$', '\\`'], $value) . '"';
        }
        
        return $value;
    }
    
    /**
     * Generate Dockerfile content with proper formatting
     */
    public static function generateDockerfileContent(array $packages = [], array $commands = []): string 
    {
        $content = "# Generated by ddev-platformsh PHP add-on\n";
        
        if (!empty($packages)) {
            $content .= "\n# Install additional packages\n";
            foreach ($packages as $package) {
                $content .= "RUN apt-get update && apt-get install -y $package && apt-get clean\n";
            }
        }
        
        if (!empty($commands)) {
            $content .= "\n# Additional setup commands\n";
            foreach ($commands as $command) {
                $content .= "RUN $command\n";
            }
        }
        
        return $content;
    }
    
    /**
     * Validate file path for security (prevent directory traversal)
     */
    public static function validateFilePath(string $filePath): bool 
    {
        // Check for obvious directory traversal patterns in the full path
        if (str_contains($filePath, '../') || str_contains($filePath, '..\\')) {
            return false;
        }
        
        // Ensure path doesn't start with dangerous locations
        $dangerousPaths = ['/etc/', '/usr/bin/', '/root/', '/home/'];
        foreach ($dangerousPaths as $dangerous) {
            if (str_starts_with($filePath, $dangerous)) {
                return false;
            }
        }
        
        // Ensure filename doesn't contain dangerous patterns
        $fileName = basename($filePath);
        if (str_contains($fileName, '..') || 
            str_contains($fileName, '\\')) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Get file operation statistics
     */
    public static function getOperationStats(): array 
    {
        return [
            'active_backups' => count(self::$backups),
            'dry_run_mode' => self::$dryRun,
            'backup_files' => array_keys(self::$backups)
        ];
    }
}