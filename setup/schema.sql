-- ══════════════════════════════════════════════════════════════
-- SCHEMA MariaDB — Portfolio Thimoty LAY
-- ══════════════════════════════════════════════════════════════

-- 1. Créer la base de données
CREATE DATABASE IF NOT EXISTS portfolio_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE portfolio_db;

-- 2. Table des utilisateurs admin
CREATE TABLE IF NOT EXISTS admin_users (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(64)  NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,       -- bcrypt hash
    otp_secret    VARCHAR(64)  NOT NULL,       -- Secret TOTP Base32 (Google Authenticator)
    is_active     TINYINT(1)   NOT NULL DEFAULT 1,
    created_at    DATETIME     NOT NULL DEFAULT NOW(),
    updated_at    DATETIME     NOT NULL DEFAULT NOW() ON UPDATE NOW()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Table des tentatives de connexion (anti brute-force)
CREATE TABLE IF NOT EXISTS login_attempts (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    ip_address   VARCHAR(45)  NOT NULL,
    attempt_time DATETIME     NOT NULL DEFAULT NOW(),
    INDEX idx_ip_time (ip_address, attempt_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Table du contenu du site (sections éditables)
CREATE TABLE IF NOT EXISTS site_content (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    section_key  VARCHAR(64)  NOT NULL UNIQUE,  -- ex: 'profil', 'experience', etc.
    content_html MEDIUMTEXT   NOT NULL,
    updated_by   INT UNSIGNED,
    updated_at   DATETIME     NOT NULL DEFAULT NOW() ON UPDATE NOW(),
    FOREIGN KEY (updated_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Table des logs d'administration
CREATE TABLE IF NOT EXISTS admin_logs (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    admin_id   INT UNSIGNED,
    action     VARCHAR(128) NOT NULL,
    ip_address VARCHAR(45)  NOT NULL,
    created_at DATETIME     NOT NULL DEFAULT NOW(),
    FOREIGN KEY (admin_id) REFERENCES admin_users(id) ON DELETE SET NULL,
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS blog_posts (
    id           INT UNSIGNED     NOT NULL AUTO_INCREMENT PRIMARY KEY,
    slug         VARCHAR(200)     NOT NULL UNIQUE,         -- URL unique (ex: mon-premier-post)
    title        VARCHAR(300)     NOT NULL,
    content_html MEDIUMTEXT       NOT NULL,
    is_published TINYINT(1)       NOT NULL DEFAULT 0,      -- 0=brouillon, 1=publié
    created_by   INT UNSIGNED     NOT NULL,
    updated_by   INT UNSIGNED     NULL,
    created_at   DATETIME         NOT NULL DEFAULT NOW(),
    updated_at   DATETIME         NULL ON UPDATE NOW(),
    FOREIGN KEY (created_by) REFERENCES admin_users(id) ON DELETE RESTRICT,
    FOREIGN KEY (updated_by) REFERENCES admin_users(id) ON DELETE SET NULL,
    INDEX idx_published_date (is_published, created_at),
    INDEX idx_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ══════════════════════════════════════════════════════════════
-- INSERTION DE L'UTILISATEUR ADMIN
-- ══════════════════════════════════════════════════════════════
-- IMPORTANT : Remplace les valeurs ci-dessous AVANT d'exécuter !
--
-- Pour générer le hash bcrypt du mot de passe, utilise le script PHP
-- fourni dans setup/generate_admin.php
--
-- Pour le secret OTP : génère-le avec setup/generate_otp.php
-- puis scanne le QR code avec Google Authenticator.
--
-- Exemple (À NE PAS utiliser en production) :
INSERT INTO admin_users (username, password_hash, otp_secret) VALUES (
    'rattack',
    '$2y$12$REMPLACE_AVEC_LE_HASH_BCRYPT_DE_TON_MOT_DE_PASSE',
    'REMPLACE_AVEC_TON_SECRET_OTP_BASE32'
);
