<?php
#ddev-generated

/**
 * Logger class for Platform.sh to DDEV translation
 * 
 * Provides centralized logging infrastructure with multiple levels,
 * debugging modes, and user-friendly error reporting.
 */

class Logger 
{
    const LEVEL_DEBUG = 0;
    const LEVEL_INFO = 1;
    const LEVEL_WARNING = 2;
    const LEVEL_ERROR = 3;
    const LEVEL_CRITICAL = 4;
    
    private static ?Logger $instance = null;
    private int $logLevel = self::LEVEL_INFO;
    private bool $debugMode = false;
    private array $logEntries = [];
    private ?string $logFile = null;
    
    private array $levelNames = [
        self::LEVEL_DEBUG => 'DEBUG',
        self::LEVEL_INFO => 'INFO',
        self::LEVEL_WARNING => 'WARNING', 
        self::LEVEL_ERROR => 'ERROR',
        self::LEVEL_CRITICAL => 'CRITICAL'
    ];
    
    private array $levelEmojis = [
        self::LEVEL_DEBUG => '🐛',
        self::LEVEL_INFO => 'ℹ️',
        self::LEVEL_WARNING => '⚠️',
        self::LEVEL_ERROR => '❌',
        self::LEVEL_CRITICAL => '🚨'
    ];
    
    private function __construct() 
    {
        // Check for debug mode environment variable
        $this->debugMode = !empty($_ENV['DDEV_PLATFORMSH_DEBUG']) || 
                          !empty($_ENV['PLATFORMSH_DEBUG']) ||
                          in_array('--debug', $_SERVER['argv'] ?? []);
        
        if ($this->debugMode) {
            $this->logLevel = self::LEVEL_DEBUG;
        }
        
        // Set up log file if in debug mode
        if ($this->debugMode) {
            $this->logFile = '/tmp/ddev-platformsh-debug-' . date('Y-m-d') . '.log';
        }
    }
    
    public static function getInstance(): Logger 
    {
        if (self::$instance === null) {
            self::$instance = new Logger();
        }
        return self::$instance;
    }
    
    /**
     * Enable or disable debug mode
     */
    public function setDebugMode(bool $enabled): void 
    {
        $this->debugMode = $enabled;
        $this->logLevel = $enabled ? self::LEVEL_DEBUG : self::LEVEL_INFO;
        
        if ($enabled && !$this->logFile) {
            $this->logFile = '/tmp/ddev-platformsh-debug-' . date('Y-m-d') . '.log';
        }
    }
    
    /**
     * Set minimum log level to display
     */
    public function setLogLevel(int $level): void 
    {
        $this->logLevel = $level;
    }
    
    /**
     * Log a debug message
     */
    public function debug(string $message, array $context = []): void 
    {
        $this->log(self::LEVEL_DEBUG, $message, $context);
    }
    
    /**
     * Log an info message
     */
    public function info(string $message, array $context = []): void 
    {
        $this->log(self::LEVEL_INFO, $message, $context);
    }
    
    /**
     * Log a warning message
     */
    public function warning(string $message, array $context = []): void 
    {
        $this->log(self::LEVEL_WARNING, $message, $context);
    }
    
    /**
     * Log an error message
     */
    public function error(string $message, array $context = []): void 
    {
        $this->log(self::LEVEL_ERROR, $message, $context);
    }
    
    /**
     * Log a critical error message
     */
    public function critical(string $message, array $context = []): void 
    {
        $this->log(self::LEVEL_CRITICAL, $message, $context);
    }
    
    /**
     * Main logging method
     */
    private function log(int $level, string $message, array $context = []): void 
    {
        if ($level < $this->logLevel) {
            return;
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $levelName = $this->levelNames[$level];
        $emoji = $this->levelEmojis[$level];
        
        // Format message with context
        $formattedMessage = $this->formatMessage($message, $context);
        
        // Create log entry
        $logEntry = [
            'timestamp' => $timestamp,
            'level' => $level,
            'level_name' => $levelName,
            'message' => $formattedMessage,
            'context' => $context
        ];
        
        $this->logEntries[] = $logEntry;
        
        // Output to console
        $output = $emoji . " " . $formattedMessage;
        
        if ($this->debugMode) {
            $output = "[$timestamp] $levelName: $output";
            
            if (!empty($context)) {
                $output .= " " . json_encode($context, JSON_UNESCAPED_SLASHES);
            }
        }
        
        // Write to appropriate output stream
        if ($level >= self::LEVEL_ERROR) {
            fwrite(STDERR, $output . "\n");
        } else {
            echo $output . "\n";
        }
        
        // Write to log file if in debug mode
        if ($this->logFile && $level >= self::LEVEL_DEBUG) {
            $fileEntry = "[$timestamp] $levelName: $formattedMessage";
            if (!empty($context)) {
                $fileEntry .= " Context: " . json_encode($context, JSON_UNESCAPED_SLASHES);
            }
            file_put_contents($this->logFile, $fileEntry . "\n", FILE_APPEND | LOCK_EX);
        }
    }
    
    /**
     * Format message with context interpolation
     */
    private function formatMessage(string $message, array $context): string 
    {
        // Replace {key} placeholders with context values
        $replacements = [];
        foreach ($context as $key => $value) {
            if (is_string($value) || is_numeric($value)) {
                $replacements['{' . $key . '}'] = $value;
            }
        }
        
        return strtr($message, $replacements);
    }
    
    /**
     * Log an operation start
     */
    public function startOperation(string $operation, array $context = []): void 
    {
        $this->info("🚀 Starting: {operation}", array_merge($context, ['operation' => $operation]));
    }
    
    /**
     * Log an operation completion
     */
    public function completeOperation(string $operation, array $context = []): void 
    {
        $this->info("✅ Completed: {operation}", array_merge($context, ['operation' => $operation]));
    }
    
    /**
     * Log an operation failure
     */
    public function failOperation(string $operation, string $reason, array $context = []): void 
    {
        $this->error("❌ Failed: {operation} - {reason}", array_merge($context, [
            'operation' => $operation,
            'reason' => $reason
        ]));
    }
    
    /**
     * Log configuration validation results
     */
    public function logValidationResults(array $errors, array $warnings = []): void 
    {
        if (empty($errors) && empty($warnings)) {
            $this->info("✅ Configuration validation passed");
            return;
        }
        
        if (!empty($warnings)) {
            foreach ($warnings as $warning) {
                $this->warning($warning);
            }
        }
        
        if (!empty($errors)) {
            $this->error("Configuration validation failed with " . count($errors) . " errors:");
            foreach ($errors as $error) {
                $this->error("  - $error");
            }
        }
    }
    
    /**
     * Log Platform.sh configuration summary
     */
    public function logConfigurationSummary(array $summary): void 
    {
        $this->info("📊 Platform.sh Configuration Summary:");
        $this->info("   App: {$summary['app_name']} ({$summary['app_type']})");
        $this->info("   PHP: {$summary['php_version']}");
        $this->info("   Services: {$summary['total_services']}");
        $this->info("   Routes: {$summary['total_routes']}");
        $this->info("   Relationships: {$summary['total_relationships']}");
        
        if ($this->debugMode) {
            $this->debug("Full configuration summary", $summary);
        }
    }
    
    /**
     * Log database compatibility check
     */
    public function logDatabaseCompatibility(array $compatibility): void 
    {
        if ($compatibility['compatible']) {
            if (!empty($compatibility['expected_version'])) {
                $this->info("✅ Database compatible: {$compatibility['expected_version']}");
            } else {
                $this->info("✅ Database compatibility check passed");
            }
        } else {
            $this->error("❌ Database compatibility issue:");
            $this->error("   {$compatibility['message']}");
            if (!empty($compatibility['expected_version'])) {
                $this->info("💡 Suggestion: Use 'ddev debug migrate-database {$compatibility['expected_version']}'");
            }
        }
    }
    
    /**
     * Log service installation
     */
    public function logServiceInstallation(string $service, bool $success, string $output = ''): void 
    {
        if ($success) {
            $this->info("✅ Service installed: {service}", ['service' => $service]);
        } else {
            $this->error("❌ Service installation failed: {service}", ['service' => $service]);
            if ($output) {
                $this->debug("Installation output: $output");
            }
        }
    }
    
    /**
     * Log file operations
     */
    public function logFileOperation(string $operation, string $file, bool $success, int $size = null): void 
    {
        if ($success) {
            $sizeInfo = $size ? " ({$size} bytes)" : "";
            $this->debug("✅ {operation}: {file}{$sizeInfo}", [
                'operation' => $operation,
                'file' => $file,
                'size' => $size
            ]);
        } else {
            $this->error("❌ {operation} failed: {file}", [
                'operation' => $operation,
                'file' => $file
            ]);
        }
    }
    
    /**
     * Log exception with full details
     */
    public function logException(Exception $e, string $context = ''): void 
    {
        $contextPrefix = $context ? "$context: " : "";
        
        $this->critical("{$contextPrefix}Exception: " . $e->getMessage());
        $this->error("   File: " . $e->getFile() . ":" . $e->getLine());
        
        if ($this->debugMode) {
            $this->debug("   Stack trace:\n" . $e->getTraceAsString());
        }
    }
    
    /**
     * Get all log entries
     */
    public function getLogEntries(): array 
    {
        return $this->logEntries;
    }
    
    /**
     * Get log entries by level
     */
    public function getLogEntriesByLevel(int $level): array 
    {
        return array_filter($this->logEntries, fn($entry) => $entry['level'] === $level);
    }
    
    /**
     * Get error and critical log entries
     */
    public function getErrors(): array 
    {
        return array_filter($this->logEntries, fn($entry) => $entry['level'] >= self::LEVEL_ERROR);
    }
    
    /**
     * Check if there are any errors logged
     */
    public function hasErrors(): bool 
    {
        return !empty($this->getErrors());
    }
    
    /**
     * Generate troubleshooting report
     */
    public function generateTroubleshootingReport(): string 
    {
        $errors = $this->getErrors();
        
        if (empty($errors)) {
            return "✅ No errors detected in the translation process.\n";
        }
        
        $report = "🔍 Troubleshooting Report\n";
        $report .= "========================\n\n";
        
        $report .= "Errors encountered during Platform.sh to DDEV translation:\n\n";
        
        foreach ($errors as $i => $error) {
            $report .= ($i + 1) . ". [{$error['level_name']}] {$error['message']}\n";
            
            // Add common solutions based on error patterns
            $solutions = $this->getSuggestedSolutions($error['message']);
            if (!empty($solutions)) {
                $report .= "   💡 Suggested solutions:\n";
                foreach ($solutions as $solution) {
                    $report .= "      - $solution\n";
                }
            }
            $report .= "\n";
        }
        
        $report .= "Debug Information:\n";
        $report .= "- Debug mode: " . ($this->debugMode ? 'enabled' : 'disabled') . "\n";
        $report .= "- Log level: " . $this->levelNames[$this->logLevel] . "\n";
        $report .= "- Total log entries: " . count($this->logEntries) . "\n";
        
        if ($this->logFile) {
            $report .= "- Debug log file: {$this->logFile}\n";
        }
        
        return $report;
    }
    
    /**
     * Get suggested solutions for common error patterns
     */
    private function getSuggestedSolutions(string $errorMessage): array 
    {
        $solutions = [];
        
        if (str_contains($errorMessage, 'yaml extension')) {
            $solutions[] = 'Ensure PHP YAML extension is installed in DDEV container';
            $solutions[] = 'Check that ddev/ddev-webserver image includes php-yaml';
        }
        
        if (str_contains($errorMessage, 'configuration file')) {
            $solutions[] = 'Verify .platform.app.yaml exists in project root';
            $solutions[] = 'Check .platform/services.yaml and .platform/routes.yaml files';
            $solutions[] = 'Ensure YAML files have valid syntax';
        }
        
        if (str_contains($errorMessage, 'database')) {
            $solutions[] = 'Use "ddev delete" to remove existing database';
            $solutions[] = 'Try "ddev debug migrate-database" to migrate database';
            $solutions[] = 'Check Platform.sh database configuration';
        }
        
        if (str_contains($errorMessage, 'service')) {
            $solutions[] = 'Verify service is supported (redis, elasticsearch, memcached)';
            $solutions[] = 'Check service version compatibility';
            $solutions[] = 'Ensure DDEV add-on installation succeeded';
        }
        
        if (str_contains($errorMessage, 'permission') || str_contains($errorMessage, 'write')) {
            $solutions[] = 'Check file permissions in .ddev directory';
            $solutions[] = 'Ensure DDEV has write access to project directory';
        }
        
        return $solutions;
    }
}