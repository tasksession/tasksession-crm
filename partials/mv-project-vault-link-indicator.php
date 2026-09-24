<?php
/**
 * Inline Media Vault project-link marker (share icon + Bootstrap tooltip).
 * Expects: $mvVaultTooltip (string), $mvVaultSharedByMe (bool).
 */
if (!isset($mvVaultTooltip) || $mvVaultTooltip === '') {
    return;
}
$t = htmlspecialchars((string) $mvVaultTooltip, ENT_QUOTES, 'UTF-8');
$mod = !empty($mvVaultSharedByMe) ? 'shared-by-me' : 'shared-with-me';
?>
<div class="shared-folder-badge mv-vault-link-inline <?php echo $mod; ?>"
     data-bs-toggle="tooltip"
     data-bs-placement="top"
     title="<?php echo $t; ?>"
     aria-label="<?php echo $t; ?>"
     role="img">
    <?php echo ts_icon('share'); ?>
</div>
