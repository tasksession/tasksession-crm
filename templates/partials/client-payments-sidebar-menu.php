<?php
/**
 * Client sidebar: Payments submenu (Invoices + Payment Methods).
 * Expects: $url, $lang, $isInvoicesEnabled, $taskPermissions (optional)
 */
if (empty($isInvoicesEnabled)) {
    return;
}
$canViewClientPayments = isset($taskPermissions) && is_object($taskPermissions) && !empty($taskPermissions->can_view_milestones);
if (!$canViewClientPayments) {
    return;
}
$paymentsMenuId = 'client-payments-menu';
$arrowIcon = ts_icon('chevron-right', 'h-6');
$payIcon = ts_icon('payments', 'h-6');
?>
<li class="<?php echo active('invoices.php'); echo active('payment-methods.php'); ?>">
    <a href="#" data-bs-toggle="collapse" data-bs-target="#<?php echo htmlspecialchars($paymentsMenuId, ENT_QUOTES, 'UTF-8'); ?>">
        <span><?php echo $payIcon; ?></span>
        <span class="menu-text"><?php echo $lang['Payments']; ?></span>
        <?php echo ts_icon('plus', 'dropdown-toggle-icon h-6'); ?>
    </a>
    <ul id="<?php echo htmlspecialchars($paymentsMenuId, ENT_QUOTES, 'UTF-8'); ?>" class="collapse">
        <li class="<?php echo active('invoices.php'); ?>"><a href="<?php echo $url; ?>client/invoices"><span class="menu-text"><?php echo htmlspecialchars($lang['All Invoices'] ?? 'All invoices'); ?></span><?php echo $arrowIcon; ?></a></li>
        <li class="<?php echo active('payment-methods.php'); ?>"><a href="<?php echo $url; ?>client/payment-methods"><span class="menu-text"><?php echo htmlspecialchars($lang['Payment Methods'] ?? 'Payment methods'); ?></span><?php echo $arrowIcon; ?></a></li>
    </ul>
</li>
