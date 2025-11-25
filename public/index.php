<?php
/**
 * Central routing file - works with both Herd (local) and Apache (production)
 * Herd uses index.php as entry point, Apache can use either index.php or routes.php
 */

// Load rate limiter
require_once dirname(__DIR__) . '/RateLimiter.php';

// Initialize rate limiter for general page requests
$rateLimiter = new RateLimiter(
    dirname(__DIR__) . '/rate_limit_data',
    120,  // Default limit: 120 requests
    60    // Default window: 60 seconds (1 minute)
);

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
    'get_products' => 'get_products.php',
    'deploy-script222' => 'deploy-script222.php',
    'blog' => 'blog.php',
];

// Check if we're accessing a .php file directly (except api.php and get_products.php)
if (preg_match('/\.php$/', $path) && !in_array($path, ['api.php', 'get_products.php', 'deploy-script222.php'])) {
    // Redirect to clean URL
    $clean_url = preg_replace('/\.php$/', '', $path);
    header("Location: /$clean_url", true, 301);
    exit;
}

// Handle sitemap.xml
if ($path === 'sitemap.xml') {
    $file = __DIR__ . '/sitemap.php';
    if (file_exists($file)) {
        include $file;
    } else {
        http_response_code(404);
        echo "404 - Sitemap not found";
    }
    exit;
}

// Handle rss.xml
if ($path === 'rss.xml') {
    $file = __DIR__ . '/rss.php';
    if (file_exists($file)) {
        include $file;
    } else {
        http_response_code(404);
        echo "404 - RSS feed not found";
    }
    exit;
}

// Check for blog post slugs (e.g., /blog/post-slug)
if (preg_match('/^blog\/(.+)$/', $path, $matches)) {
    $blog_slug = $matches[1];
    // Apply rate limit for blog pages
    $rateLimiter->limit(null, 'page_blog', 120, 60);
    
    // Include blog.php and pass the slug
    $file = __DIR__ . '/blog.php';
    if (file_exists($file)) {
        $_GET['slug'] = $blog_slug;
        http_response_code(200);
        include $file;
    } else {
        http_response_code(404);
        echo "404 - Page not found";
    }
    exit;
}

// Check if the route exists
if (array_key_exists($path, $routes)) {
    // Apply different rate limits based on the page
    $pageLimits = [
        'register' => ['limit' => 5, 'window' => 300],      // 5 submissions per 5 minutes  
        'unsubscribe' => ['limit' => 10, 'window' => 300],  // 10 requests per 5 minutes
        'get_products' => ['limit' => 100, 'window' => 60], // 100 requests per minute
        'deploy-script222' => ['limit' => 1, 'window' => 3600], // 1 request per hour
    ];
    
    // Apply rate limit for the current page
    if (isset($pageLimits[$path])) {
        $limit = $pageLimits[$path]['limit'];
        $window = $pageLimits[$path]['window'];
    } else {
        // Default rate limit for general pages
        $limit = 120;
        $window = 60;
    }
    
    // Check rate limit (will automatically send 429 response if limit exceeded)
    $rateLimiter->limit(null, 'page_' . $path, $limit, $window);
    
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