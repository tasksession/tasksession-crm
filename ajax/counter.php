<?php 
if(isset($_POST['counter_up']) && $_POST['counter_up'] == 'update_c'){
    include('../includes/loader.php');
    
    // Security: Authentication check
    if (!isset($session) || !$session->isLoggedIn()) {
        http_response_code(401);
        echo '0';
        exit;
    } 
    $us_id = isset($_SESSION['userId']) ? (int)$_SESSION['userId'] : 0;
    $format = '%s';

    // Legacy/internal chat unread count (returns like '(N) ' or false)
    $unmsg = $msg->total_unread_messages($format);
    $count_internal = 0;
    if ($unmsg) {
        // Extract digits to support existing formatter
        if (preg_match('/\d+/', $unmsg, $m)) { $count_internal = (int)$m[0]; }
    }

    // Task chat unread count based on read-tracking
    $count_taskchat = 0;
    if ($us_id > 0) {
        // Prefer $db1 if available, else fallback to mysqli via config
        if (isset($db1) && method_exists($db1, 'query')) {
            $sql = "SELECT COUNT(m.id) AS cnt
                    FROM task_chat m
                    LEFT JOIN task_chat_reads r
                      ON r.message_id = m.id AND r.user_id = {$us_id}
                    WHERE m.user_id <> {$us_id} AND r.id IS NULL";
            $res = $db1->query($sql);
            if ($res) {
                $row = $db1->fetch_row($res);
                if ($row && isset($row['cnt'])) { $count_taskchat = (int)$row['cnt']; }
            }
        } else {
            // Minimal fallback using mysqli
            require_once('../includes/bootstrap_config.php');
            $conn = (isset($connect) && $connect instanceof mysqli) ? $connect : (function_exists('crm_mysqli_open') ? crm_mysqli_open() : mysqli_connect(DB_SERVER, DB_USER, DB_PASS, DB_NAME));
            if ($conn) {
                $sql = "SELECT COUNT(m.id) AS cnt
                        FROM task_chat m
                        LEFT JOIN task_chat_reads r
                          ON r.message_id = m.id AND r.user_id = {$us_id}
                        WHERE m.user_id <> {$us_id} AND r.id IS NULL";
                if ($res = mysqli_query($conn, $sql)) {
                    $row = mysqli_fetch_assoc($res);
                    if ($row && isset($row['cnt'])) { $count_taskchat = (int)$row['cnt']; }
                }
                mysqli_close($conn);
            }
        }
    }

    $total = $count_internal + $count_taskchat;
    if ($total > 0) {
        echo sprintf($format, $total);
    } else {
        echo '0';
    }
}
?>