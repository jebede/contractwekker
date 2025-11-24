<?php
/**
 * Google XML Sitemap Generator
 * Generates a sitemap.xml file with all published pages and blog posts
 */

require_once dirname(__DIR__) . '/config.php';

// Set XML content type
header('Content-Type: application/xml; charset=utf-8');

// Base URL
$base_url = 'https://contractwekker.nl';

// Get current date/time in W3C format
$lastmod = date('Y-m-d\TH:i:s+00:00');

try {
    $pdo = Config::getDatabaseConnection();
    
    // Get all published blog posts (where published_at <= NOW())
    $stmt = $pdo->prepare("
        SELECT slug, updated_at, published_at, created_at
        FROM blogs 
        WHERE published = 1 
        AND (published_at IS NULL OR published_at <= NOW())
        ORDER BY published_at DESC, created_at DESC
    ");
    $stmt->execute();
    $blog_posts = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Sitemap error: " . $e->getMessage());
    $blog_posts = [];
}

// Start XML output
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

// Static pages
$static_pages = [
    [
        'loc' => $base_url . '/',
        'priority' => '1.0',
        'changefreq' => 'weekly'
    ],
    [
        'loc' => $base_url . '/blog',
        'priority' => '0.9',
        'changefreq' => 'daily'
    ],
    [
        'loc' => $base_url . '/faq',
        'priority' => '0.7',
        'changefreq' => 'monthly'
    ],
    [
        'loc' => $base_url . '/privacy',
        'priority' => '0.5',
        'changefreq' => 'yearly'
    ],
    [
        'loc' => $base_url . '/contact',
        'priority' => '0.6',
        'changefreq' => 'monthly'
    ],
];

// Output static pages
foreach ($static_pages as $page) {
    echo "  <url>\n";
    echo "    <loc>" . htmlspecialchars($page['loc'], ENT_XML1, 'UTF-8') . "</loc>\n";
    echo "    <lastmod>" . $lastmod . "</lastmod>\n";
    echo "    <changefreq>" . $page['changefreq'] . "</changefreq>\n";
    echo "    <priority>" . $page['priority'] . "</priority>\n";
    echo "  </url>\n";
}

// Output blog posts
foreach ($blog_posts as $post) {
    $post_url = $base_url . '/blog/' . htmlspecialchars($post['slug'], ENT_XML1, 'UTF-8');
    
    // Determine lastmod date (use updated_at, published_at, or created_at)
    $post_lastmod = $lastmod;
    if ($post['updated_at']) {
        $post_lastmod = date('Y-m-d\TH:i:s+00:00', strtotime($post['updated_at']));
    } elseif ($post['published_at']) {
        $post_lastmod = date('Y-m-d\TH:i:s+00:00', strtotime($post['published_at']));
    } elseif ($post['created_at']) {
        $post_lastmod = date('Y-m-d\TH:i:s+00:00', strtotime($post['created_at']));
    }
    
    echo "  <url>\n";
    echo "    <loc>" . $post_url . "</loc>\n";
    echo "    <lastmod>" . $post_lastmod . "</lastmod>\n";
    echo "    <changefreq>weekly</changefreq>\n";
    echo "    <priority>0.8</priority>\n";
    echo "  </url>\n";
}

// Close XML
echo '</urlset>' . "\n";
?>

