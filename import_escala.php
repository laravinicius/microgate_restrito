<?php
// import_escala.php
// Admin-only CSV import for schedules. CSV columns: date,username,shift,note

session_start();
require __DIR__ . '/bootstrap.php';

if (empty($_SESSION['user_id']) || ($_SESSION['is_admin'] ?? 0) !== 1) {
    http_response_code(403);
    echo "Acesso negado.";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_FILES['csv']) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
        echo "Erro no envio do arquivo.";
        exit;
    }

    $tmp = $_FILES['csv']['tmp_name'];
    $handle = fopen($tmp, 'r');
    if (!$handle) {
        echo "Não foi possível abrir o arquivo.";
        exit;
    }

    $pdo->beginTransaction();
    $inserted = 0;
    $skipped = 0;
    // read header
    $header = fgetcsv($handle, 1000, ',');
    // Expect header like: date,username,shift,note OR no header (we'll handle)
    rewind($handle);

    while (($row = fgetcsv($handle, 1000, ',')) !== false) {
        if (count($row) < 2) continue;
        // Normalize
        $date = trim($row[0]);
        $username = trim($row[1]);
        $shift = isset($row[2]) ? trim($row[2]) : null;
        $note = isset($row[3]) ? trim($row[3]) : null;

        if (!$date || !$username) { $skipped++; continue; }

        // Find user id
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $u = $stmt->fetch();
        if (!$u) { $skipped++; continue; }

        // Avoid duplicates for same user/date/shift
        $stmt = $pdo->prepare("SELECT id FROM schedules WHERE user_id=? AND date=? AND (shift=? OR shift IS NULL)");
        $stmt->execute([$u['id'], $date, $shift]);
        if ($stmt->rowCount()>0) { $skipped++; continue; }

        $ins = $pdo->prepare("INSERT INTO schedules (user_id, date, shift, note) VALUES (?, ?, ?, ?)");
        $ins->execute([$u['id'], $date, $shift, $note]);
        $inserted++;
    }

    $pdo->commit();
    fclose($handle);

    echo "Import completo. Inseridos: {$inserted}. Pulados: {$skipped}.";
    exit;
}

// Form upload
?><!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<title>Importar Escala</title>
</head>
<body>
<h2>Importar CSV de Escala</h2>
<p>Formato CSV: <strong>date,username,shift,note</strong> - date no formato <em>YYYY-MM-DD</em>. O arquivo pode ter header.</p>
<form method="post" enctype="multipart/form-data">
    <input type="file" name="csv" accept="text/csv" required>
    <button type="submit">Importar</button>
</form>
</body>
</html>
