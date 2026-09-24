<?php
/**
 * Performance helpers for task board updates and schema ensure.
 */

if (!function_exists('crm_batch_update_task_positions')) {
    /**
     * @param list<int> $idsInOrder task ids in desired position order (index = position)
     */
    function crm_batch_update_task_positions($database, array $idsInOrder): bool
    {
        $cases = [];
        $idList = [];
        foreach ($idsInOrder as $idx => $tid) {
            $tid = (int) $tid;
            if ($tid <= 0) {
                continue;
            }
            $cases[] = 'WHEN ' . $tid . ' THEN ' . (int) $idx;
            $idList[] = $tid;
        }
        if (empty($idList)) {
            return true;
        }
        $sql = 'UPDATE tasks SET position = CASE id ' . implode(' ', $cases)
            . ' END WHERE id IN (' . implode(',', $idList) . ')';
        if (method_exists($database, 'querySoft')) {
            return (bool) $database->querySoft($sql);
        }
        $database->query($sql);
        return true;
    }
}

if (!function_exists('crm_db_transaction')) {
    function crm_db_transaction_begin($connect): bool
    {
        if (!$connect instanceof mysqli) {
            return false;
        }
        return (bool) @mysqli_begin_transaction($connect, MYSQLI_TRANS_START_READ_WRITE);
    }

    function crm_db_transaction_commit($connect): bool
    {
        if (!$connect instanceof mysqli) {
            return false;
        }
        return (bool) @mysqli_commit($connect);
    }

    function crm_db_transaction_rollback($connect): bool
    {
        if (!$connect instanceof mysqli) {
            return false;
        }
        return (bool) @mysqli_rollback($connect);
    }
}
