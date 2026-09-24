<?php
/*
================================================================================
  Task sidebar – Activity list (JSON + HTML, lead-style)
================================================================================
*/
if (!defined('CRM_LIGHTWEIGHT_INIT')) {
    define('CRM_LIGHTWEIGHT_INIT', true);
}
error_reporting(E_ALL);
ini_set('display_errors', '0');

ob_start();
require_once __DIR__ . '/lib-initialize.php';
require_once __DIR__ . '/task.php';
require_once __DIR__ . '/permissions.php';
require_once __DIR__ . '/projects.php';

header('Content-Type: application/json');

if (!$session->isLoggedIn()) {
    if (ob_get_length()) {
        ob_clean();
    }
    echo json_encode(['status' => 'error', 'error' => 'Not logged in']);
    exit;
}

ensure_user_permissions($connect);

$taskId = isset($_GET['task_id']) ? (int)$_GET['task_id'] : 0;
if ($taskId <= 0) {
    if (ob_get_length()) {
        ob_clean();
    }
    echo json_encode(['status' => 'error', 'error' => 'Missing task id']);
    exit;
}

$task = Task::findById($taskId);
if (!$task) {
    if (ob_get_length()) {
        ob_clean();
    }
    echo json_encode(['status' => 'error', 'error' => 'Task not found']);
    exit;
}

$userType = (int)$_SESSION['accountStatus'];
$userId = (int)$_SESSION['userId'];
$canViewTask = false;

switch ($userType) {
    case 1:
        $canViewTask = true;
        break;
    case 3:
        $canViewTask = staff_can_view_task($task, $userId);
        break;
    case 2:
        $canViewTask = $task->isClientAssociated($userId);
        break;
    default:
        $canViewTask = false;
}

if (!$canViewTask) {
    if (ob_get_length()) {
        ob_clean();
    }
    echo json_encode(['status' => 'error', 'error' => 'Insufficient permissions']);
    exit;
}

$tblRes = mysqli_query($connect, "SHOW TABLES LIKE 'task_activities'");
if (!$tblRes || !mysqli_fetch_assoc($tblRes)) {
    if (ob_get_length()) {
        ob_clean();
    }
    echo json_encode(['status' => 'ok', 'data' => ['html' => '', 'count' => 0]]);
    exit;
}

function task_activity_pretty_time($ts)
{
    $ts = (int)$ts;
    if ($ts <= 0) {
        return '';
    }
    $today = date('Y-m-d');
    $d = date('Y-m-d', $ts);
    $time = date('g:ia', $ts);
    if ($d === $today) {
        return "Today at {$time}";
    }
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    if ($d === $yesterday) {
        return "Yesterday at {$time}";
    }

    return date('M j, Y', $ts) . " at {$time}";
}

$stmt = $connect->prepare('
    SELECT a.*, u.firstName, UNIX_TIMESTAMP(a.created_at) AS created_ts
      FROM task_activities a
 LEFT JOIN users u ON u.id = a.actor_user_id
     WHERE a.task_id=?
     ORDER BY a.created_at DESC, a.id DESC
     LIMIT 30
');
$stmt->bind_param('i', $taskId);
$stmt->execute();
$res = $stmt->get_result();
$rows = [];
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $rows[] = $r;
    }
}
$stmt->close();

ob_start();
if (empty($rows)) {
    echo '<div class="alert alert-light text-center">' . htmlspecialchars($lang['No activity found'] ?? 'No activity found') . '</div>';
} else {
    echo '<div class="activity-list">';
    foreach ($rows as $r) {
        $actorId = (int)($r['actor_user_id'] ?? 0);
        $actorName = trim((string)($r['firstName'] ?? ''));
        if ($actorName === '') {
            $actorName = ($actorId > 0 ? ('User #' . $actorId) : ($lang['System'] ?? 'System'));
        }
        $avatar = $actorId > 0
            ? getUserAvatarHtml($actorId, (string)($r['firstName'] ?? ''), '', 40, 40, 'img-fluid rounded-circle', (string)($r['firstName'] ?? ''))
            : '';
        $title = (string)($r['event_title'] ?? '');
        $msg = (string)($r['event_message'] ?? '');
        $createdTs = (int)($r['created_ts'] ?? 0);
        $when = task_activity_pretty_time($createdTs);
        ?>
        <div class="d-flex col-gap-10 mb-3 lead-activity-item">
            <div style="width:40px;height:40px;flex:0 0 40px;"><?php echo $avatar; ?></div>
            <div style="flex:1;">
                <div class="d-flex align-items-center col-gap-10">
                    <b><?php echo htmlspecialchars($actorName); ?></b>
                    <span class="grey font-size-12"><?php echo htmlspecialchars($when); ?></span>
                </div>
                <div class="mt-1">
                    <span class="fw-bold"><?php echo htmlspecialchars($title); ?></span>
                    <?php if (trim($msg) !== ''): ?>
                        <span class="ms-1"><u><?php echo htmlspecialchars($msg); ?></u></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }
    echo '</div>';
}
$html = ob_get_clean();

if (ob_get_length()) {
    ob_clean();
}
echo json_encode(['status' => 'ok', 'data' => ['html' => $html, 'count' => count($rows)]]);
exit;
