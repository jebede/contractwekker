<?php
require_once 'config.php';

class EmailService {
    private $smtpHost;
    private $smtpPort;
    private $smtpUsername;
    private $smtpPassword;
    private $smtpSecure;
    private $fromEmail;
    private $fromName;
    
    public function __construct() {
        $this->smtpHost = Config::get('SMTP_HOST', 'localhost');
        $this->smtpPort = Config::get('SMTP_PORT', 587);
        $this->smtpUsername = Config::get('SMTP_USERNAME', '');
        $this->smtpPassword = Config::get('SMTP_PASSWORD', '');
        $this->smtpSecure = strtolower(Config::get('SMTP_SECURE', ''));
        $this->fromEmail = Config::get('FROM_EMAIL', 'noreply@contractwekker.nl');
        $this->fromName = Config::get('FROM_NAME', 'Contractwekker');
    }
    
    public function sendContractAlert($alert, $product, $isEarlyReminder = false) {
        if ($isEarlyReminder) {
            $subject = "⏰ Vroege herinnering: {$product['name']} - nog {$alert['early_reminder_days']} dagen!";
        } else {
            $subject = "🔔 Contractwekker voor {$product['name']} - Het is tijd!";
        }
        
        $message = $this->buildAlertEmailBody($alert, $product, $isEarlyReminder);
        
        return $this->sendEmail($alert['email'], $subject, $message);
    }
    
    private function buildAlertEmailBody($alert, $product, $isEarlyReminder = false) {
        $unsubscribeUrl = Config::get('BASE_URL', 'https://contractwekker.nl') . "/unsubscribe?token=" . urlencode($alert['unsubscribe_token']);
        $settingsUrl = Config::get('BASE_URL', 'https://contractwekker.nl') . "/";
        
        $periodicText = '';
        if (!$alert['is_periodic']) {
            $periodicText = "
            <p style='margin: 20px 0; font-size: 14px; color: #666;'>
                Je ontvangt dit bericht eenmalig. Wil je het toch vaker instellen? 
                <a href='{$settingsUrl}' style='color: #4facfe; text-decoration: none;'>Dan kan dat hier</a>.
            </p>";
        }
        
        $html = "
        <!DOCTYPE html>
        <html lang='nl'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Contractwekker Alert</title>
        </head>
        <body style='font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; background-color: #f5f5f5;'>
            <div style='max-width: 600px; margin: 0 auto; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.1);'>
                <div style='background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; padding: 30px; text-align: center;'>
                    <h1 style='margin: 0; font-size: 28px; font-weight: 700;'>⏰ Contractwekker</h1>
                    <p style='margin: 10px 0 0 0; font-size: 18px; opacity: 0.9;'>Jouw reminder voor {$product['name']}</p>
                </div>
                
                <div style='padding: 30px;'>
                    <h2 style='color: #333; font-size: 24px; margin: 0 0 20px 0;'>Hoi! 👋</h2>
                    ";
        
        if ($isEarlyReminder) {
            $html .= "
                    <p style='font-size: 16px; margin: 20px 0;'>
                        Dit is een <strong>vroege herinnering</strong> voor je <strong>{$product['name']}</strong> contract. 
                        Je hoofdherinnering volgt over <strong>{$alert['early_reminder_days']} dagen</strong>. ⏰
                    </p>
                    
                    <p style='font-size: 16px; margin: 20px 0;'>
                        Nu is het perfecte moment om alvast te oriënteren op nieuwe opties en te vergelijken. 
                        Zo ben je goed voorbereid wanneer het tijd is om over te stappen! 💡
                    </p>";
        } else {
            $html .= "
                    <p style='font-size: 16px; margin: 20px 0;'>
                        Je hebt een tijdje geleden een contractwekker ingesteld voor <strong>{$product['name']}</strong>. 
                        Deze is nu afgegaan! ⏰
                    </p>
                    
                    <p style='font-size: 16px; margin: 20px 0;'>
                        Het is tijd om je contract te bekijken en eventueel over te stappen naar een betere deal. 
                        Vergelijk de nieuwste opties en bespaar geld! 💰
                    </p>";
        }
        
        $html .= "
                    
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='{$product['deeplink']}' 
                           style='display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important; color: white; text-decoration: none; padding: 15px 30px; border-radius: 8px; font-weight: 600; font-size: 16px;'>
                            🔍 Bekijk nieuwe {$product['name']} opties
                        </a>
                    </div>
                    
                    {$periodicText}
                    
                    <hr style='border: none; border-top: 1px solid #eee; margin: 30px 0;'>
                    
                    <p style='font-size: 14px; color: #666; margin: 20px 0;'>
                        Wil je geen herinneringen meer ontvangen? 
                        <a href='{$unsubscribeUrl}' style='color: #4facfe; text-decoration: none;'>Klik hier om je af te melden</a>.
                    </p>
                    
                    <p style='font-size: 12px; color: #999; margin: 20px 0 0 0; text-align: center;'>
                        Deze e-mail is verzonden door Contractwekker<br>
                        Een service om je te helpen geld te besparen op je contracten
                    </p>
                </div>
            </div>
        </body>
        </html>";
        
        return $html;
    }
    
    private function sendEmail($to, $subject, $htmlMessage) {
        // Create plain text version
        $plainMessage = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlMessage));
        
        // Headers
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $this->fromName . ' <' . $this->fromEmail . '>',
            'Reply-To: ' . $this->fromEmail,
            'X-Mailer: PHP/' . phpversion(),
            'X-Priority: 3',
        ];
        
        // Try to send using SMTP if configured, otherwise fall back to mail()
        if (!empty($this->smtpHost) && !empty($this->smtpUsername)) {
            return $this->sendSMTP($to, $subject, $htmlMessage, $headers);
        } else {
            return mail($to, $subject, $htmlMessage, implode("\r\n", $headers));
        }
    }
    
    private function sendSMTP($to, $subject, $message, $headers) {
        // Determine connection type based on SMTP_SECURE setting
        $connectionString = $this->smtpHost;
        
        // Handle SSL connection (usually port 465)
        if ($this->smtpSecure === 'ssl') {
            $connectionString = 'ssl://' . $this->smtpHost;
        }
        
        // Connect to SMTP server
        $socket = fsockopen($connectionString, $this->smtpPort, $errno, $errstr, 30);
        
        if (!$socket) {
            error_log("SMTP connection failed: $errno - $errstr");
            return false;
        }
        
        // Read initial server response
        $response = fgets($socket, 256);
        
        // Build command list based on security settings
        $commands = [];
        $commands[] = "EHLO " . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        
        // Handle TLS (STARTTLS) - typically for port 587
        if ($this->smtpSecure === 'tls') {
            $commands[] = "STARTTLS";
        }
        
        // Add authentication if credentials are provided
        if (!empty($this->smtpUsername) && !empty($this->smtpPassword)) {
            $commands[] = "AUTH LOGIN";
            $commands[] = base64_encode($this->smtpUsername);
            $commands[] = base64_encode($this->smtpPassword);
        }
        
        // Add email commands
        $commands[] = "MAIL FROM: <{$this->fromEmail}>";
        $commands[] = "RCPT TO: <{$to}>";
        $commands[] = "DATA";
        
        // Send commands
        foreach ($commands as $command) {
            // Handle STARTTLS command
            if ($command === "STARTTLS") {
                fwrite($socket, "$command\r\n");
                $response = fgets($socket, 256);
                
                if (substr($response, 0, 3) == '220') {
                    // Enable TLS encryption
                    stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                    // Send EHLO again after STARTTLS
                    fwrite($socket, "EHLO " . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "\r\n");
                    $response = fgets($socket, 256);
                }
                continue;
            }
            
            fwrite($socket, "$command\r\n");
            
            // Read response (multi-line for EHLO)
            if (strpos($command, 'EHLO') === 0) {
                do {
                    $response = fgets($socket, 256);
                } while (substr($response, 3, 1) == '-');
            } else {
                $response = fgets($socket, 256);
            }
            
            // Send email data after DATA command
            if ($command === "DATA") {
                if (substr($response, 0, 3) == '354') {
                    // Send email headers and body
                    $emailData = "Subject: {$subject}\r\n" . implode("\r\n", $headers) . "\r\n\r\n{$message}\r\n.";
                    fwrite($socket, "$emailData\r\n");
                    $response = fgets($socket, 256);
                }
            }
        }
        
        // Send QUIT command
        fwrite($socket, "QUIT\r\n");
        $response = fgets($socket, 256);
        
        fclose($socket);
        return true;
    }
}
?>