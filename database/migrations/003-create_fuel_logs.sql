-- Migration: cria tabela de abastecimentos com multiplos lancamentos por dia.

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
