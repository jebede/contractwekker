-- Contractwekker Database Schema
-- Set character set for current database
ALTER DATABASE CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Products table for contract types
CREATE TABLE products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    deeplink VARCHAR(500) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Unified alerts table (supports both email and push notifications)
CREATE TABLE alerts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) NULL,
    push_token VARCHAR(255) NULL,
    product_id INT NULL,
    custom_product_name VARCHAR(255) NULL,
    alert_period ENUM('1_month', '3_months', '1_year', '2_years', '3_years', 'custom') NOT NULL,
    -- Migration: add first_alert_date after alert_period
    first_alert_date DATE NULL,
    -- Migration: rename alert_date to next_alert_date (so just define next_alert_date here)
    next_alert_date DATE NULL,
    end_date DATE NULL,
    is_periodic BOOLEAN DEFAULT TRUE,
    is_active BOOLEAN DEFAULT TRUE,
    is_sent BOOLEAN DEFAULT FALSE,
    unsubscribe_token VARCHAR(255) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    -- Migration: update indexes
    INDEX idx_next_alert_date (next_alert_date),
    INDEX idx_first_alert_date (first_alert_date),
    INDEX idx_email (email),
    INDEX idx_push_token (push_token),
    INDEX idx_unsubscribe_token (unsubscribe_token),
    CONSTRAINT chk_notification_method CHECK (email IS NOT NULL OR push_token IS NOT NULL)
);

-- Insert default products
INSERT INTO products (name, deeplink) VALUES
('⚡ Energie', 'https://echt-groene-stroom.nl'),
('🚗 Autoverzekering', 'https://bdt9.net/c/?si=18644&li=1802449&wi=297149&ws=contractwekker&dl=autoverzekering%2Fautoverzekering-vergelijken%2F'),
('🏥 Zorgverzekering', 'https://www.awin1.com/cread.php?awinmid=8558&awinaffid=329963&clickref=contractwekker'),
('✈️ Reisverzekering', 'https://bdt9.net/c/?si=18644&li=1802449&wi=297149&ws=contractwekker&dl=verzekeringen%2Freisverzekering%2Freisverzekering-vergelijken%2F'),
('💼 Overlijdensrisicoverzekering', 'https://www.shopsdatabase.com/open/4466?category='),
('🏠 Hypotheek', 'https://www.shopsdatabase.com/open/4252?category='),
('🌐 Internetprovider', 'https://bdt9.net/c/?si=18647&li=1802770&wi=297149&ws=contractwekker'),
('📱 Mobiele abonnement', 'https://www.awin1.com/cread.php?awinmid=8373&awinaffid=329963&clickref=contractwekker'),
('🏠 Inboedelverzekering', 'https://bdt9.net/c/?si=18644&li=1802449&wi=297149&ws=contractwekker&dl=verzekeringen%2Fwoonverzekering%2Fwoonverzekering-vergelijken%2F'),
('⚖️ Rechtsbijstandverzekering', 'https://www.pricewise.nl/verzekeringen/rechtsbijstandverzekering/'),
('Anders', '#');