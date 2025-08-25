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

-- Email alerts table
CREATE TABLE email_alerts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) NOT NULL,
    product_id INT NULL,
    custom_product_name VARCHAR(255) NULL,
    alert_period ENUM('1_month', '3_months', '1_year', '2_years', '3_years', 'custom') NOT NULL,
    end_date DATE NULL,
    is_periodic BOOLEAN DEFAULT TRUE,
    next_alert_date DATETIME NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    unsubscribe_token VARCHAR(255) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_next_alert_date (next_alert_date),
    INDEX idx_email (email),
    INDEX idx_unsubscribe_token (unsubscribe_token)
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