<?php
/**
 * Central private note export (PDF / Word). Used by admin, staff, and client.
 * Output buffering avoids TCPDF "Some data has already been output" from includes.
 */
if (!ob_get_level()) {
    ob_start();
}

require_once(__DIR__ . '/../../includes/lib-initialize.php');
require_once(__DIR__ . '/../../includes/private_notes/permissions.php');
require_once(__DIR__ . '/../../includes/private_notes/export.php');

if (!$session->isLoggedIn()) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Location: ' . rtrim((string)$url, '/') . '/index.php');
    exit;
}

$userId = (int)$session->userId;
$noteId = isset($_GET['note_id']) ? (int)$_GET['note_id'] : 0;
$format = strtolower(trim((string)($_GET['format'] ?? 'pdf')));
if ($noteId <= 0 || ($format !== 'pdf' && $format !== 'word')) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Invalid export request.';
    exit;
}

$access = privateNotesGetAccessContext($database, $noteId, $userId);
if (empty($access['can_view'])) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Access denied.';
    exit;
}

$res = $database->query("SELECT title, content FROM private_notes WHERE id = {$noteId} LIMIT 1");
$note = $res ? $database->fetchArray($res) : null;
if (!$note) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Note not found.';
    exit;
}

$title = (string)($note['title'] ?? 'Private Note');
$content = !empty($note['content']) ? decryptString($note['content']) : '';

while (ob_get_level() > 0) {
    ob_end_clean();
}

$exportContent = privateNotesRewriteExportImageSourcesToLocalFiles($content);

if ($format === 'word') {
    privateNotesExportAsWord($title, $exportContent);
    exit;
}

privateNotesExportAsPdf($title, $exportContent);
