<?php
require __DIR__ . '/bootstrap.php';

$stmt = $pdo->query("SELECT NOW() AS agora");
$row = $stmt->fetch();

echo "OK - Conectado. Hora do banco: " . $row['agora'];
