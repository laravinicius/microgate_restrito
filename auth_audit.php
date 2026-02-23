<?php
declare(strict_types=1);

/**
 * Resolve o IP do cliente considerando proxies comuns.
 */
function authClientIp(): string
{
    $headers = [
        'HTTP_CF_CONNECTING_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_REAL_IP',
        'REMOTE_ADDR',
    ];

    foreach ($headers as $header) {
        $raw = trim((string)($_SERVER[$header] ?? ''));
        if ($raw === '') {
            continue;
        }

        $candidate = trim(explode(',', $raw)[0]);
        if (filter_var($candidate, FILTER_VALIDATE_IP)) {
            return $candidate;
        }
    }

    return '0.0.0.0';
}

/**
 * Cria a tabela de auditoria, se ainda não existir.
 */
function ensureAuthAuditTable(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS auth_access_logs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            username VARCHAR(100) NOT NULL,
            event_type VARCHAR(50) NOT NULL,
            success TINYINT(1) NOT NULL DEFAULT 0,
            ip_address VARCHAR(45) NOT NULL,
            user_agent VARCHAR(255) NOT NULL,
            details VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_auth_logs_created_at (created_at),
            INDEX idx_auth_logs_username (username),
            INDEX idx_auth_logs_user_id (user_id),
            INDEX idx_auth_logs_event_type (event_type),
            CONSTRAINT fk_auth_logs_user
                FOREIGN KEY (user_id) REFERENCES users(id)
                ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $checked = true;
}

/**
 * Registra eventos de autenticação sem interromper o fluxo principal da aplicação.
 */
function logAuthEvent(
    PDO $pdo,
    string $eventType,
    ?int $userId,
    string $username,
    bool $success,
    ?string $details = null
): void {
    try {
        ensureAuthAuditTable($pdo);

        $stmt = $pdo->prepare(
            "INSERT INTO auth_access_logs
                (user_id, username, event_type, success, ip_address, user_agent, details)
             VALUES
                (:user_id, :username, :event_type, :success, :ip_address, :user_agent, :details)"
        );

        $truncate = static function (string $value, int $limit): string {
            if (function_exists('mb_substr')) {
                return mb_substr($value, 0, $limit);
            }

            return substr($value, 0, $limit);
        };

        $stmt->execute([
            ':user_id' => $userId,
            ':username' => $truncate(trim($username) !== '' ? $username : 'desconhecido', 100),
            ':event_type' => $truncate($eventType, 50),
            ':success' => $success ? 1 : 0,
            ':ip_address' => $truncate(authClientIp(), 45),
            ':user_agent' => $truncate((string)($_SERVER['HTTP_USER_AGENT'] ?? 'sem-user-agent'), 255),
            ':details' => $details !== null ? $truncate($details, 255) : null,
        ]);
    } catch (Throwable $e) {
        error_log('Falha ao registrar log de autenticação: ' . $e->getMessage());
    }
}
