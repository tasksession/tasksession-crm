<?php
/**
 * Scoped online presence list for admin/staff presence toasts.
 * Production-safe: never die() on SQL; always return JSON.
 */
ob_start();
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', '0');

if (!defined('CRM_LIGHTWEIGHT_INIT')) {
    define('CRM_LIGHTWEIGHT_INIT', true);
}

function online_presence_json_exit($payload, $httpCode = 200) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code((int) $httpCode);
        header('Content-Type: application/json; charset=UTF-8');
    }
    $flags = JSON_UNESCAPED_UNICODE;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }
    $json = json_encode($payload, $flags);
    if ($json === false) {
        $json = '{"success":false,"users":[],"error":"encode_failed"}';
    }
    echo $json;
    exit;
}

register_shutdown_function(static function () {
    $err = error_get_last();
    if (!$err || !in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return;
    }
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code(200);
        header('Content-Type: application/json; charset=UTF-8');
    }
    echo '{"success":false,"users":[],"error":"fatal"}';
});

try {
    require_once dirname(__DIR__) . '/includes/lib-initialize.php';
    require_once dirname(__DIR__) . '/includes/permissions.php';
    if (!function_exists('user_presence_is_online')) {
        require_once dirname(__DIR__) . '/includes/user_presence.php';
    }
} catch (Throwable $e) {
    online_presence_json_exit(['success' => false, 'users' => [], 'error' => 'init_failed'], 500);
}

if (!isset($session) || !$session->isLoggedIn() || empty($_SESSION['userId'])) {
    online_presence_json_exit(['success' => false, 'users' => [], 'error' => 'Unauthorized'], 401);
}

$viewerId = (int) $session->userId;
$accountStatus = isset($_SESSION['accountStatus']) ? (int) $_SESSION['accountStatus'] : 0;

// Clients do not receive presence toasts.
if ($accountStatus === 2) {
    online_presence_json_exit(['success' => true, 'users' => []]);
}

if ($accountStatus !== 1 && $accountStatus !== 3) {
    online_presence_json_exit(['success' => false, 'users' => [], 'error' => 'Forbidden'], 403);
}

try {
    if (isset($connect) && $connect instanceof mysqli && function_exists('ensure_user_permissions')) {
        ensure_user_permissions($connect);
    }
} catch (Throwable $e) {
    // permissions optional for presence list scoping fallback
}

$allowedStatuses = [];
if ($accountStatus === 1) {
    $allowedStatuses = [1, 2, 3];
} else {
    // Staff: admins always; staff/clients by permission.
    $allowedStatuses = [1];
    if (function_exists('has_permission') && has_permission('staff_view')) {
        $allowedStatuses[] = 3;
    }
    if (function_exists('has_permission') && has_permission('client_view')) {
        $allowedStatuses[] = 2;
    }
}
$allowedStatuses = array_values(array_unique(array_map('intval', $allowedStatuses)));
if ($allowedStatuses === []) {
    online_presence_json_exit(['success' => true, 'users' => []]);
}

$idleSeconds = defined('USER_PRESENCE_IDLE_SECONDS') ? (int) USER_PRESENCE_IDLE_SECONDS : 300;
$cutoff = time() - $idleSeconds;
$statusList = implode(',', $allowedStatuses);

global $connect, $database, $db1, $url;
$mysqli = null;
if (isset($connect) && $connect instanceof mysqli) {
    $mysqli = $connect;
} elseif (isset($database) && is_object($database) && isset($database->connection) && $database->connection instanceof mysqli) {
    $mysqli = $database->connection;
}

if (!$mysqli instanceof mysqli) {
    online_presence_json_exit(['success' => false, 'users' => [], 'error' => 'Database unavailable'], 500);
}

$hasLastName = function_exists('crm_db_column_exists')
    ? crm_db_column_exists($mysqli, 'users', 'last_name')
    : false;
$hasLegacyLastName = function_exists('crm_db_column_exists')
    ? crm_db_column_exists($mysqli, 'users', 'lastName')
    : false;

if ($hasLastName) {
    $lastNameSelect = 'last_name';
    $lastNameSelectU = 'u.last_name';
} elseif ($hasLegacyLastName) {
    $lastNameSelect = 'lastName AS last_name';
    $lastNameSelectU = 'u.lastName AS last_name';
} else {
    $lastNameSelect = "'' AS last_name";
    $lastNameSelectU = "'' AS last_name";
}

$sql = "SELECT id, firstName, {$lastNameSelect}, accountStatus, session_status, last_seen
        FROM users
        WHERE id != {$viewerId}
          AND accountStatus IN ({$statusList})
          AND session_status = 'online'
          AND last_seen > 0
          AND last_seen >= {$cutoff}
        ORDER BY last_seen DESC
        LIMIT 40";

$result = @$mysqli->query($sql);
if (!$result) {
    // Soft fallback without last name / presence filters that might differ by schema
    error_log('[online_presence] SQL failed: ' . $mysqli->error . ' | ' . $sql);
    online_presence_json_exit([
        'success' => true,
        'users' => [],
        'error' => 'query_failed',
        'server_time' => time(),
    ]);
}

$users = [];
while ($row = $result->fetch_assoc()) {
    $uid = (int) ($row['id'] ?? 0);
    if ($uid <= 0) {
        continue;
    }
    if (function_exists('user_presence_is_online')
        && !user_presence_is_online($row['session_status'] ?? '', $row['last_seen'] ?? 0)) {
        continue;
    }

    $firstName = trim((string) ($row['firstName'] ?? ''));
    $lastName = '';
    if (function_exists('crm_user_last_name')) {
        $lastName = crm_user_last_name($row);
    } else {
        $lastName = trim((string) ($row['last_name'] ?? $row['lastName'] ?? ''));
    }
    $fullName = trim($firstName . ($lastName !== '' ? ' ' . $lastName : ''));
    if ($fullName === '') {
        $fullName = 'User';
    }

    $avatarUrl = '';
    $initials = 'U';
    $colorIndex = ($uid % 8) + 1;
    try {
        if (function_exists('getUserAvatarData')) {
            $avatar = getUserAvatarData($uid, $firstName, $lastName, 40, 40);
            if (!empty($avatar['hasImage']) && !empty($avatar['url'])) {
                $avatarUrl = (string) $avatar['url'];
            }
            if (!empty($avatar['initials'])) {
                $initials = (string) $avatar['initials'];
            }
            if (!empty($avatar['colorIndex'])) {
                $colorIndex = (int) $avatar['colorIndex'];
            }
        }
    } catch (Throwable $e) {
        // keep initials fallback
    }

    $users[] = [
        'id' => $uid,
        'full_name' => $fullName,
        'avatar_url' => $avatarUrl,
        'initials' => $initials,
        'color_index' => $colorIndex,
        'account_status' => (int) ($row['accountStatus'] ?? 0),
    ];
}

/**
 * Clients who came online while this viewer was offline (today).
 * Uses login_attempts login/logout pairs for duration.
 */
$catchup = [];
$catchupKey = '';
$canSeeClients = in_array(2, $allowedStatuses, true);
$loginAttemptsExists = false;
$laCheck = @$mysqli->query("SHOW TABLES LIKE 'login_attempts'");
if ($laCheck && $laCheck->num_rows > 0) {
    $loginAttemptsExists = true;
}

if ($canSeeClients && $loginAttemptsExists) {
    $todayStart = strtotime('today');
    if ($todayStart <= 0) {
        $todayStart = strtotime(date('Y-m-d 00:00:00'));
    }

    $currentLoginTs = 0;
    $lastLogoutTs = 0;
    $qLogin = @$mysqli->query(
        "SELECT UNIX_TIMESTAMP(attempt_time) AS ts
         FROM login_attempts
         WHERE user_id = {$viewerId} AND type = 'login' AND success = 1
         ORDER BY attempt_time DESC
         LIMIT 1"
    );
    if ($qLogin && ($lr = $qLogin->fetch_assoc())) {
        $currentLoginTs = (int) ($lr['ts'] ?? 0);
    }
    $qLogout = @$mysqli->query(
        "SELECT UNIX_TIMESTAMP(attempt_time) AS ts
         FROM login_attempts
         WHERE user_id = {$viewerId} AND type = 'logout'
         ORDER BY attempt_time DESC
         LIMIT 1"
    );
    if ($qLogout && ($lo = $qLogout->fetch_assoc())) {
        $lastLogoutTs = (int) ($lo['ts'] ?? 0);
    }

    // Window: after last logout (if today / before this login), else start of today.
    $since = $todayStart;
    if ($lastLogoutTs >= $todayStart && ($currentLoginTs <= 0 || $lastLogoutTs < $currentLoginTs)) {
        $since = $lastLogoutTs;
    }

    $catchupKey = (string) $viewerId . ':' . ($currentLoginTs > 0 ? $currentLoginTs : $todayStart);

    $loginRowsSql = "SELECT la.user_id, UNIX_TIMESTAMP(la.attempt_time) AS login_ts,
                            u.firstName, {$lastNameSelectU}, u.accountStatus, u.session_status, u.last_seen
                     FROM login_attempts la
                     INNER JOIN users u ON u.id = la.user_id
                     WHERE la.type = 'login'
                       AND la.success = 1
                       AND u.accountStatus = 2
                       AND la.user_id != {$viewerId}
                       AND la.attempt_time >= FROM_UNIXTIME({$since})
                     ORDER BY la.attempt_time ASC
                     LIMIT 120";
    $loginRowsRes = @$mysqli->query($loginRowsSql);
    $byUser = [];
    if ($loginRowsRes) {
        $now = time();
        while ($lr = $loginRowsRes->fetch_assoc()) {
            $cid = (int) ($lr['user_id'] ?? 0);
            $loginTs = (int) ($lr['login_ts'] ?? 0);
            if ($cid <= 0 || $loginTs <= 0) {
                continue;
            }

            $logoutTs = 0;
            $qOut = @$mysqli->query(
                "SELECT UNIX_TIMESTAMP(attempt_time) AS ts
                 FROM login_attempts
                 WHERE user_id = {$cid} AND type = 'logout' AND attempt_time > FROM_UNIXTIME({$loginTs})
                 ORDER BY attempt_time ASC
                 LIMIT 1"
            );
            if ($qOut && ($or = $qOut->fetch_assoc())) {
                $logoutTs = (int) ($or['ts'] ?? 0);
            }

            $stillOnline = false;
            if ($logoutTs > $loginTs) {
                $dur = $logoutTs - $loginTs;
            } else {
                $stillOnline = function_exists('user_presence_is_online')
                    ? user_presence_is_online($lr['session_status'] ?? '', $lr['last_seen'] ?? 0, $now)
                    : (strtolower(trim((string) ($lr['session_status'] ?? ''))) === 'online');
                $end = $stillOnline
                    ? $now
                    : (max((int) ($lr['last_seen'] ?? 0), $loginTs));
                $dur = max(0, $end - $loginTs);
            }

            if (!isset($byUser[$cid])) {
                $firstName = trim((string) ($lr['firstName'] ?? ''));
                $lastName = '';
                if (function_exists('crm_user_last_name')) {
                    $lastName = crm_user_last_name($lr);
                } else {
                    $lastName = trim((string) ($lr['last_name'] ?? ''));
                }
                $fullName = trim($firstName . ($lastName !== '' ? ' ' . $lastName : ''));
                if ($fullName === '') {
                    $fullName = 'User';
                }
                $avatarUrl = '';
                $initials = 'U';
                $colorIndex = ($cid % 8) + 1;
                try {
                    if (function_exists('getUserAvatarData')) {
                        $avatar = getUserAvatarData($cid, $firstName, $lastName, 40, 40);
                        if (!empty($avatar['hasImage']) && !empty($avatar['url'])) {
                            $avatarUrl = (string) $avatar['url'];
                        }
                        if (!empty($avatar['initials'])) {
                            $initials = (string) $avatar['initials'];
                        }
                        if (!empty($avatar['colorIndex'])) {
                            $colorIndex = (int) $avatar['colorIndex'];
                        }
                    }
                } catch (Throwable $e) {
                    // fallback
                }
                $byUser[$cid] = [
                    'id' => $cid,
                    'full_name' => $fullName,
                    'avatar_url' => $avatarUrl,
                    'initials' => $initials,
                    'color_index' => $colorIndex,
                    'account_status' => 2,
                    'duration_seconds' => 0,
                    'still_online' => false,
                    'login_at' => $loginTs,
                ];
            }
            $byUser[$cid]['duration_seconds'] += max(0, (int) $dur);
            $byUser[$cid]['still_online'] = !empty($byUser[$cid]['still_online']) || $stillOnline;
            $byUser[$cid]['login_at'] = max((int) $byUser[$cid]['login_at'], $loginTs);
        }
    }

    // Newest first, cap list
    uasort($byUser, static function ($a, $b) {
        return ((int) ($b['login_at'] ?? 0)) <=> ((int) ($a['login_at'] ?? 0));
    });
    $byUser = array_slice($byUser, 0, 20, true);
    foreach ($byUser as $item) {
        $secs = (int) ($item['duration_seconds'] ?? 0);
        $item['duration_label'] = function_exists('user_presence_format_duration')
            ? user_presence_format_duration($secs)
            : ($secs . 's');
        $catchup[] = $item;
    }
}

online_presence_json_exit([
    'success' => true,
    'users' => $users,
    'catchup' => $catchup,
    'catchup_key' => $catchupKey,
    'server_time' => time(),
]);
