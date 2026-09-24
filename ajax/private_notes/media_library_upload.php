<?php
require_once("../../includes/lib-initialize.php");
require_once("../../includes/private_notes/permissions.php");
require_once("../../includes/private_notes/upload_settings.php");
require_once("../../includes/private_notes/media_thumbs.php");
require_once("../../includes/csrf-middleware.php");

header('Content-Type: application/json; charset=utf-8');

$__mluLog = static function (string $line): void {
	@error_log('[media_library_upload] ' . $line);
};

$__mluLog('---');
$__mluLog('uri=' . (string) ($_SERVER['REQUEST_URI'] ?? '') . ' host=' . (string) ($_SERVER['HTTP_HOST'] ?? ''));
$__mluLog('content_type=' . (string) ($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? ''));
$__mluLog('post_keys=' . implode(',', array_keys($_POST)) . ' has_file_media=' . (isset($_FILES['media']) ? '1' : '0'));

$publicToken = trim((string)($_POST['public_token'] ?? ''));
$isPublicTokenRequest = ($publicToken !== '');
$isLoggedIn = $session->isLoggedIn();
if (!$isLoggedIn && !$isPublicTokenRequest) {
	$__mluLog('exit=401_unauthorized');
	http_response_code(401);
	echo json_encode(['status' => 'error', 'error' => 'Unauthorized']);
	exit;
}
$__mluLog('user_id=' . (int) ($session->userId ?? 0) . ' public=' . ($isPublicTokenRequest ? '1' : '0'));
if (!$isPublicTokenRequest) {
	$ct0 = function_exists('csrf_collect_request_token') ? csrf_collect_request_token() : '';
	$hasHdr = !empty($_SERVER['HTTP_X_CSRF_TOKEN']) ? 1 : 0;
	$ok0 = $ct0 !== '' && function_exists('validate_csrf_token') && validate_csrf_token($ct0);
	$__mluLog('pre_csrf: post_csrf=' . (isset($_POST['csrf_token']) ? 1 : 0) . ' x_csrf=' . $hasHdr . ' collected_len=' . strlen($ct0) . ' valid=' . ($ok0 ? '1' : '0'));
	csrf_require_for_request('json');
	$__mluLog('csrf_check=passed');
}

$noteId = isset($_POST['note_id']) ? (int)$_POST['note_id'] : 0;
$__mluLog('note_id=' . (int) $noteId);
$mediaUserId = 0;
if ($isPublicTokenRequest) {
    $pub = privateNotesGetPublicAccessByToken($database, $publicToken);
    if (!$pub || (int)$pub['id'] !== $noteId || $noteId <= 0 || (string)($pub['public_share_permission'] ?? 'view') !== 'edit') {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'error' => 'Public edit access denied']);
        exit;
    }
    $stOwner = mysqli_prepare($database->connection, "SELECT user_id FROM private_notes WHERE id = ? LIMIT 1");
    if ($stOwner) {
        mysqli_stmt_bind_param($stOwner, "i", $noteId);
        mysqli_stmt_execute($stOwner);
        $uid = 0;
        mysqli_stmt_bind_result($stOwner, $uid);
        $ownerRow = mysqli_stmt_fetch($stOwner) ? ['user_id' => $uid] : null;
        mysqli_stmt_close($stOwner);
    } else {
        $ownerRow = null;
    }
    $mediaUserId = (int)($ownerRow['user_id'] ?? 0);
    if ($mediaUserId <= 0) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'error' => 'Note not found']);
        exit;
    }
} else {
	$mediaUserId = (int) $session->userId;
	if ($noteId > 0 && ! privateNotesUserCanEdit($database, $noteId, $mediaUserId)) {
		$__mluLog('exit=403_private_note_edit: note_id=' . (int) $noteId . ' media_user=' . (int) $mediaUserId . ' (if this is a profile note id, client must send note_id=0)');
		http_response_code(403);
		echo json_encode(['status' => 'error', 'error' => 'You do not have edit permission for this note']);
		exit;
	}
	$__mluLog('private_note_permission=ok');
}

if (!isset($_FILES['media']) || !is_array($_FILES['media'])) {
	$__mluLog('exit=400_no_file');
	http_response_code(400);
	echo json_encode(['status' => 'error', 'error' => 'No media file uploaded']);
	exit;
}

$file = $_FILES['media'];
if ((int) $file['error'] !== UPLOAD_ERR_OK) {
	$__mluLog('exit=400_file_error code=' . (int) ($file['error'] ?? 0));
	http_response_code(400);
	echo json_encode(['status' => 'error', 'error' => 'Upload failed']);
	exit;
}

$settings = privateNotesGetUploadSettings($database);
$allowedExt = $settings['allowed_extensions'];
$origName = (string) $file['name'];
$ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
$__mluLog('file_meta name=' . $origName . ' ext=' . $ext . ' size=' . (int) ($file['size'] ?? 0) . ' tmp=' . (string) ($file['tmp_name'] ?? ''));

if (!in_array($ext, $allowedExt, true)) {
	$__mluLog('exit=400_ext ext=' . $ext . ' allowed=' . implode(',', $allowedExt));
	http_response_code(400);
	echo json_encode(['status' => 'error', 'error' => 'Unsupported image format']);
	exit;
}

$tmpPath = (string) $file['tmp_name'];
$mime = privateNotesValidateUploadedImage($tmpPath, $ext, $allowedExt);
if ($mime === null) {
	$__mluLog('exit=400_mime is_file=' . (is_file($tmpPath) ? '1' : '0') . ' ext=' . $ext);
	http_response_code(400);
	echo json_encode(['status' => 'error', 'error' => 'Only image uploads are allowed']);
	exit;
}
$__mluLog('mime_ok ' . $mime);

$maxBytes = (int) $settings['max_file_size_mb'] * 1024 * 1024;
if ((int) $file['size'] > $maxBytes) {
	$__mluLog('exit=400_too_big size=' . (int) $file['size'] . ' max=' . (int) $maxBytes);
	http_response_code(400);
	echo json_encode(['status' => 'error', 'error' => 'Image size must be ' . (int) $settings['max_file_size_mb'] . 'MB or less']);
	exit;
}

$subDir = '../../uploads/private-notes/' . $mediaUserId;
$__mluLog('subDir=' . $subDir);
if (!is_dir($subDir)) {
	@mkdir($subDir, 0755, true);
}
if (!is_dir($subDir)) {
	$__mluLog('exit=500_mkdir_fail');
	http_response_code(500);
	echo json_encode(['status' => 'error', 'error' => 'Could not prepare upload directory']);
	exit;
}

$fileName = uniqid('note_media_', true) . '.' . $ext;
$targetPath = $subDir . '/' . $fileName;
if (!@move_uploaded_file($tmpPath, $targetPath)) {
	$__mluLog('exit=500_move_from=' . $tmpPath);
	http_response_code(500);
	echo json_encode(['status' => 'error', 'error' => 'Could not save uploaded image']);
	exit;
}
$__mluLog('moved_to=' . $targetPath);

$relativePath = "uploads/private-notes/" . $mediaUserId . "/" . $fileName;
$noteIdInt = (int) $noteId;
$stIns = $noteIdInt > 0
    ? mysqli_prepare(
        $database->connection,
        "INSERT INTO private_note_media (note_id, user_id, file_path, mime_type) VALUES (?, ?, ?, ?)"
    )
    : mysqli_prepare(
        $database->connection,
        "INSERT INTO private_note_media (note_id, user_id, file_path, mime_type) VALUES (NULL, ?, ?, ?)"
    );
if ($stIns) {
    if ($noteIdInt > 0) {
        mysqli_stmt_bind_param($stIns, "iiss", $noteIdInt, $mediaUserId, $relativePath, $mime);
    } else {
        mysqli_stmt_bind_param($stIns, "iss", $mediaUserId, $relativePath, $mime);
    }
    mysqli_stmt_execute($stIns);
    mysqli_stmt_close($stIns);
} else {
    $newId = 0;
    $__mluLog('exit=500_insert_prepare');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'error' => 'Could not save media record']);
    exit;
}
$newId = (int) $database->insertId();
if ($newId <= 0) {
	$__mluLog('exit=500_insert_id0');
	http_response_code(500);
	echo json_encode(['status' => 'error', 'error' => 'Could not save media record']);
	exit;
}

$thumbRel = privateNotesEnsureThumbForRelativePath($relativePath, 320);
$mediaParams = ['src' => $relativePath];
if ($isPublicTokenRequest) {
    $mediaParams['token'] = $publicToken;
}
$publicUrl = rtrim((string) $url, '/') . '/share/private_media.php?' . http_build_query($mediaParams);
$__mluLog('ok new_media_id=' . (int) $newId . ' path=' . $relativePath);
echo json_encode([
    'status' => 'ok',
    'item' => [
        'id' => $newId,
        'note_id' => (int)$noteId,
        'thumb_url' => privateNotesPrivateMediaUrl($url, ($thumbRel !== '' ? $thumbRel : $relativePath), $isPublicTokenRequest ? $publicToken : ''),
        'url' => $publicUrl
    ],
    'settings' => [
        'max_file_size_mb' => (int)$settings['max_file_size_mb'],
        'allowed_extensions' => array_values($settings['allowed_extensions'])
    ]
]);
