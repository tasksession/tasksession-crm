<?php
/**
 * Invoice grid card dropdown menu (admin/staff invoices grid view).
 * Expects $row, $lang, $url; optional permission flags (default true).
 */
if (!isset($invoicesTableCanEdit)) {
    $invoicesTableCanEdit = true;
}
if (!isset($invoicesTableCanDelete)) {
    $invoicesTableCanDelete = true;
}
if (!isset($invoicesTableCanDuplicate)) {
    $invoicesTableCanDuplicate = true;
}
if (!isset($invoicesTableCanCharge)) {
    $invoicesTableCanCharge = true;
}
if (!function_exists('invoice_action_menu_icon')) {
    require_once dirname(__DIR__) . '/includes/invoice_dropdown_icons.php';
}
$isDirectClientInvoice = (empty($row['p_id']) || $row['p_id'] == 0) && !empty($row['c_id']);
$isPaidDirectInvoice = ($row['status'] == 1) && $isDirectClientInvoice;
?>
<?php if ($isPaidDirectInvoice): ?>
<li>
    <form action="<?php echo htmlspecialchars(tasksession_download_pdf_href(), ENT_QUOTES, 'UTF-8'); ?>" method="post" target="_blank" enctype="multipart/form-data" style="display:inline;">
        <input type="hidden" value="<?php echo (int) $row['id']; ?>" name="milestone_id" />
        <button type="submit" name="mile_submit" class="dropdown-item">
            <?php echo invoice_action_menu_icon('download_pdf'); ?><?php echo isset($lang['Download PDF']) ? $lang['Download PDF'] : 'Download PDF'; ?>
        </button>
    </form>
</li>
<?php if ($invoicesTableCanDuplicate): ?>
<li>
    <form method="post" action="#" style="display:inline;">
        <input type="hidden" name="invoice_id" value="<?php echo (int) $row['id']; ?>">
        <button type="submit" class="dropdown-item" name="duplicate_invoice" onclick="return confirm('<?php echo htmlspecialchars($lang['Duplicate invoice confirm'] ?? 'Create a copy of this invoice as a new unpaid invoice?', ENT_QUOTES); ?>');">
            <?php echo invoice_action_menu_icon('duplicate'); ?><?php echo htmlspecialchars($lang['Duplicate Invoice'] ?? 'Duplicate Invoice'); ?>
        </button>
    </form>
</li>
<?php endif; ?>
<?php else: ?>
<?php if ($row['status'] != 1 && $invoicesTableCanEdit): ?>
<li>
    <a href="edit-invoice?id=<?php echo (int) $row['id']; ?>">
        <?php echo invoice_action_menu_icon('edit'); ?><?php echo $lang['Edit Invoice']; ?>
    </a>
</li>
<?php endif; ?>
<?php if ($invoicesTableCanDuplicate): ?>
<li>
    <form method="post" action="#" style="display:inline;">
        <input type="hidden" name="invoice_id" value="<?php echo (int) $row['id']; ?>">
        <button type="submit" class="dropdown-item" name="duplicate_invoice" onclick="return confirm('<?php echo htmlspecialchars($lang['Duplicate invoice confirm'] ?? 'Create a copy of this invoice as a new unpaid invoice?', ENT_QUOTES); ?>');">
            <?php echo invoice_action_menu_icon('duplicate'); ?><?php echo htmlspecialchars($lang['Duplicate Invoice'] ?? 'Duplicate Invoice'); ?>
        </button>
    </form>
</li>
<?php endif; ?>
<?php if ($row['status'] != 1 && $invoicesTableCanEdit): ?>
<li>
    <form method="post" action="#" style="display:inline;">
        <input type="hidden" name="invoice_id" value="<?php echo (int) $row['id']; ?>">
        <button type="submit" class="dropdown-item" name="mark_as_paid" onclick="return confirm('<?php echo isset($lang['Are you sure you want to mark this invoice as paid?']) ? $lang['Are you sure you want to mark this invoice as paid?'] : 'Are you sure you want to mark this invoice as paid?'; ?>');">
            <?php echo invoice_action_menu_icon('mark_paid'); ?><?php echo isset($lang['Mark as Paid']) ? $lang['Mark as Paid'] : 'Mark as Paid'; ?>
        </button>
    </form>
</li>
<?php endif; ?>
<?php include dirname(__DIR__) . '/partials/invoice_auto_charge_menu_item.php'; ?>
<?php if ($row['status'] != 1 && $row['status'] != 2 && $invoicesTableCanEdit): ?>
<li>
    <form method="post" action="#" style="display:inline;">
        <input type="hidden" name="invoice_id" value="<?php echo (int) $row['id']; ?>">
        <button type="submit" class="dropdown-item" name="cancel_invoice" onclick="return confirm('<?php echo isset($lang['Are you sure you want to cancel this invoice?']) ? $lang['Are you sure you want to cancel this invoice?'] : 'Are you sure you want to cancel this invoice?'; ?>');">
            <?php echo invoice_action_menu_icon('cancel'); ?><?php echo isset($lang['Cancel Invoice']) ? $lang['Cancel Invoice'] : 'Cancel Invoice'; ?>
        </button>
    </form>
</li>
<?php endif; ?>
<?php if ($row['status'] != 1 && $row['status'] != 2): ?>
<li>
    <a href="#" onclick="copyPaymentLink(<?php echo (int) $row['id']; ?>); return false;">
        <?php echo invoice_action_menu_icon('copy_link'); ?><?php echo isset($lang['Copy payment link']) ? $lang['Copy payment link'] : 'Copy payment link'; ?>
    </a>
</li>
<?php endif; ?>
<?php
$internalProjectIds = [17];
$isInternal = (
    empty($row['p_id']) ||
    $row['p_id'] == 0 ||
    in_array($row['p_id'], $internalProjectIds) ||
    (isset($row['project_title']) && strtolower(trim($row['project_title'])) == 'internal invoice') ||
    (isset($row['project_title']) && strtolower(trim($row['project_title'])) == 'internal invoices')
);
?>
<?php if (!$isInternal): ?>
<li>
    <a href="overview?projectId=<?php echo (int) $row['p_id']; ?>">
        <?php echo invoice_action_menu_icon('view_project'); ?><?php echo $lang['View Project']; ?>
    </a>
</li>
<?php endif; ?>
<li>
    <form action="<?php echo htmlspecialchars(tasksession_download_pdf_href(), ENT_QUOTES, 'UTF-8'); ?>" method="post" target="_blank" enctype="multipart/form-data" style="display:inline;">
        <input type="hidden" value="<?php echo (int) $row['id']; ?>" name="milestone_id" />
        <button type="submit" name="mile_submit" class="dropdown-item">
            <?php echo invoice_action_menu_icon('download_pdf'); ?><?php echo isset($lang['Download PDF']) ? $lang['Download PDF'] : 'Download PDF'; ?>
        </button>
    </form>
</li>
<?php if ($row['status'] != 1 && $invoicesTableCanDelete): ?>
<li>
    <form method="post" action="#" style="display:inline;">
        <input type="hidden" value="<?php echo (int) $row['id']; ?>" name="delete_id" />
        <button type="submit" class="dropdown-item text-danger" name="delete-mile" onclick="return confirm('Are you sure you want to delete this invoice?');">
            <?php echo invoice_action_menu_icon('delete'); ?><?php echo $lang['Delete']; ?>
        </button>
    </form>
</li>
<?php endif; ?>
<?php endif; ?>
