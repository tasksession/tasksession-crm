<?php
ob_start();

require_once __DIR__ . '/../includes/lib-initialize.php';
require_once __DIR__ . '/../includes/client_bulk_payment_helper.php';
require_once __DIR__ . '/../includes/client_outstanding_invoices_helper.php';

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
    $returnUrl = client_payment_normalize_return_path(isset($_GET['return_url']) ? (string) $_GET['return_url'] : null) ?? 'index.php';

    $user = User::findById($clientId);
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    $outstandingPayload = client_outstanding_invoices_payload($clientId, $user->firstName);
    $context = client_bulk_payment_context($clientId, $outstandingPayload);
    if (!$context) {
        echo json_encode(['success' => false, 'message' => 'Bulk payment is not available']);
        exit;
    }

    $context['returnUrl'] = $returnUrl;
    $html = client_bulk_payment_render_body($context);
    $gatewayConfigs = $context['gatewayScriptConfigs'] ?? [];

    $payload = json_encode([
        'success' => true,
        'html' => $html,
        'gateways' => $gatewayConfigs,
        'primaryGateway' => $context['primaryGateway'] ?? null,
    ], JSON_INVALID_UTF8_SUBSTITUTE);

    if ($payload === false) {
        echo json_encode(['success' => false, 'message' => 'Unable to encode bulk payment response']);
        exit;
    }

    echo $payload;
} catch (Throwable $e) {
    error_log('client-bulk-payment-modal.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Unable to load bulk payment form']);
}
