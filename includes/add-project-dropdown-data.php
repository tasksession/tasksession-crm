<?php
/**
 * Builds $projectCompanyList and $projectTeamGroupList for add-new-project pages (JSON in window.*).
 * Requires $database->connection (mysqli).
 */
$projectCompanyList = [];
$projectTeamGroupList = [];

if (!isset($database) || !$database || !$database->connection) {
    return;
}

$conn = $database->connection;

$tblCc = @mysqli_query($conn, "SHOW TABLES LIKE 'client_companies'");
if ($tblCc && mysqli_num_rows($tblCc) > 0) {
    mysqli_free_result($tblCc);
    $hasIsPrimary = false;
    $colChk = @mysqli_query($conn, "SHOW COLUMNS FROM `client_company_members` LIKE 'is_primary'");
    if ($colChk && mysqli_num_rows($colChk) > 0) {
        $hasIsPrimary = true;
    }
    if ($colChk) {
        mysqli_free_result($colChk);
    }

    $cq = @mysqli_query(
        $conn,
        "SELECT `id`, `name`, `logo_path` FROM `client_companies` WHERE `deleted_at` IS NULL ORDER BY `name` ASC"
    );
    if ($cq) {
        while ($row = mysqli_fetch_assoc($cq)) {
            $cid = (int) ($row['id'] ?? 0);
            if ($cid <= 0) {
                continue;
            }
            $uids = [];
            $orderSql = $hasIsPrimary
                ? 'ORDER BY `is_primary` DESC, `user_id` ASC'
                : 'ORDER BY `user_id` ASC';
            $mq = @mysqli_query(
                $conn,
                'SELECT `user_id` FROM `client_company_members` WHERE `company_id` = ' . $cid . ' ' . $orderSql
            );
            if ($mq) {
                while ($m = mysqli_fetch_assoc($mq)) {
                    $uids[] = (int) ($m['user_id'] ?? 0);
                }
                mysqli_free_result($mq);
            }
            $uids = array_values(array_filter(array_unique($uids)));
            if (empty($uids)) {
                continue;
            }
            $primary = (int) $uids[0];
            if ($hasIsPrimary) {
                $pq = @mysqli_query(
                    $conn,
                    'SELECT `user_id` FROM `client_company_members` WHERE `company_id` = ' . $cid . ' AND `is_primary` = 1 LIMIT 1'
                );
                if ($pq && ($pr = mysqli_fetch_assoc($pq))) {
                    $puid = (int) ($pr['user_id'] ?? 0);
                    if ($puid > 0 && in_array($puid, $uids, true)) {
                        $primary = $puid;
                    }
                    mysqli_free_result($pq);
                }
            }

            $logoPath = isset($row['logo_path']) && $row['logo_path'] !== null ? (string) $row['logo_path'] : '';
            $projectCompanyList[] = [
                'id' => $cid,
                'name' => htmlspecialchars((string) ($row['name'] ?? ''), ENT_QUOTES, 'UTF-8'),
                'logo_path' => htmlspecialchars($logoPath, ENT_QUOTES, 'UTF-8'),
                'primary_user_id' => $primary,
                'member_user_ids' => $uids,
            ];
        }
        mysqli_free_result($cq);
    }
}

$tblTg = @mysqli_query($conn, "SHOW TABLES LIKE 'staff_team_groups'");
if ($tblTg && mysqli_num_rows($tblTg) > 0) {
    mysqli_free_result($tblTg);
    $tgDeleted = false;
    $dc = @mysqli_query($conn, "SHOW COLUMNS FROM `staff_team_groups` LIKE 'deleted_at'");
    if ($dc && mysqli_num_rows($dc) > 0) {
        $tgDeleted = true;
    }
    if ($dc) {
        mysqli_free_result($dc);
    }
    $where = $tgDeleted ? ' WHERE `g`.`deleted_at` IS NULL ' : '';
    $gq = @mysqli_query(
        $conn,
        'SELECT `g`.`id`, `g`.`name` FROM `staff_team_groups` `g` ' . $where . ' ORDER BY `g`.`name` ASC'
    );
    if ($gq) {
        while ($grow = mysqli_fetch_assoc($gq)) {
            $gid = (int) ($grow['id'] ?? 0);
            if ($gid <= 0) {
                continue;
            }
            $uids = [];
            $mq = @mysqli_query(
                $conn,
                'SELECT `user_id` FROM `staff_team_group_members` WHERE `group_id` = ' . $gid . ' ORDER BY `user_id` ASC'
            );
            if ($mq) {
                while ($m = mysqli_fetch_assoc($mq)) {
                    $uids[] = (int) ($m['user_id'] ?? 0);
                }
                mysqli_free_result($mq);
            }
            $uids = array_values(array_filter(array_unique($uids)));
            if (empty($uids)) {
                continue;
            }
            $projectTeamGroupList[] = [
                'id' => $gid,
                'name' => htmlspecialchars((string) ($grow['name'] ?? ''), ENT_QUOTES, 'UTF-8'),
                'user_ids' => $uids,
            ];
        }
        mysqli_free_result($gq);
    }
}
