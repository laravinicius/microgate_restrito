<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

if (!empty($_SESSION['user_id'])) {
    $level = (int)($_SESSION['is_admin'] ?? 0);

    if ($level === 3) {
        header('Location: /km_report.php', true, 302);
    } elseif ($level >= 1) {
        header('Location: /restricted.php', true, 302);
    } else {
        header('Location: /escala.php', true, 302);
    }
    exit;
}

header('Location: /login.php', true, 302);
exit;
