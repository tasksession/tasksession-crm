<?php
if (!isset($lang) || !is_array($lang)) {
    $lang = [];
}
?>
<div id="edit-milestone" class="modal fade client-payment-modal" tabindex="-1" aria-labelledby="clientPaymentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="card-title" id="clientPaymentModalLabel"><?php echo $lang['Payment Form'] ?? 'Payment form'; ?></h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo htmlspecialchars($lang['Close'] ?? 'Close', ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo ts_icon('close'); ?>
                </button>
            </div>
            <div class="modal-body payment-modal" id="client-payment-modal-body">
                <div class="text-center py-4 text-muted client-payment-modal__loading d-none">
                    <?php echo htmlspecialchars($lang['Loading'] ?? 'Loading...', ENT_QUOTES, 'UTF-8'); ?>
                </div>
            </div>
        </div>
    </div>
</div>
