<?php
/**
 * Test script for rate limiting functionality
 * Run this script from command line: php test_rate_limit.php
 */

require_once __DIR__ . '/RateLimiter.php';

echo "Testing Rate Limiter\n";
echo "====================\n\n";

// Test 1: Basic rate limiting
echo "Test 1: Basic rate limiting (5 requests per 10 seconds)\n";
$rateLimiter = new RateLimiter(__DIR__ . '/test_rate_limit_data', 5, 10);

$testIp = '192.168.1.100';
for ($i = 1; $i <= 7; $i++) {
    $result = $rateLimiter->check($testIp, 'test_endpoint', 5, 10);
    echo "Request $i: " . ($result['allowed'] ? 'ALLOWED' : 'BLOCKED') . 
         " | Remaining: {$result['remaining']}" .
         " | Reset in: " . max(0, $result['reset'] - time()) . "s\n";
}

echo "\n";

// Test 2: Different endpoints have separate limits
echo "Test 2: Different endpoints have separate limits\n";
$result1 = $rateLimiter->check($testIp, 'endpoint1', 3, 10);
$result2 = $rateLimiter->check($testIp, 'endpoint2', 3, 10);
echo "Endpoint 1: " . ($result1['allowed'] ? 'ALLOWED' : 'BLOCKED') . " | Remaining: {$result1['remaining']}\n";
echo "Endpoint 2: " . ($result2['allowed'] ? 'ALLOWED' : 'BLOCKED') . " | Remaining: {$result2['remaining']}\n";

echo "\n";

// Test 3: Different IPs have separate limits
echo "Test 3: Different IPs have separate limits\n";
$ip1 = '192.168.1.101';
$ip2 = '192.168.1.102';
$result1 = $rateLimiter->check($ip1, 'shared_endpoint', 2, 10);
$result2 = $rateLimiter->check($ip2, 'shared_endpoint', 2, 10);
echo "IP 1: " . ($result1['allowed'] ? 'ALLOWED' : 'BLOCKED') . " | Remaining: {$result1['remaining']}\n";
echo "IP 2: " . ($result2['allowed'] ? 'ALLOWED' : 'BLOCKED') . " | Remaining: {$result2['remaining']}\n";

echo "\n";

// Test 4: Reset functionality
echo "Test 4: Reset functionality\n";
$testEndpoint = 'reset_test';
$result = $rateLimiter->check($testIp, $testEndpoint, 1, 10);
echo "Before reset: " . ($result['allowed'] ? 'ALLOWED' : 'BLOCKED') . " | Remaining: {$result['remaining']}\n";
$result = $rateLimiter->check($testIp, $testEndpoint, 1, 10);
echo "Second request: " . ($result['allowed'] ? 'ALLOWED' : 'BLOCKED') . " | Remaining: {$result['remaining']}\n";
$rateLimiter->reset($testIp, $testEndpoint);
$result = $rateLimiter->check($testIp, $testEndpoint, 1, 10);
echo "After reset: " . ($result['allowed'] ? 'ALLOWED' : 'BLOCKED') . " | Remaining: {$result['remaining']}\n";

echo "\n";

// Test 5: API endpoint simulation
echo "Test 5: API endpoint simulation\n";
$apiEndpoints = [
    'get_products' => ['limit' => 100, 'window' => 60],
    'create_alert' => ['limit' => 10, 'window' => 60],
    'delete_alert' => ['limit' => 20, 'window' => 60],
];

foreach ($apiEndpoints as $endpoint => $config) {
    $result = $rateLimiter->check('127.0.0.1', 'api_' . $endpoint, $config['limit'], $config['window']);
    echo "API $endpoint: Limit {$config['limit']}/{$config['window']}s | Remaining: {$result['remaining']}\n";
}

// Clean up test data
if (is_dir(__DIR__ . '/test_rate_limit_data')) {
    $files = glob(__DIR__ . '/test_rate_limit_data/*.json');
    foreach ($files as $file) {
        unlink($file);
    }
    rmdir(__DIR__ . '/test_rate_limit_data');
}

echo "\n✅ All tests completed!\n";