<?php
if (!isset($submissions) || !isset($url)) return;
$lang = isset($lang) ? $lang : [];
$viewIcon = ts_icon('eye');
$deleteIcon = ts_icon('delete');
include __DIR__ . '/list_table_open.php';
?>
        <thead>
            <tr>
                <th><?php echo isset($lang['No.']) ? FormsSecurityHelper::escape($lang['No.']) : '#'; ?></th>
                <th>Source</th>
                <th><?php echo isset($lang['Status']) ? FormsSecurityHelper::escape($lang['Status']) : 'Status'; ?></th>
                <th>Lead ID</th>
                <th>Error</th>
                <th>Created</th>
                <th><?php echo isset($lang['Options']) ? FormsSecurityHelper::escape($lang['Options']) : 'Options'; ?></th>
            </tr>
        </thead>
        <tbody id="projects-tbl">
            <?php if (empty($submissions)): ?>
            <tr>
                <td colspan="7" class="text-center"><?php echo isset($lang['No Data']) ? FormsSecurityHelper::escape($lang['No Data']) : 'No submissions found.'; ?></td>
            </tr>
            <?php else: ?>
            <?php foreach ($submissions as $s):
                $deleteQuery = http_build_query(array_filter([
                    'delete' => (int) $s['id'],
                    'integration_id' => $integrationId ?? null,
                    'form_id' => $formId ?? null,
                    'status' => $status ?? null,
                    'date_from' => $dateFrom ?? null,
                    'date_to' => $dateTo ?? null,
                ], function ($v) { return $v !== null && $v !== ''; }));
                $submissionActions = array(
                    array(
                        'type' => 'link',
                        'href' => $url . 'admin/forms_submissions?view=' . (int) $s['id'],
                        'label' => $lang['View'] ?? 'View',
                        'icon' => $viewIcon,
                    ),
                    array(
                        'type' => 'link',
                        'href' => $url . 'admin/forms_submissions?' . $deleteQuery,
                        'label' => $lang['Delete'] ?? 'Delete',
                        'icon' => $deleteIcon,
                        'confirm' => 'Delete this submission?',
                    ),
                );
            ?>
            <tr>
                <td><?php echo (int)$s['id']; ?></td>
                <td><div class="tbl-ttl"><?php echo FormsSecurityHelper::escape($s['source']); ?></div></td>
                <td><?php echo forms_status_badge_html($s['status'] ?? ''); ?></td>
                <td><?php echo !empty($s['lead_id']) ? '<a href="' . FormsSecurityHelper::escape($url) . 'admin/leads">' . (int)$s['lead_id'] . '</a>' : '—'; ?></td>
                <td><?php echo !empty($s['error_message']) ? FormsSecurityHelper::escape($s['error_message']) : '—'; ?></td>
                <td><?php echo FormsSecurityHelper::escape($s['created_at']); ?></td>
                <?php forms_render_action_toggle('submission' . (int) $s['id'], $submissionActions); ?>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
<?php include __DIR__ . '/list_table_close.php'; ?>
