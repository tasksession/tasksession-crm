<?php
if (!isset($lang) || !is_array($lang)) {
    $lang = array();
}
?>
<div class="modal fade" id="chatReactionsModal" tabindex="-1" aria-labelledby="chatReactionsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content chat-seen-modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title" id="chatReactionsModalLabel"><?php echo htmlspecialchars($lang['Reacted by'] ?? 'Reacted by', ENT_QUOTES, 'UTF-8'); ?></h5>
                <button type="button" class="btn-close me-2" data-bs-dismiss="modal" aria-label="Close">
                    <?php echo ts_icon('close'); ?>
                </button>
            </div>
            <div class="modal-body p-0">
                <div id="chatReactionsModalLoading" class="chat-seen-modal-loading py-4 text-center" style="display:none;">Loading...</div>
                <div id="chatReactionsModalEmpty" class="chat-seen-modal-empty py-4 text-center" style="display:none;">No reactions yet</div>
                <ul id="chatReactionsModalList" class="chat-seen-list list-unstyled mb-0"></ul>
            </div>
        </div>
    </div>
</div>
