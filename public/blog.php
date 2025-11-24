<?php
session_start();
require_once '../config.php';

// Set flag to hide "Over Contractwekker" section on blog pages
$hide_meer_info = true;

// Get blog slug from query parameter or URL
$slug = $_GET['slug'] ?? null;

try {
    $pdo = Config::getDatabaseConnection();
    
    if ($slug) {
        // Individual blog post
        $stmt = $pdo->prepare("
            SELECT id, title, slug, excerpt, content, header_image, header_image_mime, 
                   published_at, created_at, updated_at
            FROM blogs 
            WHERE slug = ? AND published = 1
        ");
        $stmt->execute([$slug]);
        $post = $stmt->fetch();
        
        if (!$post) {
            http_response_code(404);
            $page_title = 'Blog post niet gevonden - Contractwekker';
            $meta_description = 'De gevraagde blog post kon niet worden gevonden.';
            $canonical_url = 'https://contractwekker.nl/blog';
            $header_subtitle = 'Blog';
            include 'views/header.php';
            echo '<div class="content-container"><h2>Blog post niet gevonden</h2><p>De gevraagde blog post bestaat niet of is niet gepubliceerd.</p><p><a href="/blog">← Terug naar blog overzicht</a></p></div>';
            include 'views/footer.php';
            exit;
        }
        
        // Replace {YEAR} with current year in post data
        $current_year = date('Y');
        $post['title'] = str_replace('{YEAR}', $current_year, $post['title']);
        $post['excerpt'] = $post['excerpt'] ? str_replace('{YEAR}', $current_year, $post['excerpt']) : null;
        $post['content'] = str_replace('{YEAR}', $current_year, $post['content']);
        
        // Set page variables for individual post
        $page_title = htmlspecialchars($post['title']) . ' - Contractwekker Blog';
        $meta_description = htmlspecialchars($post['excerpt'] ?? substr(strip_tags($post['content']), 0, 160));
        $canonical_url = 'https://contractwekker.nl/blog/' . htmlspecialchars($post['slug']);
        $header_subtitle = 'Blog';
        $header_subtitle_link = '/blog'; // Make "Blog" clickable to go back to index
        
        // Get header image if present (can be stored as complete data URI or just base64)
        $header_image_url = null;
        if ($post['header_image']) {
            $image_data = $post['header_image'];
            // Check if it's already a complete data URI
            if (strpos($image_data, 'data:') === 0) {
                // Already a complete data URI, use it directly
                $header_image_url = $image_data;
            } else {
                // Just base64, construct the data URI
                $mime_type = $post['header_image_mime'] ?? 'image/jpeg';
                $header_image_url = 'data:' . $mime_type . ';base64,' . $image_data;
            }
        }
        
    } else {
        // Blog listing page
        $stmt = $pdo->prepare("
            SELECT id, title, slug, excerpt, content, header_image, header_image_mime, 
                   published_at, created_at
            FROM blogs 
            WHERE published = 1 
            ORDER BY published_at DESC, created_at DESC
        ");
        $stmt->execute();
        $posts = $stmt->fetchAll();
        
        // Replace {YEAR} with current year in all posts
        $current_year = date('Y');
        foreach ($posts as &$post) {
            $post['title'] = str_replace('{YEAR}', $current_year, $post['title']);
            $post['excerpt'] = $post['excerpt'] ? str_replace('{YEAR}', $current_year, $post['excerpt']) : null;
        }
        unset($post); // Unset reference
        
        $page_title = 'Blog - Contractwekker';
        $meta_description = 'Lees onze blog artikelen over contracten, verzekeringen en geld besparen.';
        $canonical_url = 'https://contractwekker.nl/blog';
        $header_subtitle = 'Blog';
        $header_subtitle_link = null; // No link needed on index page
    }
    
} catch (Exception $e) {
    error_log("Blog error: " . $e->getMessage());
    http_response_code(500);
    $page_title = 'Fout - Contractwekker Blog';
    $meta_description = 'Er is een fout opgetreden bij het laden van de blog.';
    $canonical_url = 'https://contractwekker.nl/blog';
    $header_subtitle = 'Blog';
    include 'views/header.php';
    echo '<div class="content-container"><h2>Fout</h2><p>Er is een fout opgetreden bij het laden van de blog. Probeer het later opnieuw.</p></div>';
    include 'views/footer.php';
    exit;
}

// Include header
include 'views/header.php';
?>

<style>
    /* Blog-specific styles - Medium-inspired layout */
    .blog-container {
        max-width: 680px;
        margin: 0 auto;
        background: white;
        box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        overflow: hidden;
    }

    /* Blog post header with background image */
    .blog-header {
        position: relative;
        width: 100%;
        height: 500px;
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
        display: flex;
        align-items: flex-end;
        padding: 40px;
        color: white;
    }

    .blog-header::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: linear-gradient(to bottom, rgba(0,0,0,0) 0%, rgba(0,0,0,0.3) 50%, rgba(0,0,0,0.7) 100%);
    }

    .blog-header-content {
        position: relative;
        z-index: 1;
        width: 100%;
    }

    .blog-header h1 {
        font-size: 2.5rem;
        font-weight: 700;
        margin-bottom: 15px;
        line-height: 1.2;
        text-shadow: 0 2px 10px rgba(0,0,0,0.5);
    }

    .blog-meta {
        font-size: 0.95rem;
        opacity: 0.9;
        margin-top: 10px;
    }

    /* Blog content area */
    .blog-content {
        padding: 60px 40px;
        line-height: 1.8;
        font-size: 1.1rem;
        color: #333;
    }

    .blog-content h2 {
        font-size: 1.8rem;
        margin-top: 40px;
        margin-bottom: 20px;
        font-weight: 700;
        color: #2c3e50;
    }

    .blog-content h3 {
        font-size: 1.5rem;
        margin-top: 30px;
        margin-bottom: 15px;
        font-weight: 600;
        color: #34495e;
    }

    .blog-content p {
        margin-bottom: 20px;
        color: #555;
    }

    .blog-content ul, .blog-content ol {
        margin-bottom: 20px;
        padding-left: 30px;
    }

    .blog-content li {
        margin-bottom: 10px;
        color: #555;
    }

    .blog-content a {
        color: #4facfe;
        text-decoration: underline;
    }

    .blog-content a:hover {
        color: #3d8bfe;
    }

    /* Blog listing styles */
    .blog-list {
        padding: 40px;
    }

    .blog-list-item {
        margin-bottom: 60px;
        padding-bottom: 60px;
        border-bottom: 1px solid #e1e5e9;
    }

    .blog-list-item:last-child {
        border-bottom: none;
        margin-bottom: 0;
        padding-bottom: 0;
    }

    .blog-list-item-header {
        position: relative;
        width: 100%;
        height: 400px;
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
        border-radius: 12px;
        margin-bottom: 30px;
        overflow: hidden;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .blog-list-item-header::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: linear-gradient(to bottom, rgba(0,0,0,0) 0%, rgba(0,0,0,0.4) 100%);
        transition: background 0.3s ease;
    }

    a:hover .blog-list-item-header {
        transform: scale(1.02);
        box-shadow: 0 8px 25px rgba(0,0,0,0.2);
    }

    a:hover .blog-list-item-header::before {
        background: linear-gradient(to bottom, rgba(0,0,0,0.1) 0%, rgba(0,0,0,0.5) 100%);
    }

    .blog-list-item h2 {
        font-size: 2rem;
        font-weight: 700;
        margin-bottom: 15px;
        line-height: 1.3;
    }

    .blog-list-item h2 a {
        color: #2c3e50;
        text-decoration: none;
        transition: color 0.3s ease;
    }

    .blog-list-item h2 a:hover {
        color: #4facfe;
    }

    .blog-list-item .excerpt {
        font-size: 1.1rem;
        line-height: 1.7;
        color: #666;
        margin-bottom: 20px;
    }

    .blog-list-item .read-more {
        color: #4facfe;
        text-decoration: none;
        font-weight: 600;
        font-size: 1rem;
    }

    .blog-list-item .read-more:hover {
        text-decoration: underline;
    }

    /* CTA Box */
    .blog-cta {
        background: linear-gradient(135deg, #f8f9ff 0%, #f0f4ff 100%);
        border: 2px solid #e1e5f0;
        border-radius: 16px;
        padding: 40px;
        margin-top: 60px;
        text-align: center;
    }

    .blog-cta h3 {
        font-size: 1.8rem;
        margin-bottom: 15px;
        color: #2c3e50;
        margin-top: 0;
    }

    .blog-cta p {
        font-size: 1.1rem;
        color: #666;
        margin-bottom: 25px;
        line-height: 1.6;
    }

    .blog-cta .cta-button {
        display: inline-block;
        padding: 16px 40px;
        background: linear-gradient(135deg, #eaa866 0%, #ff7d04 100%);
        color: white;
        text-decoration: none;
        border-radius: 12px;
        font-size: 1.1rem;
        font-weight: 600;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(234, 168, 102, 0.3);
    }

    .blog-cta .cta-button:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(234, 168, 102, 0.4);
    }

    /* Empty state */
    .blog-empty {
        text-align: center;
        padding: 60px 40px;
        color: #666;
    }

    .blog-empty h2 {
        font-size: 2rem;
        margin-bottom: 15px;
        color: #333;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .blog-header {
            height: 350px;
            padding: 30px 20px;
        }

        .blog-header h1 {
            font-size: 1.8rem;
        }

        .blog-content {
            padding: 40px 20px;
            font-size: 1rem;
        }

        .blog-content h2 {
            font-size: 1.5rem;
        }

        .blog-content h3 {
            font-size: 1.3rem;
        }

        .blog-list {
            padding: 30px 20px;
        }

        .blog-list-item-header {
            height: 250px;
        }

        .blog-list-item h2 {
            font-size: 1.6rem;
        }

        .blog-cta {
            padding: 30px 20px;
        }

        .blog-cta h3 {
            font-size: 1.5rem;
        }

        .blog-cta .cta-button {
            padding: 14px 30px;
            font-size: 1rem;
        }
    }
</style>

<div class="blog-container">
    <?php if ($slug && $post): ?>
        <!-- Individual blog post -->
        <div class="blog-header" style="<?php if ($header_image_url): ?>background-image: url('<?php echo htmlspecialchars($header_image_url, ENT_QUOTES); ?>');<?php else: ?>background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);<?php endif; ?>">
            <div class="blog-header-content">
                <h1><?php echo htmlspecialchars($post['title']); ?></h1>
                <?php if ($post['published_at']): ?>
                    <div class="blog-meta">
                        <?php 
                        $date = new DateTime($post['published_at']);
                        echo $date->format('d F Y');
                        ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="blog-content">
            <?php echo $post['content']; ?>
        </div>

        <!-- CTA Box -->
        <div class="blog-cta">
            <h3>⏰ Vergeet nooit meer je contract op te zeggen</h3>
            <p>Stel eenvoudig een contractwekker in en ontvang op tijd een herinnering om je contract op te zeggen of over te stappen. Gratis, veilig en zonder gedoe.</p>
            <a href="/" class="cta-button">🔔 Contractwekker instellen</a>
        </div>

    <?php elseif (!$slug): ?>
        <!-- Blog listing -->
        <div class="blog-list">
            <h1 style="font-size: 2.5rem; margin-bottom: 40px; color: #2c3e50; text-align: center;">Blog</h1>
            
            <?php if (empty($posts)): ?>
                <div class="blog-empty">
                    <h2>Nog geen blog posts</h2>
                    <p>Er zijn nog geen gepubliceerde blog posts beschikbaar.</p>
                </div>
            <?php else: ?>
                <?php foreach ($posts as $post): ?>
                    <article class="blog-list-item">
                        <?php if ($post['header_image']): ?>
                            <?php
                            // Handle both complete data URI and base64-only formats
                            $image_data = $post['header_image'];
                            if (strpos($image_data, 'data:') === 0) {
                                // Already a complete data URI
                                $image_url = $image_data;
                            } else {
                                // Just base64, construct the data URI
                                $mime_type = $post['header_image_mime'] ?? 'image/jpeg';
                                $image_url = 'data:' . $mime_type . ';base64,' . $image_data;
                            }
                            ?>
                            <a href="/blog/<?php echo htmlspecialchars($post['slug']); ?>" style="display: block; text-decoration: none;">
                                <div class="blog-list-item-header" style="background-image: url('<?php echo htmlspecialchars($image_url, ENT_QUOTES); ?>'); cursor: pointer;">
                                </div>
                            </a>
                        <?php endif; ?>
                        
                        <h2><a href="/blog/<?php echo htmlspecialchars($post['slug']); ?>"><?php echo htmlspecialchars($post['title']); ?></a></h2>
                        
                        <?php if ($post['excerpt']): ?>
                            <div class="excerpt"><?php echo $post['excerpt'] ?></div>
                        <?php endif; ?>
                        
                        <?php if ($post['published_at']): ?>
                            <div class="blog-meta" style="color: #999; font-size: 0.9rem; margin-bottom: 20px;">
                                <?php 
                                $date = new DateTime($post['published_at']);
                                echo $date->format('d F Y');
                                ?>
                            </div>
                        <?php endif; ?>
                        
                        <a href="/blog/<?php echo htmlspecialchars($post['slug']); ?>" class="read-more">Lees verder →</a>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php include 'views/footer.php'; ?>

