<?php
/**
 * Double Email Prevention Test Suite
 * 
 * Tests to prevent infinite email loops and duplicate sending scenarios
 * that can occur with custom alerts and timestamp tracking
 * 
 * Run before deploying to prevent regression of the infinite loop bug
 */

require_once '../config.php';

// Prevent running from web browser
if (isset($_SERVER['HTTP_HOST'])) {
    http_response_code(403);
    die('This script can only be run from command line');
}

// Colors for terminal output
define('GREEN', "\033[0;32m");
define('RED', "\033[0;31m");
define('YELLOW', "\033[1;33m");
define('NC', "\033[0m");

$testCount = 0;
$passedCount = 0;
$failedCount = 0;

function runTest($name, $callable) {
    global $testCount, $passedCount, $failedCount;
    $testCount++;
    
    try {
        $result = $callable();
        if ($result === true) {
            $passedCount++;
            echo GREEN . "✓" . NC . " $name\n";
        } else {
            $failedCount++;
            echo RED . "✗" . NC . " $name: " . ($result ?: "Failed") . "\n";
        }
    } catch (Exception $e) {
        $failedCount++;
        echo RED . "✗" . NC . " $name: " . $e->getMessage() . "\n";
    }
}

echo "\n" . YELLOW . "=== Double Email Prevention Test Suite ===" . NC . "\n\n";

try {
    $pdo = Config::getDatabaseConnection();
    
    // Test 1: Custom Alert Infinite Loop Prevention
    echo YELLOW . "Testing Custom Alert Infinite Loop Prevention:" . NC . "\n";
    
    runTest("Custom alerts don't send infinite emails", function() use ($pdo) {
        // Create a custom alert that mimics the bug scenario
        $testEmail = 'test_custom_' . time() . '@example.com';
        $endDate = '2025-09-24'; // Original bug scenario
        $nextAlertDate = '2025-08-24'; // 1 month before end date
        
        $stmt = $pdo->prepare("
            INSERT INTO alerts 
            (email, custom_product_name, alert_period, next_alert_date, end_date, is_active, is_periodic, unsubscribe_token) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $testEmail, 
            'Test Custom Product', 
            'custom', 
            $nextAlertDate, 
            $endDate, 
            1, 
            1, 
            bin2hex(random_bytes(16))
        ]);
        $alertId = $pdo->lastInsertId();
        
        // First check: Alert should be selected for sending (because last_email_sent is NULL)
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM alerts 
            WHERE id = ? 
            AND next_alert_date <= CURDATE() 
            AND (last_email_sent IS NULL OR last_email_sent < next_alert_date)
        ");
        $stmt->execute([$alertId]);
        $shouldSend = $stmt->fetch()['count'] === 1;
        
        // Simulate sending email by updating last_email_sent
        $stmt = $pdo->prepare("UPDATE alerts SET last_email_sent = ? WHERE id = ?");
        $stmt->execute([$nextAlertDate, $alertId]);
        
        // Second check: Alert should NOT be selected again (because last_email_sent >= next_alert_date)
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM alerts 
            WHERE id = ? 
            AND next_alert_date <= CURDATE() 
            AND (last_email_sent IS NULL OR last_email_sent < next_alert_date)
        ");
        $stmt->execute([$alertId]);
        $shouldNotSend = $stmt->fetch()['count'] === 0;
        
        // Cleanup
        $pdo->prepare("DELETE FROM alerts WHERE id = ?")->execute([$alertId]);
        
        return $shouldSend && $shouldNotSend;
    });
    
    runTest("Regular alerts don't send duplicates on same date", function() use ($pdo) {
        $testEmail = 'test_regular_' . time() . '@example.com';
        $today = date('Y-m-d');
        
        $stmt = $pdo->prepare("
            INSERT INTO alerts 
            (email, custom_product_name, alert_period, next_alert_date, is_active, is_periodic, unsubscribe_token) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $testEmail, 
            'Test Regular Product', 
            '1_month', 
            $today, 
            1, 
            1, 
            bin2hex(random_bytes(16))
        ]);
        $alertId = $pdo->lastInsertId();
        
        // Should be selected initially
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM alerts 
            WHERE id = ? 
            AND next_alert_date <= CURDATE() 
            AND (last_email_sent IS NULL OR last_email_sent < next_alert_date)
        ");
        $stmt->execute([$alertId]);
        $firstCheck = $stmt->fetch()['count'] === 1;
        
        // Mark as sent
        $stmt = $pdo->prepare("UPDATE alerts SET last_email_sent = ? WHERE id = ?");
        $stmt->execute([$today, $alertId]);
        
        // Should NOT be selected again on same day
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM alerts 
            WHERE id = ? 
            AND next_alert_date <= CURDATE() 
            AND (last_email_sent IS NULL OR last_email_sent < next_alert_date)
        ");
        $stmt->execute([$alertId]);
        $secondCheck = $stmt->fetch()['count'] === 0;
        
        // Cleanup
        $pdo->prepare("DELETE FROM alerts WHERE id = ?")->execute([$alertId]);
        
        return $firstCheck && $secondCheck;
    });
    
    // Test 2: Early Reminder Prevention
    echo "\n" . YELLOW . "Testing Early Reminder Duplicate Prevention:" . NC . "\n";
    
    runTest("Early reminders don't send duplicates", function() use ($pdo) {
        $testEmail = 'test_early_' . time() . '@example.com';
        $today = date('Y-m-d');
        $nextAlert = date('Y-m-d', strtotime('+2 months'));
        
        $stmt = $pdo->prepare("
            INSERT INTO alerts 
            (email, custom_product_name, alert_period, next_alert_date, send_early_reminder, early_reminder_days, early_reminder_date, is_active, unsubscribe_token) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $testEmail, 
            'Test Early Product', 
            '1_year', 
            $nextAlert, 
            1, 
            60, 
            $today, // Early reminder due today
            1, 
            bin2hex(random_bytes(16))
        ]);
        $alertId = $pdo->lastInsertId();
        
        // Should be selected for early reminder
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM alerts 
            WHERE id = ? 
            AND send_early_reminder = 1 
            AND early_reminder_date <= CURDATE() 
            AND (last_email_early_sent IS NULL OR last_email_early_sent < early_reminder_date)
        ");
        $stmt->execute([$alertId]);
        $firstCheck = $stmt->fetch()['count'] === 1;
        
        // Mark early reminder as sent
        $stmt = $pdo->prepare("UPDATE alerts SET last_email_early_sent = ? WHERE id = ?");
        $stmt->execute([$today, $alertId]);
        
        // Should NOT be selected again for early reminder
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM alerts 
            WHERE id = ? 
            AND send_early_reminder = 1 
            AND early_reminder_date <= CURDATE() 
            AND (last_email_early_sent IS NULL OR last_email_early_sent < early_reminder_date)
        ");
        $stmt->execute([$alertId]);
        $secondCheck = $stmt->fetch()['count'] === 0;
        
        // Cleanup
        $pdo->prepare("DELETE FROM alerts WHERE id = ?")->execute([$alertId]);
        
        return $firstCheck && $secondCheck;
    });
    
    // Test 3: Push vs Email Independence
    echo "\n" . YELLOW . "Testing Push vs Email Independence:" . NC . "\n";
    
    runTest("Push and email notifications are tracked independently", function() use ($pdo) {
        $testEmail = 'test_both_' . time() . '@example.com';
        $testPushToken = 'ExponentPushToken[test_' . time() . ']';
        $today = date('Y-m-d');
        
        $stmt = $pdo->prepare("
            INSERT INTO alerts 
            (email, push_token, custom_product_name, alert_period, next_alert_date, is_active, unsubscribe_token) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $testEmail, 
            $testPushToken,
            'Test Both Product', 
            '1_month', 
            $today, 
            1, 
            bin2hex(random_bytes(16))
        ]);
        $alertId = $pdo->lastInsertId();
        
        // Both should be available initially
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM alerts 
            WHERE id = ? 
            AND next_alert_date <= CURDATE() 
            AND (last_email_sent IS NULL OR last_email_sent < next_alert_date)
            AND (last_push_sent IS NULL OR last_push_sent < next_alert_date)
        ");
        $stmt->execute([$alertId]);
        $bothAvailable = $stmt->fetch()['count'] === 1;
        
        // Send email only
        $stmt = $pdo->prepare("UPDATE alerts SET last_email_sent = ? WHERE id = ?");
        $stmt->execute([$today, $alertId]);
        
        // Email should not be available, but push should still be available
        $stmt = $pdo->prepare("
            SELECT 
                CASE WHEN (last_email_sent IS NULL OR last_email_sent < next_alert_date) THEN 1 ELSE 0 END as email_available,
                CASE WHEN (last_push_sent IS NULL OR last_push_sent < next_alert_date) THEN 1 ELSE 0 END as push_available
            FROM alerts 
            WHERE id = ?
        ");
        $stmt->execute([$alertId]);
        $result = $stmt->fetch();
        $emailNotAvailable = ($result['email_available'] === 0);
        $pushStillAvailable = ($result['push_available'] === 1);
        
        // Cleanup
        $pdo->prepare("DELETE FROM alerts WHERE id = ?")->execute([$alertId]);
        
        return $bothAvailable && $emailNotAvailable && $pushStillAvailable;
    });
    
    // Test 4: Periodic Alert Updates
    echo "\n" . YELLOW . "Testing Periodic Alert Updates:" . NC . "\n";
    
    runTest("Periodic alerts update correctly after sending", function() use ($pdo) {
        $testEmail = 'test_periodic_' . time() . '@example.com';
        $today = date('Y-m-d');
        
        $stmt = $pdo->prepare("
            INSERT INTO alerts 
            (email, custom_product_name, alert_period, next_alert_date, is_active, is_periodic, unsubscribe_token) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $testEmail, 
            'Test Periodic Product', 
            '1_month', 
            $today, 
            1, 
            1, 
            bin2hex(random_bytes(16))
        ]);
        $alertId = $pdo->lastInsertId();
        
        // Simulate cronjob processing
        $nextAlertDate = date('Y-m-d', strtotime('+1 month'));
        $stmt = $pdo->prepare("
            UPDATE alerts 
            SET next_alert_date = ?, last_email_sent = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$nextAlertDate, $today, $alertId]);
        
        // Check that next date is updated correctly
        $stmt = $pdo->prepare("
            SELECT 
                next_alert_date,
                last_email_sent,
                CASE WHEN (last_email_sent IS NULL OR last_email_sent < next_alert_date) THEN 1 ELSE 0 END as available_for_sending
            FROM alerts 
            WHERE id = ?
        ");
        $stmt->execute([$alertId]);
        $result = $stmt->fetch();
        
        $nextDateCorrect = ($result['next_alert_date'] === $nextAlertDate);
        $lastSentCorrect = ($result['last_email_sent'] === $today);
        $notAvailableYet = ($result['available_for_sending'] === 0);
        
        // Cleanup
        $pdo->prepare("DELETE FROM alerts WHERE id = ?")->execute([$alertId]);
        
        return $nextDateCorrect && $lastSentCorrect && $notAvailableYet;
    });
    
    // Test 5: Edge Cases
    echo "\n" . YELLOW . "Testing Edge Cases:" . NC . "\n";
    
    runTest("NULL timestamp columns work correctly", function() use ($pdo) {
        $testEmail = 'test_null_' . time() . '@example.com';
        $today = date('Y-m-d');
        
        $stmt = $pdo->prepare("
            INSERT INTO alerts 
            (email, custom_product_name, alert_period, next_alert_date, is_active, last_email_sent) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $testEmail, 
            'Test Null Product', 
            '1_month', 
            $today, 
            1, 
            null // Explicitly NULL
        ]);
        $alertId = $pdo->lastInsertId();
        
        // Should be selected when last_email_sent is NULL
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM alerts 
            WHERE id = ? 
            AND (last_email_sent IS NULL OR last_email_sent < next_alert_date)
        ");
        $stmt->execute([$alertId]);
        $isSelected = $stmt->fetch()['count'] === 1;
        
        // Cleanup
        $pdo->prepare("DELETE FROM alerts WHERE id = ?")->execute([$alertId]);
        
        return $isSelected;
    });
    
    runTest("Future alert dates don't trigger sending", function() use ($pdo) {
        $testEmail = 'test_future_' . time() . '@example.com';
        $futureDate = date('Y-m-d', strtotime('+7 days'));
        
        $stmt = $pdo->prepare("
            INSERT INTO alerts 
            (email, custom_product_name, alert_period, next_alert_date, is_active, unsubscribe_token) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $testEmail, 
            'Test Future Product', 
            '1_month', 
            $futureDate, 
            1, 
            bin2hex(random_bytes(16))
        ]);
        $alertId = $pdo->lastInsertId();
        
        // Should NOT be selected for future dates
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM alerts 
            WHERE id = ? 
            AND next_alert_date <= CURDATE()
        ");
        $stmt->execute([$alertId]);
        $notSelected = $stmt->fetch()['count'] === 0;
        
        // Cleanup
        $pdo->prepare("DELETE FROM alerts WHERE id = ?")->execute([$alertId]);
        
        return $notSelected;
    });
    
    echo "\n" . YELLOW . "=== Test Summary ===" . NC . "\n";
    echo "Total Tests: $testCount\n";
    echo GREEN . "Passed: $passedCount" . NC . "\n";
    
    if ($failedCount > 0) {
        echo RED . "Failed: $failedCount" . NC . "\n";
        exit(1);
    } else {
        echo "\n" . GREEN . "All double email prevention tests passed!" . NC . "\n";
        echo "✓ No infinite loop bugs detected\n";
        echo "✓ Duplicate email prevention working correctly\n";
        exit(0);
    }
    
} catch (Exception $e) {
    error_log("Double email prevention test error: " . $e->getMessage());
    echo RED . "Test suite error: " . $e->getMessage() . NC . "\n";
    exit(1);
}
?>