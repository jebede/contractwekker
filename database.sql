-- Contractwekker Database Schema
CREATE DATABASE IF NOT EXISTS contractwekker CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE contractwekker;

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
('⚡ Energie', 'https://www.vergelijk.nl/energie'),
('🚗 Autoverzekering', 'https://www.vergelijk.nl/autoverzekering'),
('🏥 Zorgverzekering', 'https://www.vergelijk.nl/zorgverzekering'),
('✈️ Reisverzekering', 'https://www.vergelijk.nl/reisverzekering'),
('💼 Overlijdensrisicoverzekering', 'https://www.vergelijk.nl/overlijdensrisicoverzekering'),
('🏠 Hypotheek', 'https://www.vergelijk.nl/hypotheek'),
('🌐 Internetprovider', 'https://www.vergelijk.nl/internet'),
('📱 Mobiele abonnement', 'https://www.vergelijk.nl/mobiel'),
('🏠 Inboedelverzekering', 'https://www.vergelijk.nl/inboedelverzekering'),
('⚖️ Rechtsbijstandverzekering', 'https://www.vergelijk.nl/rechtsbijstand'),
('Anders', '#');