<?php
/**
 * Per-user opt-in/out for chat batch emails in a given context (not shared with other users).
 */

class ChatEmailUserPreferenceRepository {

    /**
     * No row or email_enabled=1 => receive when policy allows.
     *
     * @param string $contextType project|group|task
     */
    public static function isUserEmailEnabled($userId, $contextType, $contextId) {
        if ((int) $userId <= 0 || !in_array($contextType, array('project', 'group', 'task'), true) || (int) $contextId <= 0) {
            return true;
        }
        $row = self::getRow($contextType, (int) $contextId, (int) $userId);
        if (!$row || !array_key_exists('email_enabled', $row) || $row['email_enabled'] === null) {
            return true;
        }
        return (int) $row['email_enabled'] === 1;
    }

    /**
     * @return array|null
     */
    public static function getRow($contextType, $contextId, $userId) {
        global $database;
        if (!in_array($contextType, array('project', 'group', 'task'), true) || (int) $contextId <= 0 || (int) $userId <= 0) {
            return null;
        }
        $ct = $database->escapeValue($contextType);
        $cid = (int) $contextId;
        $u = (int) $userId;
        $sql = "SELECT email_enabled, updated_at FROM chat_email_user_preference WHERE context_type = '{$ct}' AND context_id = {$cid} AND user_id = {$u} LIMIT 1";
        $q = method_exists($database, 'querySoft') ? $database->querySoft($sql) : $database->query($sql);
        if (!$q || $database->numRows($q) === 0) {
            return null;
        }
        return $database->fetchArray($q);
    }

    /**
     * @param int $enabled 0 or 1
     */
    public static function upsert($contextType, $contextId, $userId, $enabled) {
        global $database;
        if (!in_array($contextType, array('project', 'group', 'task'), true) || (int) $contextId <= 0 || (int) $userId <= 0) {
            return false;
        }
        $en = ((int) $enabled === 1) ? 1 : 0;
        $ct = $database->escapeValue($contextType);
        $cid = (int) $contextId;
        $u = (int) $userId;
        $ts = time();
        $sql = "INSERT INTO `chat_email_user_preference` (user_id, context_type, context_id, email_enabled, updated_at)
            VALUES ({$u}, '{$ct}', {$cid}, {$en}, {$ts})
            ON DUPLICATE KEY UPDATE email_enabled = VALUES(email_enabled), updated_at = VALUES(updated_at)";
        $q = method_exists($database, 'querySoft') ? $database->querySoft($sql) : $database->query($sql);
        return (bool) $q;
    }

    /**
     * Remove preference row (inherit default = enabled for delivery logic).
     */
    public static function deleteRow($contextType, $contextId, $userId) {
        global $database;
        if (!in_array($contextType, array('project', 'group', 'task'), true) || (int) $contextId <= 0 || (int) $userId <= 0) {
            return false;
        }
        $ct = $database->escapeValue($contextType);
        $cid = (int) $contextId;
        $u = (int) $userId;
        $sql = "DELETE FROM `chat_email_user_preference` WHERE context_type = '{$ct}' AND context_id = {$cid} AND user_id = {$u} LIMIT 1";
        $q = method_exists($database, 'querySoft') ? $database->querySoft($sql) : $database->query($sql);
        return (bool) $q;
    }
}
