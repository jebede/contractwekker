<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once dirname(__DIR__) . '/config.php';

try {
    $pdo = Config::getDatabaseConnection();
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

$action = $_GET['action'] ?? '';

switch($action) {
    case 'get_products':
        try {
            $stmt = $pdo->query("SELECT id, name FROM products ORDER BY name");
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($products);
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to fetch products']);
        }
        break;
        
    case 'create_alert':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        $product_id = $input['product_id'] ?? null;
        $custom_product_name = $input['custom_product_name'] ?? null;
        $alert_period = $input['alert_period'] ?? null;
        $end_date = $input['end_date'] ?? null;
        $is_periodic = $input['is_periodic'] ?? 0;
        $email = $input['email'] ?? null;
        $push_token = $input['push_token'] ?? null;
        
        // Validate required fields
        if (!$product_id || !$alert_period) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required fields']);
            exit;
        }
        
        // Ensure either email or push_token is provided
        if (!$email && !$push_token) {
            http_response_code(400);
            echo json_encode(['error' => 'Either email or push token is required']);
            exit;
        }
        
        try {
            // Calculate alert dates
            $first_alert_date = null;
            $next_alert_date = null;
            
            if ($alert_period === 'custom' && $end_date) {
                $first_alert_date = date('Y-m-d', strtotime($end_date . ' -1 month'));
                $next_alert_date = $first_alert_date;
            } else {
                $periods = [
                    '1_month' => '+1 month',
                    '3_months' => '+3 months', 
                    '1_year' => '+1 year',
                    '2_years' => '+2 years',
                    '3_years' => '+3 years'
                ];
                if (isset($periods[$alert_period])) {
                    $first_alert_date = date('Y-m-d', strtotime($periods[$alert_period]));
                    $next_alert_date = $first_alert_date;
                }
            }
            
            if (!$first_alert_date || !$next_alert_date) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid alert period']);
                exit;
            }
            
            // Generate unsubscribe token
            $unsubscribe_token = bin2hex(random_bytes(32));
            
            $stmt = $pdo->prepare("INSERT INTO alerts (product_id, custom_product_name, alert_period, first_alert_date, next_alert_date, is_periodic, email, push_token, unsubscribe_token, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            
            $params = [
                $product_id === 'other' ? null : $product_id,
                $custom_product_name,
                $alert_period,
                $first_alert_date,
                $next_alert_date,
                $is_periodic,
                $email,
                $push_token,
                $unsubscribe_token
            ];
            
            $stmt->execute($params);
            
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create alert']);
        }
        break;
        
    case 'get_alerts':
        $push_token = $_GET['push_token'] ?? null;
        $email = $_GET['email'] ?? null;
        
        if (!$push_token && !$email) {
            http_response_code(400);
            echo json_encode(['error' => 'Push token or email required']);
            exit;
        }
        
        try {
            if ($push_token) {
                // Get alerts by push token - don't expose email addresses
                $stmt = $pdo->prepare("
                    SELECT a.id, a.product_id, a.custom_product_name, a.alert_period, a.first_alert_date, 
                           a.next_alert_date, a.is_periodic, a.created_at, p.name as product_name
                    FROM alerts a 
                    LEFT JOIN products p ON a.product_id = p.id 
                    WHERE a.push_token = ? 
                    ORDER BY a.created_at DESC
                ");
                $stmt->execute([$push_token]);
            } else {
                // Get alerts by email - don't expose email addresses
                $stmt = $pdo->prepare("
                    SELECT a.id, a.product_id, a.custom_product_name, a.alert_period, a.first_alert_date, 
                           a.next_alert_date, a.is_periodic, a.created_at, p.name as product_name
                    FROM alerts a 
                    LEFT JOIN products p ON a.product_id = p.id 
                    WHERE a.email = ? 
                    ORDER BY a.created_at DESC
                ");
                $stmt->execute([$email]);
            }
            
            $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($alerts);
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to fetch alerts']);
        }
        break;
        
    case 'delete_alert':
        if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $alert_id = $input['id'] ?? null;
        $push_token = $input['push_token'] ?? null;
        
        if (!$alert_id || !$push_token) {
            http_response_code(400);
            echo json_encode(['error' => 'Alert ID and push token required']);
            exit;
        }
        
        try {
            $stmt = $pdo->prepare("DELETE FROM alerts WHERE id = ? AND push_token = ?");
            $result = $stmt->execute([$alert_id, $push_token]);
            
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true]);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Alert not found']);
            }
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to delete alert']);
        }
        break;
        
    case 'add_push_token':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $alert_id = $input['alert_id'] ?? null;
        $push_token = $input['push_token'] ?? null;
        
        if (!$alert_id || !$push_token) {
            http_response_code(400);
            echo json_encode(['error' => 'Alert ID and push token required']);
            exit;
        }
        
        try {
            // Update the existing alert to add the push token
            $stmt = $pdo->prepare("UPDATE alerts SET push_token = ? WHERE id = ?");
            $result = $stmt->execute([$push_token, $alert_id]);
            
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true]);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Alert not found']);
            }
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to add push token']);
        }
        break;
        
    case 'check_version':
        $client_version = $_GET['version'] ?? '0.0.0';
        $minimum_version = '0.0.1';
        
        // Simple version comparison
        $is_supported = version_compare($client_version, $minimum_version, '>=');
        
        echo json_encode([
            'client_version' => $client_version,
            'minimum_version' => $minimum_version,
            'is_supported' => $is_supported,
            'message' => $is_supported 
                ? 'App version is supported' 
                : 'App update required. Please update to version ' . $minimum_version . ' or higher.'
        ]);
        break;
        
    default:
        http_response_code(404);
        echo json_encode(['error' => 'Action not found']);
}
?>