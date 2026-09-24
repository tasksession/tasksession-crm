<?php
require_once("../../includes/lib-initialize.php");
require_once("../../includes/private_notes/permissions.php");
require_once("../../includes/csrf-middleware.php");

header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'error' => 'Invalid payload']);
    exit;
}

$publicToken = trim((string)($input['public_token'] ?? ''));
$isPublicTokenRequest = ($publicToken !== '');
$isLoggedIn = $session->isLoggedIn();
if (!$isLoggedIn && !$isPublicTokenRequest) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'error' => 'Unauthorized']);
    exit;
}
if (!$isPublicTokenRequest) {
    csrf_require_for_request('json');
}

$ids = isset($input['ids']) && is_array($input['ids']) ? $input['ids'] : [];
$ids = array_values(array_unique(array_map('intval', $ids)));
$ids = array_filter($ids, function ($v) { return $v > 0; });
if (empty($ids)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'error' => 'No media ids selected']);
    exit;
}

$noteId = isset($input['note_id']) ? (int)$input['note_id'] : 0;
$mediaUserId = 0;
if ($isPublicTokenRequest) {
    $pub = privateNotesGetPublicAccessByToken($database, $publicToken);
    if (!$pub || (int)$pub['id'] !== $noteId || $noteId <= 0 || (string)($pub['public_share_permission'] ?? 'view') !== 'edit') {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'error' => 'Public edit access denied']);
        exit;
    }
    $ownerSql = "SELECT user_id FROM private_notes WHERE id = {$noteId} LIMIT 1";
    $ownerRes = $database->query($ownerSql);
    $ownerRow = $ownerRes ? $database->fetchArray($ownerRes) : null;
    $mediaUserId = (int)($ownerRow['user_id'] ?? 0);
    if ($mediaUserId <= 0) {
        http_response_code(404);
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

$selSql = "SELECT id, file_path FROM private_note_media WHERE {$where}";
$selRes = $database->query($selSql);
$rows = [];
while ($selRes && ($r = $database->fetchArray($selRes))) {
    $rows[] = $r;
}
if (empty($rows)) {
    echo json_encode(['status' => 'ok', 'deleted_count' => 0]);
    exit;
}

$deletedIds = [];
foreach ($rows as $row) {
    $rid = (int)($row['id'] ?? 0);
    $path = str_replace('\\', '/', trim((string)($row['file_path'] ?? '')));
    if ($rid <= 0 || $path === '') {
        continue;
    }
    $abs = rtrim((string)SITE_ROOT, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim($path, '/'));
    if (is_file($abs)) {
        @unlink($abs);
    }
    $thumbPath = '';
    $dir = trim((string)dirname($path), '/.');
    if ($dir !== '') {
        $thumbPath = $dir . '/thumbs/' . basename($path);
    }
    if ($thumbPath !== '') {
        $thumbAbs = rtrim((string)SITE_ROOT, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim($thumbPath, '/'));
        if (is_file($thumbAbs)) {
            @unlink($thumbAbs);
        }
    }
    $deletedIds[] = $rid;
}

if (!empty($deletedIds)) {
    $idIn = implode(',', array_map('intval', $deletedIds));
    $database->query("DELETE FROM private_note_media WHERE id IN ({$idIn}) AND user_id = {$mediaUserId}");
}

echo json_encode([
    'status' => 'ok',
    'deleted_count' => count($deletedIds)
]);
