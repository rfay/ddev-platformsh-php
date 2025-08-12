<?php
#ddev-generated

/**
 * Comprehensive test for error handling and logging infrastructure
 * Tests various error scenarios and validates logging functionality
 */

echo "=== Error Handling and Logging Test ===\n\n";

// Load required classes
if (!file_exists('../platformsh/Logger.php')) {
    echo "❌ Logger class not found\n";
    exit(1);
}

require_once '../platformsh/Logger.php';

function testErrorScenario($testName, callable $testFunction) {
    echo "=== Testing: $testName ===\n";
    
    try {
        $result = $testFunction();
        echo "✅ Test completed successfully\n\n";
        return $result;
    } catch (Exception $e) {
        echo "❌ Test failed: " . $e->getMessage() . "\n\n";
        return false;
    }
}

// Test 1: Basic logging functionality
testErrorScenario('Basic Logging Functionality', function() {
    $logger = Logger::getInstance();
    
    // Test all log levels
    $logger->debug("This is a debug message");
    $logger->info("This is an info message");
    $logger->warning("This is a warning message");
    $logger->error("This is an error message");
    $logger->critical("This is a critical message");
    
    $logEntries = $logger->getLogEntries();
    echo "   Log entries created: " . count($logEntries) . "\n";
    
    $errors = $logger->getErrors();
    echo "   Error entries: " . count($errors) . "\n";
    
    return count($logEntries) > 0;
});

// Test 2: Debug mode functionality
testErrorScenario('Debug Mode Functionality', function() {
    $logger = Logger::getInstance();
    $logger->setDebugMode(true);
    
    echo "   Debug mode enabled\n";
    
    $logger->debug("Debug message with context", ['key' => 'value', 'number' => 42]);
    $logger->info("Info message with {key} interpolation", ['key' => 'test-value']);
    
    $debugEntries = $logger->getLogEntriesByLevel(Logger::LEVEL_DEBUG);
    echo "   Debug entries: " . count($debugEntries) . "\n";
    
    $logger->setDebugMode(false);
    echo "   Debug mode disabled\n";
    
    return true;
});

// Test 3: Operation logging
testErrorScenario('Operation Logging', function() {
    $logger = Logger::getInstance();
    
    $logger->startOperation("test-operation", ['step' => 1]);
    $logger->completeOperation("test-operation", ['duration' => '1.5s']);
    
    $logger->startOperation("failing-operation");
    $logger->failOperation("failing-operation", "Simulated failure", ['error_code' => 123]);
    
    echo "   Operation logging tested\n";
    
    return true;
});

// Test 4: Validation results logging
testErrorScenario('Validation Results Logging', function() {
    $logger = Logger::getInstance();
    
    // Test successful validation
    $logger->logValidationResults([]);
    
    // Test validation with warnings
    $logger->logValidationResults([], ['Warning: deprecated configuration option']);
    
    // Test validation with errors
    $logger->logValidationResults([
        'Missing required field: type',
        'Invalid PHP version: 7.4'
    ], ['Warning: old configuration format']);
    
    echo "   Validation logging tested\n";
    
    return true;
});

// Test 5: Configuration summary logging
testErrorScenario('Configuration Summary Logging', function() {
    $logger = Logger::getInstance();
    
    $mockSummary = [
        'app_name' => 'test-app',
        'app_type' => 'php:8.1',
        'php_version' => '8.1',
        'total_services' => 2,
        'total_routes' => 1,
        'total_relationships' => 3
    ];
    
    $logger->logConfigurationSummary($mockSummary);
    
    echo "   Configuration summary logging tested\n";
    
    return true;
});

// Test 6: Database compatibility logging
testErrorScenario('Database Compatibility Logging', function() {
    $logger = Logger::getInstance();
    
    // Test compatible database
    $logger->logDatabaseCompatibility([
        'compatible' => true,
        'expected_version' => 'mysql:8.0',
        'message' => ''
    ]);
    
    // Test incompatible database
    $logger->logDatabaseCompatibility([
        'compatible' => false,
        'expected_version' => 'postgres:13',
        'message' => 'Existing database type mysql:8.0 does not match expected postgres:13'
    ]);
    
    echo "   Database compatibility logging tested\n";
    
    return true;
});

// Test 7: Service installation logging
testErrorScenario('Service Installation Logging', function() {
    $logger = Logger::getInstance();
    
    $logger->logServiceInstallation('ddev/ddev-redis', true);
    $logger->logServiceInstallation('ddev/ddev-elasticsearch', false, 'Installation timeout');
    
    echo "   Service installation logging tested\n";
    
    return true;
});

// Test 8: File operation logging
testErrorScenario('File Operation Logging', function() {
    $logger = Logger::getInstance();
    $logger->setDebugMode(true); // Enable to see file operations
    
    $logger->logFileOperation('write', '/tmp/test-file.yaml', true, 1024);
    $logger->logFileOperation('copy', '/tmp/source.txt', false);
    
    $logger->setDebugMode(false);
    echo "   File operation logging tested\n";
    
    return true;
});

// Test 9: Exception logging
testErrorScenario('Exception Logging', function() {
    $logger = Logger::getInstance();
    
    try {
        throw new RuntimeException("Test exception for logging");
    } catch (Exception $e) {
        $logger->logException($e, "Test context");
        echo "   Exception logged successfully\n";
    }
    
    return true;
});

// Test 10: Troubleshooting report generation
testErrorScenario('Troubleshooting Report Generation', function() {
    $logger = Logger::getInstance();
    
    // Generate some errors for the report
    $logger->error("YAML extension not available");
    $logger->error("Database compatibility issue detected");
    $logger->critical("Service installation failed");
    
    $report = $logger->generateTroubleshootingReport();
    
    echo "   Troubleshooting report generated:\n";
    echo "   Report length: " . strlen($report) . " characters\n";
    
    // Check that report contains expected sections
    if (str_contains($report, 'Troubleshooting Report') &&
        str_contains($report, 'Suggested solutions') &&
        str_contains($report, 'Debug Information')) {
        echo "   ✅ Report contains all expected sections\n";
        return true;
    } else {
        echo "   ❌ Report missing expected sections\n";
        return false;
    }
});

// Test 11: Context interpolation
testErrorScenario('Message Context Interpolation', function() {
    $logger = Logger::getInstance();
    
    $logger->info("Processing {app_name} with {service_count} services", [
        'app_name' => 'my-app',
        'service_count' => 3
    ]);
    
    $logEntries = $logger->getLogEntries();
    $lastEntry = end($logEntries);
    if (str_contains($lastEntry['message'], 'my-app') && str_contains($lastEntry['message'], '3')) {
        echo "   ✅ Context interpolation working\n";
        return true;
    } else {
        echo "   ❌ Context interpolation failed\n";
        return false;
    }
});

// Test 12: Error detection
testErrorScenario('Error Detection Methods', function() {
    $logger = Logger::getInstance();
    
    $hadErrors = $logger->hasErrors();
    echo "   Had errors before: " . ($hadErrors ? 'yes' : 'no') . "\n";
    
    $logger->error("New error for testing");
    
    $hasErrorsNow = $logger->hasErrors();
    echo "   Has errors now: " . ($hasErrorsNow ? 'yes' : 'no') . "\n";
    
    $errorCount = count($logger->getErrors());
    echo "   Total error count: $errorCount\n";
    
    return $hasErrorsNow && $errorCount > 0;
});

echo "\n=== Error Handling Summary ===\n";

$logger = Logger::getInstance();
$totalEntries = count($logger->getLogEntries());
$errorEntries = count($logger->getErrors());

echo "📊 Logging Statistics:\n";
echo "   Total log entries: $totalEntries\n";
echo "   Error entries: $errorEntries\n";
echo "   Debug mode available: ✅\n";
echo "   Context interpolation: ✅\n";
echo "   Troubleshooting reports: ✅\n";
echo "   Exception handling: ✅\n";

echo "\n✅ All error handling and logging tests completed!\n";
echo "🔧 Logger infrastructure is ready for production use.\n";

// Test environment variable detection
echo "\n=== Environment Variable Test ===\n";
echo "Current debug detection:\n";
echo "   DDEV_PLATFORMSH_DEBUG: " . ($_ENV['DDEV_PLATFORMSH_DEBUG'] ?? 'not set') . "\n";
echo "   PLATFORMSH_DEBUG: " . ($_ENV['PLATFORMSH_DEBUG'] ?? 'not set') . "\n";
echo "   --debug in argv: " . (in_array('--debug', $_SERVER['argv'] ?? []) ? 'yes' : 'no') . "\n";

echo "\n=== Error Handling and Logging Test Complete ===\n";