<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo isset($meta_description) ? htmlspecialchars($meta_description) : 'Stel eenvoudig herinneringen in voor je contracten en ontvang op tijd een seintje om op te zeggen. Gratis, veilig en zonder gedoe.'; ?>">
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
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><a href="https://www.contractwekker.nl">⏰ Contractwekker</a></h1>
            <p><?php echo isset($header_subtitle) ? htmlspecialchars($header_subtitle) : 'Vergeet nooit meer je contract op te zeggen of over te stappen'; ?></p>
        </div>