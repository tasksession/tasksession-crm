<?php
/**
 * Resolvers for project/task CSV import (company main client, staff team groups).
 *
 * CSV rules (see runners):
 * - If company_id and main_client_id both set: company_id wins.
 * - assigned_staff / s_ids: comma-separated user ids and/or tokens "group:123".
 */
class Comon_IE_ProjectTaskImportHelpers
{
    /**
     * @return array{primary:int,member_ids:int[]}|null
     */
    public static function companyPrimaryAndMembers(mysqli $connect, int $companyId): ?array
    {
        if ($companyId <= 0) {
            return null;
        }
        $tbl = @mysqli_query($connect, "SHOW TABLES LIKE 'client_companies'");
        if (!$tbl || mysqli_num_rows($tbl) === 0) {
            if ($tbl) {
                mysqli_free_result($tbl);
            }
            return null;
        }
        mysqli_free_result($tbl);
        $cid = (int)$companyId;
        $q = @mysqli_query($connect, 'SELECT id FROM client_companies WHERE id = ' . $cid . ' AND deleted_at IS NULL LIMIT 1');
        if (!$q || mysqli_num_rows($q) === 0) {
            if ($q) {
                mysqli_free_result($q);
            }
            return null;
        }
        mysqli_free_result($q);

        $hasPrimary = false;
        $cp = @mysqli_query($connect, "SHOW COLUMNS FROM `client_company_members` LIKE 'is_primary'");
        if ($cp && mysqli_num_rows($cp) > 0) {
            $hasPrimary = true;
        }
        if ($cp) {
            mysqli_free_result($cp);
        }

        $uids = [];
        $orderSql = $hasPrimary ? 'ORDER BY is_primary DESC, user_id ASC' : 'ORDER BY user_id ASC';
        $mq = @mysqli_query($connect, 'SELECT user_id FROM client_company_members WHERE company_id = ' . $cid . ' ' . $orderSql);
        if ($mq) {
            while ($m = mysqli_fetch_assoc($mq)) {
                $u = (int)($m['user_id'] ?? 0);
                if ($u > 0) {
                    $uids[] = $u;
                }
            }
            mysqli_free_result($mq);
        }
        $uids = array_values(array_unique($uids));
        if ($uids === []) {
            return null;
        }
        $primary = $uids[0];
        if ($hasPrimary) {
            $pq = @mysqli_query($connect, 'SELECT user_id FROM client_company_members WHERE company_id = ' . $cid . ' AND is_primary = 1 LIMIT 1');
            if ($pq && ($pr = mysqli_fetch_assoc($pq))) {
                $p = (int)($pr['user_id'] ?? 0);
                if ($p > 0 && in_array($p, $uids, true)) {
                    $primary = $p;
                }
            }
            if ($pq) {
                mysqli_free_result($pq);
            }
        }
        return ['primary' => $primary, 'member_ids' => $uids];
    }

    /**
     * Expand "12,34,group:5" into comma-separated unique staff/admin user ids (1 or 3).
     *
     * @return array{0:string,1:array<int,string>} [s_ids csv, error_messages]
     */
    public static function expandStaffAssigneeTokens(mysqli $connect, string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return ['', []];
        }
        $errs = [];
        $ids = [];
        $parts = preg_split('/\s*,\s*/', $raw) ?: [];
        foreach ($parts as $p) {
            $p = trim((string)$p);
            if ($p === '') {
                continue;
            }
            if (preg_match('/^group:\s*(\d+)$/i', $p, $m)) {
                $gid = (int)$m[1];
                $gUsers = self::staffTeamGroupUserIds($connect, $gid);
                if ($gUsers === []) {
                    $errs[] = 'Unknown or empty team group id ' . $gid;
                    continue;
                }
                foreach ($gUsers as $u) {
                    if ($u > 0) {
                        $ids[$u] = true;
                    }
                }
                continue;
            }
            if (ctype_digit($p)) {
                $ids[(int)$p] = true;
            } else {
                $errs[] = 'Invalid assignee token: ' . $p;
            }
        }
        $out = [];
        foreach (array_keys($ids) as $uid) {
            if ($uid <= 0) {
                continue;
            }
            $st = @mysqli_query($connect, 'SELECT accountStatus FROM users WHERE id = ' . (int)$uid . ' LIMIT 1');
            if (!$st || mysqli_num_rows($st) === 0) {
                if ($st) {
                    mysqli_free_result($st);
                }
                $errs[] = 'User id ' . $uid . ' not found';
                continue;
            }
            $row = mysqli_fetch_assoc($st);
            mysqli_free_result($st);
            $ac = (int)($row['accountStatus'] ?? 0);
            if ($ac !== 1 && $ac !== 3) {
                $errs[] = 'User id ' . $uid . ' is not staff or admin';
                continue;
            }
            $out[] = (int)$uid;
        }
        $out = array_values(array_unique($out));
        sort($out);
        return [implode(',', $out), $errs];
    }

    /**
     * @return int[]
     */
    public static function staffTeamGroupUserIds(mysqli $connect, int $groupId): array
    {
        if ($groupId <= 0) {
            return [];
        }
        $tbl = @mysqli_query($connect, "SHOW TABLES LIKE 'staff_team_groups'");
        if (!$tbl || mysqli_num_rows($tbl) === 0) {
            if ($tbl) {
                mysqli_free_result($tbl);
            }
            return [];
        }
        mysqli_free_result($tbl);
        $uids = [];
        $mq = @mysqli_query(
            $connect,
            'SELECT user_id FROM staff_team_group_members WHERE group_id = ' . (int)$groupId . ' ORDER BY user_id ASC'
        );
        if ($mq) {
            while ($m = mysqli_fetch_assoc($mq)) {
                $u = (int)($m['user_id'] ?? 0);
                if ($u > 0) {
                    $uids[] = $u;
                }
            }
            mysqli_free_result($mq);
        }
        return array_values(array_unique($uids));
    }

    public static function isClientUser(mysqli $connect, int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }
        $q = @mysqli_query($connect, 'SELECT accountStatus FROM users WHERE id = ' . (int)$userId . ' LIMIT 1');
        if (!$q || mysqli_num_rows($q) === 0) {
            if ($q) {
                mysqli_free_result($q);
            }
            return false;
        }
        $r = mysqli_fetch_assoc($q);
        mysqli_free_result($q);
        return (int)($r['accountStatus'] ?? 0) === 2;
    }
}
