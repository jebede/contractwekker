<?php
require_once 'config.php';

function sendExpoPushNotifications($tokens, $title, $body, $data = null) {
    $messages = [];
    
    foreach ($tokens as $token) {
        if (!$token || strpos($token, 'development-token') === 0) {
            continue; // Skip development tokens
        }
        
        $messages[] = [
            'to' => $token,
            'sound' => 'default',
            'title' => $title,
            'body' => $body,
            'data' => $data
        ];
    }
    
    if (empty($messages)) {
        echo "No valid push tokens to send to.\n";
        return false;
    }
    
    $postData = json_encode($messages);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://exp.host/--/api/v2/push/send');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Accept-encoding: gzip, deflate',
        'Content-Type: application/json',
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $result = json_decode($response, true);
        echo "Push notifications sent successfully: " . json_encode($result) . "\n";
        return true;
    } else {
        echo "Error sending push notifications. HTTP Code: $httpCode, Response: $response\n";
        return false;
    }
}

try {
    $pdo = Config::getDatabaseConnection();
    
    echo "Starting push notification cronjob at " . date('Y-m-d H:i:s') . "\n";
    
    // Get all alerts that need to be sent today (push notifications only)
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("
        SELECT a.id, a.push_token, a.custom_product_name, a.alert_period, a.is_periodic, 
               a.first_alert_date, a.next_alert_date, p.name as product_name, p.deeplink
        FROM alerts a 
        LEFT JOIN products p ON a.product_id = p.id 
        WHERE a.push_token IS NOT NULL 
          AND a.is_active = 1 
          AND a.is_sent = 0
          AND a.next_alert_date = ?
        ORDER BY a.id
    ");
    
    $stmt->execute([$today]);
    $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($alerts)) {
        echo "No push notifications to send today.\n";
        exit(0);
    }
    
    echo "Found " . count($alerts) . " push notifications to send.\n";
    
    // Group alerts by push token to avoid sending multiple notifications to the same device
    $tokenGroups = [];
    foreach ($alerts as $alert) {
        $token = $alert['push_token'];
        if (!isset($tokenGroups[$token])) {
            $tokenGroups[$token] = [];
        }
        $tokenGroups[$token][] = $alert;
    }
    
    $successCount = 0;
    $errorCount = 0;
    
    foreach ($tokenGroups as $pushToken => $userAlerts) {
        try {
            if (count($userAlerts) === 1) {
                // Single alert
                $alert = $userAlerts[0];
                $productName = $alert['custom_product_name'] ?: $alert['product_name'];
                $title = "⏰ Contractwekker";
                $body = "Je contract '{$productName}' loopt binnenkort af. Tijd om over te stappen!";
                $deeplink = $alert['deeplink'] ?: 'https://www.contractwekker.nl';
                
                $sent = sendExpoPushNotifications(
                    [$pushToken], 
                    $title, 
                    $body, 
                    ['url' => $deeplink, 'alert_id' => $alert['id']]
                );
            } else {
                // Multiple alerts for same token
                $title = "⏰ Contractwekker";
                $body = "Je hebt " . count($userAlerts) . " contracten die binnenkort aflopen!";
                
                $sent = sendExpoPushNotifications(
                    [$pushToken], 
                    $title, 
                    $body, 
                    ['url' => 'https://www.contractwekker.nl', 'count' => count($userAlerts)]
                );
            }
            
            if ($sent) {
                // Mark alerts as sent
                $alertIds = array_column($userAlerts, 'id');
                $placeholders = str_repeat('?,', count($alertIds) - 1) . '?';
                $updateStmt = $pdo->prepare("UPDATE alerts SET is_sent = 1 WHERE id IN ($placeholders)");
                $updateStmt->execute($alertIds);
                
                $successCount += count($userAlerts);
                
                // Handle periodic alerts
                foreach ($userAlerts as $alert) {
                    if ($alert['is_periodic']) {
                        $nextAlertDate = null;
                        $alertPeriod = $alert['alert_period'];
                        
                        switch ($alertPeriod) {
                            case '1_month':
                                $nextAlertDate = date('Y-m-d', strtotime('+1 month'));
                                break;
                            case '3_months':
                                $nextAlertDate = date('Y-m-d', strtotime('+3 months'));
                                break;
                            case '1_year':
                                $nextAlertDate = date('Y-m-d', strtotime('+1 year'));
                                break;
                            case '2_years':
                                $nextAlertDate = date('Y-m-d', strtotime('+2 years'));
                                break;
                            case '3_years':
                                $nextAlertDate = date('Y-m-d', strtotime('+3 years'));
                                break;
                            case 'custom':
                                // For custom periods, add 1 year by default
                                $nextAlertDate = date('Y-m-d', strtotime('+1 year'));
                                break;
                        }
                        
                        if ($nextAlertDate) {
                            // Update the next alert date for recurring alerts
                            $updateStmt = $pdo->prepare("
                                UPDATE alerts 
                                SET next_alert_date = ?, is_sent = 0, updated_at = NOW() 
                                WHERE id = ?
                            ");
                            $updateStmt->execute([$nextAlertDate, $alert['id']]);
                            echo "Updated recurring alert ID {$alert['id']}, next date: $nextAlertDate\n";
                        }
                    }
                }
                
                echo "Successfully sent push notification to token: " . substr($pushToken, 0, 20) . "...\n";
            } else {
                $errorCount += count($userAlerts);
                echo "Failed to send push notification to token: " . substr($pushToken, 0, 20) . "...\n";
            }
            
        } catch (Exception $e) {
            $errorCount += count($userAlerts);
            echo "Error processing alerts for token " . substr($pushToken, 0, 20) . "...: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\nPush notification cronjob completed:\n";
    echo "- Successfully sent: $successCount alerts\n";
    echo "- Errors: $errorCount alerts\n";
    echo "- Total processed: " . ($successCount + $errorCount) . " alerts\n";
    
} catch(PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
    exit(1);
} catch(Exception $e) {
    echo "General error: " . $e->getMessage() . "\n";
    exit(1);
}
?>