<?php
session_start();
require_once '../config.php';

// Debug logging
error_log("register.php accessed - Method: " . $_SERVER['REQUEST_METHOD'] . " URI: " . $_SERVER['REQUEST_URI']);

// CSRF protection
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = generateToken();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("register.php: Not a POST request, redirecting");
    header('Location: /');
    exit;
}

// Check honeypot
if (isBot($_POST['website'] ?? '')) {
    error_log("Bot detected: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    header('Location: /');
    exit;
}

// Validate and sanitize input
$errors = [];

$email = sanitizeInput($_POST['email'] ?? '');
if (!validateEmail($email)) {
    $errors[] = 'Ongeldig e-mailadres';
}

$productId = $_POST['product_id'] ?? '';
$customProductName = null;

if ($productId === 'other') {
    $customProductName = sanitizeInput($_POST['custom_product_name'] ?? '');
    if (empty($customProductName)) {
        $errors[] = 'Vul een naam in voor je contract';
    }
    $productId = null; // Set to null for custom products
} else {
    $productId = filter_var($productId, FILTER_VALIDATE_INT);
    if (!$productId) {
        $errors[] = 'Selecteer een geldig contracttype';
    }
}

$alertPeriod = $_POST['alert_period'] ?? '';
$validPeriods = ['1_month', '3_months', '1_year', '2_years', '3_years', 'custom'];
if (!in_array($alertPeriod, $validPeriods)) {
    $errors[] = 'Selecteer een geldige periode';
}

$endDate = null;
if ($alertPeriod === 'custom') {
    $endDate = $_POST['end_date'] ?? '';
    
    if (empty($endDate)) {
        $errors[] = 'Vul een einddatum in voor je contract';
    } else {
        $endDateObj = DateTime::createFromFormat('Y-m-d', $endDate);
        if (!$endDateObj || $endDateObj->format('Y-m-d') !== $endDate) {
            $errors[] = 'Vul een geldige einddatum in';
        } elseif ($endDateObj < new DateTime()) {
            $errors[] = 'Einddatum moet in de toekomst liggen';
        }
    }
}

$isPeriodic = isset($_POST['is_periodic']) && $_POST['is_periodic'] === '1';

// Early reminder is now enabled by default (opt-out instead of opt-in)
$disableEarlyReminder = isset($_POST['disable_early_reminder']) && $_POST['disable_early_reminder'] === '1';
$sendEarlyReminder = !$disableEarlyReminder; // Enabled by default unless user opts out

// Validate early reminder days
$earlyReminderDays = 60; // Default
if (isset($_POST['early_reminder_days'])) {
    $inputDays = filter_var($_POST['early_reminder_days'], FILTER_VALIDATE_INT);
    if ($inputDays !== false && $inputDays >= 1 && $inputDays <= 365) {
        $earlyReminderDays = $inputDays;
    } else {
        $errors[] = 'Aantal dagen moet tussen 1 en 365 zijn';
    }
}

if (!empty($errors)) {
    error_log("register.php: Validation errors: " . implode(', ', $errors));
    $_SESSION['errors'] = $errors;
    $_SESSION['form_data'] = $_POST;
    header('Location: /');
    exit;
}

try {
    $pdo = Config::getDatabaseConnection();
    
    // Check if product exists and get product name (only for non-custom products)
    $productName = null;
    if ($productId !== null) {
        $stmt = $pdo->prepare("SELECT id, name FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();
        if (!$product) {
            throw new Exception("Product niet gevonden");
        }
        $productName = $product['name'];
    } else {
        $productName = $customProductName;
    }
    
    // Calculate alert dates
    $firstAlertDate = calculateNextAlertDate($alertPeriod, null, null, $endDate);
    $nextAlertDate = $firstAlertDate;
    
    // Calculate early reminder date if enabled
    $earlyReminderDate = null;
    if ($sendEarlyReminder && $earlyReminderDays > 0) {
        $earlyReminderDate = date('Y-m-d', strtotime($nextAlertDate . ' -' . $earlyReminderDays . ' days'));
    }
    
    // Generate unsubscribe token
    $unsubscribeToken = generateToken();
    
    // Insert email alert
    $stmt = $pdo->prepare("
        INSERT INTO alerts 
        (email, product_id, custom_product_name, alert_period, end_date, is_periodic, send_early_reminder, early_reminder_days, early_reminder_date, first_alert_date, next_alert_date, unsubscribe_token) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $email,
        $productId,
        $customProductName,
        $alertPeriod,
        $endDate,
        $isPeriodic ? 1 : 0,
        $sendEarlyReminder ? 1 : 0,
        $earlyReminderDays,
        $earlyReminderDate,
        $firstAlertDate,
        $nextAlertDate,
        $unsubscribeToken
    ]);
    
    $_SESSION['success'] = 'Bedankt voor een contractwekker voor ' . htmlspecialchars($productName, ENT_QUOTES, 'UTF-8') . '! Je ontvangt je contractwekker per e-mail.';
    $_SESSION['show_disclaimer'] = true;
    header('Location: success');
    
} catch (Exception $e) {
    error_log("Registration error: " . $e->getMessage());
    $_SESSION['errors'] = ['Er is een fout opgetreden. Probeer het opnieuw.'];
    $_SESSION['form_data'] = $_POST;
    header('Location: /');
}
?>