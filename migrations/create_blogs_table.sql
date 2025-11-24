-- Create blogs table
-- Note: header_image stores base64-encoded image data as BLOB
-- Recommended image size: 1600x900 pixels for optimal display
CREATE TABLE IF NOT EXISTS blogs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    excerpt TEXT,
    content LONGTEXT NOT NULL,
    header_image LONGBLOB COMMENT 'Base64-encoded image data (recommended: 1600x900)',
    header_image_mime VARCHAR(50) COMMENT 'MIME type of the header image (e.g., image/jpeg, image/png)',
    published BOOLEAN DEFAULT FALSE,
    published_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_slug (slug),
    INDEX idx_published (published),
    INDEX idx_published_at (published_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

