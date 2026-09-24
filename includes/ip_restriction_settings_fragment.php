<?php
/**
 * Shared UI fragment: IP restriction module toggle + global allowlist.
 * Expects: $settings (settings object), $lang (array), optional $fragmentFormId (string).
 * Optional: $fragmentLoginIpCandidates (string[]) same sources as login check; else $fragmentRequestIp (string).
 * Allowlist supports exact IPv4/IPv6 and CIDR (e.g. 2406:d00:ccad:10e1::/64) — see lang Global allowed IPs CIDR help.
 */
if (!isset($settings) || !is_object($settings)) {
    return;
}
$L = is_array($lang ?? null) ? $lang : array();
$fid = isset($fragmentFormId) ? (string)$fragmentFormId : 'ip-restriction-settings-form';
?>
<div class="widget-card mb-3">
    <?php
    $fragmentCandidates = array();
    if (isset($fragmentLoginIpCandidates) && is_array($fragmentLoginIpCandidates)) {
        foreach ($fragmentLoginIpCandidates as $_cand) {
            $_cand = trim((string)$_cand);
            if ($_cand !== '') {
                $fragmentCandidates[] = $_cand;
            }
        }
    } elseif (isset($fragmentRequestIp) && (string)$fragmentRequestIp !== '') {
        $fragmentCandidates[] = (string)$fragmentRequestIp;
    }
    ?>
    <div class="card-title"><?php echo htmlspecialchars($L['IPs checked on login'] ?? 'IPs checked on login', ENT_QUOTES, 'UTF-8'); ?></div>
    <?php if (!empty($fragmentCandidates)): ?>
    <p class="small mb-2 font-monospace text-break"><?php echo htmlspecialchars(implode(', ', $fragmentCandidates), ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>
    <p class="text-muted small mb-3"><?php echo htmlspecialchars($L['IP restriction settings help'] ?? 'Control whether login IP checks run, and optional global IPs merged with each user list.', ENT_QUOTES, 'UTF-8'); ?></p>
    <p class="text-muted small mb-3"><?php echo htmlspecialchars($L['IP restriction dual stack note'] ?? 'Each device may connect over IPv4 or IPv6; the address your server sees can differ from some “what is my IP” sites. Add every address users need. Failed logins in the activity log below show the IP the server recorded.', ENT_QUOTES, 'UTF-8'); ?></p>
    <div class="permission-item d-flex col-gap align-items-start mb-3">
        <div class="checkbox-wrapper-6">
            <input class="tgl tgl-light" id="<?php echo htmlspecialchars($fid, ENT_QUOTES, 'UTF-8'); ?>_module_ip" name="module_ip_restriction" type="checkbox" value="1" <?php echo !empty($settings->module_ip_restriction) ? 'checked' : ''; ?> />
            <label class="tgl-btn" for="<?php echo htmlspecialchars($fid, ENT_QUOTES, 'UTF-8'); ?>_module_ip"></label>
        </div>
        <div class="flex-grow-1">
            <label for="<?php echo htmlspecialchars($fid, ENT_QUOTES, 'UTF-8'); ?>_module_ip" class="permission-label fw-bold"><?php echo htmlspecialchars($L['IP Restriction Module'] ?? 'IP Restriction Module', ENT_QUOTES, 'UTF-8'); ?></label>
        </div>
    </div>
    <div class="form-group">
        <label for="<?php echo htmlspecialchars($fid, ENT_QUOTES, 'UTF-8'); ?>_global_ips"><?php echo htmlspecialchars($L['Global allowed IPs'] ?? 'Global allowed IPs', ENT_QUOTES, 'UTF-8'); ?></label>
        <textarea class="form-control" id="<?php echo htmlspecialchars($fid, ENT_QUOTES, 'UTF-8'); ?>_global_ips" name="global_allowed_ips" rows="3" placeholder="<?php echo htmlspecialchars($L['Global allowed IPs placeholder'] ?? '111.88.7.28, 2406:d00:ccad:10e1::/64', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string)($settings->global_allowed_ips ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
        <small class="form-text text-muted"><?php echo htmlspecialchars($L['Optional. Comma-separated IPs allowed in addition to each user list when IP restriction is enabled for that user.'] ?? '', ENT_QUOTES, 'UTF-8'); ?></small>
        <small class="form-text text-muted d-block mt-1"><?php echo htmlspecialchars($L['Global allowed IPs CIDR help'] ?? '', ENT_QUOTES, 'UTF-8'); ?></small>
    </div>
    <button type="submit" name="save_ip_restriction_settings" value="1" class="btn primary-btn"><?php echo htmlspecialchars($L['Save IP Settings'] ?? 'Save', ENT_QUOTES, 'UTF-8'); ?></button>
</div>
