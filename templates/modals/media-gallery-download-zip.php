<?php
require_once(dirname(__DIR__, 2) . "/includes/lib-initialize.php");
require_once(dirname(__DIR__, 2) . "/includes/private_notes/permissions.php");
require_once(dirname(__DIR__, 2) . "/includes/csrf-middleware.php");

$raw = file_get_contents('php://input');
$input = json_decode((string)$raw, true);
if (!is_array($input)) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'error' => 'Invalid payload']);
    exit;
}

$publicToken = trim((string)($input['public_token'] ?? ''));
$isPublicTokenRequest = ($publicToken !== '');
$isLoggedIn = $session->isLoggedIn();
if (!$isLoggedIn && !$isPublicTokenRequest) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'error' => 'Unauthorized']);
    exit;
}
if (!$isPublicTokenRequest) {
    csrf_require_for_request('json');
}

if (!class_exists('ZipArchive')) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'error' => 'ZIP extension is not available']);
    exit;
}

$ids = isset($input['ids']) && is_array($input['ids']) ? $input['ids'] : [];
$ids = array_values(array_unique(array_map('intval', $ids)));
$ids = array_values(array_filter($ids, function ($v) { return $v > 0; }));
if (empty($ids)) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'error' => 'No media ids selected']);
    exit;
}

$noteId = isset($input['note_id']) ? (int)$input['note_id'] : 0;
$mediaUserId = 0;
if ($isPublicTokenRequest) {
    $pub = privateNotesGetPublicAccessByToken($database, $publicToken);
    if (!$pub || (int)$pub['id'] !== $noteId || $noteId <= 0 || (string)($pub['public_share_permission'] ?? 'view') !== 'edit') {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'error', 'error' => 'Public edit access denied']);
        exit;
    }
    $ownerSql = "SELECT user_id FROM private_notes WHERE id = {$noteId} LIMIT 1";
    $ownerRes = $database->query($ownerSql);
    $ownerRow = $ownerRes ? $database->fetchArray($ownerRes) : null;
    $mediaUserId = (int)($ownerRow['user_id'] ?? 0);
    if ($mediaUserId <= 0) {
        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'error', 'error' => 'Note not found']);
        exit;
    }
} else {
    $mediaUserId = (int)$session->userId;
}

$in = implode(',', array_map('intval', $ids));
$where = "id IN ({$in}) AND user_id = {$mediaUserId}";
if ($noteId > 0) {
    $where .= " AND note_id = {$noteId}";
}

$sql = "SELECT id, file_path FROM private_note_media WHERE {$where} ORDER BY id DESC";
$res = $database->query($sql);
$rows = [];
while ($res && ($row = $database->fetchArray($res))) {
    $path = str_replace('\\', '/', trim((string)($row['file_path'] ?? '')));
    if ($path === '') continue;
    $abs = rtrim((string)SITE_ROOT, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim($path, '/'));
    if (!is_file($abs)) continue;
    $rows[] = ['id' => (int)$row['id'], 'path' => $path, 'abs' => $abs];
}

if (empty($rows)) {
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'error' => 'No files found']);
    exit;
}

$tmpZip = rtrim((string)sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'media_gallery_' . uniqid('', true) . '.zip';
$zip = new ZipArchive();
if ($zip->open($tmpZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'error' => 'Could not create zip']);
    exit;
}

$nameCount = [];
foreach ($rows as $row) {
    $base = basename((string)$row['path']);
    $ext = pathinfo($base, PATHINFO_EXTENSION);
    $stem = pathinfo($base, PATHINFO_FILENAME);
    $key = strtolower($base);
    if (!isset($nameCount[$key])) {
        $nameCount[$key] = 0;
        $zipName = $base;
    } else {
        $nameCount[$key]++;
        $idx = $nameCount[$key];
        $zipName = $stem . '_' . $idx . ($ext !== '' ? '.' . $ext : '');
    }
    $zip->addFile((string)$row['abs'], (string)$zipName);
}
$zip->close();

$downloadName = 'media_gallery_' . date('Ymd_His') . '.zip';
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Content-Length: ' . (string)filesize($tmpZip));
header('Cache-Control: no-store, no-cache, must-revalidate');
readfile($tmpZip);
@unlink($tmpZip);
exit;
