<?php
declare(strict_types=1);

/**
 * Funções centralizadas de autorização.
 *
 * Níveis de is_admin:
 *   0 = Usuário padrão
 *   1 = Administrador
 *   2 = Super-administrador (mesmos acessos do admin)
 *
 * Correção F-06: antes, cada arquivo verificava is_admin de forma diferente
 * (alguns !== 1, outros === 0), causando comportamento imprevisível para
 * o nível 2. Agora todas as verificações passam por estas funções.
 */

function isLoggedIn(): bool
{
    return !empty($_SESSION['user_id']);
}

function isAdmin(): bool
{
    return (int)($_SESSION['is_admin'] ?? 0) >= 1;
}

function isSuperAdmin(): bool
{
    return (int)($_SESSION['is_admin'] ?? 0) >= 2;
}

/** Exige login, redireciona para login.php se não autenticado. */
function requireLogin(): void
{
    if (!isLoggedIn()) {
        header('Location: /login.php');
        exit;
    }
}

/** Exige admin (nível >= 1), redireciona para escala.php se não autorizado. */
function requireAdmin(): void
{
    requireLogin();
    if (!isAdmin()) {
        header('Location: /escala.php');
        exit;
    }
}
