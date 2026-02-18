<?php
/**
 * Converter CSV exportado da planilha (matriz) para CSV normalizado e SQL
 * Input: /uploads/mar-26.csv (linha 1 = cabeçalho com dias ou nomes)
 * Output: /db/mar-2026.csv e /db/mar-2026.sql
 */
require __DIR__ . '/../bootstrap.php';

$in = __DIR__ . '/../uploads/mar-26.csv';
if (!file_exists($in)) { echo "Arquivo não encontrado: $in\n"; exit(1); }

// semicolon-delimited CSV exportado do Excel em pt-BR
$fh = fopen($in,'r');
if (!$fh) { echo "Não foi possível abrir $in\n"; exit(1); }
// avançar linhas vazias e localizar cabeçalho com datas (formato DD/MM/YYYY) ou com a palavra 'Dia'/'Técnico'
$header = null;
while (($tmp = fgetcsv($fh, 0, ';')) !== false) {
    $hasDate = false; $hasText = false;
    foreach ($tmp as $cell) {
        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', trim($cell))) { $hasDate = true; break; }
        if (preg_match('/^(Dia|Técnico|Técnicos|Técnico;)/i', trim($cell))) { $hasText = true; }
    }
    if ($hasDate || $hasText) { $header = $tmp; break; }
}
if (!$header) { echo "Cabeçalho não encontrado ou inválido\n"; exit(1); }
if (!$header) { echo "Cabeçalho inválido\n"; exit(1); }

// detect day columns (header cells like DD/MM/YYYY)
$dayCols = [];
foreach ($header as $i => $h) {
    $h2 = trim($h);
    if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $h2, $m)) {
        $dayCols[$i] = ['day'=> (int)$m[1], 'date'=> sprintf('%04d-%02d-%02d', (int)$m[3], (int)$m[2], (int)$m[1])];
    }
}

if (empty($dayCols)) { echo "Não identifiquei colunas de dias no cabeçalho.\n"; exit(1); }

// Assume name column is the first non-day column (usually index 0)
$nameCol = null;
foreach ($header as $i => $h) {
    if (!isset($dayCols[$i])) { $nameCol = $i; break; }
}
if ($nameCol === null) { echo "Não foi possível determinar coluna de nomes\n"; exit(1); }

// detect month/year from filename
$fname = basename($in);
$month = 3; $year = 2026;
if (preg_match('/(jan|fev|mar|abr|mai|jun|jul|ago|set|out|nov|dez)/i', $fname, $m)){
    $map = ['jan'=>1,'fev'=>2,'mar'=>3,'abr'=>4,'mai'=>5,'jun'=>6,'jul'=>7,'ago'=>8,'set'=>9,'out'=>10,'nov'=>11,'dez'=>12];
    $month = $map[strtolower($m[1])] ?? $month;
}
if (preg_match('/(20\d\d|\d{2})/', $fname, $y)){
    $yy = $y[1]; if (strlen($yy)==2) $yy = '20'.$yy; $year = (int)$yy;
}

$outCsv = __DIR__ . '/../db/mar-2026.csv';
$outSql = __DIR__ . '/../db/mar-2026.sql';
$fout = fopen($outCsv,'w'); fputcsv($fout, ['date','username','shift','note']);
$fsql = fopen($outSql,'w');

$unknown = [];
$rows = 0;

// ler linhas restantes com delimitador ';'
while (($row = fgetcsv($fh, 0, ';')) !== false) {
    // skip empty rows
    $allEmpty = true; foreach ($row as $c) { if (trim((string)$c) !== '') { $allEmpty = false; break; } }
    if ($allEmpty) continue;

    $name = trim($row[$nameCol] ?? '');
    // remover prefixos tipo '1 - ' caso existam
    $name = preg_replace('/^\s*\d+\s*-\s*/','',$name);
    if ($name === '') continue;

    // derive username candidates
    $parts = preg_split('/\s+/', $name);
    $cands = [];
    $first = preg_replace('/[^A-Za-z\.]/','',strtolower($parts[0] ?? ''));
    if ($first) $cands[] = $first;
    if (isset($parts[1])) {
        $sec = preg_replace('/[^A-Za-z\.]/','',strtolower($parts[1]));
        if ($sec) $cands[] = $first.'.'.$sec[0];
    }
    // also try first.last
    if (count($parts) >= 2) {
        $last = preg_replace('/[^A-Za-z\.]/','',strtolower(end($parts)));
        if ($last) $cands[] = $first.'.'.$last;
    }
    $cands = array_values(array_unique($cands));

    // find in DB
    $matched = null;
    foreach ($cands as $cand) {
        $stmt = $pdo->prepare('SELECT id, username FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$cand]);
        $u = $stmt->fetch();
        if ($u) { $matched = $u['username']; break; }
    }
    if (!$matched) { $unknown[$name] = $cands; continue; }

    // iterate days
    foreach ($dayCols as $i => $info) {
        $cell = trim($row[$i] ?? '');
        if ($cell === '') continue;
        $low = strtolower($cell);
        if (strpos($low,'agenda') !== false) $shift = 'AGENDA';
        elseif (strpos($low,'folga') !== false) $shift = 'FOLGA';
        elseif (strpos($low,'fer') !== false) $shift = 'FÉRIAS';
        elseif (strpos($low,'aus') !== false) $shift = 'AUSENTE';
        else $shift = strtoupper($cell);

        $date = $info['date'];
        fputcsv($fout, [$date, $matched, $shift, $cell]);
        $noteSql = addslashes($cell);
        $line = "INSERT INTO schedules (user_id, date, shift, note) SELECT id, '$date', '$shift', '$noteSql' FROM users WHERE username = '$matched';\n";
        fwrite($fsql, $line);
        $rows++;
    }
}

fclose($fh); fclose($fout); fclose($fsql);

echo "Conversão concluída. Linhas geradas: $rows\n";
if (!empty($unknown)){
    echo "Nomes não encontrados (precisa ajustar usernames no DB):\n";
    foreach ($unknown as $n=>$c) echo " - $n  (candidatos: ".implode(',',$c).")\n";
}
echo "Arquivos: $outCsv , $outSql\n";
exit(0);
