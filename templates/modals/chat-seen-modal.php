<?php
if (!isset($lang) || !is_array($lang)) {
    $lang = array();
}
?>
<div class="modal fade" id="chatSeenModal" tabindex="-1" aria-labelledby="chatSeenModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content chat-seen-modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title" id="chatSeenModalLabel"><?php echo htmlspecialchars($lang['Seen by'] ?? 'Seen by', ENT_QUOTES, 'UTF-8'); ?></h5>
                <button type="button" class="btn-close me-2" data-bs-dismiss="modal" aria-label="Close">
                    <?php echo ts_icon('close'); ?>
                </button>
            </div>
            <div class="modal-body p-0">
                <div id="chatSeenModalLoading" class="chat-seen-modal-loading py-4 text-center" style="display:none;">Loading...</div>
                <div id="chatSeenModalEmpty" class="chat-seen-modal-empty py-4 text-center" style="display:none;">No one has seen this yet</div>
                <ul id="chatSeenModalList" class="chat-seen-list list-unstyled mb-0"></ul>
            </div>
        </div>
    </div>
</div>
