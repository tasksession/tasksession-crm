<?php
/**
 * Folder-style circular share badge on media vault file grid cards.
 * Expects: $file (object), $recipientsMap (array<int, mixed>) active shared_files recipients keyed by file id.
 * Optional: $fileShareSharers (array<int, array>) from FileManager::getActiveFileShareSharerInfoForFileIds (avoids extra queries).
 */
if (!isset($file) || !is_object($file)) {
    return;
}
if (!isset($recipientsMap) || !is_array($recipientsMap)) {
    $recipientsMap = array();
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

$fid = (int)$file->id;
$at = isset($file->access_type) ? (string)$file->access_type : '';
$has_direct_share = !empty($recipientsMap[$fid]);

$sharerMeta = array(
    'shared_by_ids' => array(),
    'single_sharer_display' => '',
    'permission_for_viewer' => null,
    'permission_for_sharer' => null,
);
if (isset($fileShareSharers) && is_array($fileShareSharers) && isset($fileShareSharers[$fid]) && is_array($fileShareSharers[$fid])) {
    $sharerMeta = array_merge($sharerMeta, $fileShareSharers[$fid]);
} elseif (($has_direct_share || $at === 'shared' || $at === 'shared_by_me' || !empty($file->mv_has_vault_team_share)) && class_exists('FileManager', false)) {
    static $mvFileShareSharerPartialCache = array();
    $cacheKey = $fid . ':' . $mv_badge_viewer;
    if (!isset($mvFileShareSharerPartialCache[$cacheKey])) {
        $mvFileShareSharerPartialCache[$cacheKey] = FileManager::getActiveFileShareSharerInfoForFileIds(array($fid), $mv_badge_viewer);
    }
    $chunk = $mvFileShareSharerPartialCache[$cacheKey];
    if (isset($chunk[$fid]) && is_array($chunk[$fid])) {
        $sharerMeta = array_merge($sharerMeta, $chunk[$fid]);
    }
}

$mv_share_badge_access_label = function ($permRaw, array $lang) {
    $p = strtolower(trim((string) $permRaw));
    if ($p === 'write' || $p === 'admin' || $p === 'edit') {
        return $lang['Edit access'] ?? 'Edit access';
    }
    return $lang['Read access'] ?? 'Read access';
};

$show_badge = false;
$badge_class = 'shared-folder-badge shared-file-grid-badge';
$title = '';

if ($at !== 'owned_shared') {
    if ($at === 'vault_project_link') {
        $show_badge = true;
        $_mvSb = isset($file->vault_link_shared_by) ? (int)$file->vault_link_shared_by : -1;
        $by_me = ($mv_badge_viewer > 0 && $_mvSb >= 0 && $_mvSb === $mv_badge_viewer);
        $badge_class .= $by_me ? ' shared-by-me' : ' shared-with-me';
        $title = $by_me
            ? sprintf(
                $lang['You shared this file — %s'] ?? 'You shared this file — %s',
                $mv_share_badge_access_label($file->shared_permissions ?? 'read', $lang)
            )
            : ($lang['Linked from Media Vault'] ?? 'Linked from Media Vault');
    } elseif ($at === 'shared') {
        $show_badge = true;
        $badge_class .= ' shared-with-me';
        $permRaw = $file->shared_permissions ?? ($sharerMeta['permission_for_viewer'] ?? 'read');
        $accessLbl = $mv_share_badge_access_label($permRaw, $lang);
        $byName = isset($sharerMeta['single_sharer_display']) ? trim((string) $sharerMeta['single_sharer_display']) : '';
        $sharedByIds = isset($sharerMeta['shared_by_ids']) ? $sharerMeta['shared_by_ids'] : array();
        if ($byName !== '' && ($mv_badge_viewer <= 0 || !in_array($mv_badge_viewer, $sharedByIds, true))) {
            $title = sprintf($lang['Shared by %s — %s'] ?? 'Shared by %s — %s', $byName, $accessLbl);
        } else {
            $title = sprintf($lang['Shared with you — %s'] ?? 'Shared with you — %s', $accessLbl);
        }
    } elseif ($at === 'shared_by_me') {
        $show_badge = true;
        $badge_class .= ' shared-by-me';
        $permRaw = $file->shared_permissions ?? ($sharerMeta['permission_for_sharer'] ?? 'read');
        $accessLbl = $mv_share_badge_access_label($permRaw, $lang);
        $title = sprintf($lang['You shared this file — %s'] ?? 'You shared this file — %s', $accessLbl);
    } elseif ($mv_badge_viewer > 0 && isset($file->uploaded_by) && (int)$file->uploaded_by === $mv_badge_viewer && !empty($file->mv_has_extended_share)) {
        $show_badge = true;
        $badge_class .= ' shared-by-me';
        $title = $lang['Shared via project, profile, or link'] ?? 'Shared via project, profile, or link';
    } elseif ($mv_badge_viewer > 0 && isset($file->uploaded_by) && (int)$file->uploaded_by === $mv_badge_viewer && !empty($file->mv_has_vault_team_share)) {
        $show_badge = true;
        $badge_class .= ' shared-by-me';
        $permRaw = $sharerMeta['permission_for_sharer'] ?? ($file->shared_permissions ?? 'read');
        $accessLbl = $mv_share_badge_access_label($permRaw, $lang);
        $title = sprintf($lang['You shared this file — %s'] ?? 'You shared this file — %s', $accessLbl);
    } elseif ($has_direct_share) {
        $sharedByIds = isset($sharerMeta['shared_by_ids']) ? $sharerMeta['shared_by_ids'] : array();
        $viewerIsSharer = $mv_badge_viewer > 0 && in_array($mv_badge_viewer, $sharedByIds, true);
        $viewerIsRecipient = false;
        if (!empty($recipientsMap[$fid])) {
            foreach ($recipientsMap[$fid] as $rec) {
                if ((int)($rec['user_id'] ?? 0) === $mv_badge_viewer) {
                    $viewerIsRecipient = true;
                    break;
                }
            }
        }
        if ($viewerIsSharer) {
            $show_badge = true;
            $badge_class .= ' shared-by-me';
            $permRaw = $file->shared_permissions ?? ($sharerMeta['permission_for_sharer'] ?? 'read');
            $accessLbl = $mv_share_badge_access_label($permRaw, $lang);
            $title = sprintf($lang['You shared this file — %s'] ?? 'You shared this file — %s', $accessLbl);
        } elseif ($viewerIsRecipient) {
            $show_badge = true;
            $badge_class .= ' shared-with-me';
            $permRaw = $sharerMeta['permission_for_viewer'] ?? ($file->shared_permissions ?? 'read');
            $accessLbl = $mv_share_badge_access_label($permRaw, $lang);
            $byName = isset($sharerMeta['single_sharer_display']) ? trim((string) $sharerMeta['single_sharer_display']) : '';
            if ($byName !== '') {
                $title = sprintf($lang['Shared by %s — %s'] ?? 'Shared by %s — %s', $byName, $accessLbl);
            } else {
                $title = sprintf($lang['Shared with you — %s'] ?? 'Shared with you — %s', $accessLbl);
            }
        } elseif (!empty($sharedByIds)) {
            $show_badge = true;
            $badge_class .= ' shared-with-me';
            $title = $lang['This file has been shared'] ?? 'This file has been shared';
        }
    }
}

// Fallback for profile-shared permissions from profile media actions.
if (!$show_badge) {
    $permHint = strtolower(trim((string)($file->shared_permissions ?? '')));
    if ($permHint === 'view_profile' || $permHint === 'read_profile' || $permHint === 'write_profile' || $permHint === 'admin_profile') {
        $show_badge = true;
        $badge_class .= ' shared-with-me';
        $accessLabel = 'read';
        if ($permHint === 'view_profile') {
            $accessLabel = 'view';
        } elseif ($permHint === 'write_profile') {
            $accessLabel = 'write';
        } elseif ($permHint === 'admin_profile') {
            $accessLabel = 'admin';
        }
        $title = sprintf(
            $lang['Shared with you — %s'] ?? 'Shared with you — %s',
            $mv_share_badge_access_label($accessLabel, $lang)
        );
    }
}

if (!$show_badge) {
    return;
}
?>
                                                            <?php $badge_tt = htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>
                                                            <div class="<?php echo htmlspecialchars($badge_class, ENT_QUOTES, 'UTF-8'); ?>"
                                                                 data-bs-toggle="tooltip"
                                                                 data-bs-placement="top"
                                                                 title="<?php echo $badge_tt; ?>"
                                                                 aria-label="<?php echo $badge_tt; ?>"
                                                                 role="img">
                                                                <?php echo ts_icon('share'); ?>
                                                            </div>
