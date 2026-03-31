<?php

require dirname(__DIR__, 3) . '/app/bootstrap.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ' . route_url('login.php'));
    exit;
}

if (empty($_SESSION['is_admin']) || (int)$_SESSION['is_admin'] === 0) {
    header('Location: ' . route_url('escala.php'));
    exit;
}

$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

if ($user_id <= 0) {
    http_response_code(400);
    die('<div style="padding:20px;color:#ef4444;">Parâmetro user_id inválido.</div>');
}

$stmt = $pdo->prepare("SELECT id, username, full_name FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    http_response_code(404);
    die('<div style="padding:20px;color:#ef4444;">Técnico não encontrado.</div>');
}

// Apenas admin nível 1 pode editar escalas
$canEdit = ((int)$_SESSION['is_admin'] === 1);

// CSRF token para chamadas AJAX de edição
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$tech_name = htmlspecialchars($user['full_name'] ?: $user['username']);
?><!DOCTYPE html>
<html lang="pt-br" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agenda de <?= $tech_name ?></title>
    <link rel="stylesheet" href="<?= htmlspecialchars(asset_url('css/output.css')) ?>">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="<?= htmlspecialchars(asset_url('js/app-routes.js')) ?>"></script>
    <script src="<?= htmlspecialchars(asset_url('js/theme.js')) ?>"></script>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap');

        *, *::before, *::after { box-sizing: border-box; }

        html, body {
            margin: 0;
            padding: 0;
            font-family: 'Inter', sans-serif;
            background-color: #141414;
            color: #f9fafb;
            min-height: 100%;
        }

        body { background-image: none !important; }

        /* ── Popover de edição ── */
        .edit-popover {
            position: absolute;
            z-index: 1000;
            background: #1e1e1e;
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 10px;
            padding: 8px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.6);
            display: flex;
            flex-direction: column;
            gap: 4px;
            min-width: 130px;
        }

        .edit-popover button {
            width: 100%;
            padding: 7px 12px;
            border-radius: 6px;
            border: none;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            text-align: left;
            transition: opacity 0.1s;
        }

        .edit-popover button:hover { opacity: 0.85; }

        .popover-agenda  { background: #166534; color: #bbf7d0; }
        .popover-folga   { background: #1e40af; color: #bfdbfe; }
        .popover-ferias  { background: #9a3412; color: #fed7aa; }
        .popover-ausente { background: #4b5563; color: #e5e7eb; }
        .popover-remover { background: rgba(239,68,68,0.15); color: #fca5a5; border: 1px solid rgba(239,68,68,0.25) !important; }

        .popover-divider {
            height: 1px;
            background: rgba(255,255,255,0.08);
            margin: 2px 0;
        }

        /* Botão de edição na célula */
        .day-edit-btn {
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 4px;
            color: #9ca3af;
            cursor: pointer;
            padding: 2px 5px;
            font-size: 10px;
            display: inline-flex;
            align-items: center;
            gap: 3px;
            transition: background 0.15s, color 0.15s;
            white-space: nowrap;
        }

        .day-edit-btn:hover {
            background: rgba(255,255,255,0.16);
            color: #fff;
        }

        /* Toast de feedback */
        #schedule-toast {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.25s;
            z-index: 9999;
            white-space: nowrap;
        }
        #schedule-toast.show { opacity: 1; }
        #schedule-toast.success { background: #166534; color: #bbf7d0; border: 1px solid #4ade80; }
        #schedule-toast.error   { background: #7f1d1d; color: #fecaca; border: 1px solid #ef4444; }
    </style>
</head>
<body>
    <div style="max-width:900px;margin:0 auto;padding:20px 16px 40px;">
        <div id="calendar-wrap" class="w-full overflow-hidden">
            <div style="padding:16px;text-align:center;color:#6b7280;">Carregando...</div>
        </div>
    </div>

    <div id="schedule-toast"></div>

    <script>
        window.TARGET_USER_ID  = <?= (int)$user_id ?>;
        window.ADMIN_EDIT_MODE = <?= $canEdit ? 'true' : 'false' ?>;
        window.CSRF_TOKEN      = <?= json_encode($_SESSION['csrf_token']) ?>;
    </script>
    <script src="<?= htmlspecialchars(asset_url('js/escala-abas.js')) ?>?v=<?= time() ?>"></script>
</body>
</html>
