<?php
ob_start();

require_once __DIR__ . '/../includes/lib-initialize.php';
require_once __DIR__ . '/../includes/csrf-middleware.php';
require_once __DIR__ . '/../payment-api/stripe-php/init.php';
require_once __DIR__ . '/../includes/client_payment_modal_helper.php';
require_once __DIR__ . '/../includes/client_bulk_payment_helper.php';
require_once __DIR__ . '/../includes/stripe_saved_payment.php';
require_once __DIR__ . '/../includes/client-stripe-payment-success.php';

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: application/json; charset=utf-8');

function client_single_payment_json_response(array $payload, int $status = 200, bool $flushDeferredEmails = false): void
{
    http_response_code($status);
    unset($payload['skip_emails'], $payload['defer_emails']);

    $encoded = json_encode($payload, JSON_INVALID_UTF8_SUBSTITUTE);
    if ($encoded === false) {
        echo json_encode(['success' => false, 'message' => 'Unable to encode response']);
        exit;
    }

    echo $encoded;

    if ($flushDeferredEmails) {
        client_payment_flush_response();
        client_finalize_stripe_payment_flush_deferred_emails();
    }

    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        client_single_payment_json_response(['success' => false, 'message' => 'Invalid request method'], 405);
    }

    csrf_require_for_request('json');

    if (!$session->isLoggedIn() || (int) $_SESSION['accountStatus'] !== 2) {
        client_single_payment_json_response(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    if (!empty($_POST['bulk_pay'])) {
        client_single_payment_json_response(['success' => false, 'message' => 'Invalid single payment request'], 400);
    }

    $clientId = (int) $session->userId;
    $projectId = (int) ($_POST['proj_Id'] ?? 0);
    $milestoneId = (int) ($_POST['milestone_id'] ?? 0);

    $adminSettings = settings::findById(1);
    if (!$adminSettings) {
        client_single_payment_json_response(['success' => false, 'message' => 'Payment settings unavailable'], 500);
    }

    $stripeSkEncrypted = $adminSettings->stripe_sk ?? '';
    $stripeSk = decryptString($stripeSkEncrypted);
    if ($stripeSk !== '') {
        \Stripe\Stripe::setApiKey($stripeSk);
    }

    $paymentPayload = $_POST;
    $paymentPayload['defer_emails'] = true;

    $result = client_single_payment_process(
        $clientId,
        $projectId,
        $milestoneId,
        $paymentPayload,
        $adminSettings,
        (string) $url,
        is_array($lang ?? null) ? $lang : []
    );

    $flushEmails = !empty($result['success']) && empty($result['already_paid']);
    client_single_payment_json_response($result, !empty($result['success']) ? 200 : 422, $flushEmails);
} catch (Throwable $e) {
    error_log('client-single-payment-process.php: ' . $e->getMessage());
    client_single_payment_json_response([
        'success' => false,
        'message' => 'Unable to process payment',
    ], 500);
}
