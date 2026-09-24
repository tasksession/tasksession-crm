<?php
/**
 * Free edition addon enablement (settings flag only — no license verifier).
 */

require_once __DIR__ . '/addon_registry.php';

/**
 * Paid-addon expiration checks are Pro-only; Free edition is a no-op.
 */
function comon_run_addon_expiration_checks()
{
    return;
}

/**
 * Whether a paid addon module is enabled.
 * Free edition never ships paid addons (Marketing / Ecommerce / AI).
 *
 * @param string $addonKey
 * @return bool
 */
function comon_addon_is_enabled($addonKey)
{
    static $cache = [];

    $addonKey = (string) $addonKey;
    if (isset($cache[$addonKey])) {
        return $cache[$addonKey];
    }

    if (function_exists('tasksession_is_free_edition') && tasksession_is_free_edition()
        && in_array($addonKey, array('marketing', 'woocommerce', 'ai'), true)) {
        $cache[$addonKey] = false;
        return $cache[$addonKey];
    }

    if (comon_addon_is_free($addonKey)) {
        $cache[$addonKey] = comon_addon_free_is_active($addonKey);

        return $cache[$addonKey];
    }

    // Paid addons require Pro license — always off in Free package.
    $cache[$addonKey] = false;

    return $cache[$addonKey];
}

/**
 * UI / admin state for an addon card.
 *
 * @param string $addonKey
 * @return string coming_soon|active|expired|inactive
 */
function comon_addon_license_state($addonKey)
{
    $addonKey = (string) $addonKey;
    if (comon_addon_is_free($addonKey)) {
        return comon_addon_free_is_active($addonKey) ? 'active' : 'inactive';
    }

    $def = comon_get_addon_definition($addonKey);
    if (!$def || !empty($def['coming_soon'])) {
        return 'coming_soon';
    }

    return 'inactive';
}
