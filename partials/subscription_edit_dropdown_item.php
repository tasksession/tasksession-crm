<?php
/**
 * Edit subscription → parent invoice (edit-invoice.php).
 * Expects: $row with id, optional $lang, optional $subscriptionEditReturnUrl
 */
if (!isset($row['id'])) {
    return;
}
$parentId = (int) $row['id'];
$editReturn = isset($subscriptionEditReturnUrl) ? $subscriptionEditReturnUrl : 'subscriptions';
$editLabel = isset($lang['Edit subscription']) ? $lang['Edit subscription'] : (isset($lang['Edit Subscription']) ? $lang['Edit Subscription'] : 'Edit subscription');
$editHref = 'edit-invoice?id=' . $parentId . '&return_url=' . rawurlencode($editReturn);
?>
<li>
    <a href="<?php echo htmlspecialchars($editHref, ENT_QUOTES, 'UTF-8'); ?>" class="dropdown-item">
        <?php echo ts_icon('edit', 'me-2 tasksession-timer-log-menu-ico'); ?>
        <?php echo htmlspecialchars($editLabel, ENT_QUOTES, 'UTF-8'); ?>
    </a>
</li>
