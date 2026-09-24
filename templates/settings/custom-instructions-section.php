<?php
/**
 * Settings > Assistant preferences (response instructions card)
 *
 * Used by ai/preferences.php and ai/setting.php.
 *
 * Optional vars:
 * - $customInstructionsInlineSave (bool) — show Save inside the card (settings page)
 * - $customInstructionsFormAction (string) — form action URL (optional)
 */
if (!isset($prefs) || !is_array($prefs)) {
    $prefs = ['custom_instructions' => ''];
}
if (!isset($csrfToken)) {
    $csrfToken = function_exists('generate_csrf_token') ? generate_csrf_token() : '';
}
$maxLen = function_exists('ai_max_custom_instructions_length') ? (int) ai_max_custom_instructions_length() : 4000;
$instructions = (string) ($prefs['custom_instructions'] ?? '');
$inlineSave = !empty($customInstructionsInlineSave);
$formAction = isset($customInstructionsFormAction) ? (string) $customInstructionsFormAction : '';
$formId = 'custom-instructions-form';
$taId = 'custom_instructions';
$countId = 'custom-instructions-count';
?>
<style>
#custom-instructions-settings .card-header.push-notifications-card__header {
  display: flex;
  align-items: center;
  gap: 1rem;
}
#custom-instructions-settings .push-notifications-card__header-icon {
  flex-shrink: 0;
  width: 48px;
  height: 48px;
  border-radius: 50%;
  background: color-mix(in srgb, var(--primary-color) 14%, var(--card-body-color));
  color: var(--primary-color);
  display: flex;
  align-items: center;
  justify-content: center;
}
#custom-instructions-settings .push-notifications-card__header-icon-svg {
  width: 24px;
  height: 24px;
}
#custom-instructions-settings .push-notifications-card__header-text {
  flex: 1 1 auto;
  min-width: 0;
}
#custom-instructions-settings .push-notifications-card__header-text h4 {
  margin: 0;
}
#custom-instructions-settings .push-notifications-card__header-text p {
  margin: 5px 0 0;
  color: color-mix(in srgb, var(--body-font-color) 72%, transparent);
}
.ai-settings-section #custom-instructions-settings {
  border: 1px solid rgba(127, 127, 127, 0.28);
  border-radius: 10px;
  background: rgba(127, 127, 127, 0.06);
  overflow: hidden;
}
.ai-settings-section #custom-instructions-settings .card-header {
  padding: 1rem 1.1rem;
  border-bottom: 1px solid rgba(127, 127, 127, 0.22);
}
.ai-settings-section #custom-instructions-settings .card-body {
  padding: 1rem 1.1rem 1.1rem;
}
</style>
<div class="settings-card w-100" id="custom-instructions-settings">
    <div class="card-header push-notifications-card__header">
        <div class="push-notifications-card__header-icon" aria-hidden="true">
            <?php echo function_exists('ts_icon') ? ts_icon('sparkles', 'push-notifications-card__header-icon-svg') : ''; ?>
        </div>
        <div class="push-notifications-card__header-text">
            <h4><?php echo htmlspecialchars($lang['Response Instructions'] ?? 'Response instructions', ENT_QUOTES, 'UTF-8'); ?></h4>
            <p><?php echo htmlspecialchars($lang['AI custom instructions help'] ?? 'Personal style for the AI assistant. Cannot override security or permissions.', ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
    </div>

    <div class="card-body">
        <form method="post" id="<?php echo htmlspecialchars($formId, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $formAction !== '' ? ' action="' . htmlspecialchars($formAction, ENT_QUOTES, 'UTF-8') . '"' : ''; ?>>
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="save_custom_instructions" value="1">
            <input type="hidden" name="ai_settings_section" value="section-instructions">
            <label class="visually-hidden" for="<?php echo htmlspecialchars($taId, ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo htmlspecialchars($lang['Response Instructions'] ?? 'Response instructions', ENT_QUOTES, 'UTF-8'); ?>
            </label>
            <textarea
                name="custom_instructions"
                id="<?php echo htmlspecialchars($taId, ENT_QUOTES, 'UTF-8'); ?>"
                class="form-control ai-settings-input"
                rows="10"
                maxlength="<?php echo (int) $maxLen; ?>"
                placeholder="<?php echo htmlspecialchars($lang['e.g. Keep answers short and use bullet points.'] ?? 'e.g. Keep answers short and use bullet points.', ENT_QUOTES, 'UTF-8'); ?>"
            ><?php echo htmlspecialchars($instructions, ENT_QUOTES, 'UTF-8'); ?></textarea>
            <div class="d-flex justify-content-between align-items-center mt-2 flex-wrap col-gap">
                <span class="push-settings-row__sub mb-0" id="<?php echo htmlspecialchars($countId, ENT_QUOTES, 'UTF-8'); ?>"><?php echo strlen($instructions); ?> / <?php echo (int) $maxLen; ?></span>
                <?php if ($inlineSave): ?>
                <button type="submit" class="btn primary-btn m-0"><?php echo htmlspecialchars($lang['Save'] ?? 'Save', ENT_QUOTES, 'UTF-8'); ?></button>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>
<script>
(function () {
  var ta = document.getElementById(<?php echo json_encode($taId); ?>);
  var count = document.getElementById(<?php echo json_encode($countId); ?>);
  if (!ta || !count) return;
  var max = parseInt(ta.getAttribute('maxlength') || '4000', 10) || 4000;
  function sync() {
    count.textContent = (ta.value || '').length + ' / ' + max;
  }
  ta.addEventListener('input', sync);
  sync();
})();
</script>
