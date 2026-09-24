<?php
/*
================================================================================
  Global Tags – Fetch All Tags (for dropdowns)
  Location: includes/tags/tags_list.php
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

// Tags (available grouped by category)
$tagsByCategory = [];
$catRes = mysqli_query($connect, "SELECT * FROM tag_categories ORDER BY sort_order ASC, id ASC");
if ($catRes) {
    while ($cat = mysqli_fetch_assoc($catRes)) {
        $cid = (int)$cat['id'];
        $tagRows = [];
        $tagRes = mysqli_query($connect, "SELECT * FROM tags WHERE category_id=$cid ORDER BY id ASC");
        if ($tagRes) {
            while ($t = mysqli_fetch_assoc($tagRes)) $tagRows[] = $t;
        }
        $tagsByCategory[] = [
            'category_id' => $cid,
            'category_name' => $cat['name'],
            'tags' => array_map(function ($t) {
                return ['id' => (int)$t['id'], 'name' => $t['name'], 'color_class' => $t['color_class'] ?? ''];
            }, $tagRows)
        ];
    }
}

if (ob_get_length()) { ob_clean(); }
echo json_encode([
    'status' => 'ok',
    'data' => [
        'tags_by_category' => $tagsByCategory,
    ],
]);
exit;

