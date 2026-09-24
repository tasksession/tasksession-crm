<?php
if (!isset($forms) || !isset($url)) return;
$lang = isset($lang) ? $lang : [];
$aiHelpersPath = dirname(__DIR__, 3) . '/ai/helpers.php';
if (is_file($aiHelpersPath)) {
    require_once $aiHelpersPath;
}
$canAiForms = function_exists('ai_contextual_button_allowed') && ai_contextual_button_allowed();
$deleteConfirm = isset($lang['Delete']) ? ($lang['Delete'] . ' this form?') : 'Delete this form?';
$editIcon = ts_icon('edit');
$linkIcon = ts_icon('link');
$aiIcon = ts_icon('sparkles');
$deleteIcon = ts_icon('delete');
include __DIR__ . '/list_table_open.php';
?>
        <thead>
            <tr>
                <th><?php echo isset($lang['No.']) ? FormsSecurityHelper::escape($lang['No.']) : '#'; ?></th>
                <th><?php echo isset($lang['Name']) ? FormsSecurityHelper::escape($lang['Name']) : 'Name'; ?></th>
                <th>Slug</th>
                <th><?php echo isset($lang['Status']) ? FormsSecurityHelper::escape($lang['Status']) : 'Status'; ?></th>
                <th>Integration</th>
                <th><?php echo isset($lang['Options']) ? FormsSecurityHelper::escape($lang['Options']) : 'Options'; ?></th>
            </tr>
        </thead>
        <tbody id="projects-tbl">
            <?php if (empty($forms)): ?>
            <tr>
                <td colspan="6" class="text-center"><?php echo isset($lang['No Data']) ? FormsSecurityHelper::escape($lang['No Data']) : 'No forms found.'; ?></td>
            </tr>
            <?php else: ?>
            <?php $no = 1; foreach ($forms as $f):
                $settings = [];
                if (!empty($f['settings_json'])) {
                    $decoded = json_decode($f['settings_json'], true);
                    if (is_array($decoded)) {
                        $settings = $decoded;
                    }
                }
                $embedId = isset($settings['embed_integration_id']) ? (int)$settings['embed_integration_id'] : 0;
                $integrationStatus = $embedId > 0 ? 'Integrated' : '—';
                $formActions = array(
                    array(
                        'type' => 'link',
                        'href' => $url . 'admin/forms_edit?id=' . (int) $f['id'],
                        'label' => $lang['Edit'] ?? 'Edit',
                        'icon' => $editIcon,
                    ),
                    array(
                        'type' => 'link',
                        'href' => $url . 'admin/forms_mappings?form_id=' . (int) $f['id'],
                        'label' => $lang['Field Mappings'] ?? 'Mappings',
                        'icon' => $linkIcon,
                    ),
                );
                if ($canAiForms) {
                    $formActions[] = array(
                        'type' => 'link',
                        'href' => ai_assistant_tool_prefill_url('forms.summarize_submissions', ['form_id' => (int) $f['id']]),
                        'label' => 'AI',
                        'icon' => $aiIcon,
                    );
                }
                $formActions[] = array(
                    'type' => 'link',
                    'href' => $url . 'admin/forms?delete=' . (int) $f['id'],
                    'label' => $lang['Delete'] ?? 'Delete',
                    'icon' => $deleteIcon,
                    'confirm' => $deleteConfirm,
                );
            ?>
            <tr>
                <td><?php echo (int)$no++; ?></td>
                <td><div class="tbl-ttl"><?php echo FormsSecurityHelper::escape($f['name']); ?></div></td>
                <td><?php echo FormsSecurityHelper::escape($f['slug']); ?></td>
                <td><?php echo forms_status_badge_html($f['status'] ?? ''); ?></td>
                <td><?php echo $embedId > 0 ? forms_status_badge_html('integrated') : '—'; ?></td>
                <?php forms_render_action_toggle('form' . (int) $f['id'], $formActions); ?>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
<?php include __DIR__ . '/list_table_close.php'; ?>
