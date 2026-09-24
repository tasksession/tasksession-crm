<?php
require_once __DIR__ . '/../../includes/lib-initialize.php';
require_once __DIR__ . '/../../includes/import-export/CsvUtilities.php';

if (!$session->isLoggedIn()) {
    header('HTTP/1.1 403 Forbidden');
    exit;
}
$isAdmin = isset($_SESSION['accountStatus']) && (int)$_SESSION['accountStatus'] === 1;
if (!$isAdmin) {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

$isSample = isset($_GET['sample']) && $_GET['sample'] === '1';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . ($isSample ? 'tasks-sample.csv' : 'tasks-export.csv'));
$out = fopen('php://output', 'w');

$headers = [
    'Task ID',
    'Task Title',
    'Description',
    'Project ID',
    'Assigned To',
    'Start Date',
    'Due Date',
    'Status',
];
$headerNormUsed = [];
foreach ($headers as $h0) {
    $nh = strtolower(trim((string)$h0));
    $nh = preg_replace('/\s+/', ' ', $nh);
    $headerNormUsed[$nh] = true;
}
$cfRes = mysqli_query(
    $connect,
    "SELECT id, label FROM custom_fields WHERE entity_type = 'task' AND COALESCE(is_disabled, 0) = 0 ORDER BY sort_order ASC, id ASC"
);
$cfIds = [];
if ($cfRes) {
    while ($cf = mysqli_fetch_assoc($cfRes)) {
        $fid = (int)$cf['id'];
        $base = trim((string)($cf['label'] ?? ''));
        if ($base === '') {
            $base = 'Custom field ' . $fid;
        }
        $colName = $base;
        $norm = strtolower($base);
        $norm = preg_replace('/\s+/', ' ', $norm);
        if (isset($headerNormUsed[$norm])) {
            $colName = $base . ' [' . $fid . ']';
            $norm = strtolower($colName);
            $norm = preg_replace('/\s+/', ' ', $norm);
        }
        $headerNormUsed[$norm] = true;
        $headers[] = $colName;
        $cfIds[] = $fid;
    }
}
fputcsv($out, $headers);

if ($isSample) {
    $sampleRow = [
        '',
        'Sample task',
        'Task description',
        '0',
        '1',
        date('Y-m-d', strtotime('+1 day')),
        date('Y-m-d', strtotime('+2 week')),
        'todo',
    ];
    foreach ($cfIds as $_fid) {
        $sampleRow[] = '';
    }
    fputcsv($out, Comon_IE_CsvUtilities::safeCsvRow($sampleRow));
    fclose($out);
    exit;
}

$res = mysqli_query($connect, 'SELECT * FROM tasks ORDER BY id DESC LIMIT 5000');
if ($res) {
    while ($t = mysqli_fetch_assoc($res)) {
        $tid = (int)($t['id'] ?? 0);
        $row = [
            (string)$tid,
            $t['title'] ?? '',
            $t['description'] ?? '',
            (string)($t['project_id'] ?? '0'),
            $t['assigned_to'] ?? '',
            $t['start_date'] ?? '',
            $t['due_date'] ?? '',
            $t['status'] ?? 'todo',
        ];
        foreach ($cfIds as $fid) {
            $val = '';
            $st = $connect->prepare('SELECT field_value FROM task_custom_field_values WHERE task_id = ? AND custom_field_id = ? LIMIT 1');
            if ($st) {
                $st->bind_param('ii', $tid, $fid);
                $st->execute();
                $r2 = $st->get_result();
                if ($r2 && ($x = $r2->fetch_assoc())) {
                    $val = (string)$x['field_value'];
                }
                $st->close();
            }
            $row[] = $val;
        }
        fputcsv($out, Comon_IE_CsvUtilities::safeCsvRow($row));
    }
}
fclose($out);
exit;
