<?php
require_once(__DIR__ . "/includes/lib-initialize.php");

$role = isset($_GET['role']) ? strtolower(trim((string)$_GET['role'])) : '';
$slug = isset($_GET['slug']) ? strtolower(trim((string)$_GET['slug'])) : '';

$allowedRoles = array('admin', 'staff', 'client');
if (!in_array($role, $allowedRoles, true) || $slug === '') {
    http_response_code(404);
    exit('Page not found');
}

$routes = get_menu_routes_for_role($role);
$target = null;

foreach ($routes as $route) {
    if (!empty($route['slug']) && $route['slug'] === $slug) {
        $target = $route['target'];
        break;
    }
}

if ($target === null) {
    http_response_code(404);
    exit('Page not found');
}

$_SERVER['SCRIPT_NAME'] = '/' . $role . '/' . $target;
$_SERVER['PHP_SELF'] = '/' . $role . '/' . $target;
$targetDir = __DIR__ . '/' . $role;
$originalCwd = getcwd();
if ($originalCwd !== false) {
    chdir($targetDir);
}
require_once($targetDir . '/' . $target);
if ($originalCwd !== false) {
    chdir($originalCwd);
}
?>
