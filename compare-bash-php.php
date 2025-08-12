<?php
#ddev-generated

/**
 * Compare bash script output with PHP script output to ensure functional equivalence
 */

echo "=== Bash vs PHP Output Comparison ===\n\n";

function runBashScript($script, $args) {
    $command = "bash $script " . implode(' ', array_map('escapeshellarg', $args));
    $output = shell_exec($command);
    return trim($output);
}

function runPhpScript($script, $args) {
    $command = "php $script " . implode(' ', array_map('escapeshellarg', $args));
    $output = shell_exec($command);
    return trim($output);
}

function compareJsonOutput($bashOutput, $phpOutput, $testName) {
    echo "=== $testName ===\n";
    
    // Parse JSON outputs
    $bashJson = json_decode($bashOutput, true);
    $phpJson = json_decode($phpOutput, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "❌ JSON parsing error in bash output: " . json_last_error_msg() . "\n";
        echo "Bash output: $bashOutput\n";
        return false;
    }
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "❌ JSON parsing error in PHP output: " . json_last_error_msg() . "\n";
        echo "PHP output: $phpOutput\n";
        return false;
    }
    
    // Compare structures
    if ($bashJson === $phpJson) {
        echo "✅ Outputs are identical\n";
        return true;
    } else {
        echo "⚠️  Outputs differ\n";
        echo "Bash output:\n" . json_encode($bashJson, JSON_PRETTY_PRINT) . "\n";
        echo "PHP output:\n" . json_encode($phpJson, JSON_PRETTY_PRINT) . "\n";
        
        // Find differences
        $bashKeys = array_keys($bashJson[0] ?? []);
        $phpKeys = array_keys($phpJson[0] ?? []);
        
        $missingInPhp = array_diff($bashKeys, $phpKeys);
        $extraInPhp = array_diff($phpKeys, $bashKeys);
        
        if (!empty($missingInPhp)) {
            echo "Missing in PHP: " . implode(', ', $missingInPhp) . "\n";
        }
        if (!empty($extraInPhp)) {
            echo "Extra in PHP: " . implode(', ', $extraInPhp) . "\n";
        }
        
        return false;
    }
}

// Test 1: Database relationship - MySQL
echo "Testing database relationships...\n\n";
$bashDb = runBashScript('platformsh/generate_db_relationship.sh', ['db', 'mariadb:10.4', 'database']);
$phpDb = runPhpScript('platformsh/generate_db_relationship.php', ['db', 'mariadb:10.4', 'database']);
compareJsonOutput($bashDb, $phpDb, 'Database Relationship (MariaDB 10.4)');

// Test 2: Database relationship - PostgreSQL
$bashPg = runBashScript('platformsh/generate_db_relationship.sh', ['pgdb', 'postgres:13', 'database']);
$phpPg = runPhpScript('platformsh/generate_db_relationship.php', ['pgdb', 'postgres:13', 'database']);
compareJsonOutput($bashPg, $phpPg, 'Database Relationship (PostgreSQL 13)');

// Test 3: Redis relationship
$bashRedis = runBashScript('platformsh/generate_redis_relationship.sh', ['cache']);
$phpRedis = runPhpScript('platformsh/generate_redis_relationship.php', ['cache']);
compareJsonOutput($bashRedis, $phpRedis, 'Redis Relationship');

// Test 4: Elasticsearch relationship
$bashEs = runBashScript('platformsh/generate_elasticsearch_relationship.sh', ['search']);
$phpEs = runPhpScript('platformsh/generate_elasticsearch_relationship.php', ['search']);
compareJsonOutput($bashEs, $phpEs, 'Elasticsearch Relationship');

// Test 5: Memcached relationship
$bashMc = runBashScript('platformsh/generate_memcached_relationship.sh', ['cache']);
$phpMc = runPhpScript('platformsh/generate_memcached_relationship.php', ['cache']);
compareJsonOutput($bashMc, $phpMc, 'Memcached Relationship');

// Test 6: Route generation
$bashRoute = runBashScript('platformsh/generate_route.sh', [
    'https://{default}/', 
    'main', 
    'https://main-project.platform.sh/', 
    'app:http', 
    'upstream', 
    'https://{default}/'
]);
$phpRoute = runPhpScript('platformsh/generate_route.php', [
    'https://{default}/', 
    'main', 
    'https://main-project.platform.sh/', 
    'app:http', 
    'upstream', 
    'https://{default}/'
]);

// Route outputs are objects, not arrays, so we need to wrap them
$bashRouteWrapped = '[' . $bashRoute . ']';
$phpRouteWrapped = '[' . $phpRoute . ']';
compareJsonOutput($bashRouteWrapped, $phpRouteWrapped, 'Route Generation');

// Test 7: Test with edge cases
echo "\n=== Edge Case Tests ===\n\n";

// Empty/null ID in route
$bashRouteNoId = runBashScript('platformsh/generate_route.sh', [
    'https://example.com/', 
    '', 
    'https://prod.example.com/', 
    'app:http', 
    'upstream', 
    'https://example.com/'
]);
$phpRouteNoId = runPhpScript('platformsh/generate_route.php', [
    'https://example.com/', 
    '', 
    'https://prod.example.com/', 
    'app:http', 
    'upstream', 
    'https://example.com/'
]);

$bashRouteNoIdWrapped = '[' . $bashRouteNoId . ']';
$phpRouteNoIdWrapped = '[' . $phpRouteNoId . ']';
compareJsonOutput($bashRouteNoIdWrapped, $phpRouteNoIdWrapped, 'Route Generation (No ID)');

// Test 8: Unsupported database type (should fail)
echo "\n=== Error Handling Tests ===\n\n";
$bashUnsupported = shell_exec('bash platformsh/generate_db_relationship.sh db unsupported:1.0 database 2>&1');
$phpUnsupported = shell_exec('php platformsh/generate_db_relationship.php db unsupported:1.0 database 2>&1');

echo "=== Unsupported Database Type ===\n";
if (strpos($bashUnsupported, 'no recognized dbtype') !== false && 
    strpos($phpUnsupported, 'no recognized dbtype') !== false) {
    echo "✅ Both bash and PHP properly handle unsupported database types\n";
} else {
    echo "⚠️  Error handling differs between bash and PHP\n";
    echo "Bash error: $bashUnsupported\n";
    echo "PHP error: $phpUnsupported\n";
}

echo "\n=== Summary ===\n";
echo "Comparison tests completed. Check outputs above for any differences.\n";
echo "All scripts should produce functionally equivalent JSON structures.\n";