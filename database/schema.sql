-- ============================================================================
-- MAN GO Platform Database Schema
-- Architecture Multi-Vendeurs Universelle
-- Compatible PHP 8.x, MySQL / PDO
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `hero_slides`;
DROP TABLE IF EXISTS `translations`;
DROP TABLE IF EXISTS `languages`;
DROP TABLE IF EXISTS `system_settings`;
DROP TABLE IF EXISTS `ads`;
DROP TABLE IF EXISTS `stands`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `roles`;
SET FOREIGN_KEY_CHECKS = 1;

-- ----------------------------------------------------------------------------
-- 1. ROLES UTILISATEURS
-- ----------------------------------------------------------------------------
CREATE TABLE `roles` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) NOT NULL UNIQUE,
    `description` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `roles` (`id`, `name`, `description`) VALUES
(1, 'Super Admin', 'Administrateur système complet'),
(2, 'Admin', 'Gestionnaire de la plateforme'),
(3, 'Moderator', 'Modérateur de contenu et KYC'),
(4, 'Seller', 'Vendeur / Propriétaire de Stand'),
(5, 'User', 'Acheteur / Utilisateur standard');

-- ----------------------------------------------------------------------------
-- 2. UTILISATEURS
-- ----------------------------------------------------------------------------
CREATE TABLE `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `role_id` INT NOT NULL DEFAULT 5,
    `firstname` VARCHAR(100) NOT NULL,
    `lastname` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `phone` VARCHAR(30) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `avatar` VARCHAR(255) DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `is_verified` TINYINT(1) DEFAULT 0,
    `kyc_status` ENUM('NONE', 'PENDING', 'APPROVED', 'REJECTED') DEFAULT 'NONE',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 3. CATEGORIES UNIVERSELLES
-- ----------------------------------------------------------------------------
CREATE TABLE `categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `parent_id` INT DEFAULT NULL,
    `name_key` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(120) NOT NULL UNIQUE,
    `icon` VARCHAR(100) DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`parent_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `categories` (`id`, `parent_id`, `name_key`, `slug`, `icon`) VALUES
(1, NULL, 'cat_products', 'produits', 'shopping-bag'),
(2, NULL, 'cat_stands', 'boutiques-stands', 'store'),
(3, NULL, 'cat_services', 'services', 'briefcase'),
(4, NULL, 'cat_ads', 'annonces-pubs', 'megaphone');

-- ----------------------------------------------------------------------------
-- 4. STANDS NUMÉRIQUES PROFESSIONNELS
-- ----------------------------------------------------------------------------
CREATE TABLE `stands` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `slug` VARCHAR(160) NOT NULL UNIQUE,
    `category_description` VARCHAR(255) DEFAULT NULL,
    `logo` VARCHAR(255) DEFAULT NULL,
    `banner` VARCHAR(255) DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `is_certified` TINYINT(1) DEFAULT 0,
    `status` ENUM('PENDING', 'ACTIVE', 'SUSPENDED') DEFAULT 'ACTIVE',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 5. ANNONCES / PRODUITS / SERVICES
-- ----------------------------------------------------------------------------
CREATE TABLE `ads` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `stand_id` INT DEFAULT NULL,
    `user_id` INT NOT NULL,
    `category_id` INT NOT NULL,
    `title` VARCHAR(200) NOT NULL,
    `slug` VARCHAR(220) NOT NULL UNIQUE,
    `description` TEXT DEFAULT NULL,
    `price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `currency` VARCHAR(10) DEFAULT 'FCFA',
    `badge` VARCHAR(50) DEFAULT NULL,
    `main_image` VARCHAR(255) DEFAULT NULL,
    `status` ENUM('DRAFT', 'PUBLISHED', 'MODERATED', 'ARCHIVED') DEFAULT 'PUBLISHED',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`stand_id`) REFERENCES `stands`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 6. PARAMÈTRES SYSTEME DYNAMIQUES
-- ----------------------------------------------------------------------------
CREATE TABLE `system_settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(100) NOT NULL UNIQUE,
    `setting_value` TEXT NULL,
    `description` VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `system_settings` (`setting_key`, `setting_value`, `description`) VALUES
('site_name', 'MAN GO', 'Nom de la plateforme'),
('site_slogan', 'One Market, One Movement.', 'Slogan officiel'),
('contact_email', 'support@mango-app.com', 'Adresse e-mail de support'),
('default_currency', 'FCFA', 'Devise principale du système');

-- ----------------------------------------------------------------------------
-- 7. LANGUES ET TRADUCTIONS
-- ----------------------------------------------------------------------------
CREATE TABLE `languages` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(5) NOT NULL UNIQUE,
    `name` VARCHAR(50) NOT NULL,
    `is_default` TINYINT(1) DEFAULT 0,
    `is_active` TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `languages` (`id`, `code`, `name`, `is_default`, `is_active`) VALUES
(1, 'fr', 'Français', 1, 1),
(2, 'en', 'English', 0, 1);

CREATE TABLE `translations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `lang_code` VARCHAR(5) NOT NULL,
    `trans_key` VARCHAR(150) NOT NULL,
    `trans_value` TEXT NOT NULL,
    UNIQUE KEY `lang_key_unique` (`lang_code`, `trans_key`),
    FOREIGN KEY (`lang_code`) REFERENCES `languages`(`code`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `translations` (`lang_code`, `trans_key`, `trans_value`) VALUES
('fr', 'hero_badge', 'Marketplace Multi-Vendeurs Universelle'),
('fr', 'hero_title', 'Achetez, Vendez et Proposez vos Services'),
('fr', 'hero_subtitle', 'Découvrez des milliers d\'annonces, visitez des boutiques certifiées et entrez en contact direct avec les vendeurs.'),
('fr', 'search_placeholder', 'Que recherchez-vous aujourd\'hui ?'),
('fr', 'btn_search', 'Rechercher'),
('fr', 'cat_title', 'Parcourir par Catégorie'),
('fr', 'recent_ads_title', 'Annonces Récentes'),
('fr', 'view_all', 'Voir tout'),
('fr', 'certified_stands_title', 'Boutiques & Stands Certifiés'),
('fr', 'cta_title', 'Vous êtes commerçant ou prestataire ?'),
('fr', 'cta_subtitle', 'Ouvrez votre propre boutique sur MAN GO, développez votre réseau et touchez de nouveaux clients dès aujourd\'hui.'),
('fr', 'btn_create_seller', 'Créer mon compte Vendeur'),
('en', 'hero_badge', 'Universal Multi-Vendor Marketplace'),
('en', 'hero_title', 'Buy, Sell and Offer your Services'),
('en', 'hero_subtitle', 'Discover thousands of listings, visit certified stores and get in direct contact with sellers.'),
('en', 'search_placeholder', 'What are you looking for today?'),
('en', 'btn_search', 'Search'),
('en', 'cat_title', 'Browse by Category'),
('en', 'recent_ads_title', 'Recent Listings'),
('en', 'view_all', 'View all'),
('en', 'certified_stands_title', 'Certified Stores & Stands'),
('en', 'cta_title', 'Are you a merchant or service provider?'),
('en', 'cta_subtitle', 'Open your own store on MAN GO, grow your network and reach new customers today.'),
('en', 'btn_create_seller', 'Create Seller Account');

-- ----------------------------------------------------------------------------
-- 8. SLIDES DU HERO DYNAMIQUE
-- ----------------------------------------------------------------------------
CREATE TABLE `hero_slides` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `image_url` VARCHAR(255) NOT NULL,
    `title` VARCHAR(150) NULL,
    `sort_order` INT DEFAULT 0,
    `is_active` TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `hero_slides` (`image_url`, `title`, `sort_order`, `is_active`) VALUES
('https://images.unsplash.com/photo-1472851294608-062f824d29cc?auto=format&fit=crop&w=1920&q=80', 'Commerce & Électronique', 1, 1),
('https://images.unsplash.com/photo-1556742049-0a670f4a4591?auto=format&fit=crop&w=1920&q=80', 'Boutiques & Services', 2, 1),
('https://images.unsplash.com/photo-1521791136064-7986c2920216?auto=format&fit=crop&w=1920&q=80', 'Partenariats & Affaires', 3, 1);