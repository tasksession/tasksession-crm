<?php
/**
 * Upload-folder ACL after path jail.
 *
 * Rules (must not break live media):
 * - system-uploads: public
 * - Mapped DB row (task_files / files / email_attachments): enforce membership
 * - No DB row: authenticated only (chat/invoice files live in user-uploads with
 *   no filename index; inventing discussion membership would 403 those)
 * - Admins (accountStatus 1): full access to private folders
 * - ACL dependency errors fail open for authenticated users (same as before)
 */

if (!function_exists('tasksession_session_user_id')) {
    function tasksession_session_user_id() {
        if (isset($_SESSION['userId']) && (int) $_SESSION['userId'] > 0) {
            return (int) $_SESSION['userId'];
        }
        if (isset($_SESSION['logged_user_id']) && (int) $_SESSION['logged_user_id'] > 0) {
            return (int) $_SESSION['logged_user_id'];
        }
        if (isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] > 0) {
            return (int) $_SESSION['user_id'];
        }
        if (isset($_SESSION['user']['id']) && (int) $_SESSION['user']['id'] > 0) {
            return (int) $_SESSION['user']['id'];
        }
        return 0;
    }
}

if (!function_exists('tasksession_file_acl_boot')) {
    /**
     * Load DB + task/vault classes without lib-initialize.php from function
     * scope (included top-level $database would stay local and ACL would fail open).
     */
    function tasksession_file_acl_boot() {
        if (!defined('CRM_LIGHTWEIGHT_INIT')) {
            define('CRM_LIGHTWEIGHT_INIT', true);
        }
        if (!isset($GLOBALS['database']) || !is_object($GLOBALS['database'])) {
            require_once __DIR__ . '/database.php';
            if (isset($database) && is_object($database)) {
                $GLOBALS['database'] = $database;
            }
            if (isset($db) && is_object($db) && !isset($GLOBALS['db'])) {
                $GLOBALS['db'] = $db;
            }
        }
        if ((!isset($GLOBALS['connect']) || !($GLOBALS['connect'] instanceof mysqli)) && isset($connect) && $connect instanceof mysqli) {
            $GLOBALS['connect'] = $connect;
        }
        if (!class_exists('DatabaseObject')) {
            require_once __DIR__ . '/database-object.php';
        }
    }
}

if (!function_exists('tasksession_file_acl_mysqli')) {
    function tasksession_file_acl_mysqli() {
        global $connect;
        if (isset($connect) && $connect instanceof mysqli && (int) $connect->connect_errno === 0) {
            return $connect;
        }
        tasksession_file_acl_boot();
        if (isset($connect) && $connect instanceof mysqli && (int) $connect->connect_errno === 0) {
            return $connect;
        }
        return null;
    }
}

if (!function_exists('tasksession_file_acl_is_admin')) {
    function tasksession_file_acl_is_admin($user_id) {
        $user_id = (int) $user_id;
        if ($user_id <= 0) {
            return false;
        }
        if (isset($_SESSION['accountStatus']) && (int) $_SESSION['accountStatus'] === 1) {
            $sid = tasksession_session_user_id();
            if ($sid === 0 || $sid === $user_id) {
                return true;
            }
        }
        $db = tasksession_file_acl_mysqli();
        if (!$db) {
            return false;
        }
        $stmt = $db->prepare('SELECT accountStatus FROM users WHERE id = ? LIMIT 1');
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();
        return $row && (int) $row['accountStatus'] === 1;
    }
}

if (!function_exists('tasksession_file_acl_account_status')) {
    function tasksession_file_acl_account_status($user_id) {
        $user_id = (int) $user_id;
        if (isset($_SESSION['accountStatus']) && (int) $_SESSION['accountStatus'] > 0) {
            $sid = tasksession_session_user_id();
            if ($sid === 0 || $sid === $user_id) {
                return (int) $_SESSION['accountStatus'];
            }
        }
        $db = tasksession_file_acl_mysqli();
        if (!$db) {
            return 0;
        }
        $stmt = $db->prepare('SELECT accountStatus FROM users WHERE id = ? LIMIT 1');
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();
        return $row ? (int) $row['accountStatus'] : 0;
    }
}

if (!function_exists('tasksession_file_acl_table_exists')) {
    function tasksession_file_acl_table_exists($table) {
        $db = tasksession_file_acl_mysqli();
        if (!$db) {
            return false;
        }
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $table);
        if ($table === '') {
            return false;
        }
        $result = $db->query("SHOW TABLES LIKE '" . $db->real_escape_string($table) . "'");
        return $result && $result->num_rows > 0;
    }
}

if (!function_exists('tasksession_file_acl_task_files')) {
    function tasksession_file_acl_task_files($filename, $user_id) {
        $db = tasksession_file_acl_mysqli();
        if (!$db || !tasksession_file_acl_table_exists('task_files')) {
            return true;
        }
        $stmt = $db->prepare('SELECT task_id, user_id FROM task_files WHERE filename = ? LIMIT 1');
        if (!$stmt) {
            return true;
        }
        $stmt->bind_param('s', $filename);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();
        if (!$row) {
            return true;
        }
        if ((int) $row['user_id'] === (int) $user_id) {
            return true;
        }

        tasksession_file_acl_boot();
        if (!function_exists('ensure_user_permissions')) {
            require_once __DIR__ . '/permissions.php';
        }
        if (!class_exists('projects')) {
            require_once __DIR__ . '/projects.php';
        }
        if (!class_exists('Task')) {
            require_once __DIR__ . '/task.php';
        }
        if (!isset($_SESSION['userId'])) {
            $_SESSION['userId'] = (int) $user_id;
        }
        if (function_exists('ensure_user_permissions')) {
            ensure_user_permissions($db);
        }

        $task = class_exists('Task') ? Task::findById((int) $row['task_id']) : null;
        if (!$task) {
            return false;
        }

        $status = tasksession_file_acl_account_status($user_id);
        if ($status === 1) {
            return true;
        }
        if ($status === 3) {
            return function_exists('staff_can_view_task') && staff_can_view_task($task, $user_id);
        }
        if ($status === 2) {
            return method_exists($task, 'isClientAssociated') && $task->isClientAssociated($user_id);
        }
        if (function_exists('staff_can_view_task') && staff_can_view_task($task, $user_id)) {
            return true;
        }
        return method_exists($task, 'isClientAssociated') && $task->isClientAssociated($user_id);
    }
}

if (!function_exists('tasksession_file_acl_file_sharing')) {
    function tasksession_file_acl_file_sharing($filename, $user_id) {
        tasksession_file_acl_boot();
        $database = $GLOBALS['database'] ?? null;
        if (!isset($database) || !is_object($database)) {
            return true;
        }
        if (!tasksession_file_acl_table_exists('files')) {
            return true;
        }

        $names = array($filename);
        if (strpos($filename, 'thumb_') === 0) {
            $stripped = substr($filename, 6);
            if ($stripped !== '') {
                $names[] = $stripped;
            }
        }

        $file = null;
        foreach ($names as $name) {
            $sql = "SELECT * FROM files WHERE filename = '" . $database->escapeValue($name) . "' LIMIT 1";
            $result = $database->query($sql);
            if ($result && $database->numRows($result) > 0) {
                $file = $database->fetchArray($result);
                break;
            }
        }
        if (!$file) {
            return true;
        }

        $fm = dirname(__DIR__) . '/vendor/google/gdrive/includes/file_manager.php';
        if (!class_exists('FileFolder') && is_file($fm)) {
            require_once $fm;
        }
        if (!class_exists('FileFolder') || !method_exists('FileFolder', 'userCanAccessMediaVaultFile')) {
            return ((int) ($file['uploaded_by'] ?? 0) === (int) $user_id);
        }
        return (bool) FileFolder::userCanAccessMediaVaultFile($file, (int) $user_id);
    }
}

if (!function_exists('tasksession_file_acl_email')) {
    function tasksession_file_acl_email($filename, $user_id) {
        $db = tasksession_file_acl_mysqli();
        if (!$db || !tasksession_file_acl_table_exists('email_attachments')) {
            return false;
        }
        $sql = 'SELECT ea.storage_quota_user_id, em.account_id
                FROM email_attachments ea
                LEFT JOIN email_messages em ON ea.message_id = em.id
                WHERE ea.filename = ?
                LIMIT 1';
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('s', $filename);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();
        if (!$row) {
            return false;
        }
        if ((int) $row['storage_quota_user_id'] === (int) $user_id) {
            return true;
        }
        $accountId = (int) ($row['account_id'] ?? 0);
        if ($accountId <= 0) {
            return false;
        }
        $acc = $db->prepare('SELECT user_id FROM email_accounts WHERE id = ? LIMIT 1');
        if (!$acc) {
            return false;
        }
        $acc->bind_param('i', $accountId);
        $acc->execute();
        $accRes = $acc->get_result();
        $accRow = $accRes ? $accRes->fetch_assoc() : null;
        $acc->close();
        return $accRow && (int) $accRow['user_id'] === (int) $user_id;
    }
}

if (!function_exists('tasksession_can_access_upload')) {
    /**
     * @param string $folder One of tasksession_allowed_upload_folders(), or empty
     * @param string $filename Basename only
     * @param int|null $user_id
     */
    function tasksession_can_access_upload($folder, $filename, $user_id = null) {
        if (function_exists('tasksession_safe_upload_basename')) {
            $filename = tasksession_safe_upload_basename($filename);
        } else {
            $filename = basename(str_replace("\0", '', (string) $filename));
        }
        if ($filename === '') {
            return false;
        }

        $folder = (string) $folder;
        if ($folder === 'system-uploads') {
            return true;
        }

        if ($user_id === null) {
            $user_id = tasksession_session_user_id();
        }
        $user_id = (int) $user_id;
        if ($user_id <= 0) {
            return false;
        }

        try {
            if (tasksession_file_acl_is_admin($user_id)) {
                return true;
            }
            if ($folder === 'task-files') {
                return tasksession_file_acl_task_files($filename, $user_id);
            }
            if ($folder === 'file-sharing') {
                return tasksession_file_acl_file_sharing($filename, $user_id);
            }
            if ($folder === 'email-attachments') {
                return tasksession_file_acl_email($filename, $user_id);
            }
            // user-uploads / profile-pics / unknown allowed folder: authenticated
            return true;
        } catch (Throwable $e) {
            error_log('tasksession file ACL: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            return true;
        }
    }
}
