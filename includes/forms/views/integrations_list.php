<?php
if (!isset($integrations) || !isset($url)) return;

$formsBaseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
    . '://' . ($_SERVER['HTTP_HOST'] ?? '') . dirname(dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$formsSubmitBase = rtrim($formsBaseUrl, '/') . '/includes/forms/api/submit.php';
$lang = isset($lang) ? $lang : [];
$editIcon = ts_icon('edit');
$linkIcon = ts_icon('link');
$copyIcon = ts_icon('duplicate');
include __DIR__ . '/list_table_open.php';
?>
        <thead>
            <tr>
                <th><?php echo isset($lang['No.']) ? FormsSecurityHelper::escape($lang['No.']) : '#'; ?></th>
                <th><?php echo isset($lang['Name']) ? FormsSecurityHelper::escape($lang['Name']) : 'Name'; ?></th>
                <th>Source Key</th>
                <th><?php echo isset($lang['Status']) ? FormsSecurityHelper::escape($lang['Status']) : 'Status'; ?></th>
                <th><?php echo isset($lang['Options']) ? FormsSecurityHelper::escape($lang['Options']) : 'Options'; ?></th>
            </tr>
        </thead>
        <tbody id="projects-tbl">
            <?php if (empty($integrations)): ?>
            <tr>
                <td colspan="5" class="text-center"><?php echo isset($lang['No Data']) ? FormsSecurityHelper::escape($lang['No Data']) : 'No integrations found.'; ?></td>
            </tr>
            <?php else: ?>
            <?php $no = 1; foreach ($integrations as $i):
                $webhookUrl = $formsSubmitBase . '?integration=' . ($i['source_key'] ?? '');
                $integrationActions = array(
                    array(
                        'type' => 'button',
                        'label' => 'Copy webhook URL',
                        'icon' => $copyIcon,
                        'class' => 'forms-copy-webhook-btn',
                        'data' => array('webhook-url' => $webhookUrl),
                    ),
                    array(
                        'type' => 'link',
                        'href' => $url . 'admin/forms_integrations?edit=' . (int) $i['id'],
                        'label' => $lang['Edit'] ?? 'Edit',
                        'icon' => $editIcon,
                    ),
                    array(
                        'type' => 'link',
                        'href' => $url . 'admin/forms_mappings?integration_id=' . (int) $i['id'],
                        'label' => $lang['Field Mappings'] ?? 'Mappings',
                        'icon' => $linkIcon,
                    ),
                );
            ?>
            <tr>
                <td><?php echo (int)$no++; ?></td>
                <td><div class="tbl-ttl"><?php echo FormsSecurityHelper::escape($i['name']); ?></div></td>
                <td><code><?php echo FormsSecurityHelper::escape($i['source_key']); ?></code></td>
                <td><?php echo forms_status_badge_html($i['status'] ?? ''); ?></td>
                <?php forms_render_action_toggle('integration' . (int) $i['id'], $integrationActions); ?>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
<?php include __DIR__ . '/list_table_close.php'; ?>
<script>
function copyIntegrationWebhookUrl(el) {
    var url = el.getAttribute('data-webhook-url');
    if (!url) return;
    function showCopiedTooltip(button) {
        var originalTitle = button.getAttribute('data-original-title') || button.getAttribute('title') || '';
        var bsOriginal = button.getAttribute('data-bs-original-title');
        var hasBootstrap = typeof bootstrap !== 'undefined' && bootstrap.Tooltip;
        button.setAttribute('title', 'Copied');
        if (hasBootstrap) {
            var tip = bootstrap.Tooltip.getInstance(button) || new bootstrap.Tooltip(button);
            tip.setContent({ '.tooltip-inner': 'Copied' });
            tip.show();
            setTimeout(function () {
                tip.hide();
                var restore = bsOriginal || originalTitle || 'Copy webhook URL';
                button.setAttribute('title', restore);
                tip.setContent({ '.tooltip-inner': restore });
            }, 1200);
        }
    }
    function done() { showCopiedTooltip(el); }
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(url).then(done).catch(function () {
            fallbackCopyIntegrationUrl(url);
            done();
        });
    } else {
        fallbackCopyIntegrationUrl(url);
        done();
    }
}
function fallbackCopyIntegrationUrl(text) {
    var temp = document.createElement('input');
    temp.type = 'text';
    temp.value = text;
    document.body.appendChild(temp);
    temp.select();
    try { document.execCommand('copy'); } catch (e) {}
    document.body.removeChild(temp);
}
document.addEventListener('click', function (e) {
    var btn = e.target && e.target.closest ? e.target.closest('.forms-copy-webhook-btn') : null;
    if (!btn) return;
    e.preventDefault();
    copyIntegrationWebhookUrl(btn);
});
</script>
