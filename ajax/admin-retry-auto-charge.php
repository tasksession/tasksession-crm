<?php
require_once(__DIR__ . '/../includes/lib-initialize.php');
require_once(__DIR__ . '/../includes/stripe_saved_payment.php');

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

$invoiceId = (int) ($_POST['invoice_id'] ?? 0);
if ($invoiceId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid invoice.']);
    exit;
}

$invoice = milestone::findById($invoiceId);
if (!$invoice || !stripe_invoice_eligible_for_manual_auto_charge_retry($invoice)) {
    echo json_encode(['success' => false, 'message' => 'Auto charge is not available for this invoice.']);
    exit;
}

$adminSettings = settings::findById(1);
ob_start();
$result = stripe_attempt_auto_charge_for_invoice($invoice, $adminSettings, $url, 'manual_retry');
ob_end_clean();

if (!empty($result['success'])) {
    echo json_encode(['success' => true, 'message' => 'Payment successful.']);
    exit;
}

echo json_encode([
    'success' => false,
    'message' => $result['error'] ?? ($result['reason'] ?? 'Charge failed.'),
]);
