<?php
if (!isset($lang) || !is_array($lang)) {
    $lang = [];
}
?>
<div id="comonSuccessModal" class="modal fade comon-success-modal" tabindex="-1" aria-labelledby="comonSuccessModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body comon-success-modal__body">
                <button type="button" class="btn-close comon-success-modal__close" data-bs-dismiss="modal" aria-label="<?php echo htmlspecialchars($lang['Close'] ?? 'Close', ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo ts_icon('close'); ?>
                </button>
                <div class="comon-success-modal__icon" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </div>
                <h4 class="comon-success-modal__title" id="comonSuccessModalLabel"></h4>
                <p class="comon-success-modal__subtitle"></p>
                <div class="comon-success-modal__assignees" aria-label="<?php echo htmlspecialchars($lang['Assign To (Multiple Staff)'] ?? 'Assigned staff', ENT_QUOTES, 'UTF-8'); ?>"></div>
                <div class="comon-success-modal__actions">
                    <button type="button" class="btn primary-btn comon-success-modal__btn-view"></button>
                    <a href="#" class="btn btn-outline-grey comon-success-modal__btn-kanban"></a>
                </div>
            </div>
        </div>
    </div>
</div>
