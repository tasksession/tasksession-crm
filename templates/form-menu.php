<?php
/**
 * Forms module – left-side navigation (same pattern as system-nav.php)
 * Include on all form-related admin pages for consistent sidebar menu.
 */
$current_page = basename($_SERVER['PHP_SELF'], '.php');
$forms_base = isset($url) ? $url . 'admin/' : '../';

$form_nav_items = [
    'forms' => [
        'title' => isset($lang['Forms']) ? $lang['Forms'] : 'Forms',
        'url' => $forms_base . 'forms'
    ],
    'forms_add' => [
        'title' => isset($lang['Add New']) ? $lang['Add New'] . ' ' . (isset($lang['Forms']) ? $lang['Forms'] : 'Form') : 'Add Form',
        'url' => $forms_base . 'forms_add'
    ],
    'forms_integrations' => [
        'title' => isset($lang['Forms Integrations']) ? $lang['Forms Integrations'] : 'Integrations',
        'url' => $forms_base . 'forms_integrations'
    ],
    'forms_mappings' => [
        'title' => isset($lang['Field Mappings']) ? $lang['Field Mappings'] : 'Field Mappings',
        'url' => $forms_base . 'forms_mappings'
    ],
    'forms_webhooks' => [
        'title' => isset($lang['Webhooks']) ? $lang['Webhooks'] : 'Webhooks',
        'url' => $forms_base . 'forms_webhooks'
    ],
    'forms_submissions' => [
        'title' => isset($lang['Submission Logs']) ? $lang['Submission Logs'] : 'Submission Logs',
        'url' => $forms_base . 'forms_submissions'
    ],
    'forms_debug' => [
        'title' => isset($lang['Forms Debug']) ? $lang['Forms Debug'] : 'Debug',
        'url' => $forms_base . 'forms_debug'
    ],
    'help-form' => [
        'title' => isset($lang['How it works']) ? $lang['How it works'] : 'How it works',
        'url' => $forms_base . 'help-form'
    ],
];
?>
<div class="col-md-3 ss-left">
    <h2 class="page-title"><?php echo isset($lang['Form settings']) ? $lang['Form settings'] : 'Form settings'; ?></h2>
    <div class="ss-sidenav">
        <ul>
            <?php foreach ($form_nav_items as $page => $item): ?>
                <?php
                $is_active = ($current_page === $page);
                if ($page === 'forms' && in_array($current_page, ['forms_add', 'forms_edit'], true)) {
                    $is_active = true;
                }
                if ($page === 'help-form' && $current_page === 'help-form') {
                    $is_active = true;
                }
                ?>
                <li<?php echo $is_active ? ' class="active"' : ''; ?>>
                    <a href="<?php echo htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
