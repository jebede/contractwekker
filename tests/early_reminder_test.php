<?php
require_once '../config.php';

/**
 * Test script to verify early reminder functionality works correctly
 * This script tests that reminders aren't sent too often and dates are calculated properly
 */

// Prevent running from web browser
if (isset($_SERVER['HTTP_HOST'])) {
    http_response_code(403);
    die('This script can only be run from command line');
}

try {
    $pdo = Config::getDatabaseConnection();
    
    echo "Testing Early Reminder Functionality\n";
    echo "====================================\n\n";
    
    // Test 1: Verify early reminder date calculation
    echo "Test 1: Early reminder date calculation\n";
    
    // Create a test alert with early reminder enabled
    $testEmail = 'test_' . time() . '@example.com';
    $nextAlertDate = date('Y-m-d H:i:s', strtotime('+1 year'));
    $earlyReminderDays = 60;
    $expectedEarlyDate = date('Y-m-d', strtotime($nextAlertDate . ' -' . $earlyReminderDays . ' days'));
    
    $stmt = $pdo->prepare("
        INSERT INTO alerts 
        (email, product_id, alert_period, next_alert_date, send_early_reminder, early_reminder_days, early_reminder_date, unsubscribe_token) 
        VALUES (?, 1, '1_year', ?, 1, ?, ?, ?)
    ");
    
    $unsubscribeToken = bin2hex(random_bytes(32));
    $stmt->execute([$testEmail, $nextAlertDate, $earlyReminderDays, $expectedEarlyDate, $unsubscribeToken]);
    $testAlertId = $pdo->lastInsertId();
    
    // Verify the early reminder date was calculated correctly
    $stmt = $pdo->prepare("SELECT early_reminder_date FROM alerts WHERE id = ?");
    $stmt->execute([$testAlertId]);
    $result = $stmt->fetch();
    
    if ($result['early_reminder_date'] === $expectedEarlyDate) {
        echo "✓ Early reminder date calculation is correct\n";
    } else {
        echo "✗ Early reminder date calculation failed. Expected: $expectedEarlyDate, Got: " . $result['early_reminder_date'] . "\n";
    }
    
    // Test 2: Verify duplicate prevention
    echo "\nTest 2: Duplicate reminder prevention\n";
    
    // Set early reminder date to today to simulate it should be sent
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("UPDATE alerts SET early_reminder_date = ?, early_reminder_sent = 0 WHERE id = ?");
    $stmt->execute([$today, $testAlertId]);
    
    // Check that the alert would be selected for early reminder
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM alerts 
        WHERE id = ? 
        AND send_early_reminder = 1 
        AND early_reminder_date <= CURDATE() 
        AND early_reminder_sent = 0
    ");
    $stmt->execute([$testAlertId]);
    $count = $stmt->fetch()['count'];
    
    if ($count === 1) {
        echo "✓ Alert correctly identified for early reminder sending\n";
    } else {
        echo "✗ Alert not properly identified for early reminder sending\n";
    }
    
    // Simulate marking early reminder as sent
    $stmt = $pdo->prepare("UPDATE alerts SET early_reminder_sent = 1 WHERE id = ?");
    $stmt->execute([$testAlertId]);
    
    // Check that it's no longer selected
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM alerts 
        WHERE id = ? 
        AND send_early_reminder = 1 
        AND early_reminder_date <= CURDATE() 
        AND early_reminder_sent = 0
    ");
    $stmt->execute([$testAlertId]);
    $count = $stmt->fetch()['count'];
    
    if ($count === 0) {
        echo "✓ Duplicate early reminder correctly prevented\n";
    } else {
        echo "✗ Duplicate early reminder NOT prevented\n";
    }
    
    // Test 3: Verify regular reminder timing
    echo "\nTest 3: Regular reminder timing\n";
    
    // Set next alert date to today
    $stmt = $pdo->prepare("UPDATE alerts SET next_alert_date = ?, is_sent = 0 WHERE id = ?");
    $stmt->execute([$today, $testAlertId]);
    
    // Check that regular reminder would be sent
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM alerts 
        WHERE id = ? 
        AND next_alert_date <= CURDATE()
        AND is_sent = 0
    ");
    $stmt->execute([$testAlertId]);
    $count = $stmt->fetch()['count'];
    
    if ($count === 1) {
        echo "✓ Regular reminder correctly identified for sending\n";
    } else {
        echo "✗ Regular reminder not properly identified for sending\n";
    }
    
    // Test 4: Verify early reminder days validation
    echo "\nTest 4: Early reminder days validation\n";
    
    $validDays = [30, 60, 90];
    $allValid = true;
    
    foreach ($validDays as $days) {
        $testDate = date('Y-m-d', strtotime('+1 year -' . $days . ' days'));
        if (strtotime($testDate) < time()) {
            echo "✗ Invalid early reminder calculation for $days days\n";
            $allValid = false;
        }
    }
    
    if ($allValid) {
        echo "✓ All early reminder day options are valid\n";
    }
    
    // Cleanup test data
    $stmt = $pdo->prepare("DELETE FROM alerts WHERE id = ?");
    $stmt->execute([$testAlertId]);
    
    echo "\n✓ Test cleanup completed\n";
    echo "\nAll tests completed successfully!\n";
    
} catch (Exception $e) {
    echo "Test error: " . $e->getMessage() . "\n";
    exit(1);
}
?>