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
header('Content-Disposition: attachment; filename=' . ($isSample ? 'projects-sample.csv' : 'projects-export.csv'));
$out = fopen('php://output', 'w');

$headers = [
    'Project ID',
    'Project Title',
    'Project Description',
    'Budget',
    'Status',
    'Archive',
    'Trash',
    'Start Date',
    'End Date',
    'Company ID',
    'Main Client ID',
    'Additional Client IDs',
    'Assigned Staff',
];
$headerNormUsed = [];
foreach ($headers as $h0) {
    $nh = strtolower(trim((string)$h0));
    $nh = preg_replace('/\s+/', ' ', $nh);
    $headerNormUsed[$nh] = true;
}
$cfRes = mysqli_query(
    $connect,
    "SELECT id, label FROM custom_fields WHERE entity_type = 'project' AND COALESCE(is_disabled, 0) = 0 ORDER BY sort_order ASC, id ASC"
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
    $mainClientSample = '';
    $cq = @mysqli_query($connect, 'SELECT id FROM users WHERE accountStatus = 2 ORDER BY id ASC LIMIT 1');
    if ($cq && ($cr = mysqli_fetch_assoc($cq))) {
        $mainClientSample = (string)(int)($cr['id'] ?? 0);
    }
    if ($cq) {
        mysqli_free_result($cq);
    }
    $staffIds = [];
    $sq = @mysqli_query($connect, 'SELECT id FROM users WHERE accountStatus IN (1, 3) ORDER BY id ASC LIMIT 2');
    if ($sq) {
        while ($sr = mysqli_fetch_assoc($sq)) {
            $sid = (int)($sr['id'] ?? 0);
            if ($sid > 0) {
                $staffIds[] = $sid;
            }
        }
        mysqli_free_result($sq);
    }
    if ($staffIds === []) {
        $staffCsv = '';
    } elseif (isset($staffIds[1])) {
        $staffCsv = $staffIds[0] . ',' . $staffIds[1];
    } else {
        $staffCsv = (string)$staffIds[0];
    }
    $sampleTitle = 'Sample Project ' . gmdate('Y-m-d H:i') . ' UTC';
    $sampleRow = [
        '',
        $sampleTitle,
        'Imported project description',
        '5000',
        '0',
        '0',
        '0',
        date('Y-m-d', strtotime('+1 week')),
        date('Y-m-d', strtotime('+2 month')),
        '',
        $mainClientSample,
        '',
        $staffCsv,
    ];
    foreach ($cfIds as $_fid) {
        $sampleRow[] = '';
    }
    fputcsv($out, Comon_IE_CsvUtilities::safeCsvRow($sampleRow));
    fclose($out);
    exit;
}

$res = mysqli_query($connect, 'SELECT * FROM projects WHERE trash != 1 ORDER BY p_id DESC LIMIT 5000');
if ($res) {
    while ($p = mysqli_fetch_assoc($res)) {
        $pid = (int)($p['p_id'] ?? 0);
        $row = [
            (string)$pid,
            $p['project_title'] ?? '',
            $p['project_desc'] ?? '',
            (string)($p['budget'] ?? '0'),
            (string)($p['status'] ?? '0'),
            (string)($p['archive'] ?? '0'),
            (string)($p['trash'] ?? '0'),
            $p['start_time'] ?? '',
            $p['end_time'] ?? '',
            '',
            (string)($p['main_client_id'] ?? $p['c_id'] ?? ''),
            $p['c_ids'] ?? '',
            $p['s_ids'] ?? '',
        ];
        foreach ($cfIds as $fid) {
            $val = '';
            $st = $connect->prepare('SELECT field_value FROM project_custom_field_values WHERE project_id = ? AND custom_field_id = ? LIMIT 1');
            if ($st) {
                $st->bind_param('ii', $pid, $fid);
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
