-- ============================================
-- MaxxCloud Main Bucket (Bucket-1) Setup Script
-- Vereinfachte Version - Erstellt alle Tabellen neu
-- ============================================
-- 
-- WICHTIG: Dieses Script löscht KEINE existierenden Daten!
-- Es verwendet "CREATE TABLE IF NOT EXISTS", daher sind existierende Tabellen sicher.
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

-- Füge fehlende Spalten hinzu (Fehler werden ignoriert wenn Spalte bereits existiert)
-- Hinweis: Führe diese Befehle einzeln aus und ignoriere Fehler wenn Spalte bereits existiert

-- display_name
ALTER TABLE users ADD COLUMN display_name VARCHAR(120) DEFAULT NULL AFTER email;

-- avatar_url  
ALTER TABLE users ADD COLUMN avatar_url VARCHAR(255) DEFAULT NULL AFTER display_name;

-- bucket_id
ALTER TABLE users ADD COLUMN bucket_id VARCHAR(100) DEFAULT NULL AFTER avatar_url;
CREATE INDEX idx_bucket_id ON users(bucket_id);

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

-- Füge path Spalte hinzu (Fehler ignorieren wenn bereits vorhanden)
ALTER TABLE folders ADD COLUMN path VARCHAR(255) DEFAULT NULL AFTER name;

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

-- Füge size_bytes Spalte hinzu falls nicht vorhanden
ALTER TABLE files 
    ADD COLUMN IF NOT EXISTS size_bytes BIGINT UNSIGNED DEFAULT 0 AFTER mime_type;

-- ============================================
-- 4. File Data Tabelle (Storage)
-- ============================================
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
-- Diese Tabelle wird auch in der Hauptdatenbank benötigt
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

