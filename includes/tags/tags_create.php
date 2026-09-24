<?php
/*
================================================================================
  Global Tags – Create Tag Instantly (AJAX)
  Location: includes/tags/tags_create.php
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

$tagName = trim($input['tag_name'] ?? '');
$categoryId = (int)($input['category_id'] ?? 0);

if ($tagName === '') {
    if (ob_get_length()) { ob_clean(); }
    echo json_encode(['status' => 'error', 'error' => 'Tag name is required']);
    exit;
}

// If no category specified, use the first available category
if ($categoryId <= 0) {
    $catRes = mysqli_query($connect, "SELECT id FROM tag_categories ORDER BY sort_order ASC, id ASC LIMIT 1");
    if ($catRes && $row = mysqli_fetch_assoc($catRes)) {
        $categoryId = (int)$row['id'];
    } else {
        // No categories exist, create a default one
        $stmt = $connect->prepare("INSERT INTO tag_categories (name, sort_order) VALUES (?, 0)");
        $defaultCatName = 'General';
        $stmt->bind_param('s', $defaultCatName);
        $stmt->execute();
        $categoryId = (int)$connect->insert_id;
        $stmt->close();
    }
}

// Check if tag already exists in this category
$checkStmt = $connect->prepare("SELECT id, name, color_class FROM tags WHERE category_id=? AND name=? LIMIT 1");
$checkStmt->bind_param('is', $categoryId, $tagName);
$checkStmt->execute();
$checkRes = $checkStmt->get_result();
if ($checkRes && $existing = $checkRes->fetch_assoc()) {
    $checkStmt->close();
    if (ob_get_length()) { ob_clean(); }
    echo json_encode([
        'status' => 'ok',
        'data' => [
            'tag' => [
                'id' => (int)$existing['id'],
                'name' => $existing['name'],
                'color_class' => $existing['color_class'] ?? ''
            ]
        ]
    ]);
    exit;
}
$checkStmt->close();

// Create new tag
$colorClass = 'badge tags-bg'; // Default color
$stmt = $connect->prepare("INSERT INTO tags (category_id, name, color_class) VALUES (?, ?, ?)");
$stmt->bind_param('iss', $categoryId, $tagName, $colorClass);
$ok = $stmt->execute();
$newTagId = $ok ? (int)$connect->insert_id : 0;
$stmt->close();

if (!$ok || $newTagId <= 0) {
    if (ob_get_length()) { ob_clean(); }
    echo json_encode(['status' => 'error', 'error' => 'Failed to create tag']);
    exit;
}

if (ob_get_length()) { ob_clean(); }
echo json_encode([
    'status' => 'ok',
    'data' => [
        'tag' => [
            'id' => $newTagId,
            'name' => $tagName,
            'color_class' => $colorClass
        ]
    ]
]);
exit;

