<?php
/**
 * Persistence for per-context email overrides (chat_email_context).
 */

class ChatEmailContextRepository {

    /**
     * @return array|null
     */
    public static function getRow($contextType, $contextId) {
        global $database;
        $ct = $database->escapeValue($contextType);
        if (!in_array($contextType, array('project', 'group', 'task'), true)) {
            return null;
        }
        $cid = (int) $contextId;
        $sql = "SELECT o_admin, o_staff, o_client, o_mention, updated_at FROM chat_email_context WHERE context_type = '{$ct}' AND context_id = {$cid} LIMIT 1";
        $q = $database->query($sql);
        if (!$q || $database->numRows($q) == 0) {
            return null;
        }
        $row = $database->fetchArray($q);
        return $row;
    }

    public static function upsert($contextType, $contextId, $oAdmin, $oStaff, $oClient, $oMention) {
        global $database;
        if (!in_array($contextType, array('project', 'group', 'task'), true)) {
            return false;
        }
        $cid = (int) $contextId;
        if ($cid <= 0) {
            return false;
        }
        $ct = $database->escapeValue($contextType);
        $ts = time();
        $va = self::valOrNull($oAdmin, $database);
        $vs = self::valOrNull($oStaff, $database);
        $vc = self::valOrNull($oClient, $database);
        $vm = self::valOrNull($oMention, $database);
        $sql = "INSERT INTO `chat_email_context` (context_type, context_id, o_admin, o_staff, o_client, o_mention, updated_at)
            VALUES ('{$ct}', {$cid}, {$va}, {$vs}, {$vc}, {$vm}, {$ts})
            ON DUPLICATE KEY UPDATE o_admin = VALUES(o_admin), o_staff = VALUES(o_staff), o_client = VALUES(o_client), o_mention = VALUES(o_mention), updated_at = VALUES(updated_at)";
        return (bool) $database->query($sql);
    }

    public static function deleteRow($contextType, $contextId) {
        global $database;
        if (!in_array($contextType, array('project', 'group', 'task'), true)) {
            return false;
        }
        $ct = $database->escapeValue($contextType);
        $cid = (int) $contextId;
        $sql = "DELETE FROM `chat_email_context` WHERE context_type = '{$ct}' AND context_id = {$cid} LIMIT 1";
        return (bool) $database->query($sql);
    }

    /**
     * @param 'o_staff'|'o_client' $whichColumn
     * @param int|null|true $value true = clear column (NULL); int 0/1 = set
     * @return bool
     */
    public static function upsertSingleRoleColumn($contextType, $contextId, $whichColumn, $value) {
        if (!in_array($contextType, array('project', 'group', 'task'), true) || (int) $contextId <= 0) {
            return false;
        }
        if ($whichColumn !== 'o_staff' && $whichColumn !== 'o_client') {
            return false;
        }
        $row = self::getRow($contextType, (int) $contextId);
        $a = $row && array_key_exists('o_admin', $row) ? $row['o_admin'] : null;
        $s = $row && array_key_exists('o_staff', $row) ? $row['o_staff'] : null;
        $c = $row && array_key_exists('o_client', $row) ? $row['o_client'] : null;
        $m = $row && array_key_exists('o_mention', $row) ? $row['o_mention'] : null;
        if ($value === true) {
            if ($whichColumn === 'o_staff') {
                $s = null;
            } else {
                $c = null;
            }
        } else {
            if ($value !== null) {
                $n = (int) $value;
                if ($whichColumn === 'o_staff') {
                    $s = ($n === 1) ? 1 : 0;
                } else {
                    $c = ($n === 1) ? 1 : 0;
                }
            }
        }
        if ($a === null && $s === null && $c === null && $m === null) {
            return $row ? self::deleteRow($contextType, (int) $contextId) : true;
        }
        return self::upsert($contextType, (int) $contextId, $a, $s, $c, $m);
    }

    private static function valOrNull($v, $database) {
        if ($v === null || $v === '' || (is_string($v) && strcasecmp($v, 'null') === 0)) {
            return 'NULL';
        }
        $n = (int) $v;
        if ($n !== 0 && $n !== 1) {
            return 'NULL';
        }
        return (string) $n;
    }
}
