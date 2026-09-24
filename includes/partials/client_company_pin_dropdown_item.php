<?php
if (empty($companyPinsEnabled)) {
    return;
}
$ccIsPinned = !empty($ccIsPinned);
$pinCompanyId = isset($cid) ? (int) $cid : 0;
if ($pinCompanyId <= 0) {
    return;
}
?>
<li>
    <?php if ($ccIsPinned): ?>
    <button type="button" class="dropdown-item d-flex align-items-center js-unpin-client-company" data-company-id="<?php echo $pinCompanyId; ?>">
        <?php echo ts_icon('pin', 'tasksession-timer-log-menu-ico me-2'); ?>
        <?php echo htmlspecialchars($lang['Unpin company'] ?? 'Unpin company'); ?>
    </button>
    <?php else: ?>
    <button type="button" class="dropdown-item d-flex align-items-center js-pin-client-company" data-company-id="<?php echo $pinCompanyId; ?>">
        <?php echo ts_icon('pin', 'tasksession-timer-log-menu-ico me-2'); ?>
        <?php echo htmlspecialchars($lang['Pin company'] ?? 'Pin company'); ?>
    </button>
    <?php endif; ?>
</li>
