<?php
require_once("../../includes/lib-initialize.php");
require_once("../../includes/private_notes/permissions.php");
require_once("../../includes/private_notes/upload_settings.php");
require_once("../../includes/csrf-middleware.php");

header('Content-Type: application/json; charset=utf-8');

$__umLog = static function (string $line): void {
	$logDir = dirname(__DIR__, 2) . '/logs';
	if (!is_dir($logDir)) {
		@mkdir($logDir, 0755, true);
	}
	@file_put_contents(
		$logDir . '/upload_media.log',
		'[' . date('Y-m-d H:i:s') . '] ' . $line . "\n",
		FILE_APPEND | LOCK_EX
	);
};

$publicToken = trim((string) ($_POST['public_token'] ?? ''));
$isPublicTokenRequest = ($publicToken !== '');
$isLoggedIn = $session->isLoggedIn();
$__umLog('uri=' . (string) ($_SERVER['REQUEST_URI'] ?? '') . ' user=' . (int) ($session->userId ?? 0));

if (!$isLoggedIn && !$isPublicTokenRequest) {
	$__umLog('exit=401');
	http_response_code(401);
	echo json_encode(['status' => 'error', 'error' => 'Unauthorized']);
	exit;
}
if (!$isPublicTokenRequest) {
	$ct0 = function_exists('csrf_collect_request_token') ? csrf_collect_request_token() : '';
	$__umLog('pre_csrf len=' . strlen($ct0) . ' valid=' . (function_exists('validate_csrf_token') && $ct0 && validate_csrf_token($ct0) ? '1' : '0'));
	csrf_require_for_request('json');
	$__umLog('csrf_ok');
}

$noteId = isset($_POST['note_id']) ? (int) $_POST['note_id'] : 0;
$__umLog('note_id=' . (int) $noteId);
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
    $ownerUserId = (int)($ownerRow['user_id'] ?? 0);
    if ($ownerUserId <= 0) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'error' => 'Note not found']);
        exit;
    }
    $mediaUserId = $ownerUserId;
} else {
	$mediaUserId = (int) $session->userId;
	if ($noteId > 0 && ! privateNotesUserCanEdit($database, $noteId, $mediaUserId)) {
		$__umLog('exit=403_edit (profile note id sent as private note? send note_id=0)');
		http_response_code(403);
		echo json_encode(['status' => 'error', 'error' => 'You do not have edit permission for this note']);
		exit;
	}
	$__umLog('perm_ok');
}

if (!isset($_FILES['media']) || !is_array($_FILES['media'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'error' => 'No media file uploaded']);
    exit;
}

$file = $_FILES['media'];
if ((int) $file['error'] !== UPLOAD_ERR_OK) {
	$__umLog('exit=400_file_err ' . (int) ($file['error'] ?? 0));
	http_response_code(400);
	echo json_encode(['status' => 'error', 'error' => 'Upload failed']);
	exit;
}

$uploadSettings = privateNotesGetUploadSettings($database);
$allowedExt = $uploadSettings['allowed_extensions'];
$origName = (string) $file['name'];
$ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
$tmpPath = (string) $file['tmp_name'];
$__umLog('name=' . $origName . ' ext=' . $ext . ' size=' . (int) ($file['size'] ?? 0));

if (!in_array($ext, $allowedExt, true)) {
	$__umLog('exit=400_ext');
	http_response_code(400);
	echo json_encode(['status' => 'error', 'error' => 'Unsupported image format']);
	exit;
}

$mime = privateNotesValidateUploadedImage($tmpPath, $ext, $allowedExt);
if ($mime === null) {
	$__umLog('exit=400_mime');
	http_response_code(400);
	echo json_encode(['status' => 'error', 'error' => 'Only image uploads are allowed']);
	exit;
}
$__umLog('mime=' . $mime);

$maxBytes = (int)$uploadSettings['max_file_size_mb'] * 1024 * 1024;
if ((int)$file['size'] > $maxBytes) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'error' => 'Image size must be ' . (int)$uploadSettings['max_file_size_mb'] . 'MB or less']);
    exit;
}

$subDir = "../../uploads/private-notes/" . $mediaUserId;
if (!is_dir($subDir)) {
    @mkdir($subDir, 0755, true);
}
if (!is_dir($subDir)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'error' => 'Could not prepare upload directory']);
    exit;
}

$fileName = uniqid('note_media_', true) . "." . $ext;
$targetPath = $subDir . "/" . $fileName;
if (!@move_uploaded_file($tmpPath, $targetPath)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'error' => 'Could not save uploaded image']);
    exit;
}

$relativePath = "uploads/private-notes/" . $mediaUserId . "/" . $fileName;
$escapedPath = $database->escapeValue($relativePath);
$escapedMime = $database->escapeValue($mime);
$noteIdSql = $noteId > 0 ? (string) (int) $noteId : 'NULL';
$insertSql = "INSERT INTO private_note_media (note_id, user_id, file_path, mime_type)
              VALUES ({$noteIdSql}, " . (int) $mediaUserId . ", '{$escapedPath}', '{$escapedMime}')";
$database->query($insertSql);

$mediaParams = ['src' => $relativePath];
if ($isPublicTokenRequest) {
    $mediaParams['token'] = $publicToken;
}
$publicUrl = rtrim((string)$url, '/') . '/share/private_media.php?' . http_build_query($mediaParams);
echo json_encode(['status' => 'ok', 'url' => $publicUrl]);
