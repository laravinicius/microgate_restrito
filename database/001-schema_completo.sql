-- Schema completo do Microgate Restrito (baseline atual)
-- Este arquivo deve ser atualizado sempre que uma migration nova for adicionada.

SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    is_admin TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    allow_fuel TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_username (username),
    KEY idx_users_active_role (is_active, is_admin)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS schedules (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    date DATE NOT NULL,
    shift VARCHAR(50) DEFAULT NULL,
    note VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_schedules_user_date (user_id, date),
    KEY idx_schedules_date (date),
    CONSTRAINT fk_schedules_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS holidays (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date DATE NOT NULL,
    name VARCHAR(120) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_holidays_date (date),
    KEY idx_holidays_active_date (is_active, date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS mileage_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    log_date DATE NOT NULL,
    km_start INT DEFAULT NULL,
    km_end INT DEFAULT NULL,
    photo_start VARCHAR(255) DEFAULT NULL,
    photo_end VARCHAR(255) DEFAULT NULL,
    lat_start DECIMAL(10,7) DEFAULT NULL,
    lng_start DECIMAL(10,7) DEFAULT NULL,
    lat_end DECIMAL(10,7) DEFAULT NULL,
    lng_end DECIMAL(10,7) DEFAULT NULL,
    saved_at_start DATETIME DEFAULT NULL,
    saved_at_end DATETIME DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_mileage_user_date (user_id, log_date),
    KEY idx_mileage_log_date (log_date),
    CONSTRAINT fk_mileage_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS fuel_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    fueled_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fuel_price DECIMAL(10,2) NOT NULL,
    liters DECIMAL(10,3) NOT NULL,
    total_amount DECIMAL(12,2) NOT NULL,
    current_km INT DEFAULT NULL,
    receipt_photo VARCHAR(255) NOT NULL,
    lat DECIMAL(10,7) DEFAULT NULL,
    lng DECIMAL(10,7) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_fuel_user_date (user_id, fueled_at),
    KEY idx_fuel_fueled_at (fueled_at),
    CONSTRAINT fk_fuel_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS auth_access_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    username VARCHAR(100) NOT NULL,
    event_type VARCHAR(50) NOT NULL,
    success TINYINT(1) NOT NULL DEFAULT 0,
    ip_address VARCHAR(45) NOT NULL,
    user_agent VARCHAR(255) NOT NULL,
    details VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_auth_logs_created_at (created_at),
    KEY idx_auth_logs_username (username),
    KEY idx_auth_logs_user_id (user_id),
    KEY idx_auth_logs_event_type (event_type),
    CONSTRAINT fk_auth_logs_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS password_reset_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    username VARCHAR(100) NOT NULL,
    phone VARCHAR(30) NOT NULL,
    ip_address VARCHAR(45) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    requested_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    handled_at TIMESTAMP NULL DEFAULT NULL,
    handled_by INT NULL,
    KEY idx_password_reset_requests_status (status),
    KEY idx_password_reset_requests_requested_at (requested_at),
    KEY idx_password_reset_requests_created_at (created_at),
    KEY idx_password_reset_requests_username (username),
    KEY idx_password_reset_requests_ip_address (ip_address),
    CONSTRAINT fk_password_reset_requests_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_password_reset_requests_handled_by
        FOREIGN KEY (handled_by) REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Migrations refletidas neste baseline:
-- 002-add_allow_fuel_to_users.sql
-- 003-create_fuel_logs.sql
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS allow_fuel TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active;
