<?php
require_once(__DIR__ . '/../includes/lib-initialize.php');
require_once(__DIR__ . '/../includes/private_notes/permissions.php');
require_once(__DIR__ . '/../includes/private_notes/export.php');

header('X-Robots-Tag: noindex, nofollow, noarchive', true);

$token = trim((string)($_GET['token'] ?? ''));
$format = strtolower(trim((string)($_GET['format'] ?? 'pdf')));
if ($token === '' || ($format !== 'pdf' && $format !== 'word')) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Invalid export request.';
    exit;
}

$noteRow = privateNotesGetPublicAccessByToken($database, $token);
if (!$noteRow) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Shared note not found.';
    exit;
}

$title = (string)($noteRow['title'] ?? 'Shared Note');
$content = !empty($noteRow['content']) ? (string)decryptString((string)$noteRow['content']) : '';

$exportContent = privateNotesRewriteExportImageSourcesToLocalFiles($content);

if ($format === 'word') {
    privateNotesExportAsWord($title, $exportContent);
    exit;
}
privateNotesExportAsPdf($title, $exportContent);
exit;
