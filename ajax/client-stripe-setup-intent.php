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

$adminSettings = settings::findById(1);
$stripe_sk = !empty($adminSettings->stripe_sk) ? decryptString($adminSettings->stripe_sk) : '';
if (empty($stripe_sk)) {
    echo json_encode(['success' => false, 'message' => 'Stripe is not configured.']);
    exit;
}

$userId = (int) $session->userId;
$user = User::findById($userId);
$email = $user ? $user->email : '';

try {
    \Stripe\Stripe::setApiKey($stripe_sk);
    $customerId = stripe_get_or_create_customer_for_user($userId, $email, $stripe_sk);
    $intent = \Stripe\SetupIntent::create([
        'customer' => $customerId,
        'payment_method_types' => ['card'],
        'usage' => 'off_session',
    ]);
    echo json_encode(['success' => true, 'client_secret' => $intent->client_secret]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
