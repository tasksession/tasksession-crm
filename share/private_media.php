<?php
require_once(__DIR__ . '/../includes/lib-initialize.php');
require_once(__DIR__ . '/../includes/private_notes/permissions.php');

@ini_set('display_errors', '0');
@ini_set('html_errors', '0');

header('X-Robots-Tag: noindex, nofollow, noarchive', true);
header('X-Content-Type-Options: nosniff');

$token = trim((string)($_GET['token'] ?? ''));
$src = trim((string)($_GET['src'] ?? ''));
if ($src === '') {
    http_response_code(400);
    exit('Invalid request');
}

$src = html_entity_decode($src, ENT_QUOTES, 'UTF-8');
$src = str_replace('\\', '/', $src);
$parsed = parse_url($src);
if ($parsed && isset($parsed['path'])) {
    $src = (string)$parsed['path'];
}
$src = ltrim($src, '/');
if (strpos($src, 'uploads/private-notes/') === false) {
    $pos = strpos($src, '/uploads/private-notes/');
    if ($pos !== false) {
        $src = ltrim(substr($src, $pos + 1), '/');
    }
}

if (strpos($src, 'uploads/private-notes/') !== 0) {
    http_response_code(403);
    exit('Forbidden');
}

$ext = strtolower((string)pathinfo($src, PATHINFO_EXTENSION));
$allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tiff', 'svg', 'ico'];
if (!in_array($ext, $allowed, true)) {
    http_response_code(403);
    exit('Forbidden');
}

$abs = rtrim((string)SITE_ROOT, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $src);
$realBase = realpath(rtrim((string)SITE_ROOT, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'uploads');
$realFile = realpath($abs);
if (!$realBase || !$realFile || strpos($realFile, $realBase) !== 0 || !is_file($realFile)) {
    http_response_code(404);
    exit('Not found');
}

$ownerId = 0;
if (preg_match('#^uploads/private-notes/([0-9]+)/#', $src, $m)) {
    $ownerId = (int)$m[1];
}
if ($ownerId <= 0) {
    http_response_code(403);
    exit('Forbidden');
}

$authorized = false;
if ($token !== '') {
    $pub = privateNotesGetPublicAccessByToken($database, $token);
    if ($pub && !empty($pub['id']) && (int)($pub['user_id'] ?? 0) === $ownerId) {
        $authorized = true;
    }
}

if (!$authorized && $session->isLoggedIn()) {
    $userId = (int)$session->userId;
    if ($userId > 0) {
        $acctSql = "SELECT accountStatus FROM users WHERE id = {$userId} LIMIT 1";
        $acctRes = $database->query($acctSql);
        $acctRow = $acctRes ? $database->fetchArray($acctRes) : null;
        $accountStatus = (int)($acctRow['accountStatus'] ?? 0);

        // Admin and staff can access private-note media while authenticated.
        if ($accountStatus === 1 || $accountStatus === 3) {
            $authorized = true;
        } elseif ($userId === $ownerId) {
            $authorized = true;
        } else {
            // DB stores full file path; thumbs live under …/thumbs/ same basename — use parent for ACL lookup.
            $lookupSrc = $src;
            if (preg_match('#^uploads/private-notes/([0-9]+)/thumbs/([^/]+)$#', $src, $tm)) {
                $lookupSrc = 'uploads/private-notes/' . $tm[1] . '/' . $tm[2];
            }
            $pathEsc = $database->escapeValue($lookupSrc);
            $mediaSql = "SELECT note_id FROM private_note_media WHERE file_path = '{$pathEsc}' LIMIT 1";
            $mediaRes = $database->query($mediaSql);
            $mediaRow = $mediaRes ? $database->fetchArray($mediaRes) : null;
            $noteId = (int)($mediaRow['note_id'] ?? 0);
            if ($noteId > 0) {
                $ctx = privateNotesGetAccessContext($database, $noteId, $userId);
                $authorized = !empty($ctx['can_view']);
            }
        }
    }
}

if (!$authorized) {
    http_response_code(403);
    exit('Forbidden');
}
$mime = function_exists('mime_content_type') ? (string)mime_content_type($realFile) : '';
if ($mime === '') {
    $mime = 'application/octet-stream';
}
// Binary-safe response: clear any buffered text to avoid corrupting image bytes.
while (ob_get_level() > 0) {
    @ob_end_clean();
}

header('Content-Type: ' . $mime);
header('Cache-Control: private, max-age=900');
header('Content-Disposition: inline; filename="' . basename($realFile) . '"');
header('Accept-Ranges: bytes');

$fp = @fopen($realFile, 'rb');
if (!$fp) {
    http_response_code(500);
    exit('Stream error');
}
@set_time_limit(0);
fpassthru($fp);
fclose($fp);
exit;

