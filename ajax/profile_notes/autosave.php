<?php
/**
 * Autosave for profile tab notes (table profile_notes: subject user + viewer as creator).
 * Not the same as ajax/private_notes/autosave.php.
 */
require_once __DIR__ . '/../../includes/lib-initialize.php';
require_once __DIR__ . '/../../includes/csrf-middleware.php';

header('Content-Type: application/json; charset=utf-8');

if (!$session->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'error' => 'Unauthorized']);
    exit;
}

$creatorType = 'admin';
if ($session->isStaff()) {
    $creatorType = 'staff';
} elseif (!$session->isAdmin()) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'error' => 'Access denied']);
    exit;
}

csrf_require_for_request('json');

$raw = csrf_request_body_raw();
$input = json_decode($raw !== '' ? $raw : '[]', true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'error' => 'Invalid payload']);
    exit;
}

$noteId = (int) ($input['note_id'] ?? 0);
$profileUserId = (int) ($input['profile_user_id'] ?? 0);
$clientUpdatedAt = trim((string) ($input['client_updated_at'] ?? ''));
$content = trim(sanitize_tinymce_content((string) ($input['note_content'] ?? '')));

if ($profileUserId <= 0) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'error' => 'Invalid profile user']);
    exit;
}
$subjectUser = User::findById($profileUserId);
if (!$subjectUser) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'error' => 'User not found']);
    exit;
}
if ($session->isStaff()) {
    require_once __DIR__ . '/../../includes/permissions.php';
    ensure_user_permissions($connect);
    if ((int) $subjectUser->accountStatus === 2 && !has_permission('client_view')) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'error' => 'Access denied']);
        exit;
    }
}
if ($content === '') {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'error' => 'Note content cannot be empty']);
    exit;
}

$creatorId = (int) $session->userId;
$ct = $creatorType;
$enc = encryptString($content);

if ($noteId <= 0) {
    $stmt = $connect->prepare('INSERT INTO profile_notes (user_id, creator_id, creator_type, content, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())');
    $stmt->bind_param('iiss', $profileUserId, $creatorId, $ct, $enc);
    if (!$stmt->execute()) {
        $stmt->close();
        http_response_code(500);
        echo json_encode(['status' => 'error', 'error' => 'Failed to create note']);
        exit;
    }
    $newId = (int) $connect->insert_id;
    $stmt->close();

    $q = $connect->prepare('SELECT updated_at FROM profile_notes WHERE id = ? AND user_id = ? AND creator_id = ? AND creator_type = ? LIMIT 1');
    $q->bind_param('iiis', $newId, $profileUserId, $creatorId, $ct);
    $q->execute();
    $row = $q->get_result()->fetch_assoc();
    $q->close();
    $updatedAt = (string) ($row['updated_at'] ?? '');

    echo json_encode([
        'status' => 'ok',
        'saved' => true,
        'created' => true,
        'note_id' => $newId,
        'updated_at' => $updatedAt,
    ]);
    exit;
}

$sel = $connect->prepare('SELECT id, content, updated_at FROM profile_notes WHERE id = ? AND user_id = ? AND creator_id = ? AND creator_type = ? LIMIT 1');
$sel->bind_param('iiis', $noteId, $profileUserId, $creatorId, $ct);
$sel->execute();
$noteRow = $sel->get_result()->fetch_assoc();
$sel->close();

if (empty($noteRow['id'])) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'error' => 'Note not found']);
    exit;
}

$serverUpdatedAt = (string) ($noteRow['updated_at'] ?? '');
if ($clientUpdatedAt !== '' && $serverUpdatedAt !== '' && $clientUpdatedAt !== $serverUpdatedAt) {
    http_response_code(409);
    echo json_encode([
        'status' => 'conflict',
        'error' => 'Note was updated elsewhere',
        'updated_at' => $serverUpdatedAt,
    ]);
    exit;
}

$upd = $connect->prepare('UPDATE profile_notes SET content = ?, updated_at = NOW() WHERE id = ? AND user_id = ? AND creator_id = ? AND creator_type = ?');
$upd->bind_param('siiis', $enc, $noteId, $profileUserId, $creatorId, $ct);
if (!$upd->execute()) {
    $upd->close();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'error' => 'Failed to update note']);
    exit;
}
$upd->close();

$q2 = $connect->prepare('SELECT updated_at FROM profile_notes WHERE id = ? LIMIT 1');
$q2->bind_param('i', $noteId);
$q2->execute();
$row2 = $q2->get_result()->fetch_assoc();
$q2->close();
$updatedAt2 = (string) ($row2['updated_at'] ?? '');

echo json_encode([
    'status' => 'ok',
    'saved' => true,
    'created' => false,
    'note_id' => $noteId,
    'updated_at' => $updatedAt2,
]);
