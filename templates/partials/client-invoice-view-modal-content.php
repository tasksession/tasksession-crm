<?php
if (empty($latestMile1) || empty($edit_id1)) {
    return;
}

$h = static function ($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};
?>
<div class="modal-header d-flex align-items-center justify-content-between">
    <h4 class="card-title"><?php echo $h($lang['Invoice'] ?? 'Invoice'); ?></h4>
    <div class="d-flex align-items-center col-gap-10">
        <div class="icons-btn d-flex">
            <a href="#" class="prnintpage me-2" onclick="window.print(); return false;">
                <?php echo ts_icon('printer', 'h-6'); ?>
                <?php echo $h($lang['Print Invoice'] ?? 'Print Invoice'); ?>
            </a>
            <form action="<?php echo $h(function_exists('tasksession_download_pdf_href') ? tasksession_download_pdf_href() : (rtrim((string) ($url ?? ''), '/') . '/templates/download_pdf')); ?>" method="post" target="_blank" enctype="multipart/form-data">
                <input type="hidden" value="<?php echo (int) $edit_id1; ?>" name="milestone_id" />
                <button type="submit" name="mile_submit">
                    <?php echo ts_icon('download', 'h-6'); ?>
                    <?php echo $h($lang['Download PDF'] ?? 'Download PDF'); ?>
                </button>
            </form>
        </div>
        <button type="button" class="btn-close me-2" data-bs-dismiss="modal" aria-label="<?php echo $h($lang['Close'] ?? 'Close'); ?>">
            <?php echo ts_icon('close'); ?>
        </button>
    </div>
</div>
<div class="client-invoice-view-modal__scroll">
    <div id="invoicecont" class="invoice-box">
        <div id="editor"></div>
        <?php include __DIR__ . '/../invoice-modal.php'; ?>
    </div>
</div>
