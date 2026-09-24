<?php
ob_start();
require_once __DIR__ . '/../../includes/lib-initialize.php';
require_once __DIR__ . '/../../includes/import-export/CsvUtilities.php';
while (ob_get_level() > 0) {
    ob_end_clean();
}

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
$isAll = isset($_GET['all']) && $_GET['all'] === '1';
$notesType = isset($_GET['notes_type']) ? strtolower(trim((string)$_GET['notes_type'])) : 'private';
if (!in_array($notesType, ['private', 'profile', 'project', 'system'], true)) {
    $notesType = 'private';
}

if (!$isSample && !$isAll) {
    header('Location: ' . rtrim($url, '/') . '/import-export/migration/export/');
    exit;
}

if ($isAll) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=notes-docs-export.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, [
        'Note Type',
        'Note ID',
        'User ID',
        'Project ID',
        'Creator ID',
        'Creator Type',
        'Title',
        'Content',
        'Color',
        'Created At',
        'Updated At',
    ]);

    $qPriv = mysqli_query($connect, 'SELECT id, user_id, title, content, color, created_at, updated_at FROM private_notes ORDER BY id DESC LIMIT 20000');
    if ($qPriv) {
        while ($r = mysqli_fetch_assoc($qPriv)) {
            fputcsv($out, Comon_IE_CsvUtilities::safeCsvRow([
                'private',
                (int)($r['id'] ?? 0),
                (int)($r['user_id'] ?? 0),
                '',
                '',
                '',
                (string)($r['title'] ?? ''),
                (string)decryptString((string)($r['content'] ?? '')),
                (string)($r['color'] ?? ''),
                (string)($r['created_at'] ?? ''),
                (string)($r['updated_at'] ?? ''),
            ]));
        }
        mysqli_free_result($qPriv);
    }

    $qProfile = mysqli_query($connect, 'SELECT id, user_id, creator_id, creator_type, content, color, created_at, updated_at FROM profile_notes ORDER BY id DESC LIMIT 20000');
    if ($qProfile) {
        while ($r = mysqli_fetch_assoc($qProfile)) {
            fputcsv($out, Comon_IE_CsvUtilities::safeCsvRow([
                'profile',
                (int)($r['id'] ?? 0),
                (int)($r['user_id'] ?? 0),
                '',
                (int)($r['creator_id'] ?? 0),
                (string)($r['creator_type'] ?? ''),
                '',
                (string)decryptString((string)($r['content'] ?? '')),
                (string)($r['color'] ?? ''),
                (string)($r['created_at'] ?? ''),
                (string)($r['updated_at'] ?? ''),
            ]));
        }
        mysqli_free_result($qProfile);
    }

    $qProject = mysqli_query($connect, 'SELECT id, project_id, creator_id, creator_type, title, content, color, created_at, updated_at FROM project_tab_notes ORDER BY id DESC LIMIT 20000');
    if ($qProject) {
        while ($r = mysqli_fetch_assoc($qProject)) {
            fputcsv($out, Comon_IE_CsvUtilities::safeCsvRow([
                'project',
                (int)($r['id'] ?? 0),
                '',
                (int)($r['project_id'] ?? 0),
                (int)($r['creator_id'] ?? 0),
                (string)($r['creator_type'] ?? ''),
                (string)($r['title'] ?? ''),
                (string)decryptString((string)($r['content'] ?? '')),
                (string)($r['color'] ?? ''),
                (string)($r['created_at'] ?? ''),
                (string)($r['updated_at'] ?? ''),
            ]));
        }
        mysqli_free_result($qProject);
    }

    fclose($out);
    exit;
}

if ($notesType === 'system') {
    $headers = ['Note Type', 'User ID', 'Project ID', 'Creator ID', 'Creator Type', 'Title', 'Content', 'Color'];
    $rows = [
        ['private', (int)$session->userId, '', '', '', 'Private doc sample', '<p>Sample private note</p>', 'default'],
        ['profile', 1, '', (int)$session->userId, 'admin', '', '<p>Sample profile note</p>', 'blue'],
        ['project', '', 1, (int)$session->userId, 'admin', 'Project update', '<p>Sample project note</p>', 'green'],
    ];
    $name = 'notes-system-import-sample.csv';
} elseif ($notesType === 'profile') {
    $headers = ['User ID', 'Creator ID', 'Creator Type', 'Content', 'Color'];
    $rows = [[1, (int)$session->userId, 'admin', '<p>Sample profile note</p>', 'blue']];
    $name = 'profile-docs-import-sample.csv';
} elseif ($notesType === 'project') {
    $headers = ['Project ID', 'Creator ID', 'Creator Type', 'Title', 'Content', 'Color'];
    $rows = [[1, (int)$session->userId, 'admin', 'Project update', '<p>Sample project note</p>', 'green']];
    $name = 'project-docs-import-sample.csv';
} else {
    $headers = ['User ID', 'Title', 'Content', 'Color'];
    $rows = [[(int)$session->userId, 'Private doc sample', '<p>Sample private note</p>', 'default']];
    $name = 'private-docs-import-sample.csv';
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $name);
$out = fopen('php://output', 'w');
fputcsv($out, $headers);
foreach ($rows as $r) {
    fputcsv($out, Comon_IE_CsvUtilities::safeCsvRow($r));
}
fclose($out);
exit;
