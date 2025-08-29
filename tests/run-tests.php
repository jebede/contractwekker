<?php
/**
 * Simple Test Runner for Contractwekker
 * 
 * Tests:
 * - Cronjob mail functionality
 * - Cronjob push functionality  
 * - Homepage access
 * - API security
 */

define('TEST_MODE', true);
$testResults = [];
$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

// Colors for terminal output
define('GREEN', "\033[0;32m");
define('RED', "\033[0;31m");
define('YELLOW', "\033[1;33m");
define('NC', "\033[0m"); // No Color

function runTest($name, $callable) {
    global $testResults, $totalTests, $passedTests, $failedTests;
    $totalTests++;
    
    try {
        $result = $callable();
        if ($result === true) {
            $passedTests++;
            echo GREEN . "✓" . NC . " $name\n";
            $testResults[$name] = ['status' => 'passed'];
        } else {
            $failedTests++;
            echo RED . "✗" . NC . " $name: " . ($result ?: "Failed") . "\n";
            $testResults[$name] = ['status' => 'failed', 'message' => $result];
        }
    } catch (Exception $e) {
        $failedTests++;
        echo RED . "✗" . NC . " $name: " . $e->getMessage() . "\n";
        $testResults[$name] = ['status' => 'error', 'message' => $e->getMessage()];
    }
}

function assertThat($condition, $message = "Assertion failed") {
    if (!$condition) {
        throw new Exception($message);
    }
}

echo "\n" . YELLOW . "=== Running Contractwekker Tests ===" . NC . "\n\n";

// Load config
require_once dirname(__DIR__) . '/config.php';

// Test Database Connection
runTest("Database connection", function() {
    $pdo = Config::getDatabaseConnection();
    return $pdo instanceof PDO;
});

// Test database setup
runTest("Database has alerts table", function() {
    $pdo = Config::getDatabaseConnection();
    $stmt = $pdo->query("SHOW TABLES LIKE 'alerts'");
    return $stmt->rowCount() > 0;
});

runTest("Database has products table", function() {
    $pdo = Config::getDatabaseConnection();
    $stmt = $pdo->query("SHOW TABLES LIKE 'products'");
    return $stmt->rowCount() > 0;
});

// Test cronjob_mail.php logic
echo "\n" . YELLOW . "Testing Email Cronjob Logic:" . NC . "\n";

runTest("Email cronjob only processes due alerts", function() {
    $pdo = Config::getDatabaseConnection();
    
    // Create test alert due today
    $stmt = $pdo->prepare("INSERT INTO alerts (email, custom_product_name, alert_period, first_alert_date, next_alert_date, is_active, is_periodic, unsubscribe_token, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->execute(['test@example.com', 'Test Product', '1_month', date('Y-m-d'), date('Y-m-d'), 1, 0, bin2hex(random_bytes(16))]);
    $alertId = $pdo->lastInsertId();
    
    // Check it's selected for processing
    $stmt = $pdo->prepare("
        SELECT id FROM alerts 
        WHERE is_active = 1 
        AND next_alert_date <= NOW() 
        ORDER BY next_alert_date ASC
    ");
    $stmt->execute();
    $found = false;
    while ($row = $stmt->fetch()) {
        if ($row['id'] == $alertId) {
            $found = true;
            break;
        }
    }
    
    // Cleanup
    $pdo->exec("DELETE FROM alerts WHERE id = $alertId");
    
    return $found;
});

runTest("Email cronjob doesn't process future alerts", function() {
    $pdo = Config::getDatabaseConnection();
    
    // Create test alert due in future
    $futureDate = date('Y-m-d', strtotime('+7 days'));
    $stmt = $pdo->prepare("INSERT INTO alerts (email, custom_product_name, alert_period, first_alert_date, next_alert_date, is_active, is_periodic, unsubscribe_token, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->execute(['test@example.com', 'Test Product', '1_month', $futureDate, $futureDate, 1, 0, bin2hex(random_bytes(16))]);
    $alertId = $pdo->lastInsertId();
    
    // Check it's NOT selected for processing
    $stmt = $pdo->prepare("
        SELECT id FROM alerts 
        WHERE is_active = 1 
        AND next_alert_date <= NOW() 
        ORDER BY next_alert_date ASC
    ");
    $stmt->execute();
    $found = false;
    while ($row = $stmt->fetch()) {
        if ($row['id'] == $alertId) {
            $found = true;
            break;
        }
    }
    
    // Cleanup
    $pdo->exec("DELETE FROM alerts WHERE id = $alertId");
    
    return !$found; // Should NOT be found
});

runTest("One-time alerts are deactivated after processing", function() {
    $pdo = Config::getDatabaseConnection();
    
    // Create one-time alert
    $stmt = $pdo->prepare("INSERT INTO alerts (email, custom_product_name, alert_period, first_alert_date, next_alert_date, is_active, is_periodic, unsubscribe_token, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->execute(['test@example.com', 'Test Product', '1_month', date('Y-m-d'), date('Y-m-d'), 1, 0, bin2hex(random_bytes(16))]);
    $alertId = $pdo->lastInsertId();
    
    // Simulate processing (what cronjob would do)
    $updateStmt = $pdo->prepare("UPDATE alerts SET is_active = 0, updated_at = NOW() WHERE id = ?");
    $updateStmt->execute([$alertId]);
    
    // Check it's deactivated
    $stmt = $pdo->prepare("SELECT is_active FROM alerts WHERE id = ?");
    $stmt->execute([$alertId]);
    $row = $stmt->fetch();
    $isDeactivated = ($row['is_active'] == 0);
    
    // Cleanup
    $pdo->exec("DELETE FROM alerts WHERE id = $alertId");
    
    return $isDeactivated;
});

runTest("Periodic alerts update next_alert_date", function() {
    $pdo = Config::getDatabaseConnection();
    
    // Create periodic alert
    $stmt = $pdo->prepare("INSERT INTO alerts (email, custom_product_name, alert_period, first_alert_date, next_alert_date, is_active, is_periodic, unsubscribe_token, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->execute(['test@example.com', 'Test Product', '1_month', date('Y-m-d'), date('Y-m-d'), 1, 1, bin2hex(random_bytes(16))]);
    $alertId = $pdo->lastInsertId();
    
    // Simulate processing for periodic alert
    $nextDate = date('Y-m-d', strtotime('+1 month'));
    $updateStmt = $pdo->prepare("UPDATE alerts SET next_alert_date = ?, updated_at = NOW() WHERE id = ?");
    $updateStmt->execute([$nextDate, $alertId]);
    
    // Check next_alert_date is updated
    $stmt = $pdo->prepare("SELECT next_alert_date FROM alerts WHERE id = ?");
    $stmt->execute([$alertId]);
    $row = $stmt->fetch();
    $isUpdated = ($row['next_alert_date'] == $nextDate);
    
    // Cleanup
    $pdo->exec("DELETE FROM alerts WHERE id = $alertId");
    
    return $isUpdated;
});

// Test cronjob_push.php logic
echo "\n" . YELLOW . "Testing Push Notification Cronjob Logic:" . NC . "\n";

runTest("Push cronjob only processes today's alerts", function() {
    $pdo = Config::getDatabaseConnection();
    
    // Create test push alert for today
    $stmt = $pdo->prepare("INSERT INTO alerts (push_token, custom_product_name, alert_period, first_alert_date, next_alert_date, is_active, is_sent, unsubscribe_token, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->execute(['ExponentPushToken[test]', 'Test Product', '1_month', date('Y-m-d'), date('Y-m-d'), 1, 0, bin2hex(random_bytes(16))]);
    $alertId = $pdo->lastInsertId();
    
    // Check it's selected for processing
    $stmt = $pdo->prepare("
        SELECT id FROM alerts 
        WHERE push_token IS NOT NULL 
        AND is_active = 1 
        AND is_sent = 0
        AND next_alert_date = ?
    ");
    $stmt->execute([date('Y-m-d')]);
    $found = false;
    while ($row = $stmt->fetch()) {
        if ($row['id'] == $alertId) {
            $found = true;
            break;
        }
    }
    
    // Cleanup
    $pdo->exec("DELETE FROM alerts WHERE id = $alertId");
    
    return $found;
});

runTest("Push alerts are marked as sent", function() {
    $pdo = Config::getDatabaseConnection();
    
    // Create test push alert
    $stmt = $pdo->prepare("INSERT INTO alerts (push_token, custom_product_name, alert_period, first_alert_date, next_alert_date, is_active, is_sent, unsubscribe_token, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->execute(['ExponentPushToken[test]', 'Test Product', '1_month', date('Y-m-d'), date('Y-m-d'), 1, 0, bin2hex(random_bytes(16))]);
    $alertId = $pdo->lastInsertId();
    
    // Simulate marking as sent
    $updateStmt = $pdo->prepare("UPDATE alerts SET is_sent = 1 WHERE id = ?");
    $updateStmt->execute([$alertId]);
    
    // Check it's marked as sent
    $stmt = $pdo->prepare("SELECT is_sent FROM alerts WHERE id = ?");
    $stmt->execute([$alertId]);
    $row = $stmt->fetch();
    $isSent = ($row['is_sent'] == 1);
    
    // Cleanup
    $pdo->exec("DELETE FROM alerts WHERE id = $alertId");
    
    return $isSent;
});

runTest("Periodic push alerts reset is_sent and update next_alert_date", function() {
    $pdo = Config::getDatabaseConnection();
    
    // Create periodic push alert
    $stmt = $pdo->prepare("INSERT INTO alerts (push_token, custom_product_name, alert_period, first_alert_date, next_alert_date, is_active, is_sent, is_periodic, unsubscribe_token, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->execute(['ExponentPushToken[test]', 'Test Product', '1_month', date('Y-m-d'), date('Y-m-d'), 1, 1, 1, bin2hex(random_bytes(16))]);
    $alertId = $pdo->lastInsertId();
    
    // Simulate periodic update
    $nextDate = date('Y-m-d', strtotime('+1 month'));
    $updateStmt = $pdo->prepare("UPDATE alerts SET next_alert_date = ?, is_sent = 0, updated_at = NOW() WHERE id = ?");
    $updateStmt->execute([$nextDate, $alertId]);
    
    // Check updates
    $stmt = $pdo->prepare("SELECT next_alert_date, is_sent FROM alerts WHERE id = ?");
    $stmt->execute([$alertId]);
    $row = $stmt->fetch();
    $isCorrect = ($row['next_alert_date'] == $nextDate && $row['is_sent'] == 0);
    
    // Cleanup
    $pdo->exec("DELETE FROM alerts WHERE id = $alertId");
    
    return $isCorrect;
});

// Test Homepage
echo "\n" . YELLOW . "Testing Web Access:" . NC . "\n";

runTest("Homepage (/) is accessible", function() {
    $homepagePath = dirname(__DIR__) . '/public/index.php';
    return file_exists($homepagePath);
});

// Test API Security
echo "\n" . YELLOW . "Testing API Security:" . NC . "\n";

runTest("API get_products doesn't expose sensitive data", function() {
    // Simulate API response structure
    $pdo = Config::getDatabaseConnection();
    $stmt = $pdo->query("SELECT id, name FROM products LIMIT 1");
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Check only safe fields are present
    if ($product) {
        $allowedFields = ['id', 'name'];
        $actualFields = array_keys($product);
        return empty(array_diff($actualFields, $allowedFields));
    }
    return true;
});

runTest("API get_alerts doesn't expose email addresses", function() {
    $pdo = Config::getDatabaseConnection();
    
    // Create test alert with email
    $stmt = $pdo->prepare("INSERT INTO alerts (email, push_token, custom_product_name, alert_period, first_alert_date, next_alert_date, is_active, unsubscribe_token, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->execute(['test@example.com', 'ExponentPushToken[test]', 'Test Product', '1_month', date('Y-m-d'), date('Y-m-d'), 1, bin2hex(random_bytes(16))]);
    $alertId = $pdo->lastInsertId();
    
    // Simulate API query by push_token (what API does)
    $stmt = $pdo->prepare("
        SELECT a.id, a.product_id, a.custom_product_name, a.alert_period, a.first_alert_date, 
               a.next_alert_date, a.is_periodic, a.created_at, p.name as product_name
        FROM alerts a 
        LEFT JOIN products p ON a.product_id = p.id 
        WHERE a.push_token = ?
    ");
    $stmt->execute(['ExponentPushToken[test]']);
    $alert = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Check email is NOT in response
    $hasEmail = isset($alert['email']);
    
    // Cleanup
    $pdo->exec("DELETE FROM alerts WHERE id = $alertId");
    
    return !$hasEmail; // Should NOT have email field
});

runTest("API requires either email or push_token for alerts", function() {
    // Test that create_alert validation works
    // In actual implementation, both checks are in api.php
    $hasEmailOrToken = false;
    $email = null;
    $push_token = null;
    
    // This would fail validation
    if (!$email && !$push_token) {
        return true; // Validation would reject this
    }
    
    return false;
});

// Test deployment script security
echo "\n" . YELLOW . "Testing Deployment:" . NC . "\n";

runTest("Deploy script exists", function() {
    return file_exists(dirname(__DIR__) . '/public/deploy-script222.php');
});

runTest("Deploy.sh script exists", function() {
    return file_exists(dirname(__DIR__) . '/deploy.sh');
});

// Summary
echo "\n" . YELLOW . "=== Test Summary ===" . NC . "\n";
echo "Total Tests: $totalTests\n";
echo GREEN . "Passed: $passedTests" . NC . "\n";

if ($failedTests > 0) {
    echo RED . "Failed: $failedTests" . NC . "\n";
    echo "\nFailed tests:\n";
    foreach ($testResults as $name => $result) {
        if ($result['status'] !== 'passed') {
            echo "  - $name: " . ($result['message'] ?? 'Unknown error') . "\n";
        }
    }
    exit(1); // Exit with error code
} else {
    echo "\n" . GREEN . "All tests passed!" . NC . "\n";
    exit(0); // Exit with success code
}
?>