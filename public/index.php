<?php
/**
 * Central routing file - works with both Herd (local) and Apache (production)
 * Herd uses index.php as entry point, Apache can use either index.php or routes.php
 */

// Get the requested path
$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);

// Remove leading slash and trailing slash if present
$path = trim($path, '/');

// Define available routes (mapping clean URLs to PHP files)
$routes = [
    '' => 'home.php',              // Homepage
    'contact' => 'contact.php',
    'faq' => 'faq.php',
    'privacy' => 'privacy.php',
    'register' => 'register.php',
    'success' => 'success.php',
    'unsubscribe' => 'unsubscribe.php',
];

// Check if we're accessing a .php file directly (except api.php and get_products.php)
if (preg_match('/\.php$/', $path) && !in_array($path, ['api.php', 'get_products.php', 'deploy-script222.php'])) {
    // Redirect to clean URL
    $clean_url = preg_replace('/\.php$/', '', $path);
    header("Location: /$clean_url", true, 301);
    exit;
}

// Check if the route exists
if (array_key_exists($path, $routes)) {
    // Include the corresponding PHP file
    $file = __DIR__ . '/' . $routes[$path];
    
    if (file_exists($file)) {
        // Set proper status code
        http_response_code(200);
        
        // Include the file
        include $file;
    } else {
        // File not found
        http_response_code(404);
        echo "404 - Page not found";
    }
} else {
    // Check if it's a direct file request that should be served
    $file_path = __DIR__ . '/' . $path;
    if (file_exists($file_path) && is_file($file_path)) {
        // Let the server handle static files
        return false;
    }
    
    // Route not found - show 404
    http_response_code(404);
    echo "404 - Page not found";
}