<?php
/**
 * Serves uploaded custom webfonts (bypasses uploads/.htaccess deny rules).
 * URL (relative to this folder): font-serve.php?id=123&v=filemtime
 */
define('DS', DIRECTORY_SEPARATOR);
define('SITE_ROOT', dirname(dirname(__DIR__)));

require_once SITE_ROOT . DS . 'includes' . DS . 'database.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id < 1) {
    header('HTTP/1.0 404 Not Found');
    exit;
}

$res = $db->query('SELECT file_path FROM custom_fonts WHERE id=' . $id . ' LIMIT 1');
$row = $res ? $res->fetch_assoc() : null;
if (!$row) {
    header('HTTP/1.0 404 Not Found');
    exit;
}
$rel = isset($row['file_path']) ? str_replace('\\', '/', (string)$row['file_path']) : '';
$rel = ltrim($rel, '/');

if ($rel === '' || !preg_match('#^uploads/custom-fonts/[a-zA-Z0-9_.-]+$#', $rel)) {
    header('HTTP/1.0 403 Forbidden');
    exit;
}

$local = SITE_ROOT . DS . str_replace('/', DS, $rel);
$realFile = realpath($local);
$allowedDir = realpath(SITE_ROOT . DS . 'uploads' . DS . 'custom-fonts');

if ($realFile === false || $allowedDir === false || strpos($realFile, $allowedDir) !== 0) {
    header('HTTP/1.0 403 Forbidden');
    exit;
}

$ext = strtolower(pathinfo($realFile, PATHINFO_EXTENSION));
if (!in_array($ext, array('woff', 'woff2'), true)) {
    header('HTTP/1.0 403 Forbidden');
    exit;
}

$mime = ($ext === 'woff2') ? 'font/woff2' : 'font/woff';

header('Content-Type: ' . $mime);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=604800');
header('Content-Length: ' . (string)filesize($realFile));

readfile($realFile);
exit;
