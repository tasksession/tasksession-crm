<?php
require_once __DIR__ . '/../includes/lib-initialize.php';
require_once __DIR__ . '/../includes/stripe_saved_payment.php';

while (ob_get_level() > 0) {
    ob_end_clean();
}
header('Content-Type: application/json; charset=utf-8');

if (!$session->isLoggedIn() || !in_array((int) $_SESSION['accountStatus'], [1, 3], true)) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
if ((int) $_SESSION['accountStatus'] === 3) {
    require_once __DIR__ . '/../includes/permissions.php';
    if (!has_permission('milestone_update')) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
}

require_once __DIR__ . '/../includes/csrf-middleware.php';
csrf_require_for_request();

$parentId = (int) ($_POST['subscription_id'] ?? 0);
$enabledRaw = $_POST['enabled'] ?? '0';
$enabled = in_array((string) $enabledRaw, ['1', 'true', 'on'], true);

if ($parentId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid subscription.']);
    exit;
}

$parent = milestone::findByMilestoneId($parentId);
if (!$parent || (int) ($parent->is_recurring ?? 0) !== 1) {
    echo json_encode(['success' => false, 'message' => 'Subscription not found.']);
    exit;
}

$isParent = empty($parent->recurring_parent_id)
    || $parent->recurring_parent_id == 0
    || $parent->recurring_parent_id === '0';
if (!$isParent) {
    echo json_encode(['success' => false, 'message' => 'Only parent subscriptions can be updated.']);
    exit;
}

$parent->recurring_auto_charge = $enabled ? 1 : 0;
if (!$parent->save()) {
    echo json_encode(['success' => false, 'message' => 'Could not save subscription.']);
    exit;
}

$chargeNote = null;
if ($enabled) {
    $adminSettings = settings::findById(1);
    if ($adminSettings) {
        stripe_auto_charge_due_invoices_for_parent($parentId, $adminSettings, $url);
        $chargeNote = 'Due unpaid invoices were queued for auto charge where possible.';
    }
}

echo json_encode([
    'success' => true,
    'enabled' => $enabled,
    'message' => $enabled
        ? ('Auto charge enabled.' . ($chargeNote ? ' ' . $chargeNote : ''))
        : 'Auto charge disabled.',
]);
