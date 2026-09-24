<?php
/**
 * Global client payment modal shell + assets.
 * Include once per page that supports Pay Now for project invoices.
 */
if (!empty($GLOBALS['client_payment_modal_bundle_loaded'])) {
    return;
}
$GLOBALS['client_payment_modal_bundle_loaded'] = true;

if (!isset($lang) || !is_array($lang)) {
    $lang = [];
}

$bundleUrl = rtrim((string) ($url ?? ''), '/');
require_once __DIR__ . '/../includes/client_payment_gateway_registry.php';
$bulkPaymentEnabled = client_payment_has_bulk_gateway();
if (!function_exists('generate_csrf_token')) {
    require_once __DIR__ . '/../includes/functions.php';
}
$clientPaymentCsrfToken = function_exists('generate_csrf_token') ? generate_csrf_token() : '';
include __DIR__ . '/modals/client-payment-modal.php';
?>
<script>
window.clientPaymentModalConfig = {
    ajaxUrl: <?php echo json_encode($bundleUrl . '/ajax/client-payment-modal.php'); ?>,
    bulkAjaxUrl: <?php echo json_encode($bundleUrl . '/ajax/client-bulk-payment-modal.php'); ?>,
    bulkProcessUrl: <?php echo json_encode($bundleUrl . '/ajax/client-bulk-payment-process.php'); ?>,
    singleProcessUrl: <?php echo json_encode($bundleUrl . '/ajax/client-single-payment-process.php'); ?>,
    csrfToken: <?php echo json_encode($clientPaymentCsrfToken); ?>,
    makePaymentLabel: <?php echo json_encode($lang['Complete Payment'] ?? 'Complete payment'); ?>,
    paymentFormTitle: <?php echo json_encode($lang['Payment Form'] ?? 'Payment form'); ?>,
    payAllTitle: <?php echo json_encode($lang['Pay all invoices'] ?? 'Pay all invoices'); ?>,
    loadingLabel: <?php echo json_encode($lang['Loading'] ?? 'Loading...'); ?>,
    invalidResponseLabel: <?php echo json_encode($lang['Invalid server response. Please refresh and try again.'] ?? 'Invalid server response. Please refresh and try again.'); ?>,
    bulkLabels: {
        paid: <?php echo json_encode($lang['Paid'] ?? 'Paid'); ?>,
        paymentComplete: <?php echo json_encode($lang['Payment Complete'] ?? 'Payment complete'); ?>,
        allPaid: <?php echo json_encode($lang['All invoices paid successfully'] ?? 'All invoices paid successfully'); ?>,
        allPaidDetail: <?php echo json_encode($lang['Bulk payment complete detail'] ?? 'Your payment has been processed and all selected invoices are now marked as paid.'); ?>,
        retryRemaining: <?php echo json_encode($lang['Retry remaining invoices'] ?? 'Retry remaining invoices'); ?>,
        invoiceFailed: <?php echo json_encode($lang['Invoice payment failed'] ?? 'Invoice payment failed.'); ?>,
        unavailable: <?php echo json_encode($lang['Pay all unavailable'] ?? 'Pay all is not available right now.'); ?>,
        invalidResponse: <?php echo json_encode($lang['Invalid server response. Please refresh and try again.'] ?? 'Invalid server response. Please refresh and try again.'); ?>
    },
    singleLabels: {
        paid: <?php echo json_encode($lang['Paid'] ?? 'Paid'); ?>,
        thankYouTitle: <?php echo json_encode($lang['Thank you for your payment!'] ?? 'Thank you for your payment!'); ?>,
        thankYouDetail: <?php echo json_encode($lang["Weve received your payment. Thank you!."] ?? "We've received your payment. Thank you!."); ?>,
        invoiceFailed: <?php echo json_encode($lang['Invoice payment failed'] ?? 'Invoice payment failed.'); ?>,
        unavailable: <?php echo json_encode($lang['Payment is not available.'] ?? 'Payment is not available.'); ?>,
        invalidResponse: <?php echo json_encode($lang['Invalid server response. Please refresh and try again.'] ?? 'Invalid server response. Please refresh and try again.'); ?>
    }
};
</script>
<?php if (!empty($stripe_pk) || $bulkPaymentEnabled): ?>
<script src="https://js.stripe.com/v3/"></script>
<script src="<?php echo htmlspecialchars($bundleUrl . '/assets/js/stripe-payment.js', ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="<?php echo htmlspecialchars($bundleUrl . '/assets/js/client-single-payment.js', ENT_QUOTES, 'UTF-8'); ?>"></script>
<?php if ($bulkPaymentEnabled): ?>
<script src="<?php echo htmlspecialchars($bundleUrl . '/assets/js/client-bulk-payment.js', ENT_QUOTES, 'UTF-8'); ?>"></script>
<?php endif; ?>
<script src="<?php echo htmlspecialchars($bundleUrl . '/assets/js/client-payment-gateway.js', ENT_QUOTES, 'UTF-8'); ?>"></script>
<?php endif; ?>
<?php if (!empty($checkout_id) && !empty($checkout_pk)): ?>
<script src="https://www.2checkout.com/checkout/api/2co.min.js"></script>
<?php endif; ?>
<script src="<?php echo htmlspecialchars($bundleUrl . '/assets/js/client-payment-modal.js', ENT_QUOTES, 'UTF-8'); ?>"></script>
<style>
div#payment-request-button { margin-bottom: 20px; }
.bigbutton:disabled { opacity: 0.7; cursor: not-allowed; }
.spinner-icon { display: inline-block; width: 20px; height: 20px; vertical-align: middle; margin-right: 8px; }
.spinner-circle-animated { animation: client-payment-spinner-rotate 1s linear infinite; transform-origin: center; }
@keyframes client-payment-spinner-rotate {
    0% { transform: rotate(0deg); stroke-dashoffset: 24; }
    50% { stroke-dashoffset: 0; }
    100% { transform: rotate(360deg); stroke-dashoffset: -24; }
}
</style>
