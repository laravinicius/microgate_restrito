<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

if (!empty($_SESSION['user_id'])) {
    $isAdminLevel = (int)($_SESSION['is_admin'] ?? 0);

    if ($isAdminLevel >= 1) {
        header('Location: /restricted.php', true, 302);
        exit;
    }

    header('Location: /escala.php', true, 302);
    exit;
}

header('Location: /login.php', true, 302);
exit;
