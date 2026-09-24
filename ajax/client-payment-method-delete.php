<?php
require_once(__DIR__ . '/../includes/lib-initialize.php');
require_once(__DIR__ . '/../payment-api/stripe-php/init.php');
require_once(__DIR__ . '/../includes/stripe_saved_payment.php');

while (ob_get_level() > 0) {
    ob_end_clean();
}
header('Content-Type: application/json; charset=utf-8');

if (!$session->isLoggedIn() || (int) $_SESSION['accountStatus'] !== 2) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once(__DIR__ . '/../includes/csrf-middleware.php');
csrf_require_for_request();

$pmId = trim($_POST['payment_method_id'] ?? '');
if ($pmId === '') {
    echo json_encode(['success' => false, 'message' => 'Payment method required.']);
    exit;
}

$adminSettings = settings::findById(1);
$stripe_sk = !empty($adminSettings->stripe_sk) ? decryptString($adminSettings->stripe_sk) : '';
$userId = (int) $session->userId;

if (!stripe_delete_payment_method_for_user($userId, $pmId, $stripe_sk)) {
    echo json_encode(['success' => false, 'message' => 'Could not remove card.']);
    exit;
}
echo json_encode(['success' => true]);
