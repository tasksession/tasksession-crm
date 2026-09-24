<?php
/**
 * Shared helpers for admin media table partials (include_once from vault / project table).
 */
if (!function_exists('media_table_render_owner_cell')) {
    /**
     * Owner column: projects-style user-box (+ discussion chat when project id set) + visible name.
     *
     * @param int         $userId               Owner user id (0 if unknown)
     * @param string      $displayName          Shown next to avatar (falls back to user record name)
     * @param int         $discussionProjectId  If > 0, avatar submits chat form to discussion.php for this project
     */
    function media_table_render_owner_cell($userId, $displayName, $discussionProjectId = 0) {
        $userId = (int)$userId;
        $discussionProjectId = (int)$discussionProjectId;
        $displayName = trim((string)$displayName);

        echo '<div class="media-table-owner-inline d-flex align-items-center" style="text-align: left;">';

        if ($userId <= 0) {
            $txt = $displayName !== '' ? htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') : '—';
            echo '<div style="text-align: left; min-width: 0;"><div class="text-muted small">' . $txt . '</div></div>';
            echo '</div>';
            return;
        }

        $u = User::findById($userId);
        $fn = $u ? (string)$u->firstName : '';
        $ln = $u ? (string)($u->lastName ?? '') : '';
        $fallbackName = trim($fn . ' ' . $ln);
        $label = $displayName !== '' ? $displayName : ($fallbackName !== '' ? $fallbackName : 'User');
        $labelEsc = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
        $tooltipTitle = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');

        echo '<div class="user-box flex-shrink-0">';
        if ($discussionProjectId > 0) {
            echo '<form action="../discussion?project_id=' . $discussionProjectId . '" method="post">';
            echo '<input type="hidden" name="user_id" value="' . $userId . '">';
            echo '<input type="hidden" name="project_id" value="' . $discussionProjectId . '">';
            echo '<button name="chat" type="submit" class="border-0 bg-transparent p-0 lh-1" data-bs-toggle="tooltip" data-bs-placement="top" aria-label="' . $tooltipTitle . '" data-bs-original-title="' . $tooltipTitle . '">';
            echo getUserAvatarHtml($userId, $fn, $ln, 36, 36, 'img-fluid rounded-circle', $label);
            echo '</button></form>';
        } else {
            echo '<span class="d-inline-block lh-1" data-bs-toggle="tooltip" data-bs-placement="top" title="' . $tooltipTitle . '">';
            echo getUserAvatarHtml($userId, $fn, $ln, 36, 36, 'img-fluid rounded-circle', $label);
            echo '</span>';
        }
        echo '</div>';

        echo '<div style="text-align: left; min-width: 0;"><div>' . $labelEsc . '</div></div>';
        echo '</div>';
    }
}

if (!function_exists('media_table_render_folder_shared_avatars_cell')) {
    /**
     * Stack of recipient avatars for a shared folder (media vault table).
     * Matches projects-style user-box row; optional chat shortcut when $discussionProjectId > 0.
     *
     * @param array<int, array{user_id:int, firstName:string, lastName:string}> $recipients
     * @param int                                                                   $discussionProjectId 0 = avatar only
     */
    function media_table_render_folder_shared_avatars_cell(array $recipients, $discussionProjectId = 0) {
        $discussionProjectId = (int)$discussionProjectId;
        $valid = array();
        foreach ($recipients as $r) {
            $uid = isset($r['user_id']) ? (int)$r['user_id'] : 0;
            if ($uid <= 0) {
                continue;
            }
            $valid[] = $r;
        }
        if (empty($valid)) {
            echo '<span class="text-muted small">—</span>';
            return;
        }
        $total = count($valid);
        $show = min(3, $total);
        echo '<div class="d-flex align-items-center">';
        for ($i = 0; $i < $show; $i++) {
            $r = $valid[$i];
            $uid = (int)$r['user_id'];
            $fn = isset($r['firstName']) ? (string)$r['firstName'] : '';
            $ln = isset($r['lastName']) ? (string)$r['lastName'] : '';
            $label = trim($fn . ' ' . $ln);
            if ($label === '') {
                $label = 'User';
            }
            echo '<div class="user-box flex-shrink-0">';
            if ($discussionProjectId > 0) {
                echo '<form action="../discussion?project_id=' . $discussionProjectId . '" method="post">';
                echo '<input type="hidden" name="user_id" value="' . $uid . '">';
                echo '<input type="hidden" name="project_id" value="' . $discussionProjectId . '">';
                echo '<button name="chat" type="submit" class="border-0 bg-transparent p-0 lh-1" data-bs-toggle="tooltip" data-bs-placement="top" aria-label="' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '" data-bs-original-title="' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '">';
                echo getUserAvatarHtml($uid, $fn, $ln, 36, 36, 'img-fluid rounded-circle', $label);
                echo '</button></form>';
            } else {
                echo '<span class="d-inline-block lh-1" data-bs-toggle="tooltip" data-bs-placement="top" title="' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '">';
                echo getUserAvatarHtml($uid, $fn, $ln, 36, 36, 'img-fluid rounded-circle', $label);
                echo '</span>';
            }
            echo '</div>';
        }
        if ($total > 3) {
            echo '<div class="plus-more">+' . ($total - 3) . '</div>';
        }
        echo '</div>';
    }
}
