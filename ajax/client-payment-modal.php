<?php
ob_start();

require_once __DIR__ . '/../includes/lib-initialize.php';
require_once __DIR__ . '/../includes/client_payment_modal_helper.php';

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: application/json; charset=utf-8');

try {
    if (!$session->isLoggedIn() || (int) $_SESSION['accountStatus'] !== 2) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    $clientId = (int) $session->userId;
    $projectId = isset($_GET['projectId']) ? (int) $_GET['projectId'] : 0;
    $milestoneId = isset($_GET['milestone_id']) ? (int) $_GET['milestone_id'] : 0;
    $returnUrl = client_payment_normalize_return_path(isset($_GET['return_url']) ? (string) $_GET['return_url'] : null) ?? '';

    if ($projectId <= 0 || $milestoneId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
        exit;
    }

    $context = client_payment_modal_context($clientId, $projectId, $milestoneId);
    if (!$context) {
        echo json_encode(['success' => false, 'message' => 'Invoice not available for payment']);
        exit;
    }

    $context['returnUrl'] = $returnUrl;

    $html = client_payment_modal_render_body($context);
    $payload = json_encode([
        'success' => true,
        'html' => $html,
        'stripe' => $context['stripeConfig'],
        'twoCheckout' => $context['twoCheckoutConfig'],
    ], JSON_INVALID_UTF8_SUBSTITUTE);

    if ($payload === false) {
        echo json_encode(['success' => false, 'message' => 'Unable to encode payment form response']);
        exit;
    }

    echo $payload;
} catch (Throwable $e) {
    error_log('client-payment-modal.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Unable to load payment form']);
}
