<?php
require_once("../../includes/lib-initialize.php");
require_once("../../includes/private_notes/permissions.php");
require_once("../../includes/private_notes/upload_settings.php");
require_once("../../includes/private_notes/media_thumbs.php");

header('Content-Type: application/json; charset=utf-8');

$isLoggedIn = $session->isLoggedIn();
$publicToken = trim((string)($_REQUEST['public_token'] ?? ''));
$isPublicTokenRequest = ($publicToken !== '');
if (!$isLoggedIn && !$isPublicTokenRequest) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'error' => 'Unauthorized']);
    exit;
}

$scope = strtolower(trim((string)($_REQUEST['scope'] ?? 'note')));
if ($scope !== 'all' && $scope !== 'note') {
    $scope = 'note';
}
$noteId = isset($_REQUEST['note_id']) ? (int)$_REQUEST['note_id'] : 0;
$limit = isset($_REQUEST['limit']) ? (int)$_REQUEST['limit'] : 30;
$offset = isset($_REQUEST['offset']) ? (int)$_REQUEST['offset'] : 0;
$limit = max(1, min(60, $limit));
$offset = max(0, $offset);

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
    $scope = 'note';
} else {
    $mediaUserId = (int)$session->userId;
}

$where = ["user_id = {$mediaUserId}"];
if ($scope === 'note' && $noteId > 0) {
    if (!$isPublicTokenRequest && !privateNotesUserCanEdit($database, $noteId, $mediaUserId)) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'error' => 'Access denied']);
        exit;
    }
    $where[] = "note_id = {$noteId}";
}

$whereSql = implode(' AND ', $where);
$countSql = "SELECT COUNT(*) AS total FROM private_note_media WHERE {$whereSql}";
$countRes = $database->query($countSql);
$countRow = $countRes ? $database->fetchArray($countRes) : null;
$total = (int)($countRow['total'] ?? 0);

$sql = "SELECT id, note_id, file_path
        FROM private_note_media
        WHERE {$whereSql}
        ORDER BY id DESC
        LIMIT {$limit} OFFSET {$offset}";
$res = $database->query($sql);
$items = [];
while ($res && ($row = $database->fetchArray($res))) {
    $path = trim((string)($row['file_path'] ?? ''));
    if ($path === '') {
        continue;
    }
    $thumbRel = privateNotesEnsureThumbForRelativePath($path, 320);
    $mediaParams = ['src' => $path];
    if ($isPublicTokenRequest) {
        $mediaParams['token'] = $publicToken;
    }
    $items[] = [
        'id' => (int)($row['id'] ?? 0),
        'note_id' => (int)($row['note_id'] ?? 0),
        'thumb_url' => privateNotesPrivateMediaUrl($url, ($thumbRel !== '' ? $thumbRel : $path), $isPublicTokenRequest ? $publicToken : ''),
        'url' => rtrim((string)$url, '/') . '/share/private_media.php?' . http_build_query($mediaParams)
    ];
}

$settings = privateNotesGetUploadSettings($database);
echo json_encode([
    'status' => 'ok',
    'items' => $items,
    'paging' => [
        'offset' => $offset,
        'limit' => $limit,
        'total' => $total,
        'has_more' => ($offset + count($items)) < $total
    ],
    'settings' => [
        'max_file_size_mb' => (int)$settings['max_file_size_mb'],
        'allowed_extensions' => array_values($settings['allowed_extensions'])
    ]
]);
