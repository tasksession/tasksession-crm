<?php
/**
 * Mega search bulk delete AJAX.
 */
ob_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/lib-initialize.php';
require_once __DIR__ . '/../includes/mega_search_helper.php';
require_once __DIR__ . '/../includes/mega_search_bulk.php';

if (!$session->isLoggedIn()) {
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$accountStatus = isset($_SESSION['accountStatus']) ? (int) $_SESSION['accountStatus'] : 0;
if (!in_array($accountStatus, [1, 2, 3], true)) {
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$tab = isset($_POST['tab']) ? trim((string) $_POST['tab']) : '';
$idsRaw = $_POST['ids'] ?? '';
$ids = [];
if (is_array($idsRaw)) {
    $ids = $idsRaw;
} elseif (is_string($idsRaw) && $idsRaw !== '') {
    $ids = explode(',', $idsRaw);
}

$ctx = comon_mega_search_context($session);

try {
    $result = comon_mega_search_bulk_delete($tab, $ids, $ctx);
    ob_clean();
    echo json_encode([
        'success' => true,
        'deleted' => (int) ($result['deleted'] ?? 0),
        'skipped' => (int) ($result['skipped'] ?? 0),
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    ob_clean();
    $msg = $e->getMessage();
    $code = ($msg === 'Forbidden') ? 403 : 400;
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'error' => $msg === 'Forbidden' ? 'You do not have permission to delete these items.' : $msg,
    ], JSON_UNESCAPED_UNICODE);
}
