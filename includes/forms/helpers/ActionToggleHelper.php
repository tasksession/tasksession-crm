<?php
/**
 * Forms list tables — same row action dropdown as ecommerce settings (stores table).
 */

if (!function_exists('forms_action_menu_icon_html')) {
    function forms_action_menu_icon_html($iconHtml)
    {
        $iconHtml = (string) $iconHtml;
        if ($iconHtml === '' || strpos($iconHtml, 'tasksession-timer-log-menu-ico') !== false) {
            return $iconHtml;
        }
        return preg_replace(
            '/class="ts-icon /',
            'class="ts-icon tasksession-timer-log-menu-ico ',
            $iconHtml,
            1
        );
    }
}

if (!function_exists('forms_render_action_toggle')) {
    /**
     * @param int|string $rowId
     * @param array<int,array<string,mixed>> $items type=link|form|button, label, href|hidden|data|confirm|icon|onclick
     */
    function forms_render_action_toggle($rowId, array $items)
    {
        global $lang;
        $dropdownId = 'actionDropdown' . preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $rowId);
        $actionLabel = htmlspecialchars($lang['Action'] ?? 'Action', ENT_QUOTES, 'UTF-8');
        $viewIcon = forms_action_menu_icon_html(ts_icon('info'));
        ?>
        <td class="extra-height">
            <div class="action-toggle" data-bs-toggle="collapse" data-bs-target="#<?php echo htmlspecialchars($dropdownId, ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo $actionLabel; ?>
                <?php echo ts_icon('chevron-down'); ?>
            </div>
            <div id="<?php echo htmlspecialchars($dropdownId, ENT_QUOTES, 'UTF-8'); ?>" class="toggle-action collapse shadow-dept">
                <ul>
                    <?php foreach ($items as $item) : ?>
                        <?php
                        $type = isset($item['type']) ? (string) $item['type'] : 'link';
                        $label = htmlspecialchars((string) ($item['label'] ?? ''), ENT_QUOTES, 'UTF-8');
                        $icon = !empty($item['icon'])
                            ? forms_action_menu_icon_html($item['icon'])
                            : $viewIcon;
                        ?>
                        <li>
                            <?php if ($type === 'form') : ?>
                                <form method="<?php echo htmlspecialchars((string) ($item['method'] ?? 'post'), ENT_QUOTES, 'UTF-8'); ?>"
                                      action="<?php echo htmlspecialchars((string) ($item['action'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php
                                    $hidden = isset($item['hidden']) && is_array($item['hidden']) ? $item['hidden'] : array();
                                    foreach ($hidden as $hk => $hv) :
                                        ?>
                                        <input type="hidden" name="<?php echo htmlspecialchars((string) $hk, ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo htmlspecialchars((string) $hv, ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php endforeach; ?>
                                    <button type="submit"<?php echo !empty($item['confirm']) ? ' onclick="return confirm(\'' . htmlspecialchars((string) $item['confirm'], ENT_QUOTES, 'UTF-8') . '\');"' : ''; ?>>
                                        <?php echo $icon; ?> <?php echo $label; ?>
                                    </button>
                                </form>
                            <?php elseif ($type === 'button') : ?>
                                <button type="button" class="<?php echo htmlspecialchars((string) ($item['class'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                    <?php
                                    if (!empty($item['onclick'])) {
                                        echo ' onclick="' . htmlspecialchars((string) $item['onclick'], ENT_QUOTES, 'UTF-8') . '"';
                                    }
                                    $dataAttrs = isset($item['data']) && is_array($item['data']) ? $item['data'] : array();
                                    foreach ($dataAttrs as $dataKey => $dataVal) :
                                        $attrName = 'data-' . preg_replace('/[^a-z0-9_-]/i', '', (string) $dataKey);
                                        ?>
                                        <?php echo htmlspecialchars($attrName, ENT_QUOTES, 'UTF-8'); ?>="<?php echo htmlspecialchars((string) $dataVal, ENT_QUOTES, 'UTF-8'); ?>"
                                    <?php endforeach; ?>>
                                    <?php echo $icon; ?> <?php echo $label; ?>
                                </button>
                            <?php else : ?>
                                <a href="<?php echo htmlspecialchars((string) ($item['href'] ?? '#'), ENT_QUOTES, 'UTF-8'); ?>"
                                    <?php
                                    if (!empty($item['confirm'])) {
                                        echo ' onclick="return confirm(\'' . htmlspecialchars((string) $item['confirm'], ENT_QUOTES, 'UTF-8') . '\');"';
                                    } elseif (!empty($item['onclick'])) {
                                        echo ' onclick="' . htmlspecialchars((string) $item['onclick'], ENT_QUOTES, 'UTF-8') . '"';
                                    }
                                    ?>>
                                    <?php echo $icon; ?> <?php echo $label; ?>
                                </a>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </td>
        <?php
    }
}

if (!function_exists('forms_status_badge_html')) {
    function forms_status_badge_html($status, $labels = array())
    {
        $raw = trim((string) $status);
        $key = strtolower($raw);
        $display = $raw !== '' ? $raw : '—';
        if (isset($labels[$key])) {
            $display = (string) $labels[$key];
        }
        $class = 'badge';
        if (in_array($key, array('active', 'success', 'integrated', 'yes'), true)) {
            $class = 'badge success';
        } elseif (in_array($key, array('inactive', 'failed', 'error', 'no'), true)) {
            $class = 'badge inprogress';
        }
        return '<span class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '">' . FormsSecurityHelper::escape($display) . '</span>';
    }
}
