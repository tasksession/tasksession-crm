<?php
/*
================================================================================
  Global Tags – Add/Remove Tag from Entity (Generic)
  Location: includes/tags/tags.php
  Supports: lead, task, project, invoice, etc.
================================================================================
*/
ob_start();
require_once(__DIR__ . "/../lib-initialize.php");

header('Content-Type: application/json');

if (!$session->isLoggedIn()) {
    if (ob_get_length()) { ob_clean(); }
    echo json_encode(['status' => 'error', 'error' => 'Not logged in']);
    exit;
}
if (!isset($_SESSION['accountStatus']) || !in_array((int)$_SESSION['accountStatus'], [1, 3], true)) {
    if (ob_get_length()) { ob_clean(); }
    echo json_encode(['status' => 'error', 'error' => 'Not authorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = $_POST;

$entityType = (string)($input['entity_type'] ?? '');
$entityId = (int)($input['entity_id'] ?? 0);
$tagId = (int)($input['tag_id'] ?? 0);
$action = (string)($input['action'] ?? '');

// Validate entity type
$allowedTypes = ['lead', 'task', 'project', 'invoice'];
if (!in_array($entityType, $allowedTypes)) {
    if (ob_get_length()) { ob_clean(); }
    echo json_encode(['status' => 'error', 'error' => 'Invalid entity type']);
    exit;
}

if ($entityId <= 0 || $tagId <= 0 || ($action !== 'add' && $action !== 'remove')) {
    if (ob_get_length()) { ob_clean(); }
    echo json_encode(['status' => 'error', 'error' => 'Missing required parameters']);
    exit;
}

// Tag name for activity/notification message
$tagName = '';
$st = $connect->prepare("SELECT name FROM tags WHERE id=? LIMIT 1");
if ($st) {
    $st->bind_param('i', $tagId);
    $st->execute();
    $r = $st->get_result();
    $row = $r ? $r->fetch_assoc() : null;
    $tagName = (string)($row['name'] ?? '');
    $st->close();
}

if ($action === 'add') {
    $stmt = $connect->prepare("INSERT IGNORE INTO taggables (tag_id, entity_type, entity_id) VALUES (?, ?, ?)");
    $stmt->bind_param('isi', $tagId, $entityType, $entityId);
    $ok = $stmt->execute();
    $stmt->close();
    
    // Record activity and send notifications (entity-specific)
    if ($ok && $entityType === 'lead') {
        try {
            $__leadAct = __DIR__ . "/../leads/activity_helper.php";
            if (is_file($__leadAct)) {
                require_once($__leadAct);
                $actorId = (int)$session->userId;
                $title = $lang['Tags'] ?? 'Tags';
                $msg = $tagName !== '' ? ("Tag added: " . $tagName) : "Tag added";
                recordLeadActivity($entityId, $actorId, 'lead_tag_added', $title, $msg, ['tag_id' => (string)$tagId, 'tag_name' => $tagName]);
                notifyLeadUsers($entityId, $actorId, 'lead_tag_added', $title, $msg);
            }
        } catch (Exception $e) {}
    }
    // TODO: Add activity/notification for other entity types (task, project, invoice) when implemented
    
    if (ob_get_length()) { ob_clean(); }
    echo json_encode(['status' => $ok ? 'ok' : 'error', 'error' => $ok ? null : 'Failed to add tag']);
    exit;
}

if ($action === 'remove') {
    $stmt = $connect->prepare("DELETE FROM taggables WHERE tag_id=? AND entity_type=? AND entity_id=?");
    $stmt->bind_param('isi', $tagId, $entityType, $entityId);
    $ok = $stmt->execute();
    $stmt->close();
    
    // Record activity and send notifications (entity-specific)
    if ($ok && $entityType === 'lead') {
        try {
            $__leadAct = __DIR__ . "/../leads/activity_helper.php";
            if (is_file($__leadAct)) {
                require_once($__leadAct);
                $actorId = (int)$session->userId;
                $title = $lang['Tags'] ?? 'Tags';
                $msg = $tagName !== '' ? ("Tag removed: " . $tagName) : "Tag removed";
                recordLeadActivity($entityId, $actorId, 'lead_tag_removed', $title, $msg, ['tag_id' => (string)$tagId, 'tag_name' => $tagName]);
                notifyLeadUsers($entityId, $actorId, 'lead_tag_removed', $title, $msg);
            }
        } catch (Exception $e) {}
    }
    // TODO: Add activity/notification for other entity types (task, project, invoice) when implemented
    
    if (ob_get_length()) { ob_clean(); }
    echo json_encode(['status' => $ok ? 'ok' : 'error', 'error' => $ok ? null : 'Failed to remove tag']);
    exit;
}

if (ob_get_length()) { ob_clean(); }
echo json_encode(['status' => 'error', 'error' => 'Unsupported action']);
exit;

