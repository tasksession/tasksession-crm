<?php
/*
================================================================================
  Custom Fields – List Custom Fields
  Location: includes/custom-fields/list.php
================================================================================
*/
ob_start();
require_once(__DIR__ . "/../lib-initialize.php");

header('Content-Type: application/json');

if (function_exists('tasksession_is_free_edition') && tasksession_is_free_edition()) {
    if (ob_get_level() > 0) { ob_clean(); }
    echo json_encode(['status' => 'success', 'fields' => []]);
    exit;
}

if (!$session->isLoggedIn()) {
    if (ob_get_level() > 0) { ob_clean(); }
    echo json_encode(['status' => 'error', 'error' => 'Not logged in']);
    exit;
}
$entityType = isset($_GET['entity_type']) ? trim((string)$_GET['entity_type']) : 'lead';
if (!in_array($entityType, ['lead', 'project', 'task', 'client', 'staff', 'admin', 'company'], true)) {
    $entityType = 'lead';
}

$search = isset($_GET['search']) ? trim((string)$_GET['search']) : '';
$adminList = isset($_GET['admin']) && (string)$_GET['admin'] === '1';
$accountStatus = isset($_SESSION['accountStatus']) ? (int)$_SESSION['accountStatus'] : 0;

if (!in_array($accountStatus, [1, 2, 3], true)) {
    if (ob_get_level() > 0) { ob_clean(); }
    echo json_encode(['status' => 'error', 'error' => 'Not authorized']);
    exit;
}

// Admin/staff can list all entities; clients can only fetch active client/task fields.
if ($accountStatus === 2) {
    if (!in_array($entityType, ['client', 'task'], true) || $adminList) {
        if (ob_get_level() > 0) { ob_clean(); }
        echo json_encode(['status' => 'error', 'error' => 'Not authorized']);
        exit;
    }
}

$sql = "SELECT * FROM custom_fields WHERE entity_type = ?";
$params = [$entityType];
$types = 's';

if (!$adminList) {
    $sql .= ' AND COALESCE(is_disabled, 0) = 0';
}

if ($search !== '') {
    $sql .= " AND (label LIKE ? OR description LIKE ?)";
    $searchTerm = '%' . $search . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= 'ss';
}

$sql .= " ORDER BY sort_order ASC, id ASC";

$stmt = $connect->prepare($sql);
if (!$stmt) {
    if (ob_get_level() > 0) { ob_clean(); }
    echo json_encode(['status' => 'error', 'error' => 'Failed to prepare statement']);
    exit;
}

if ($search !== '') {
    $stmt->bind_param($types, $entityType, $searchTerm, $searchTerm);
} else {
    $stmt->bind_param($types, $entityType);
}

$stmt->execute();
$result = $stmt->get_result();

$fields = [];
while ($row = $result->fetch_assoc()) {
    $row['list_items'] = !empty($row['list_items']) ? json_decode($row['list_items'], true) : [];
    $row['is_disabled'] = isset($row['is_disabled']) ? (int) $row['is_disabled'] : 0;
    $fields[] = $row;
}

$stmt->close();

if (ob_get_level() > 0) { ob_clean(); }
echo json_encode(['status' => 'ok', 'data' => $fields]);
exit;

