<?php
if (!function_exists('has_permission')) {
    $basePermissionsFile = dirname(__DIR__) . '/permissions.php';
    if (is_file($basePermissionsFile)) {
        require_once($basePermissionsFile);
    }
}

function privateNotesGetAccessContext($database, $noteId, $userId) {
    $noteId = (int)$noteId;
    $userId = (int)$userId;
    if ($noteId <= 0 || $userId <= 0) {
        return ['can_view' => false, 'can_edit' => false, 'is_owner' => false, 'permission' => 'none', 'owner_user_id' => 0];
    }

    $noteSql = "SELECT id, user_id FROM private_notes WHERE id = {$noteId} LIMIT 1";
    $noteRes = $database->querySoft($noteSql);
    $note = $noteRes ? $database->fetchArray($noteRes) : null;
    if (!$note || empty($note['id'])) {
        return ['can_view' => false, 'can_edit' => false, 'is_owner' => false, 'permission' => 'none', 'owner_user_id' => 0];
    }

    $ownerUserId = (int)$note['user_id'];
    if ($ownerUserId === $userId) {
        return ['can_view' => true, 'can_edit' => true, 'is_owner' => true, 'permission' => 'owner', 'owner_user_id' => $ownerUserId];
    }

    $shareSql = "SELECT permission FROM private_note_shares WHERE note_id = {$noteId} AND shared_with_user_id = {$userId} LIMIT 1";
    $shareRes = $database->querySoft($shareSql);
    $share = $shareRes ? $database->fetchArray($shareRes) : null;
    $permission = isset($share['permission']) ? (string)$share['permission'] : 'none';
    if ($permission !== 'edit' && $permission !== 'view') {
        return ['can_view' => false, 'can_edit' => false, 'is_owner' => false, 'permission' => 'none', 'owner_user_id' => $ownerUserId];
    }

    return [
        'can_view' => true,
        'can_edit' => ($permission === 'edit'),
        'is_owner' => false,
        'permission' => $permission,
        'owner_user_id' => $ownerUserId
    ];
}

function privateNotesUserCanView($database, $noteId, $userId) {
    $ctx = privateNotesGetAccessContext($database, $noteId, $userId);
    return !empty($ctx['can_view']);
}

function privateNotesUserCanEdit($database, $noteId, $userId) {
    $ctx = privateNotesGetAccessContext($database, $noteId, $userId);
    return !empty($ctx['can_edit']);
}

function privateNotesResolveShareTargets($database, $search = '', $excludeUserId = 0, $limit = 30) {
    global $url;
    $excludeUserId = (int)$excludeUserId;
    $limit = max(1, min(100, (int)$limit));
    $safeSearch = trim((string)$search);
    $safeSearchLike = $database->escapeValue($safeSearch);

    $allowedIds = privateNotesResolveShareableUserIds($database, $excludeUserId);
    if (empty($allowedIds)) {
        return [];
    }
    $inIds = implode(',', array_map('intval', $allowedIds));
    $where = "u.id <> {$excludeUserId} AND u.status = 0 AND u.id IN ({$inIds})";
    if ($safeSearch !== '') {
        $where .= " AND (u.firstName LIKE '%{$safeSearchLike}%' OR u.email LIKE '%{$safeSearchLike}%')";
    }

    $sql = "SELECT u.id, u.firstName, u.email, u.accountStatus,
            (SELECT pp.filename FROM profile_pics pp WHERE pp.fkUserId = u.id LIMIT 1) AS profile_filename
            FROM users u
            WHERE {$where}
            ORDER BY u.firstName ASC
            LIMIT {$limit}";
    $res = $database->query($sql);
    $rows = [];
    $defaultAvatar = rtrim((string)($url ?? ''), '/') . '/assets/images/upload-img.jpg';
    while ($res && ($row = $database->fetchArray($res))) {
        $fn = isset($row['profile_filename']) ? (string)$row['profile_filename'] : '';
        $avatarUrl = ($fn !== '' && function_exists('getProfilePicUrl'))
            ? getProfilePicUrl($fn, 32, 32)
            : $defaultAvatar;
        $rows[] = [
            'id' => (int)$row['id'],
            'name' => (string)($row['firstName'] ?? ''),
            'email' => (string)($row['email'] ?? ''),
            'account_status' => (string)($row['accountStatus'] ?? ''),
            'avatar_url' => $avatarUrl
        ];
    }
    return $rows;
}

/**
 * When the profile subject is not in the first page of share targets (name sort + limit),
 * fetch that user alone if they are allowed to receive a share.
 */
function privateNotesResolveOneShareTargetRow($database, $targetUserId, $excludeUserId, $search = '') {
    global $url;
    $targetUserId = (int) $targetUserId;
    $excludeUserId = (int) $excludeUserId;
    if ($targetUserId <= 0 || $targetUserId === $excludeUserId) {
        return null;
    }
    $allowedIds = privateNotesResolveShareableUserIds($database, $excludeUserId);
    if (empty($allowedIds) || !in_array($targetUserId, $allowedIds, true)) {
        return null;
    }
    $safeSearch = trim((string) $search);
    $safeSearchLike = $database->escapeValue($safeSearch);
    $searchClause = '';
    if ($safeSearch !== '') {
        $searchClause = " AND (u.firstName LIKE '%{$safeSearchLike}%' OR u.email LIKE '%{$safeSearchLike}%')";
    }
    $sql = "SELECT u.id, u.firstName, u.email, u.accountStatus,
            (SELECT pp.filename FROM profile_pics pp WHERE pp.fkUserId = u.id LIMIT 1) AS profile_filename
            FROM users u
            WHERE u.id = {$targetUserId} AND u.status = 0{$searchClause}
            LIMIT 1";
    $res = $database->query($sql);
    if (!$res || !($row = $database->fetchArray($res))) {
        return null;
    }
    $defaultAvatar = rtrim((string) ($url ?? ''), '/') . '/assets/images/upload-img.jpg';
    $fn = isset($row['profile_filename']) ? (string) $row['profile_filename'] : '';
    $avatarUrl = ($fn !== '' && function_exists('getProfilePicUrl'))
        ? getProfilePicUrl($fn, 32, 32)
        : $defaultAvatar;
    return [
        'id' => (int) $row['id'],
        'name' => (string) ($row['firstName'] ?? ''),
        'email' => (string) ($row['email'] ?? ''),
        'account_status' => (string) ($row['accountStatus'] ?? ''),
        'avatar_url' => $avatarUrl
    ];
}

function privateNotesResolveShareableUserIds($database, $ownerUserId) {
    global $connect;
    $ownerUserId = (int)$ownerUserId;
    if ($ownerUserId <= 0) {
        return [];
    }

    $ownerSql = "SELECT id, accountStatus, assigned_team FROM users WHERE id = {$ownerUserId} LIMIT 1";
    $ownerRes = $database->querySoft($ownerSql);
    $owner = $ownerRes ? $database->fetchArray($ownerRes) : null;
    if (!$owner || empty($owner['id'])) {
        return [];
    }

    $ownerStatus = (int)($owner['accountStatus'] ?? 0);
    $ids = [];

    if ($ownerStatus === 1) {
        $res = $database->querySoft("SELECT id FROM users WHERE id <> {$ownerUserId} AND status = 0");
        while ($res && ($row = $database->fetchArray($res))) {
            $ids[] = (int)$row['id'];
        }
        return array_values(array_unique(array_filter($ids)));
    }

    if ($ownerStatus === 3) {
        if (function_exists('ensure_user_permissions') && isset($connect)) {
            ensure_user_permissions($connect);
        }
        $hasClientView = function_exists('has_permission') ? has_permission('client_view') : true;
        $hasStaffView = function_exists('has_permission') ? has_permission('staff_view') : true;

        // Admins are always visible to staff.
        $adminRes = $database->querySoft("SELECT id FROM users WHERE accountStatus = 1 AND status = 0 AND id <> {$ownerUserId}");
        while ($adminRes && ($row = $database->fetchArray($adminRes))) {
            $ids[] = (int)$row['id'];
        }

        if ($hasStaffView) {
            $staffRes = $database->querySoft("SELECT id FROM users WHERE accountStatus = 3 AND status = 0 AND id <> {$ownerUserId}");
            while ($staffRes && ($row = $database->fetchArray($staffRes))) {
                $ids[] = (int)$row['id'];
            }
        }

        if ($hasClientView) {
            $clientRes = $database->querySoft("SELECT id FROM users WHERE accountStatus = 2 AND status = 0 AND id <> {$ownerUserId}");
            while ($clientRes && ($row = $database->fetchArray($clientRes))) {
                $ids[] = (int)$row['id'];
            }
        } else {
            $assignedClientRes = $database->querySoft("SELECT id FROM users WHERE accountStatus = 2 AND status = 0 AND FIND_IN_SET({$ownerUserId}, assigned_team) > 0");
            while ($assignedClientRes && ($row = $database->fetchArray($assignedClientRes))) {
                $ids[] = (int)$row['id'];
            }
        }
        return array_values(array_unique(array_filter($ids)));
    }

    if ($ownerStatus === 2) {
        $assignedIds = [];
        $assignedTeamCsv = (string)($owner['assigned_team'] ?? '');
        foreach (explode(',', $assignedTeamCsv) as $rawId) {
            $idVal = (int)trim($rawId);
            if ($idVal > 0 && $idVal !== $ownerUserId) {
                $assignedIds[] = $idVal;
            }
        }
        $ids = $assignedIds;

        // Include users linked through client's related projects
        // (main client, additional clients and assigned staff).
        $projectSql = "SELECT p_id, c_id, main_client_id, c_ids, s_ids
                       FROM projects
                       WHERE (c_id = {$ownerUserId}
                              OR main_client_id = {$ownerUserId}
                              OR FIND_IN_SET({$ownerUserId}, c_ids) > 0)";
        $projectRes = $database->query($projectSql);
        while ($projectRes && ($projectRow = $database->fetchArray($projectRes))) {
            $projectMainClientId = (int)($projectRow['c_id'] ?? 0);
            $projectAltMainClientId = (int)($projectRow['main_client_id'] ?? 0);
            if ($projectMainClientId > 0 && $projectMainClientId !== $ownerUserId) {
                $ids[] = $projectMainClientId;
            }
            if ($projectAltMainClientId > 0 && $projectAltMainClientId !== $ownerUserId) {
                $ids[] = $projectAltMainClientId;
            }

            foreach (explode(',', (string)($projectRow['c_ids'] ?? '')) as $rawCid) {
                $cid = (int)trim($rawCid);
                if ($cid > 0 && $cid !== $ownerUserId) {
                    $ids[] = $cid;
                }
            }
            foreach (explode(',', (string)($projectRow['s_ids'] ?? '')) as $rawSid) {
                $sid = (int)trim($rawSid);
                if ($sid > 0 && $sid !== $ownerUserId) {
                    $ids[] = $sid;
                }
            }
        }

        // Match chatting visibility: show admin/staff with direct message history.
        $msgSql = "SELECT DISTINCT
                    CASE WHEN m.user_id = {$ownerUserId} THEN m.receiver ELSE m.user_id END AS other_id
                   FROM messages m
                   LEFT JOIN users u2 ON u2.id = (CASE WHEN m.user_id = {$ownerUserId} THEN m.receiver ELSE m.user_id END)
                   WHERE (m.user_id = {$ownerUserId} OR m.receiver = {$ownerUserId})
                     AND m.Project_id = 0
                     AND u2.accountStatus IN (1, 3)
                     AND u2.status = 0";
        $msgRes = $database->querySoft($msgSql);
        while ($msgRes && ($row = $database->fetchArray($msgRes))) {
            $otherId = (int)($row['other_id'] ?? 0);
            if ($otherId > 0 && $otherId !== $ownerUserId) {
                $ids[] = $otherId;
            }
        }
        return array_values(array_unique(array_filter($ids)));
    }

    return [];
}

function privateNotesCanShareWithUser($database, $ownerUserId, $targetUserId) {
    $targetUserId = (int)$targetUserId;
    if ($targetUserId <= 0) {
        return false;
    }
    $allowed = privateNotesResolveShareableUserIds($database, (int)$ownerUserId);
    return in_array($targetUserId, $allowed, true);
}

function privateNotesGeneratePublicToken($bytes = 24) {
    $bytes = max(16, (int)$bytes);
    $raw = random_bytes($bytes);
    return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
}

function privateNotesBuildPublicUrl($token) {
    global $url;
    $base = rtrim((string)($url ?? ''), '/');
    $token = trim((string)$token);
    if ($token === '') {
        return '';
    }
    return $base . '/share/doc.php?' . rawurlencode($token);
}

function privateNotesGetPublicAccessByToken($database, $token) {
    $tokenEsc = $database->escapeValue(trim((string)$token));
    if ($tokenEsc === '') {
        return null;
    }
    $sql = "SELECT id, user_id, title, content, updated_at, public_share_permission, public_share_enabled
            FROM private_notes
            WHERE public_share_token = '{$tokenEsc}'
            LIMIT 1";
    $res = $database->querySoft($sql);
    $row = $res ? $database->fetchArray($res) : null;
    if (!$row || empty($row['id']) || (int)($row['public_share_enabled'] ?? 0) !== 1) {
        return null;
    }
    return $row;
}
  