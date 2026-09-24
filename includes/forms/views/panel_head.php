<?php
/**
 * Panel header — same markup/classes as ai/setting.php (ai-settings-panel-head).
 *
 * Set before include:
 *   $formsPanelTitle (required)
 *   $formsPanelSub (optional)
 *   $formsPanelActionsHtml (optional HTML for right-side actions)
 */
if (!isset($formsPanelTitle) || $formsPanelTitle === '') {
    return;
}
$formsPanelSub = isset($formsPanelSub) ? (string) $formsPanelSub : '';
$formsPanelActionsHtml = isset($formsPanelActionsHtml) ? (string) $formsPanelActionsHtml : '';
?>
<div class="ai-settings-panel-head">
    <div>
        <h2 class="page-title"><?php echo FormsSecurityHelper::escape($formsPanelTitle); ?></h2>
        <?php if ($formsPanelSub !== ''): ?>
        <p class="ai-settings-panel-sub"><?php echo FormsSecurityHelper::escape($formsPanelSub); ?></p>
        <?php endif; ?>
    </div>
    <?php if ($formsPanelActionsHtml !== ''): ?>
    <div class="forms-panel-actions">
        <?php echo $formsPanelActionsHtml; ?>
    </div>
    <?php endif; ?>
</div>
