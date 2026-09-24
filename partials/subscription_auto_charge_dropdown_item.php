<?php
/**
 * Auto charge toggle at bottom of subscription action menus (parent invoice only).
 * Expects: $row (subscription), optional $lang
 */
if (!isset($row['id'])) {
    return;
}
$subAutoChargeId = (int) $row['id'];
$subAutoChargeOn = !empty($row['recurring_auto_charge']);
$subAutoChargeLabel = isset($lang['Auto charge']) ? $lang['Auto charge'] : 'Auto charge';
$subAutoChargeInputId = 'subscription_auto_charge_' . $subAutoChargeId;
?>
<li><hr class="dropdown-divider subscription-auto-charge-divider"></li>
<li>
    <div class="dropdown-item subscription-auto-charge-menu-row d-flex col-gap align-items-center" onclick="event.stopPropagation();">
        <div class="checkbox-wrapper-6 checkbox-wrapper-6-sm">
            <input class="tgl tgl-light js-subscription-auto-charge-toggle"
                   id="<?php echo htmlspecialchars($subAutoChargeInputId, ENT_QUOTES, 'UTF-8'); ?>"
                   type="checkbox"
                   value="1"
                   data-subscription-id="<?php echo $subAutoChargeId; ?>"
                   <?php echo $subAutoChargeOn ? 'checked' : ''; ?>>
            <label class="tgl-btn" for="<?php echo htmlspecialchars($subAutoChargeInputId, ENT_QUOTES, 'UTF-8'); ?>"></label>
        </div>
        <label class="permission-label mb-0 subscription-auto-charge-menu-label" for="<?php echo htmlspecialchars($subAutoChargeInputId, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($subAutoChargeLabel, ENT_QUOTES, 'UTF-8'); ?></label>
    </div>
</li>
