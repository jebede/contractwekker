<?php
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once '../config.php';

try {
    $pdo = Config::getDatabaseConnection();
    
    $stmt = $pdo->prepare("SELECT id, name FROM products ORDER BY id ASC");
    $stmt->execute();
    $products = $stmt->fetchAll();
    
    echo json_encode($products, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log("Error fetching products: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Unable to fetch products']);
}
?>