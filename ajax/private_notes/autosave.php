<?php
require_once("../../includes/lib-initialize.php");
require_once("../../includes/private_notes/permissions.php");
require_once("../../includes/csrf-middleware.php");

header('Content-Type: application/json; charset=utf-8');

$isLoggedIn = $session->isLoggedIn();
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'error' => 'Invalid payload']);
    exit;
}

$publicToken = trim((string)($input['public_token'] ?? ''));
$isPublicTokenRequest = ($publicToken !== '');
if (!$isLoggedIn && !$isPublicTokenRequest) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'error' => 'Unauthorized']);
    exit;
}
if (!$isPublicTokenRequest) {
    csrf_require_for_request('json');
}

$userId = $isLoggedIn ? (int)$session->userId : 0;
$noteId = (int)($input['note_id'] ?? 0);
$title = trim((string)($input['title'] ?? ''));
$content = trim(sanitize_tinymce_content((string)($input['note_content'] ?? '')));
$clientUpdatedAt = trim((string)($input['client_updated_at'] ?? ''));

if ($content === '') {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'error' => 'Note content cannot be empty']);
    exit;
}
if (strlen($title) > 255) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'error' => 'Title is too long']);
    exit;
}

if ($noteId <= 0) {
    if ($isPublicTokenRequest) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'error' => 'Invalid note id']);
        exit;
    }

    $encryptedContent = encryptString($content);
    $titleEsc = $database->escapeValue($title);
    $contentEsc = $database->escapeValue($encryptedContent);
    $insertSql = "INSERT INTO private_notes (user_id, title, content, color, created_at, updated_at)
                  VALUES ({$userId}, '{$titleEsc}', '{$contentEsc}', '', NOW(), NOW())";
    $insertOk = $database->query($insertSql);
    if (!$insertOk) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'error' => 'Failed to create note']);
        exit;
    }

    $newNoteId = (int)$database->insertId();
    $updatedSql = "SELECT updated_at FROM private_notes WHERE id = {$newNoteId} LIMIT 1";
    $updatedRes = $database->query($updatedSql);
    $updatedRow = $updatedRes ? $database->fetchArray($updatedRes) : null;
    $updatedAt = (string)($updatedRow['updated_at'] ?? '');

    echo json_encode([
        'status' => 'ok',
        'saved' => true,
        'created' => true,
        'note_id' => $newNoteId,
        'updated_at' => $updatedAt
    ]);
    exit;
}

$ctx = null;
if ($isPublicTokenRequest) {
    $pub = privateNotesGetPublicAccessByToken($database, $publicToken);
    if (!$pub || (int)$pub['id'] !== $noteId || (string)($pub['public_share_permission'] ?? 'view') !== 'edit') {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'error' => 'Public edit access denied']);
        exit;
    }
} else {
    $ctx = privateNotesGetAccessContext($database, $noteId, $userId);
    if (empty($ctx['can_edit'])) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'error' => 'You do not have permission to edit this note']);
        exit;
    }
}

$noteSql = "SELECT id, title, content, updated_at FROM private_notes WHERE id = {$noteId} LIMIT 1";
$noteRes = $database->query($noteSql);
$noteRow = $noteRes ? $database->fetchArray($noteRes) : null;
if (!$noteRow || empty($noteRow['id'])) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'error' => 'Note not found']);
    exit;
}

$serverUpdatedAt = (string)($noteRow['updated_at'] ?? '');
if ($clientUpdatedAt !== '' && $serverUpdatedAt !== '' && $clientUpdatedAt !== $serverUpdatedAt) {
    http_response_code(409);
    echo json_encode([
        'status' => 'conflict',
        'error' => 'Note was updated elsewhere',
        'updated_at' => $serverUpdatedAt
    ]);
    exit;
}

$currentTitle = (string)($noteRow['title'] ?? '');
$currentContent = '';
if (!empty($noteRow['content'])) {
    $currentContent = (string)decryptString((string)$noteRow['content']);
}

$effectiveTitle = $isPublicTokenRequest ? $currentTitle : $title;

if ($currentTitle === $effectiveTitle && $currentContent === $content) {
    echo json_encode([
        'status' => 'ok',
        'saved' => false,
        'updated_at' => $serverUpdatedAt
    ]);
    exit;
}

$encryptedContent = encryptString($content);
$titleEsc = $database->escapeValue($effectiveTitle);
$contentEsc = $database->escapeValue($encryptedContent);
$updateSql = "UPDATE private_notes
              SET title = '{$titleEsc}', content = '{$contentEsc}', updated_at = NOW()
              WHERE id = {$noteId}";
$ok = $database->query($updateSql);
if (!$ok) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'error' => 'Failed to save note']);
    exit;
}

$updatedSql = "SELECT updated_at FROM private_notes WHERE id = {$noteId} LIMIT 1";
$updatedRes = $database->query($updatedSql);
$updatedRow = $updatedRes ? $database->fetchArray($updatedRes) : null;
$updatedAt = (string)($updatedRow['updated_at'] ?? '');

echo json_encode([
    'status' => 'ok',
    'saved' => true,
    'created' => false,
    'note_id' => $noteId,
    'updated_at' => $updatedAt
]);
