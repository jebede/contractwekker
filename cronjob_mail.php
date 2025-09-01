<?php
// This script should be run every minute via cron
// Example crontab entry: * * * * * /usr/bin/php /path/to/contractwekker/cronjob.php

require_once 'config.php';
require_once 'email.php';

// Prevent running from web browser
if (isset($_SERVER['HTTP_HOST'])) {
    http_response_code(403);
    die('This script can only be run from command line');
}

try {
    $pdo = Config::getDatabaseConnection();
    $emailService = new EmailService();
    
    // Find alerts that need to be sent (including early reminders)
    $stmt = $pdo->prepare("
        SELECT a.*, p.name as product_name, p.deeplink,
               CASE 
                   WHEN a.send_early_reminder = 1 
                        AND a.early_reminder_date <= CURDATE() 
                        AND (a.last_email_early_sent IS NULL OR a.last_email_early_sent < a.early_reminder_date)
                   THEN 'early'
                   WHEN a.next_alert_date <= CURDATE() 
                        AND (a.last_email_sent IS NULL OR a.last_email_sent < a.next_alert_date)
                   THEN 'regular'
                   ELSE NULL
               END as reminder_type
        FROM alerts a 
        LEFT JOIN products p ON a.product_id = p.id 
        WHERE a.is_active = 1 
        AND a.email IS NOT NULL
        AND (
            (a.send_early_reminder = 1 AND a.early_reminder_date <= CURDATE() AND (a.last_email_early_sent IS NULL OR a.last_email_early_sent < a.early_reminder_date))
            OR (a.next_alert_date <= CURDATE() AND (a.last_email_sent IS NULL OR a.last_email_sent < a.next_alert_date))
        )
        ORDER BY a.next_alert_date ASC 
        LIMIT 50
    ");
    
    $stmt->execute();
    $alerts = $stmt->fetchAll();
    
    $sentCount = 0;
    $errorCount = 0;
    
    foreach ($alerts as $alert) {
        try {
            // Prepare product data
            $product = [
                'name' => $alert['product_name'] ?: $alert['custom_product_name'],
                'deeplink' => $alert['deeplink'] ?: '#'
            ];
            
            // Check if this is an early reminder
            $isEarlyReminder = ($alert['reminder_type'] === 'early');
            
            // Send email with appropriate message
            $sent = $emailService->sendContractAlert($alert, $product, $isEarlyReminder);
            
            if ($sent) {
                $sentCount++;
                
                if ($isEarlyReminder) {
                    // Mark early reminder as sent with the target date
                    $updateStmt = $pdo->prepare("
                        UPDATE alerts 
                        SET last_email_early_sent = ?, updated_at = NOW() 
                        WHERE id = ?
                    ");
                    $updateStmt->execute([$alert['early_reminder_date'], $alert['id']]);
                    
                    echo "Sent early reminder to {$alert['email']} for {$product['name']} ({$alert['early_reminder_days']} days before main reminder)\n";
                } else {
                    // Handle regular reminder
                    if ($alert['is_periodic'] && $alert['alert_period'] !== 'custom') {
                        // Calculate next alert date for periodic alerts (excluding custom)
                        $nextAlertDate = calculateNextAlertDate(
                            $alert['alert_period'], 
                            null, 
                            null,
                            $alert['end_date']
                        );
                        
                        // Calculate new early reminder date if enabled
                        $earlyReminderDate = null;
                        if ($alert['send_early_reminder'] && $alert['early_reminder_days'] > 0) {
                            $earlyReminderDate = date('Y-m-d', strtotime($nextAlertDate . ' -' . $alert['early_reminder_days'] . ' days'));
                        }
                        
                        // Update next alert date and mark regular email as sent
                        $updateStmt = $pdo->prepare("
                            UPDATE alerts 
                            SET next_alert_date = ?, 
                                early_reminder_date = ?,
                                last_email_sent = ?,
                                updated_at = NOW() 
                            WHERE id = ?
                        ");
                        $updateStmt->execute([$nextAlertDate, $earlyReminderDate, $alert['next_alert_date'], $alert['id']]);
                        
                        echo "Updated periodic alert {$alert['id']} for {$alert['email']} - next: {$nextAlertDate}\n";
                    } else {
                        // Deactivate one-time alert or custom alert and mark as sent
                        $updateStmt = $pdo->prepare("
                            UPDATE alerts 
                            SET is_active = 0, 
                                last_email_sent = ?,
                                updated_at = NOW() 
                            WHERE id = ?
                        ");
                        $updateStmt->execute([$alert['next_alert_date'], $alert['id']]);
                        
                        $alertType = ($alert['alert_period'] === 'custom') ? 'custom' : 'one-time';
                        echo "Deactivated {$alertType} alert {$alert['id']} for {$alert['email']}\n";
                    }
                    
                    echo "Sent regular alert to {$alert['email']} for {$product['name']}\n";
                }
                
            } else {
                $errorCount++;
                error_log("Failed to send alert {$alert['id']} to {$alert['email']}");
                echo "Failed to send alert to {$alert['email']}\n";
            }
            
            // Small delay to prevent overwhelming the mail server
            usleep(100000); // 0.1 second
            
        } catch (Exception $e) {
            $errorCount++;
            error_log("Error processing alert {$alert['id']}: " . $e->getMessage());
            echo "Error processing alert {$alert['id']}\n";
        }
    }
    
    if ($sentCount > 0 || $errorCount > 0) {
        echo "Cronjob completed: {$sentCount} emails sent, {$errorCount} errors\n";
        error_log("Contractwekker cronjob: {$sentCount} emails sent, {$errorCount} errors");
    }
    
} catch (Exception $e) {
    error_log("Cronjob error: " . $e->getMessage());
    echo "An error occurred. Check logs for details.\n";
    exit(1);
}
?>