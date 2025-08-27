<?php
require_once '../config.php';

$token = $_GET['token'] ?? '';
$success = false;
$error = '';

if (empty($token)) {
    $error = 'Ongeldige afmeldlink';
} else {
    try {
        $pdo = Config::getDatabaseConnection();
        
        // Find the alert by token
        $stmt = $pdo->prepare("
            SELECT a.*, p.name as product_name 
            FROM alerts a 
            LEFT JOIN products p ON a.product_id = p.id 
            WHERE a.unsubscribe_token = ? AND a.is_active = 1
        ");
        $stmt->execute([$token]);
        $alert = $stmt->fetch();
        
        if (!$alert) {
            $error = 'Afmeldlink niet gevonden of al gebruikt';
        } else {
            // Deactivate the alert
            $updateStmt = $pdo->prepare("
                UPDATE alerts 
                SET is_active = 0, updated_at = NOW() 
                WHERE unsubscribe_token = ?
            ");
            $updateStmt->execute([$token]);
            
            $success = true;
        }
        
    } catch (Exception $e) {
        error_log("Unsubscribe error: " . $e->getMessage());
        $error = 'Er is een fout opgetreden bij het afmelden';
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contractwekker - Afmelden</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #ffffff 0%, #b2c5c9 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .unsubscribe-container {
            background: white;
            border-radius: 20px;
            padding: 60px 40px;
            text-align: center;
            max-width: 500px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }

        .icon {
            font-size: 4rem;
            margin-bottom: 20px;
        }

        .success-icon {
            color: #28a745;
        }

        .error-icon {
            color: #dc3545;
        }

        h1 {
            font-size: 2rem;
            margin-bottom: 20px;
            color: #333;
        }

        .success h1 {
            color: #28a745;
        }

        .error h1 {
            color: #dc3545;
        }

        p {
            color: #666;
            font-size: 1.1rem;
            line-height: 1.6;
            margin-bottom: 30px;
        }

        .product-name {
            font-weight: bold;
            color: #4facfe;
        }

        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
            margin: 10px;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(79, 172, 254, 0.3);
        }

        .btn-secondary {
            background: #6c757d;
        }

        .btn-secondary:hover {
            box-shadow: 0 10px 20px rgba(108, 117, 125, 0.3);
        }
    </style>
</head>
<body>
    <div class="unsubscribe-container">
        <?php if ($success): ?>
            <div class="success">
                <div class="icon success-icon">✅</div>
                <h1>Succesvol afgemeld</h1>
                <p>
                    Je bent succesvol afgemeld voor contractherinneringen voor 
                    <span class="product-name"><?= htmlspecialchars($alert['product_name'] ?: $alert['custom_product_name'], ENT_QUOTES, 'UTF-8') ?></span>.
                </p>
                <p>Je ontvangt geen verdere e-mails meer voor dit contract.</p>
                <a href="index.html" class="btn">Nieuwe wekker instellen</a>
            </div>
        <?php else: ?>
            <div class="error">
                <div class="icon error-icon">❌</div>
                <h1>Afmelden mislukt</h1>
                <p><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
                <a href="index.html" class="btn btn-secondary">Terug naar home</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>