<?php
require __DIR__ . '/../bootstrap.php';

$sqlFile = __DIR__ . '/../db/mar-2026.sql';
if (!file_exists($sqlFile)) {
    echo "SQL file not found: $sqlFile\n";
    exit(1);
}

$sql = file_get_contents($sqlFile);
if ($sql === false) { echo "Failed to read SQL file\n"; exit(1); }

$statements = array_filter(array_map('trim', explode(";\n", $sql)));

try {
    $pdo->beginTransaction();
    $total = 0;
    foreach ($statements as $st) {
        if ($st === '') continue;
        // ensure ends clean
        $st = rtrim($st, ";\n ");
        try {
            $affected = $pdo->exec($st);
            if ($affected === false) $affected = 0;
            $total += (int)$affected;
        } catch (PDOException $e) {
            // log and continue
            echo "Statement error: " . $e->getMessage() . "\n";
        }
    }
    $pdo->commit();
    echo "SQL applied. Total affected rows: $total\n";
} catch (Exception $e) {
    $pdo->rollBack();
    echo "Transaction failed: " . $e->getMessage() . "\n";
    exit(1);
}

exit(0);
