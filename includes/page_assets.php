<?php
/**
 * Per-page optional asset flags (opt-out safe).
 *
 * Defaults keep current global header/footer behavior. Pages call
 * comon_page_assets_set() before templates/header.php to skip unused CSS/JS.
 *
 * Keys:
 * - jquery_ui              — jquery-ui CSS (header) + JS (main-footer)
 * - rich_text              — assets/css/rich-text.css
 * - file_sharing           — vendor/.../file-sharing.css (task-sidebar)
 * - email_notification_modal — email-notification modal + JS
 * - ai_rail                — true | lazy | false (Ask AI rail CSS/JS)
 * - task_sidebar_bundle    — subtasks/files/images/sidebar JS (true | lazy | false)
 */

if (!function_exists('comon_page_assets_defaults')) {
    function comon_page_assets_defaults()
    {
        return array(
            'jquery_ui' => true,
            'rich_text' => true,
            'file_sharing' => true,
            'email_notification_modal' => true,
            'ai_rail' => true,
            'task_sidebar_bundle' => true,
        );
    }
}

if (!function_exists('comon_page_assets_all')) {
    function comon_page_assets_all()
    {
        if (!isset($GLOBALS['comon_page_assets']) || !is_array($GLOBALS['comon_page_assets'])) {
            $GLOBALS['comon_page_assets'] = comon_page_assets_defaults();
        }
        return $GLOBALS['comon_page_assets'];
    }
}

if (!function_exists('comon_page_assets_set')) {
    /**
     * @param array $overrides Key => bool|string (ai_rail may be true|false|'lazy')
     */
    function comon_page_assets_set(array $overrides)
    {
        $current = comon_page_assets_all();
        foreach ($overrides as $key => $value) {
            $key = (string) $key;
            if ($key === '') {
                continue;
            }
            $current[$key] = $value;
        }
        $GLOBALS['comon_page_assets'] = $current;
    }
}

if (!function_exists('comon_page_asset')) {
    /**
     * @param string $key
     * @return mixed Flag value (bool or string for ai_rail)
     */
    function comon_page_asset($key)
    {
        $all = comon_page_assets_all();
        $key = (string) $key;
        if (!array_key_exists($key, $all)) {
            $defaults = comon_page_assets_defaults();
            return array_key_exists($key, $defaults) ? $defaults[$key] : true;
        }
        return $all[$key];
    }
}

if (!function_exists('comon_page_asset_enabled')) {
    /**
     * True when the asset should load eagerly (not false / not lazy).
     */
    function comon_page_asset_enabled($key)
    {
        $value = comon_page_asset($key);
        if ($value === false || $value === 0 || $value === '0' || $value === 'false') {
            return false;
        }
        if ($value === 'lazy') {
            return false;
        }
        return (bool) $value;
    }
}

if (!function_exists('comon_page_asset_lazy')) {
    function comon_page_asset_lazy($key)
    {
        return comon_page_asset($key) === 'lazy';
    }
}
