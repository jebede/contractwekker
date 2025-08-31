<?php
session_start();

if (!isset($_SESSION['success'])) {
    header('Location: /');
    exit;
}

$message = $_SESSION['success'];
$showDisclaimer = $_SESSION['show_disclaimer'] ?? false;
unset($_SESSION['success']);
unset($_SESSION['show_disclaimer']);
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contractwekker - Succes!</title>
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

        .success-container {
            background: white;
            border-radius: 20px;
            padding: 60px 40px;
            text-align: center;
            max-width: 500px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }

        .success-icon {
            font-size: 4rem;
            margin-bottom: 20px;
        }

        h1 {
            color: #28a745;
            font-size: 2rem;
            margin-bottom: 20px;
        }

        p {
            color: #666;
            font-size: 1.1rem;
            line-height: 1.6;
            margin-bottom: 30px;
        }
        
        .disclaimer {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
            text-align: left;
        }
        
        .disclaimer strong {
            color: #495057;
        }

        .back-btn {
            display: inline-block;
            padding: 12px 30px;
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(79, 172, 254, 0.3);
        }
    </style>
</head>
<body>
    <div class="success-container">
        <div class="success-icon">✅</div>
        <h1>Gelukt!</h1>
        <p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
        
        <?php if ($showDisclaimer): ?>
        <div class="disclaimer">
            <p><strong>Let op:</strong> We doen ons best om ervoor te zorgen dat je tijdig bericht ontvangt, maar het kan voorkomen dat een e-mail niet aankomt (bijv. door spamfilters). Voeg daarom ook <strong>noreply@contractwekker.nl</strong> toe aan je contacten. Houd daarnaast zelf altijd je contracten in de gaten als extra zekerheid.</p>
        </div>
        <?php endif; ?>
        
        <a href="/" class="back-btn">Nieuwe wekker instellen</a>
    </div>
</body>
</html>