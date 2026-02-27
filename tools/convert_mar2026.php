<?php
/**
 * Converter XLSX (mar-26.xlsx) para CSV e SQL para março/2026
 * - Lê /uploads/mar-26.xlsx
 * - Assume primeira aba contém: coluna A = nome completo, colunas B.. = dias (1..31)
 * - Gera db/mar-2026.csv e db/mar-2026.sql
 */

require __DIR__ . '/../bootstrap.php';

$xlsxPath = __DIR__ . '/../uploads/mar-26.xlsx';
if (!file_exists($xlsxPath)) {
    echo "Arquivo not found: $xlsxPath\n";
    exit(1);
}

// unzip XML parts using system unzip (ZipArchive not available)
$shared = [];
$ss = shell_exec("unzip -p " . escapeshellarg($xlsxPath) . " xl/sharedStrings.xml 2>/dev/null");
if ($ss) {
    $xml = simplexml_load_string($ss);
    if ($xml && isset($xml->si)) {
        foreach ($xml->si as $si) {
            $text = '';
            if (isset($si->t)) {
                $text = (string)$si->t;
            } else {
                foreach ($si->r as $r) { $text .= (string)$r->t; }
            }
            $shared[] = trim((string)$text);
        }
    }
}

// try worksheets
$sheetXml = shell_exec("unzip -p " . escapeshellarg($xlsxPath) . " xl/worksheets/sheet1.xml 2>/dev/null");
if (!$sheetXml) {
    // search other sheets
    for ($i=1;$i<=10;$i++){
        $sheetXml = shell_exec("unzip -p " . escapeshellarg($xlsxPath) . " xl/worksheets/sheet{$i}.xml 2>/dev/null");
        if ($sheetXml) break;
    }
}
if (!$sheetXml) {
    echo "Nenhuma worksheet encontrada\n";
    exit(1);
}

$xml = simplexml_load_string($sheetXml);

// build grid of cells
$cells = [];
$maxRow = 0;
$maxCol = 0;

foreach ($xml->sheetData->row as $row) {
    $r = (int)$row['r'];
    if ($r > $maxRow) $maxRow = $r;
    foreach ($row->c as $c) {
        $ref = (string)$c['r'];
        if (preg_match('/^([A-Z]+)(\d+)$/', $ref, $m)) {
            $col = $m[1]; $rowNum = (int)$m[2];
            $v = '';
            if (isset($c->v)) {
                $v = (string)$c->v;
                if (isset($c['t']) && (string)$c['t'] === 's') {
                    $idxs = (int)$v;
                    $v = $shared[$idxs] ?? $v;
                }
            } elseif (isset($c->is->t)) {
                $v = (string)$c->is->t;
            }
            $cells[$rowNum][$col] = trim((string)$v);
            // estimate max col (A=1..)
            $colNum = 0; foreach (str_split($col) as $chr) { $colNum = $colNum*26 + (ord($chr)-64); }
            if ($colNum > $maxCol) $maxCol = $colNum;
        }
    }
}

// find day columns from header row (assume row 1)
$dayCols = [];
for ($c=1;$c<=$maxCol;$c++){
    // convert col num to letter
    $col=''; $n=$c; while($n>0){ $mod=($n-1)%26; $col=chr(65+$mod).$col; $n = intval(($n-1)/26); }
    $val = $cells[1][$col] ?? '';
    if (preg_match('/^(\d{1,2})$/', trim($val), $mm)) {
        $dayCols[$col] = (int)$mm[1];
    }
}

if (empty($dayCols)) {
    echo "Não identifiquei colunas de dias na primeira linha.\n";
    exit(1);
}

// assume name column is first column (A) or detect column with many text values
$nameCol = 'A';
// detect candidate columns where many rows have text with space
for ($c=1;$c<=3;$c++){
    $col=''; $n=$c; while($n>0){ $mod=($n-1)%26; $col=chr(65+$mod).$col; $n = intval(($n-1)/26); }
    $countNames = 0; for ($r=2;$r<=$maxRow;$r++){ $v = $cells[$r][$col] ?? ''; if ($v && preg_match('/[[:alpha:]]+\s+[[:alpha:]]+/',$v)) $countNames++; }
    if ($countNames > ($maxRow-2)/4) { $nameCol = $col; break; }
}

// parse month/year from filename
$fname = basename($xlsxPath);
$month = 3; $year = 2026;
if (preg_match('/(jan|fev|mar|abr|mai|jun|jul|ago|set|out|nov|dez)/i', $fname, $m)){
    $map = ['jan'=>1,'fev'=>2,'mar'=>3,'abr'=>4,'mai'=>5,'jun'=>6,'jul'=>7,'ago'=>8,'set'=>9,'out'=>10,'nov'=>11,'dez'=>12];
    $month = $map[strtolower($m[1])] ?? $month;
}
if (preg_match('/(20\d\d|\d{2})(?=\.xlsx|_|-|$)/', $fname, $y)){
    $yy = $y[1]; if (strlen($yy)==2) $yy = '20'.$yy; $year = (int)$yy;
}

$outCsv = __DIR__ . '/../db/mar-2026.csv';
$outSql = __DIR__ . '/../db/mar-2026.sql';
$fhCsv = fopen($outCsv,'w');
fputcsv($fhCsv, ['date','username','shift','note']);
$fhSql = fopen($outSql,'w');

$unknown = [];
$rowsInserted = 0;

for ($r=2;$r<=$maxRow;$r++){
    $name = $cells[$r][$nameCol] ?? '';
    if (!$name) continue;
    // derive username candidates
    $usernameCandidates = [];
    $parts = preg_split('/\s+/', trim($name));
    $first = preg_replace('/[^A-Za-z]/','',strtolower($parts[0]));
    if ($first) $usernameCandidates[] = $first;
    if (isset($parts[1])){
        $second = $parts[1];
        $initial = preg_replace('/[^A-Za-z]/','',strtolower($second));
        if ($initial) $usernameCandidates[] = $first.'.'.$initial;
    }
    // also try first.last initial style
    if (count($parts)>=2){
        $last = preg_replace('/[^A-Za-z]/','',strtolower(end($parts)));
        if ($last) $usernameCandidates[] = $first.'.'.$last;
    }

    // dedupe
    $usernameCandidates = array_values(array_unique($usernameCandidates));

    // try to find a matching user in DB
    $userId = null; $matchedUsername = null;
    foreach ($usernameCandidates as $cand){
        $stmt = $pdo->prepare('SELECT id, username FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$cand]);
        $u = $stmt->fetch();
        if ($u) { $userId = $u['id']; $matchedUsername = $u['username']; break; }
    }
    if (!$userId) {
        $unknown[$name] = $usernameCandidates;
        continue; // skip unknowns
    }

    // iterate day columns
    foreach ($dayCols as $col=>$dayNum){
        $cellVal = $cells[$r][$col] ?? '';
        $cellVal = trim($cellVal);
        if ($cellVal==='') continue;
        $low = strtolower($cellVal);
        // normalize shift
        if (strpos($low,'agenda')!==false) $shift = 'AGENDA';
        elseif (strpos($low,'folga')!==false) $shift = 'FOLGA';
        elseif (strpos($low,'fer')!==false) $shift = 'FÉRIAS';
        elseif (strpos($low,'aus')!==false) $shift = 'AUSENTE';
        else $shift = strtoupper($cellVal);

        $date = sprintf('%04d-%02d-%02d',$year,$month,$dayNum);
        // write CSV
        fputcsv($fhCsv, [$date, $matchedUsername, $shift, $cellVal]);
        // write SQL using SELECT id FROM users
        $dateSql = $pdo->quote($date);
        $shiftSql = $pdo->quote($shift);
        $noteSql = $pdo->quote($cellVal);
        $userSql = $pdo->quote($matchedUsername);
        $line = "INSERT INTO schedules (user_id, date, shift, note) SELECT id, $dateSql, $shiftSql, $noteSql FROM users WHERE username = $userSql;\n";
        fwrite($fhSql, $line);
        $rowsInserted++;
    }
}

fclose($fhCsv);
fclose($fhSql);

echo "Conversão concluída. Linhas geradas: $rowsInserted\n";
if (!empty($unknown)){
    echo "Nomes não encontrados no banco (verifique usernames):\n";
    foreach ($unknown as $k=>$cands) echo " - $k  (candidatos: ".implode(',', $cands).")\n";
}

echo "Arquivos gerados: $outCsv , $outSql\n";
exit(0);
