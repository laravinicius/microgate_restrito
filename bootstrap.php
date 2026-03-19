<?php
declare(strict_types=1);

// Define o fuso horário para as funções de data do PHP
date_default_timezone_set('America/Sao_Paulo');

if (!defined('APP_ROOT')) {
    define('APP_ROOT', __DIR__);
}

// Tempo máximo de inatividade em segundos (30 minutos)
const SESSION_INACTIVITY_TIMEOUT = 1800;

if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443)
    );

    // O cookie de sessão não deve ter lifetime fixo longo.
    // A validade real é controlada pela verificação de inatividade abaixo.
    // lifetime=0 faz o cookie expirar ao fechar o navegador (session cookie).
    ini_set('session.gc_maxlifetime', (string)SESSION_INACTIVITY_TIMEOUT);
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'lifetime' => 0,        // ← cookie some ao fechar o navegador
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/auth_helper.php';

// ─── Verificação de inatividade server-side ───────────────────────────────────
// Verifica quanto tempo se passou desde a última atividade do usuário logado.
// Se ultrapassar SESSION_INACTIVITY_TIMEOUT, derruba a sessão antes que o PHP
// a destrua automaticamente (o gc do PHP não é imediato).
if (!empty($_SESSION['user_id'])) {
    $lastActivity = (int)($_SESSION['last_activity'] ?? 0);

    if ($lastActivity > 0 && (time() - $lastActivity) > SESSION_INACTIVITY_TIMEOUT) {
        // Sessão expirada por inatividade — limpa e destrói
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(), '',
                time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }
        session_destroy();

        // Reinicia sessão limpa para que páginas possam redirecionar normalmente
        session_start();

        // Se for requisição de API (JSON), responde 401 em vez de redirecionar
        $acceptsJson = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
        if ($acceptsJson) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'session_expired']);
            exit;
        }

        header('Location: /login.php?error=session_expired');
        exit;
    }

    // Atualiza timestamp de última atividade a cada requisição
    $_SESSION['last_activity'] = time();
}

// Sincroniza o fuso horário da sessão do Banco de Dados com o horário de Brasília
$pdo->exec("SET time_zone = '-03:00'");