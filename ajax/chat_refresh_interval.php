<?php
if (!ob_get_level()) {
    ob_start();
}
@ini_set('display_errors', '0');

require_once __DIR__ . '/../includes/ajax_bootstrap.php';

if (ob_get_length()) {
    ob_clean();
}

// Security: Authentication check
if (!isset($session) || !$session->isLoggedIn()) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

$refresh_interval = isset($_POST['refresh_interval']) ? (int)$_POST['refresh_interval'] : 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($refresh_interval < 2 || $refresh_interval > 20) {
        echo json_encode(['success' => false, 'message' => 'Invalid interval', 'received' => $_POST['refresh_interval'], 'parsed' => $refresh_interval]);
        exit;
    }
    global $connect;
    if (empty($connect)) {
        echo json_encode(['success' => false, 'message' => 'Database unavailable']);
        exit;
    }
    $stmt = mysqli_prepare($connect, 'UPDATE settings SET chat_refresh_interval = ? WHERE id = 1');
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Update failed']);
        exit;
    }
    mysqli_stmt_bind_param($stmt, 'i', $refresh_interval);
    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    echo json_encode(['success' => (bool)$result]);
} else {
    echo json_encode([
        'success' => true,
        'refresh_interval' => ajax_bootstrap_settings_int('chat_refresh_interval', 5),
    ]);
} 