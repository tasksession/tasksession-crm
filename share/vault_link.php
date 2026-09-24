<?php
/**
 * @deprecated Use share/media.php — permanent redirect keeps old bookmarks working.
 */
$q = isset($_SERVER['QUERY_STRING']) ? (string)$_SERVER['QUERY_STRING'] : '';
$target = 'media.php' . ($q !== '' ? '?' . $q : '');
header('Location: ' . $target, true, 301);
exit;
