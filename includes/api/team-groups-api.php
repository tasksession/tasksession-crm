<?php
ob_start();
require_once(__DIR__ . '/../lib-initialize.php');
require_once(__DIR__ . '/../permissions.php');
header('Content-Type: application/json; charset=utf-8');

$conn = $database->connection;
ensure_user_permissions($conn);

function tg_table_exists(mysqli $conn) {
    $r = mysqli_query($conn, "SHOW TABLES LIKE 'staff_team_groups'");
    return $r && mysqli_num_rows($r) > 0;
}

function tg_deleted_at_exists(mysqli $conn) {
    static $v = null;
    if ($v !== null) {
        return $v;
    }
    $r = mysqli_query($conn, "SHOW COLUMNS FROM staff_team_groups LIKE 'deleted_at'");
    $v = $r && mysqli_num_rows($r) > 0;
    return $v;
}

function tg_err($code, $msg) {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

function tg_user_can($action) {
    $accountStatus = isset($_SESSION['accountStatus']) ? (int) $_SESSION['accountStatus'] : 0;
    if ($accountStatus === 1) {
        return true;
    }
    if ($accountStatus !== 3) {
        return false;
    }

    if (in_array($action, ['list', 'get'], true)) {
        return function_exists('has_permission') && has_permission('staff_view');
    }
    if (in_array($action, ['create', 'update', 'clone'], true)) {
        return function_exists('has_permission') && has_permission('staff_create');
    }
    if (in_array($action, ['delete', 'restore', 'purge'], true)) {
        return function_exists('has_permission') && has_permission('staff_profile_delete');
    }
    return false;
}

function tg_require_csrf(array $body) {
    $token = '';
    if (!empty($_SERVER['HTTP_X_CSRF_TOKEN'])) {
        $token = (string) $_SERVER['HTTP_X_CSRF_TOKEN'];
    } elseif (isset($body['csrf_token'])) {
        $token = (string) $body['csrf_token'];
    }

    if ($token === '' || !function_exists('validate_csrf_token') || !validate_csrf_token($token)) {
        tg_err(403, 'Invalid CSRF token');
    }
}

if (!tg_table_exists($conn)) {
    tg_err(503, 'Team groups are not available on this installation.');
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$raw = file_get_contents('php://input');
$body = [];
if ($raw !== '' && $raw !== false) {
    $decoded = json_decode($raw, true);
    $body = is_array($decoded) ? $decoded : [];
}

$action = $method === 'GET' ? (string) ($_GET['action'] ?? '') : (string) ($body['action'] ?? '');
if (!$session->isLoggedIn() || !tg_user_can($action)) {
    tg_err(403, 'Forbidden');
}
if ($method === 'POST' && !in_array($action, ['list', 'get'], true)) {
    tg_require_csrf($body);
}

function tg_validate_member_ids(mysqli $conn, array $ids) {
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    if (empty($ids)) {
        return [];
    }
    $out = [];
    foreach ($ids as $id) {
        if ($id <= 0) {
            continue;
        }
        $stmt = mysqli_prepare($conn, 'SELECT id FROM users WHERE id = ? AND status = 0 AND (accountStatus = 1 OR accountStatus = 3) LIMIT 1');
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($res && ($row = mysqli_fetch_assoc($res))) {
            $out[] = (int) $row['id'];
        }
        mysqli_stmt_close($stmt);
    }
    sort($out);
    return array_values(array_unique($out));
}

if ($action === 'list') {
    $groups = [];
    $activeSql = tg_deleted_at_exists($conn) ? ' WHERE g.deleted_at IS NULL ' : '';
    $q = mysqli_query(
        $conn,
        "SELECT g.id, g.name, g.created_at,
        (SELECT COUNT(*) FROM staff_team_group_members m WHERE m.group_id = g.id) AS member_count
        FROM staff_team_groups g
        $activeSql
        ORDER BY g.name ASC"
    );
    if ($q) {
        while ($row = mysqli_fetch_assoc($q)) {
            $gid = (int) $row['id'];
            $members = [];
            $mq = mysqli_prepare(
                $conn,
                'SELECT u.id, u.firstName, u.email FROM staff_team_group_members m
                INNER JOIN users u ON u.id = m.user_id WHERE m.group_id = ? ORDER BY u.firstName ASC LIMIT 8'
            );
            if ($mq) {
                mysqli_stmt_bind_param($mq, 'i', $gid);
                mysqli_stmt_execute($mq);
                $mr = mysqli_stmt_get_result($mq);
                while ($mr && ($u = mysqli_fetch_assoc($mr))) {
                    $uid = (int) $u['id'];
                    $fn = trim((string) ($u['firstName'] ?? ''));
                    $members[] = [
                        'id' => $uid,
                        'label' => $fn !== '' ? $fn : ('User #' . $uid),
                        'email' => (string) ($u['email'] ?? ''),
                    ];
                }
                mysqli_stmt_close($mq);
            }
            $groups[] = [
                'id' => $gid,
                'name' => (string) $row['name'],
                'member_count' => (int) $row['member_count'],
                'created_at' => isset($row['created_at']) ? (string) $row['created_at'] : '',
                'members_preview' => $members,
            ];
        }
    }
    echo json_encode(['ok' => true, 'groups' => $groups]);
    exit;
}

if ($action === 'get') {
    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    if ($id <= 0) {
        tg_err(400, 'Invalid id');
    }
    $sqlGet = 'SELECT id, name FROM staff_team_groups WHERE id = ?';
    if (tg_deleted_at_exists($conn)) {
        $sqlGet .= ' AND deleted_at IS NULL';
    }
    $sqlGet .= ' LIMIT 1';
    $stmt = mysqli_prepare($conn, $sqlGet);
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $g = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);
    if (!$g) {
        tg_err(404, 'Group not found');
    }
    $uids = [];
    $ms = mysqli_prepare($conn, 'SELECT user_id FROM staff_team_group_members WHERE group_id = ? ORDER BY user_id ASC');
    mysqli_stmt_bind_param($ms, 'i', $id);
    mysqli_stmt_execute($ms);
    $mres = mysqli_stmt_get_result($ms);
    while ($mres && ($r = mysqli_fetch_assoc($mres))) {
        $uids[] = (int) $r['user_id'];
    }
    mysqli_stmt_close($ms);
    echo json_encode([
        'ok' => true,
        'group' => [
            'id' => (int) $g['id'],
            'name' => (string) $g['name'],
            'user_ids' => $uids,
        ],
    ]);
    exit;
}

if ($method !== 'POST') {
    tg_err(405, 'Method not allowed');
}

if ($action === 'create') {
    $name = isset($body['name']) ? trim((string) $body['name']) : '';
    $userIds = isset($body['user_ids']) && is_array($body['user_ids']) ? $body['user_ids'] : [];
    if ($name === '') {
        tg_err(400, 'Name is required');
    }
    $validIds = tg_validate_member_ids($conn, $userIds);
    $uid = (int) $session->userId;
    $stmt = mysqli_prepare($conn, 'INSERT INTO staff_team_groups (name, created_by) VALUES (?, ?)');
    mysqli_stmt_bind_param($stmt, 'si', $name, $uid);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        tg_err(500, 'Could not create group');
    }
    $newId = (int) mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
    $ins = mysqli_prepare($conn, 'INSERT IGNORE INTO staff_team_group_members (group_id, user_id) VALUES (?, ?)');
    foreach ($validIds as $mid) {
        mysqli_stmt_bind_param($ins, 'ii', $newId, $mid);
        mysqli_stmt_execute($ins);
    }
    mysqli_stmt_close($ins);
    echo json_encode(['ok' => true, 'id' => $newId]);
    exit;
}

if ($action === 'update') {
    $id = isset($body['id']) ? (int) $body['id'] : 0;
    $name = isset($body['name']) ? trim((string) $body['name']) : '';
    $userIds = isset($body['user_ids']) && is_array($body['user_ids']) ? $body['user_ids'] : [];
    if ($id <= 0 || $name === '') {
        tg_err(400, 'Invalid id or name');
    }
    $validIds = tg_validate_member_ids($conn, $userIds);
    $sqlUp = 'UPDATE staff_team_groups SET name = ? WHERE id = ?';
    if (tg_deleted_at_exists($conn)) {
        $sqlUp .= ' AND deleted_at IS NULL';
    }
    $sqlUp .= ' LIMIT 1';
    $stmt = mysqli_prepare($conn, $sqlUp);
    mysqli_stmt_bind_param($stmt, 'si', $name, $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    $del = mysqli_prepare($conn, 'DELETE FROM staff_team_group_members WHERE group_id = ?');
    mysqli_stmt_bind_param($del, 'i', $id);
    mysqli_stmt_execute($del);
    mysqli_stmt_close($del);
    $ins = mysqli_prepare($conn, 'INSERT IGNORE INTO staff_team_group_members (group_id, user_id) VALUES (?, ?)');
    foreach ($validIds as $mid) {
        mysqli_stmt_bind_param($ins, 'ii', $id, $mid);
        mysqli_stmt_execute($ins);
    }
    mysqli_stmt_close($ins);
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'delete') {
    $id = isset($body['id']) ? (int) $body['id'] : 0;
    if ($id <= 0) {
        tg_err(400, 'Invalid id');
    }
    if (tg_deleted_at_exists($conn)) {
        $stmt = mysqli_prepare($conn, 'UPDATE staff_team_groups SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL LIMIT 1');
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $aff = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);
        if ($aff < 1) {
            tg_err(404, 'Group not found or already in trash');
        }
    } else {
        $stmt = mysqli_prepare($conn, 'DELETE FROM staff_team_groups WHERE id = ? LIMIT 1');
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'restore') {
    if (!tg_deleted_at_exists($conn)) {
        tg_err(503, 'Team group trash is not available on this installation.');
    }
    $id = isset($body['id']) ? (int) $body['id'] : 0;
    if ($id <= 0) {
        tg_err(400, 'Invalid id');
    }
    $stmt = mysqli_prepare($conn, 'UPDATE staff_team_groups SET deleted_at = NULL WHERE id = ? AND deleted_at IS NOT NULL LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $aff = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    if ($aff < 1) {
        tg_err(404, 'Group not found or not in trash');
    }
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'purge') {
    if (!tg_deleted_at_exists($conn)) {
        tg_err(503, 'Team group trash is not available on this installation.');
    }
    $id = isset($body['id']) ? (int) $body['id'] : 0;
    if ($id <= 0) {
        tg_err(400, 'Invalid id');
    }
    $stmt = mysqli_prepare($conn, 'DELETE FROM staff_team_groups WHERE id = ? AND deleted_at IS NOT NULL LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $aff = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    if ($aff < 1) {
        tg_err(400, 'Group is not in trash or already removed');
    }
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'clone') {
    $id = isset($body['id']) ? (int) $body['id'] : 0;
    if ($id <= 0) {
        tg_err(400, 'Invalid id');
    }
    $sqlClone = 'SELECT id, name FROM staff_team_groups WHERE id = ?';
    if (tg_deleted_at_exists($conn)) {
        $sqlClone .= ' AND deleted_at IS NULL';
    }
    $sqlClone .= ' LIMIT 1';
    $stmt = mysqli_prepare($conn, $sqlClone);
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $src = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    if (!$src) {
        tg_err(404, 'Group not found');
    }
    $suffix = ' (copy)';
    $base = trim((string) ($src['name'] ?? ''));
    if ($base === '') {
        $base = 'Group #' . $id;
    }
    $maxLen = 191;
    $maxBase = $maxLen - strlen($suffix);
    if ($maxBase < 1) {
        $newName = substr($base, 0, $maxLen);
    } elseif (strlen($base) + strlen($suffix) <= $maxLen) {
        $newName = $base . $suffix;
    } else {
        $newName = substr($base, 0, $maxBase) . $suffix;
    }
    $uid = (int) $session->userId;
    $stmt2 = mysqli_prepare($conn, 'INSERT INTO staff_team_groups (name, created_by) VALUES (?, ?)');
    mysqli_stmt_bind_param($stmt2, 'si', $newName, $uid);
    if (!mysqli_stmt_execute($stmt2)) {
        mysqli_stmt_close($stmt2);
        tg_err(500, 'Could not clone group');
    }
    $newId = (int) mysqli_insert_id($conn);
    mysqli_stmt_close($stmt2);
    $ms = mysqli_prepare($conn, 'SELECT user_id FROM staff_team_group_members WHERE group_id = ?');
    mysqli_stmt_bind_param($ms, 'i', $id);
    mysqli_stmt_execute($ms);
    $mres = mysqli_stmt_get_result($ms);
    $ins = mysqli_prepare($conn, 'INSERT IGNORE INTO staff_team_group_members (group_id, user_id) VALUES (?, ?)');
    while ($mres && ($row = mysqli_fetch_assoc($mres))) {
        $mid = (int) $row['user_id'];
        mysqli_stmt_bind_param($ins, 'ii', $newId, $mid);
        mysqli_stmt_execute($ins);
    }
    mysqli_stmt_close($ms);
    mysqli_stmt_close($ins);
    echo json_encode(['ok' => true, 'id' => $newId]);
    exit;
}

tg_err(400, 'Unknown action');
