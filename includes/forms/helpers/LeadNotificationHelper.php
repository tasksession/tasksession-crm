<?php
/**
 * Forms module – mysqli-only helper to create "New lead created" notifications.
 * Does not depend on $db1 or includes/notifications.php.
 */

/**
 * Notify admins and staff with "View Other User Leads" (lead_view_all) that a new lead was created.
 * Uses only the given mysqli connection; safe to call from form/webhook context.
 *
 * @param mysqli $connect
 * @param int $leadId
 * @param string $sourceLabel e.g. "Form" or "Webhook (wordpress)"
 * @return void
 */
function notifyAdminsNewLeadCreated($connect, $leadId, $sourceLabel)
{
    if (!$connect instanceof mysqli) {
        return;
    }
    $leadId = (int) $leadId;
    $sourceLabel = trim((string) $sourceLabel) ?: 'form/webhook';

    // Recipients: admins (accountStatus = 1) + staff with lead_view_all permission
    $recipientIds = [];
    $sql = "SELECT id FROM users WHERE accountStatus = 1
            UNION
            SELECT u.id FROM users u
            INNER JOIN role_permissions rp ON rp.role_id = u.role_id AND rp.permission_key = 'lead_view_all' AND rp.value = 1
            ORDER BY id ASC";
    $res = $connect->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $recipientIds[] = (int) $row['id'];
        }
    }
    if (empty($recipientIds)) {
        return;
    }

    $fromUserId = $recipientIds[0];
    $type = 'lead_created';
    $title = 'New lead created';
    $message = 'A new lead was created from ' . $sourceLabel . '.';
    $relatedType = 'lead';
    $isRead = 0;
    // Use DB NOW() so timezone matches admin panel (form/webhook may run without app timezone set)
    $insertStmt = $connect->prepare(
        'INSERT INTO notifications (user_id, from_user_id, type, title, message, related_id, related_type, is_read, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
    );
    if (!$insertStmt) {
        return;
    }
    $userId = 0;
    $insertStmt->bind_param('iissssii', $userId, $fromUserId, $type, $title, $message, $leadId, $relatedType, $isRead);
    foreach ($recipientIds as $recipientId) {
        $userId = $recipientId;
        $insertStmt->execute();
    }
    $insertStmt->close();
}
