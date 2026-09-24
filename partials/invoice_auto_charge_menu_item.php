<?php
/**
 * Dropdown / action menu: charge client saved card (recurring auto-charge enabled).
 * Expects $row or $invoiceRow with id; $lang optional.
 */
if (!isset($invoiceRow) && isset($row)) {
    $invoiceRow = $row;
}
if (empty($invoiceRow['id'])) {
    return;
}
if (!function_exists('stripe_invoice_eligible_for_manual_auto_charge_retry')) {
    require_once dirname(__DIR__) . '/includes/stripe_saved_payment.php';
}
if (!function_exists('invoice_action_menu_icon')) {
    require_once dirname(__DIR__) . '/includes/invoice_dropdown_icons.php';
}
$invForAutoCharge = milestone::findById((int) $invoiceRow['id']);
if (isset($invoicesTableCanCharge) && !$invoicesTableCanCharge) {
    return;
}
if (!$invForAutoCharge || !stripe_invoice_eligible_for_manual_auto_charge_retry($invForAutoCharge)) {
    return;
}
$chargeLabel = $lang['Charge saved card'] ?? 'Charge saved card';
?>
<li>
    <button type="button" class="dropdown-item retry-auto-charge-btn" data-invoice-id="<?php echo (int) $invoiceRow['id']; ?>">
        <?php echo invoice_action_menu_icon('retry_charge'); ?><?php echo htmlspecialchars($chargeLabel); ?>
    </button>
</li>
