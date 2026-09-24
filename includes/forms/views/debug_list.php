<?php
if (!isset($debugLogs) || !isset($url)) return;
$lang = isset($lang) ? $lang : [];
include __DIR__ . '/list_table_open.php';
?>
        <thead>
            <tr>
                <th><?php echo isset($lang['No.']) ? FormsSecurityHelper::escape($lang['No.']) : '#'; ?></th>
                <th>Level</th>
                <th>Route</th>
                <th>Source</th>
                <th>Request ID</th>
                <th>Message</th>
                <th>Created</th>
            </tr>
        </thead>
        <tbody id="projects-tbl">
            <?php if (empty($debugLogs)): ?>
            <tr>
                <td colspan="7" class="text-center"><?php echo isset($lang['No Data']) ? FormsSecurityHelper::escape($lang['No Data']) : 'No log entries found.'; ?></td>
            </tr>
            <?php else: ?>
            <?php foreach ($debugLogs as $l): ?>
            <tr>
                <td><?php echo (int)$l['id']; ?></td>
                <td><?php echo forms_status_badge_html($l['level'] ?? ''); ?></td>
                <td><?php echo FormsSecurityHelper::escape($l['route']); ?></td>
                <td><div class="tbl-ttl"><?php echo FormsSecurityHelper::escape($l['source']); ?></div></td>
                <td><code><?php echo FormsSecurityHelper::escape($l['request_id']); ?></code></td>
                <td class="max-width-250"><?php echo FormsSecurityHelper::escape($l['message']); ?></td>
                <td><?php echo FormsSecurityHelper::escape($l['created_at']); ?></td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
<?php include __DIR__ . '/list_table_close.php'; ?>
