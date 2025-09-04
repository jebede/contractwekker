<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo isset($meta_description) ? htmlspecialchars($meta_description) : 'Stel eenvoudig herinneringen in voor je contracten en ontvang op tijd een seintje om op te zeggen. Gratis, veilig en zonder gedoe.'; ?>">
    <link rel="icon" type="image/png" href="/images/icons/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="/images/icons/favicon.svg" />
    <link rel="shortcut icon" href="/images/icons/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="/images/icons/apple-touch-icon.png" />
    <meta name="apple-mobile-web-app-title" content="Contractwekker" />
    <link rel="manifest" href="/images/icons/site.webmanifest" />
    <link rel="canonical" href="<?php echo isset($canonical_url) ? htmlspecialchars($canonical_url) : 'https://contractwekker.nl'; ?>">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) : 'Contractwekker - Tijdig contract opzeggen of overstappen'; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            color: #333;
            background: linear-gradient(135deg, #ffffff 0%, #b2c5c9 100%);
            min-height: 100vh;
            padding: 20px;
        }

        h1 a {
            color: white;
            text-decoration: none;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }

        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            font-weight: 700;
        }

        .header p {
            font-size: 1.2rem;
            opacity: 0.9;
            color: #fff;
        }

        .form-container, .content-container {
            padding: 40px 30px;
        }

        .alert {
            max-width: 600px;
            margin: 20px auto;
            padding: 15px 20px;
            border-radius: 12px;
            border: 1px solid;
            animation: slideIn 0.3s ease;
        }

        .alert-error {
            background-color: #fee;
            border-color: #fcc;
            color: #c33;
        }

        .alert-success {
            background-color: #efe;
            border-color: #cfc;
            color: #3a3;
        }

        .alert p {
            margin: 0;
            padding: 5px 0;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .form-group {
            margin-bottom: 25px;
        }

        label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #555;
            font-size: 0.95rem;
        }

        select, input[type="text"], input[type="email"], input[type="number"], input[type="date"] {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e1e5e9;
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #fafbfc;
        }

        select:focus, input:focus {
            outline: none;
            border-color: #4facfe;
            background: white;
            box-shadow: 0 0 0 3px rgba(79, 172, 254, 0.1);
        }

        .custom-period {
            display: none;
            margin-top: 15px;
        }

        .custom-period.show {
            display: block;
            margin-top: 15px;
        }

        .custom-period input {
            flex: 1;
        }

        .custom-period select {
            flex: 1;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 12px;
            border: 2px solid #e1e5e9;
            transition: all 0.3s ease;
        }

        .checkbox-group:hover {
            background: #e9ecef;
        }

        .checkbox-group input[type="checkbox"] {
            width: 20px;
            height: 20px;
            accent-color: #4facfe;
        }

        .checkbox-group label {
            margin: 0;
            font-weight: 500;
            cursor: pointer;
        }

        .periodic-text {
            font-size: 0.9rem;
            color: #666;
            margin-top: 5px;
        }

        .service-info-box {
            background: linear-gradient(135deg, #f8f9ff 0%, #f0f4ff 100%);
            border: 2px solid #e1e5f0;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .service-info-box p {
            margin-bottom: 10px;
        }

        .summary-box {
            background: linear-gradient(135deg, #f8f9ff 0%, #f0f4ff 100%);
            border: 2px solid #e1e5f0;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
        }

        .summary-text {
            flex: 1;
            font-size: 1rem;
            line-height: 1.5;
            color: #333;
        }

        .edit-button {
            background: #4facfe;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            flex-shrink: 0;
            transition: all 0.3s ease;
        }

        .edit-button:hover {
            background: #3d8bfe;
            transform: translateY(-1px);
        }

        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .modal-content {
            background: white;
            border-radius: 20px;
            padding: 30px;
            width: 90%;
            max-width: 500px;
            max-height: 80vh;
            overflow-y: auto;
        }

        .modal-content h3 {
            margin: 0 0 25px 0;
            text-align: center;
            font-size: 1.4rem;
            color: #333;
        }

        .modal-section {
            margin-bottom: 25px;
        }

        .modal-section h4 {
            font-size: 1.1rem;
            margin-bottom: 12px;
            color: #555;
            font-weight: 600;
        }

        .modal-option {
            background: #f8f9fa;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .modal-option:hover {
            background: #e9ecef;
        }

        .modal-option.selected {
            background: #e6f3ff;
            border-color: #4facfe;
        }

        .modal-option.selected span {
            color: #4facfe;
            font-weight: 600;
        }

        .modal-buttons {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }

        .modal-button {
            flex: 1;
            padding: 15px;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.3s ease;
        }

        .modal-button.primary {
            background: #4facfe;
            color: white;
        }

        .modal-button.primary:hover {
            background: #3d8bfe;
        }

        .modal-button.secondary {
            background: #f8f9fa;
            color: #666;
            border: 1px solid #e1e5e9;
        }

        .modal-button.secondary:hover {
            background: #e9ecef;
        }

        .days-input-wrapper {
            display: flex;
            align-items: center;
            background: white;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            padding: 8px 12px;
            max-width: 120px;
            margin-top: 8px;
        }

        .days-input-wrapper input[type="number"] {
            border: none;
            background: none;
            padding: 4px;
            font-size: 1rem;
            text-align: center;
            width: 50px;
            margin: 0;
        }

        .days-input-wrapper input[type="number"]:focus {
            box-shadow: none;
            border: none;
            outline: none;
        }

        .days-input-wrapper span {
            font-size: 0.9rem;
            color: #666;
            margin-left: 8px;
        }

        .submit-btn, .btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #eaa866 0%, #ff7d04 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 20px;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .submit-btn:hover, .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        .submit-btn:active, .btn:active {
            transform: translateY(0);
        }

        .honeypot {
            position: absolute;
            left: -9999px;
            width: 1px;
            height: 1px;
            overflow: hidden;
        }

        .custom-select {
            position: relative;
        }

        .custom-select::after {
            content: '▼';
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            pointer-events: none;
            color: #666;
            font-size: 0.8rem;
        }

        select {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            cursor: pointer;
        }


        /* Content styles */
        h2 {
            color: #333;
            margin-bottom: 20px;
            font-size: 1.8rem;
        }

        h3 {
            color: #555;
            margin-bottom: 15px;
            margin-top: 25px;
            font-size: 1.3rem;
        }

        p {
            margin-bottom: 15px;
            color: #666;
        }

        ul {
            margin-bottom: 15px;
            padding-left: 20px;
        }

        li {
            margin-bottom: 5px;
            color: #666;
        }

        /* Meer info footer styles */
        .footer-nav {
            text-align: center;
            padding: 20px 0;
            color: #666;
        }

        .footer-nav a,
        .footer-nav button {
            color: #666;
            text-decoration: none;
            margin: 0 15px;
            font-size: 0.9rem;
        }

        .footer-nav button {
            background: none;
            border: none;
            cursor: pointer;
            font-family: inherit;
        }

        .footer-nav button:hover {
            color: #4facfe;
        }

        .meer-info-content {
            max-width: 800px;
            margin: 50px auto 0;
            background-color: #f8f9fa;
            border-top: 1px solid #e9ecef;
            border-radius: 15px;
            text-align: left;
            line-height: 1.6;
            color: #333;
        }
        
        .meer-info-inner {
            padding: 30px 20px;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.5s ease;
        }

        .meer-info-content.expanded .meer-info-inner {
            max-height: 2000px;
        }

        .meer-info-content h3 {
            color: #2c3e50;
            margin-bottom: 20px;
            text-align: center;
        }

        .meer-info-content h4 {
            color: #34495e;
            margin-bottom: 10px;
            margin-top: 25px;
        }

        .meer-info-content .section {
            margin-bottom: 25px;
        }

        .contract-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }

        .contract-item {
            margin: 5px 0;
        }

        .voordelen-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 15px;
        }

        .voordeel-card {
            background: white;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid;
        }

        .voordeel-card.besparen {
            border-left-color: #3498db;
        }

        .voordeel-card.tijd {
            border-left-color: #27ae60;
        }

        .voordeel-card.timing {
            border-left-color: #e74c3c;
        }

        .voordeel-card.veilig {
            border-left-color: #9b59b6;
        }

        .voordeel-card p {
            margin: 0;
            color: #333;
        }

        .meer-info-footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
        }

        .meer-info-footer p {
            font-style: italic;
            color: #666;
            margin: 0;
        }

        @media (max-width: 768px) {
            .container {
                margin: 10px;
                border-radius: 15px;
            }
            
            .header h1 {
                font-size: 2rem;
            }
            
            .form-container, .content-container {
                padding: 30px 20px;
            }

            .footer-nav a,
            .footer-nav button {
                margin: 0 8px;
                font-size: 0.85rem;
            }

            .meer-info-content {
                padding: 20px 15px;
            }

            .contract-grid {
                grid-template-columns: 1fr;
            }

            .voordelen-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <?php include __DIR__ . '/matomo.php'; ?>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><a href="https://www.contractwekker.nl">⏰ Contractwekker</a></h1>
            <p><?php echo isset($header_subtitle) ? htmlspecialchars($header_subtitle) : 'Vergeet nooit meer je contract op te zeggen of over te stappen'; ?></p>
        </div>