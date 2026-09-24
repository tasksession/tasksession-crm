<?php
require_once("../../includes/lib-initialize.php");
require_once("../../includes/private_notes/permissions.php");

header('Content-Type: application/json; charset=utf-8');

if (!$session->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'error' => 'Unauthorized']);
    exit;
}

$userId = (int)$session->userId;
$search = trim((string)($_GET['q'] ?? ''));

$rows = privateNotesResolveShareTargets($database, $search, $userId, 30);
echo json_encode(['status' => 'ok', 'data' => ['users' => $rows]]);
