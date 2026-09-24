<?php
error_reporting(0);
ini_set('display_errors', 0);
while (ob_get_level() > 0) {
    ob_end_clean();
}
ob_start();
require_once __DIR__ . '/../../includes/lib-initialize.php';
if (ob_get_level() > 0) {
    ob_clean();
}
header('Content-Type: application/json; charset=utf-8');

try {
    if (!$session->isLoggedIn()) {
        echo json_encode(['status' => 'error', 'error' => 'Not logged in']);
        exit;
    }
    $isAdmin = isset($_SESSION['accountStatus']) && (int)$_SESSION['accountStatus'] === 1;
    if (!$isAdmin) {
        echo json_encode(['status' => 'error', 'error' => 'Not authorized']);
        exit;
    }
    if (!isset($_POST['csrf_token'])) {
        echo json_encode(['status' => 'error', 'error' => 'Security token missing']);
        exit;
    }
    $csrfOk = function_exists('validate_csrf_token')
        ? validate_csrf_token($_POST['csrf_token'])
        : (isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token']));
    if (!$csrfOk) {
        echo json_encode(['status' => 'error', 'error' => 'Invalid security token']);
        exit;
    }
    if (!isset($_FILES['csv_file']) || empty($_FILES['csv_file']['tmp_name'])) {
        echo json_encode(['status' => 'error', 'error' => 'File is required']);
        exit;
    }
    $file = $_FILES['csv_file'];
    $maxSize = 50 * 1024 * 1024;
    $ext = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
    if (!in_array($ext, ['csv', 'xlsx'], true) || (int)($file['size'] ?? 0) > $maxSize || (int)($file['error'] ?? 1) !== UPLOAD_ERR_OK || !is_uploaded_file((string)$file['tmp_name'])) {
        echo json_encode(['status' => 'error', 'error' => 'Invalid upload']);
        exit;
    }

    require_once __DIR__ . '/../../includes/import-export/bootstrap.php';
    $response = Comon_IE_NoteImportRunner::processWebRequest($connect, $session, $_POST, $_FILES, $isAdmin, false);
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
} catch (Throwable $e) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    echo json_encode(['status' => 'error', 'error' => 'Import failed']);
    exit;
}
