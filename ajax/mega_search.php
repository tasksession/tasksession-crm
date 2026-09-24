<?php
/**
 * Mega search AJAX — instant cross-module search.
 */
if (!defined('CRM_LIGHTWEIGHT_INIT')) {
    define('CRM_LIGHTWEIGHT_INIT', true);
}
ob_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/lib-initialize.php';
require_once __DIR__ . '/../includes/mega_search_helper.php';
require_once __DIR__ . '/../includes/mega_search_render.php';

if (!$session->isLoggedIn()) {
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$accountStatus = isset($_SESSION['accountStatus']) ? (int) $_SESSION['accountStatus'] : 0;
if (!in_array($accountStatus, [1, 2, 3], true)) {
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$tab = isset($_GET['tab']) ? trim((string) $_GET['tab']) : 'people';
$loadMore = !empty($_GET['load_more']);
$offset = isset($_GET['offset']) ? max(0, (int) $_GET['offset']) : 0;

$ctx = comon_mega_search_context($session);

if ($loadMore) {
    if ($q === '' || $tab === '' || empty($ctx['tabs'][$tab]['enabled'])) {
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'Invalid request']);
        exit;
    }

    $data = comon_mega_search_fetch_tab($tab, $q, $ctx, $offset);
    $loadedAfter = $offset + count($data['rows']);
    $total = (int) ($data['count'] ?? 0);
    $rowNoStart = $offset + 1;
    if ($tab === 'leads') {
        $rowsHtml = mega_search_render_rows_fragment($tab, $data['rows'], $ctx, $rowNoStart);
    } else {
        $rowsHtml = mega_search_render_rows_fragment($tab, $data['rows'], $ctx);
    }

    ob_clean();
    echo json_encode([
        'success' => true,
        'q' => $q,
        'tab' => $tab,
        'rows_html' => $rowsHtml,
        'load_more_html' => mega_search_render_load_more_html($tab, $q, $ctx, $total, $loadedAfter),
        'has_more' => $loadedAfter < $total,
        'next_offset' => $loadedAfter,
        'tbody_selector' => mega_search_tbody_selector($tab),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$tabs = comon_mega_search_run($q, $tab, $ctx);

$activeTab = $tab;
if ($activeTab === '' || empty($ctx['tabs'][$activeTab]['enabled'])) {
    foreach ($ctx['tabs'] as $key => $t) {
        if (!empty($t['enabled'])) {
            $activeTab = $key;
            break;
        }
    }
}

ob_clean();
echo json_encode([
    'success' => true,
    'q' => $q,
    'active_tab' => $activeTab,
    'placeholder' => comon_mega_search_placeholder($ctx),
    'tabs' => $tabs,
], JSON_UNESCAPED_UNICODE);
