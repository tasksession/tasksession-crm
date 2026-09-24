<?php
/**
 * Chat/list presence vs login audit.
 * Presence: last_seen within USER_PRESENCE_IDLE_SECONDS (WhatsApp/Slack-style).
 * Batch chat emails keep their own 15-minute SQL — do not use this constant there.
 */

if (!defined('USER_PRESENCE_IDLE_SECONDS')) {
    define('USER_PRESENCE_IDLE_SECONDS', 300);
}

if (!function_exists('user_presence_is_online')) {
    function user_presence_is_online($session_status, $last_seen, $now = null): bool
    {
        if (strtolower(trim((string) $session_status)) !== 'online') {
            return false;
        }
        $seen = (int) $last_seen;
        if ($seen <= 0) {
            return false;
        }
        $now = $now === null ? time() : (int) $now;
        return ($now - $seen) < USER_PRESENCE_IDLE_SECONDS;
    }
}

if (!function_exists('user_presence_status')) {
    function user_presence_status($session_status, $last_seen, $now = null): string
    {
        return user_presence_is_online($session_status, $last_seen, $now) ? 'online' : 'offline';
    }
}

if (!function_exists('user_presence_from_user')) {
    function user_presence_from_user($user, $now = null): bool
    {
        if (!is_object($user)) {
            return false;
        }
        $status = $user->session_status ?? 'offline';
        $seen = $user->last_seen ?? 0;
        return user_presence_is_online($status, $seen, $now);
    }
}

if (!function_exists('user_presence_maybe_persist_offline')) {
    /**
     * If the user looks idle, set users.session_status = offline (helps 15-min batch emails).
     * Does not insert login_attempts logout rows.
     */
    function user_presence_maybe_persist_offline($userId, $session_status, $last_seen, $now = null): void
    {
        $userId = (int) $userId;
        if ($userId <= 0) {
            return;
        }
        if (user_presence_is_online($session_status, $last_seen, $now)) {
            return;
        }
        if (strtolower(trim((string) $session_status)) !== 'online') {
            return;
        }

        $now = $now === null ? time() : (int) $now;
        $cutoff = $now - USER_PRESENCE_IDLE_SECONDS;

        global $connect, $db1, $database;

        if (isset($connect) && $connect instanceof mysqli) {
            $stmt = $connect->prepare(
                'UPDATE users SET session_status = ? WHERE id = ? AND session_status = ? AND (last_seen = 0 OR last_seen < ?)'
            );
            if ($stmt) {
                $offline = 'offline';
                $online = 'online';
                $stmt->bind_param('sisi', $offline, $userId, $online, $cutoff);
                $stmt->execute();
                $stmt->close();
            }
            return;
        }

        $sql = "UPDATE users SET session_status = 'offline'
                WHERE id = {$userId}
                  AND session_status = 'online'
                  AND (last_seen = 0 OR last_seen < {$cutoff})";
        if (isset($db1) && is_object($db1) && method_exists($db1, 'query')) {
            @$db1->query($sql);
            return;
        }
        if (isset($database) && is_object($database) && method_exists($database, 'query')) {
            @$database->query($sql);
        }
    }
}

if (!function_exists('user_presence_format_duration')) {
    function user_presence_format_duration($seconds): string
    {
        $seconds = (int) $seconds;
        if ($seconds < 0) {
            $seconds = 0;
        }
        if ($seconds < 60) {
            return $seconds . ' seconds';
        }
        if ($seconds < 3600) {
            $minutes = (int) floor($seconds / 60);
            $secs = $seconds % 60;
            return $minutes . 'm ' . $secs . 's';
        }
        $hours = (int) floor($seconds / 3600);
        $minutes = (int) floor(($seconds % 3600) / 60);
        return $hours . 'h ' . $minutes . 'm';
    }
}

if (!function_exists('user_presence_activity_duration_label')) {
    /**
     * Activity tab label. Logout row = login→logout. Else Active only if still present; otherwise login→last_seen.
     */
    function user_presence_activity_duration_label($loginTime, $logoutTime, $lastSeen, $sessionStatus): string
    {
        $loginTime = (int) $loginTime;
        if ($loginTime <= 0) {
            return '-';
        }
        if ($logoutTime !== null && (int) $logoutTime > 0) {
            return user_presence_format_duration((int) $logoutTime - $loginTime);
        }
        if (user_presence_is_online($sessionStatus, $lastSeen)) {
            return 'Active';
        }
        $end = (int) $lastSeen > 0 ? (int) $lastSeen : $loginTime;
        return user_presence_format_duration($end - $loginTime);
    }
}
