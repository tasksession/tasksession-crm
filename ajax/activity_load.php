<?php
/**
 * Paginated activity rows for the root activity page.
 */
error_reporting(E_ERROR | E_PARSE);
require_once __DIR__ . '/../includes/lib-initialize.php';
require_once __DIR__ . '/../includes/notifications.php';
require_once __DIR__ . '/../includes/activity_page_helper.php';

header('Content-Type: application/json');

if (!$session->isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$accountStatus = (int) ($_SESSION['accountStatus'] ?? 0);
if (!in_array($accountStatus, [1, 2, 3], true)) {
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

$limit = 20;
$days = 60;
$offset = isset($_GET['offset']) ? max(0, (int) $_GET['offset']) : 0;
$userId = (int) $session->userId;
$roleBase = activityPageRoleBase($accountStatus);

$rawNotifs = Notifications::getActivityPageNotifications($userId, $limit, $offset, $days);
$rawNotifs = is_array($rawNotifs) ? $rawNotifs : [];
$items = activityPageItemsFromNotifications($rawNotifs, $lang, $roleBase, (string) $url);
$html = activityPageRenderItemsHtml($items, $lang);

echo json_encode([
    'success' => true,
    'html' => $html,
    'count' => count($rawNotifs),
    'has_more' => count($rawNotifs) >= $limit,
]);
