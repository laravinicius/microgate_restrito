<?php
declare(strict_types=1);

function ensurePasswordResetRequestsTable(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS password_reset_requests (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            username VARCHAR(100) NOT NULL,
            phone VARCHAR(30) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            requested_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            handled_at TIMESTAMP NULL DEFAULT NULL,
            handled_by INT NULL,
            INDEX idx_password_reset_requests_status (status),
            INDEX idx_password_reset_requests_requested_at (requested_at),
            INDEX idx_password_reset_requests_username (username),
            CONSTRAINT fk_password_reset_requests_user
                FOREIGN KEY (user_id) REFERENCES users(id)
                ON DELETE SET NULL,
            CONSTRAINT fk_password_reset_requests_handled_by
                FOREIGN KEY (handled_by) REFERENCES users(id)
                ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $checked = true;
}

