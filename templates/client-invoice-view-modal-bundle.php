<?php
/**
 * Global client invoice view modal shell + assets.
 */
if (!empty($GLOBALS['client_invoice_view_modal_bundle_loaded'])) {
    return;
}
$GLOBALS['client_invoice_view_modal_bundle_loaded'] = true;

if (!isset($lang) || !is_array($lang)) {
    $lang = [];
}

$bundleUrl = rtrim((string) ($url ?? ''), '/');
include __DIR__ . '/modals/client-invoice-view-modal.php';
?>
<script>
window.clientInvoiceViewModalConfig = {
    ajaxUrl: <?php echo json_encode($bundleUrl . '/ajax/client-invoice-view-modal.php'); ?>,
    loadingLabel: <?php echo json_encode($lang['Loading'] ?? 'Loading...'); ?>,
    errorLabel: <?php echo json_encode($lang['Unable to load invoice'] ?? 'Unable to load invoice'); ?>
};
</script>
<script src="<?php echo htmlspecialchars($bundleUrl . '/assets/js/client-invoice-view-modal.js', ENT_QUOTES, 'UTF-8'); ?>"></script>
