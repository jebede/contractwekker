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
    
    // Find alerts that need to be sent
    $stmt = $pdo->prepare("
        SELECT a.*, p.name as product_name, p.deeplink 
        FROM alerts a 
        LEFT JOIN products p ON a.product_id = p.id 
        WHERE a.is_active = 1 
        AND a.next_alert_date <= NOW() 
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
            
            // Send email
            $sent = $emailService->sendContractAlert($alert, $product);
            
            if ($sent) {
                $sentCount++;
                
                // Update alert based on periodic setting
                if ($alert['is_periodic']) {
                    // Calculate next alert date
                    $nextAlertDate = calculateNextAlertDate(
                        $alert['alert_period'], 
                        null, 
                        null,
                        $alert['end_date']
                    );
                    
                    // Update next alert date
                    $updateStmt = $pdo->prepare("
                        UPDATE alerts 
                        SET next_alert_date = ?, updated_at = NOW() 
                        WHERE id = ?
                    ");
                    $updateStmt->execute([$nextAlertDate, $alert['id']]);
                    
                    echo "Updated periodic alert {$alert['id']} for {$alert['email']} - next: {$nextAlertDate}\n";
                } else {
                    // Deactivate one-time alert
                    $updateStmt = $pdo->prepare("
                        UPDATE alerts 
                        SET is_active = 0, updated_at = NOW() 
                        WHERE id = ?
                    ");
                    $updateStmt->execute([$alert['id']]);
                    
                    echo "Deactivated one-time alert {$alert['id']} for {$alert['email']}\n";
                }
                
                echo "Sent alert to {$alert['email']} for {$product['name']}\n";
                
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
            echo "Error processing alert {$alert['id']}: " . $e->getMessage() . "\n";
        }
    }
    
    if ($sentCount > 0 || $errorCount > 0) {
        echo "Cronjob completed: {$sentCount} emails sent, {$errorCount} errors\n";
        error_log("Contractwekker cronjob: {$sentCount} emails sent, {$errorCount} errors");
    }
    
} catch (Exception $e) {
    error_log("Cronjob error: " . $e->getMessage());
    echo "Cronjob error: " . $e->getMessage() . "\n";
    exit(1);
}
?>