<?php
declare(strict_types=1);

/**
 * Funções centralizadas de autorização.
 *
 * Níveis de is_admin:
 *   0 = Padrão        — acessa apenas escala.php
 *   1 = Administrador — acesso total ao painel
 *   2 = Gerente       — acesso ao painel (sem gestão de usuários)
 */

function isLoggedIn(): bool
{
    return !empty($_SESSION['user_id']);
}

/** Admin completo ou gerente (níveis 1 e 2). */
function isAdmin(): bool
{
    return in_array((int)($_SESSION['is_admin'] ?? 0), [1, 2], true);
}

/** Somente administrador nível 1. */
function isSuperAdmin(): bool
{
    return (int)($_SESSION['is_admin'] ?? 0) === 1;
}

/** Exige login, redireciona para login.php se não autenticado. */
function requireLogin(): void
{
    if (!isLoggedIn()) {
        header('Location: /login.php');
        exit;
    }
}

/** Exige admin (níveis 1 ou 2), redireciona se não autorizado. */
function requireAdmin(): void
{
    requireLogin();
    if (!isAdmin()) {
        header('Location: /escala.php');
        exit;
    }
}
