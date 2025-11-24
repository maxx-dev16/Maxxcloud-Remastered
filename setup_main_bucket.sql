-- ============================================
-- MaxxCloud Main Bucket (Bucket-1) Setup Script
-- ============================================
-- 
-- Dieses Script erstellt alle notwendigen Tabellen für den Main Bucket.
-- Führe dieses Script in der Datenbank aus, die für Bucket-1 konfiguriert ist.
--
-- Datenbank: u120_2Y9MEq18EI (oder wie in buckets.php konfiguriert)
-- User: u120_2Y9MEq18EI
-- ============================================

-- ============================================
-- 1. Users Tabelle
-- ============================================
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    display_name VARCHAR(120) DEFAULT NULL,
    avatar_url VARCHAR(255) DEFAULT NULL,
    bucket_id VARCHAR(100) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_bucket_id (bucket_id),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Füge Spalten hinzu, falls sie noch nicht existieren
SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'users' 
    AND COLUMN_NAME = 'display_name'
);
SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE users ADD COLUMN display_name VARCHAR(120) DEFAULT NULL AFTER email', 
    'SELECT "Column display_name already exists" AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'users' 
    AND COLUMN_NAME = 'avatar_url'
);
SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE users ADD COLUMN avatar_url VARCHAR(255) DEFAULT NULL AFTER display_name', 
    'SELECT "Column avatar_url already exists" AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'users' 
    AND COLUMN_NAME = 'bucket_id'
);
SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE users ADD COLUMN bucket_id VARCHAR(100) DEFAULT NULL AFTER avatar_url, ADD INDEX idx_bucket_id (bucket_id)', 
    'SELECT "Column bucket_id already exists" AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================
-- 2. Folders Tabelle
-- ============================================
CREATE TABLE IF NOT EXISTS folders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    name VARCHAR(190) NOT NULL,
    path VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_folder_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Füge path Spalte hinzu, falls sie noch nicht existiert
SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'folders' 
    AND COLUMN_NAME = 'path'
);
SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE folders ADD COLUMN path VARCHAR(255) DEFAULT NULL AFTER name', 
    'SELECT "Column path already exists" AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================
-- 3. Files Tabelle
-- ============================================
CREATE TABLE IF NOT EXISTS files (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    folder_id INT UNSIGNED NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(120) DEFAULT NULL,
    size_bytes BIGINT UNSIGNED DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_files_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_files_folder FOREIGN KEY (folder_id) REFERENCES folders(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_folder_id (folder_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Füge size_bytes Spalte hinzu, falls sie noch nicht existiert
SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'files' 
    AND COLUMN_NAME = 'size_bytes'
);
SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE files ADD COLUMN size_bytes BIGINT UNSIGNED DEFAULT 0 AFTER mime_type', 
    'SELECT "Column size_bytes already exists" AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================
-- 4. File Data Tabelle (Storage)
-- ============================================
-- Diese Tabelle speichert die tatsächlichen Dateiinhalte
CREATE TABLE IF NOT EXISTS file_data (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    file_id INT UNSIGNED NOT NULL,
    file_content LONGBLOB NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_file_id (file_id),
    INDEX idx_user_file (user_id, file_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 5. User Buckets Tabelle (Hauptdatenbank)
-- ============================================
-- Diese Tabelle speichert die Zuordnung von Usern zu Buckets
-- Wird in der Hauptdatenbank (Master-DB) erstellt
CREATE TABLE IF NOT EXISTS user_buckets (
    user_id INT UNSIGNED PRIMARY KEY,
    bucket_id VARCHAR(100) NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_bucket_id (bucket_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Script abgeschlossen
-- ============================================
SELECT 'Setup abgeschlossen! Alle Tabellen wurden erstellt.' AS status;



