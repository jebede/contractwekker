<?php
/**
 * RSS Feed Generator for Blog Posts
 * Generates an RSS 2.0 feed with Media RSS extension for images
 * Includes the latest 20 published blog posts with excerpts and images
 */

require_once dirname(__DIR__) . '/config.php';

// Set RSS content type
header('Content-Type: application/rss+xml; charset=utf-8');

// Base URL
$base_url = 'https://contractwekker.nl';
$site_name = 'Contractwekker';
$site_description = 'Lees onze blog artikelen over contracten, verzekeringen en geld besparen.';

try {
    $pdo = Config::getDatabaseConnection();
    
    // Get latest 20 published blog posts
    $stmt = $pdo->prepare("
        SELECT id, title, slug, excerpt, content, header_image, header_image_mime, 
               published_at, created_at, updated_at
        FROM blogs 
        WHERE published = 1 
        AND (published_at IS NULL OR published_at <= NOW())
        ORDER BY COALESCE(published_at, created_at) DESC, created_at DESC
        LIMIT 20
    ");
    $stmt->execute();
    $blog_posts = $stmt->fetchAll();
    
    // Replace {YEAR} with current year in all posts
    $current_year = date('Y');
    foreach ($blog_posts as &$post) {
        $post['title'] = str_replace('{YEAR}', $current_year, $post['title']);
        $post['excerpt'] = $post['excerpt'] ? str_replace('{YEAR}', $current_year, $post['excerpt']) : null;
    }
    unset($post);
    
} catch (Exception $e) {
    error_log("RSS feed error: " . $e->getMessage());
    $blog_posts = [];
}

// Get the most recent post date for lastBuildDate
$last_build_date = date('r');
if (!empty($blog_posts)) {
    $most_recent = $blog_posts[0];
    $pub_date = $most_recent['published_at'] ?? $most_recent['created_at'];
    if ($pub_date) {
        $date_obj = new DateTime($pub_date);
        $last_build_date = $date_obj->format('r');
    }
}

// Start RSS XML output
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<rss version="2.0" xmlns:media="http://search.yahoo.com/mrss/" xmlns:content="http://purl.org/rss/1.0/modules/content/">
<channel>
    <title><?php echo htmlspecialchars($site_name . ' Blog', ENT_XML1, 'UTF-8'); ?></title>
    <link><?php echo htmlspecialchars($base_url . '/blog', ENT_XML1, 'UTF-8'); ?></link>
    <description><?php echo htmlspecialchars($site_description, ENT_XML1, 'UTF-8'); ?></description>
    <language>nl-NL</language>
    <lastBuildDate><?php echo htmlspecialchars($last_build_date, ENT_XML1, 'UTF-8'); ?></lastBuildDate>
    <pubDate><?php echo htmlspecialchars($last_build_date, ENT_XML1, 'UTF-8'); ?></pubDate>
    <ttl>60</ttl>
    <image>
        <url><?php echo htmlspecialchars($base_url . '/images/icon-cw-512.png', ENT_XML1, 'UTF-8'); ?></url>
        <title><?php echo htmlspecialchars($site_name, ENT_XML1, 'UTF-8'); ?></title>
        <link><?php echo htmlspecialchars($base_url . '/blog', ENT_XML1, 'UTF-8'); ?></link>
    </image>

<?php foreach ($blog_posts as $post): ?>
    <?php
    // Build post URL
    $post_url = $base_url . '/blog/' . htmlspecialchars($post['slug'], ENT_XML1, 'UTF-8');
    
    // Get publication date
    $pub_date = $post['published_at'] ?? $post['created_at'];
    $pub_date_formatted = date('r', strtotime($pub_date));
    
    // Prepare description (excerpt or first part of content)
    $description = '';
    if ($post['excerpt']) {
        $description = strip_tags($post['excerpt']);
    } else {
        $description = strip_tags($post['content']);
        // Limit to 300 characters
        if (mb_strlen($description) > 300) {
            $description = mb_substr($description, 0, 300) . '...';
        }
    }
    
    // Prepare full content with image for content:encoded
    $full_content = '';
    if ($post['header_image']) {
        // Handle both complete data URI and base64-only formats
        $image_data = $post['header_image'];
        $image_url = '';
        
        if (strpos($image_data, 'data:') === 0) {
            // Already a complete data URI
            $image_url = $image_data;
        } else {
            // Just base64, construct the data URI
            $mime_type = $post['header_image_mime'] ?? 'image/jpeg';
            $image_url = 'data:' . $mime_type . ';base64,' . $image_data;
        }
        
        $full_content .= '<p><img src="' . htmlspecialchars($image_url, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') . '" style="max-width: 100%; height: auto;" /></p>';
    }
    
    if ($post['excerpt']) {
        $full_content .= $post['excerpt'];
    }
    
    // Get image MIME type and size for media:content
    $image_mime = $post['header_image_mime'] ?? 'image/jpeg';
    $image_size = 0;
    if ($post['header_image']) {
        $image_data = $post['header_image'];
        // If it's base64, calculate size
        if (strpos($image_data, 'data:') === 0) {
            // Extract base64 part from data URI
            $base64_part = substr($image_data, strpos($image_data, ',') + 1);
            $image_size = strlen(base64_decode($base64_part));
        } else {
            $image_size = strlen(base64_decode($image_data));
        }
    }
    ?>
    <item>
        <title><?php echo htmlspecialchars($post['title'], ENT_XML1, 'UTF-8'); ?></title>
        <link><?php echo htmlspecialchars($post_url, ENT_XML1, 'UTF-8'); ?></link>
        <guid isPermaLink="true"><?php echo htmlspecialchars($post_url, ENT_XML1, 'UTF-8'); ?></guid>
        <description><?php echo htmlspecialchars($description, ENT_XML1, 'UTF-8'); ?></description>
        <pubDate><?php echo htmlspecialchars($pub_date_formatted, ENT_XML1, 'UTF-8'); ?></pubDate>
        <author><?php echo htmlspecialchars($site_name, ENT_XML1, 'UTF-8'); ?></author>
        
        <?php if ($post['header_image']): ?>
            <?php
            $image_data = $post['header_image'];
            if (strpos($image_data, 'data:') === 0) {
                $image_url = $image_data;
            } else {
                $mime_type = $post['header_image_mime'] ?? 'image/jpeg';
                $image_url = 'data:' . $mime_type . ';base64,' . $image_data;
            }
            ?>
            <media:content url="<?php echo htmlspecialchars($image_url, ENT_XML1, 'UTF-8'); ?>" type="<?php echo htmlspecialchars($image_mime, ENT_XML1, 'UTF-8'); ?>" medium="image">
                <media:title><?php echo htmlspecialchars($post['title'], ENT_XML1, 'UTF-8'); ?></media:title>
                <?php if ($image_size > 0): ?>
                <media:fileSize><?php echo $image_size; ?></media:fileSize>
                <?php endif; ?>
            </media:content>
            <enclosure url="<?php echo htmlspecialchars($image_url, ENT_XML1, 'UTF-8'); ?>" type="<?php echo htmlspecialchars($image_mime, ENT_XML1, 'UTF-8'); ?>" length="<?php echo $image_size; ?>" />
        <?php endif; ?>
        
        <?php if ($full_content): ?>
        <content:encoded><![CDATA[<?php echo $full_content; ?>]]></content:encoded>
        <?php endif; ?>
    </item>
<?php endforeach; ?>

</channel>
</rss>
