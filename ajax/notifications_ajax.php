<?php
error_reporting(E_ERROR | E_PARSE);
require_once('../includes/lib-initialize.php');
require_once('../includes/notifications.php');
require_once('../includes/notification_helper.php');
header('Content-Type: application/json');

if (!isset($_SESSION['userId'])) {
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$user_id = $session->userId;

/**
 * For "New lead created" bell items, show the lead row's created_at (actual lead date),
 * not relative text like "Just now".
 */
function notification_time_label_for_lead_created($notification, $defaultTimeAgo) {
    if (!isset($notification->type) || $notification->type !== 'lead_created') {
        return $defaultTimeAgo;
    }
    $leadId = isset($notification->related_id) ? (int)$notification->related_id : 0;
    if ($leadId <= 0) {
        return $defaultTimeAgo;
    }
    global $connect;
    if (!$connect instanceof mysqli) {
        return $defaultTimeAgo;
    }
    $stmt = $connect->prepare('SELECT created_at FROM leads WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return $defaultTimeAgo;
    }
    $stmt->bind_param('i', $leadId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    if (!$row || empty($row['created_at'])) {
        return $defaultTimeAgo;
    }
    $ts = strtotime($row['created_at']);
    if ($ts === false) {
        return $defaultTimeAgo;
    }
    return date('M j, Y, g:i A', $ts);
}

switch ($action) {
    case 'get_notifications':
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
        
        $poll = Notifications::getNotificationsPollPayload($user_id, $limit, $offset);
        $notifications = $poll['notifications'];
        $unread_count = $poll['unread_count'];
        
        $result = [];
        foreach ($notifications as $notification) {
            $defaultAgo = $notification->getTimeAgo();
            $message = $notification->message;
            if (
                isset($notification->type)
                && $notification->type === Notifications::TYPE_ECOMMERCE_ORDER_RECEIVED
                && isset($notification->related_type)
                && $notification->related_type === 'ecommerce_orders'
            ) {
                $message = 'WooCommerce • Waiting for processing';
            }
            $agentType = '';
            $emailAccountId = 0;
            $emailThreadId = '';
            if (
                isset($notification->type)
                && (
                    $notification->type === Notifications::TYPE_AI_AGENT_ALERT
                    || $notification->type === Notifications::TYPE_AI_AGENT_DIGEST
                    || $notification->type === Notifications::TYPE_AI_AGENT_AUTO_REPLY
                    || (is_string($notification->type) && strpos($notification->type, 'ai_agent_') === 0)
                )
            ) {
                if (!function_exists('ai_agent_notify_alert_by_id')) {
                    $notifyPath = dirname(__DIR__) . '/ai/includes/agent-notify.php';
                    if (is_readable($notifyPath)) {
                        require_once $notifyPath;
                    }
                }
                if (
                    $notification->type === Notifications::TYPE_AI_AGENT_ALERT
                    && function_exists('ai_agent_notify_alert_by_id')
                ) {
                    $alertRow = ai_agent_notify_alert_by_id((int) ($notification->related_id ?? 0));
                    if (is_array($alertRow)) {
                        $agentType = preg_replace('/[^a-z_]/', '', (string) ($alertRow['agent_type'] ?? ''));
                    }
                }
                if ($notification->type === Notifications::TYPE_AI_AGENT_AUTO_REPLY) {
                    $mid = (int) ($notification->related_id ?? 0);
                    if ($mid > 0 && isset($connect) && $connect instanceof mysqli) {
                        $mq = @$connect->query(
                            'SELECT account_id, thread_id FROM email_messages WHERE id = ' . $mid . ' LIMIT 1'
                        );
                        if ($mq && ($mr = $mq->fetch_assoc())) {
                            $emailAccountId = (int) ($mr['account_id'] ?? 0);
                            $emailThreadId = trim((string) ($mr['thread_id'] ?? ''));
                            if ($emailThreadId === '') {
                                $emailThreadId = (string) $mid;
                            }
                        }
                        if ($mq) {
                            $mq->free();
                        }
                    }
                }
            }
            $result[] = [
                'id' => $notification->id,
                'user_id' => property_exists($notification, 'user_id') ? (int) $notification->user_id : null,
                'type' => $notification->type,
                'title' => $notification->title,
                'message' => $message,
                'is_read' => $notification->is_read,
                'created_at' => $notification->created_at,
                'time_ago' => notification_time_label_for_lead_created($notification, $defaultAgo),
                'from_user' => [
                    'name' => property_exists($notification, 'firstName') ? $notification->firstName : null,
                    'profile_pic' => property_exists($notification, 'profile_pic') ? $notification->profile_pic : null
                ],
                'from_user_id' => property_exists($notification, 'from_user_id') ? (int) $notification->from_user_id : null,
                'related_id' => $notification->related_id,
                'related_type' => $notification->related_type,
                'related_project_id' => property_exists($notification, 'related_project_id') ? $notification->related_project_id : null,
                'upload_count' => property_exists($notification, 'upload_count') ? (int)$notification->upload_count : 1,
                'order_count' => property_exists($notification, 'order_count') ? (int)$notification->order_count : 1,
                'agent_type' => $agentType,
                'account_id' => $emailAccountId,
                'thread_id' => $emailThreadId,
            ];
        }
        
        echo json_encode([
            'success' => true,
            'notifications' => $result,
            'unread_count' => $unread_count
        ]);
        break;
        
    case 'mark_as_read':
        $notification_id = $_POST['notification_id'] ?? 0;
        
        if ($notification_id) {
            // For grouped project-media upload notifications, mark the whole group as read.
            global $db1;
            $notification_id = (int)$notification_id;
            $metaSql = "SELECT type, from_user_id, related_project_id FROM notifications WHERE id = " . $db1->escape($notification_id) . " AND user_id = " . $db1->escape($user_id) . " LIMIT 1";
            $metaRes = $db1->query($metaSql);
            $meta = $metaRes ? $db1->fetch_row($metaRes) : null;

            if ($meta && isset($meta['type']) && $meta['type'] === 'project_media_file_uploaded') {
                $from_user_id = (int)$meta['from_user_id'];
                $related_project_id = (int)$meta['related_project_id'];
                $markSql = "UPDATE notifications
                            SET is_read = 1
                            WHERE user_id = " . $db1->escape($user_id) . "
                              AND is_read = 0
                              AND type = 'project_media_file_uploaded'
                              AND from_user_id = " . $db1->escape($from_user_id) . "
                              AND related_project_id = " . $db1->escape($related_project_id);
                $db1->query($markSql);
            } else {
                Notifications::markAsRead($notification_id, $user_id);
            }
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['error' => 'Invalid notification ID']);
        }
        break;
        
    case 'mark_all_as_read':
        Notifications::markAllAsRead($user_id);
        echo json_encode(['success' => true]);
        break;
        
    case 'get_unread_count':
        $unread_count = Notifications::getUnreadCountGrouped($user_id);
        echo json_encode([
            'success' => true,
            'unread_count' => $unread_count
        ]);
        break;
        
    default:
        echo json_encode(['error' => 'Invalid action']);
        break;
}
?> 