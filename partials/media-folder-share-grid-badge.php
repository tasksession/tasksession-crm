<?php
/**
 * Corner share badge on folder grid cards (Media Vault + project media).
 * Expects: $folder (object). Optional: $lang (array), $currentUserId (int) for vault_project_link tooltips.
 */
if (!isset($folder) || !is_object($folder)) {
    return;
}
if (!isset($lang) || !is_array($lang)) {
    $lang = array();
}

$mv_badge_viewer = 0;
if (isset($currentUserId)) {
    $mv_badge_viewer = (int)$currentUserId;
} elseif (isset($session) && is_object($session) && isset($session->userId)) {
    $mv_badge_viewer = (int)$session->userId;
}

$_mv_folder_share_badge = isset($folder->access_type) && (
    $folder->access_type === 'shared'
    || $folder->access_type === 'shared_by_me'
    || $folder->access_type === 'vault_project_link'
);
if (!$_mv_folder_share_badge && !empty($folder->mv_has_extended_share)) {
    $_mv_folder_share_badge = true;
}
if (!$_mv_folder_share_badge) {
    return;
}

$badge_class = 'shared-folder-badge';
$badge_title = '';
$mv_folder_access_label = static function ($permRaw, array $lang): string {
    $perm = strtolower(trim((string)$permRaw));
    switch ($perm) {
        case 'view':
        case 'view_profile':
            return $lang['View only — can view files only'] ?? 'View only — can view files only';
        case 'read':
        case 'read_profile':
            return $lang['Read only — can view and download files'] ?? 'Read only — can view and download files';
        case 'write':
        case 'write_profile':
            return $lang['Read & write — can add, edit, and delete files'] ?? 'Read & write — can add, edit, and delete files';
        case 'admin':
        case 'admin_profile':
            return $lang['Admin — full control including sharing'] ?? 'Admin — full control including sharing';
        default:
            return ucfirst($perm !== '' ? $perm : 'read');
    }
};
$mv_folder_highest_perm = static function ($rawPerm, $fallback = 'read'): string {
    $rank = array('view' => 1, 'read' => 2, 'write' => 3, 'admin' => 4);
    $normalize = static function ($perm) {
        $perm = strtolower(trim((string)$perm));
        if ($perm === 'view_profile') return 'view';
        if ($perm === 'read_profile') return 'read';
        if ($perm === 'write_profile') return 'write';
        if ($perm === 'admin_profile') return 'admin';
        return $perm;
    };
    $bestFallback = $normalize($fallback);
    if (!isset($rank[$bestFallback])) {
        $bestFallback = 'read';
    }
    $best = $bestFallback;
    $bestRank = $rank[$best];
    $hasTokenPerm = false;
    foreach (explode(',', (string)$rawPerm) as $token) {
        $perm = $normalize($token);
        if (!isset($rank[$perm])) {
            continue;
        }
        if (!$hasTokenPerm) {
            $best = $perm;
            $bestRank = $rank[$perm];
            $hasTokenPerm = true;
            continue;
        }
        if ($rank[$perm] > $bestRank) {
            $best = $perm;
            $bestRank = $rank[$perm];
        }
    }
    if (!$hasTokenPerm) {
        return $bestFallback;
    }
    return $best;
};

if (isset($folder->access_type) && $folder->access_type === 'vault_project_link') {
    $_mvSb = isset($folder->vault_link_shared_by) ? (int)$folder->vault_link_shared_by : -1;
    $by_me = ($mv_badge_viewer > 0 && $_mvSb >= 0 && $_mvSb === $mv_badge_viewer);
    $effectivePerm = $mv_folder_highest_perm($folder->shared_permissions ?? 'read', 'read');
    $permTitle = $mv_folder_access_label($effectivePerm, $lang);
    $badge_class .= $by_me ? ' shared-by-me' : ' shared-with-me';
    $badge_title = $by_me
        ? (($lang['You shared this folder'] ?? 'You shared this folder') . ' — ' . $permTitle)
        : (($lang['Shared with you'] ?? 'Shared with you') . ' — ' . $permTitle);
} elseif (isset($folder->access_type) && $folder->access_type === 'shared') {
    $badge_class .= ' shared-with-me';
    $badge_title = ($lang['Shared with you'] ?? 'Shared with you') . ' — ' . $mv_folder_access_label($folder->shared_permissions ?? 'read', $lang);
} else {
    $badge_class .= ' shared-by-me';
    $owned_ext = !empty($folder->mv_has_extended_share)
        && (!isset($folder->access_type) || $folder->access_type === 'owned');
    $badge_title = $owned_ext
        ? ($lang['Shared via project, profile, or link'] ?? 'Shared via project, profile, or link')
        : ($lang['You shared this folder'] ?? 'You shared this folder');
}
$badge_tt = htmlspecialchars($badge_title, ENT_QUOTES, 'UTF-8');
?>
                                                            <div class="<?php echo $badge_class; ?>"
                                                                 data-bs-toggle="tooltip"
                                                                 data-bs-placement="top"
                                                                 title="<?php echo $badge_tt; ?>"
                                                                 aria-label="<?php echo $badge_tt; ?>"
                                                                 role="img">
                                                                <?php echo ts_icon('share'); ?>
                                                            </div>
