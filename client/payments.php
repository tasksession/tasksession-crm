<?php
require_once __DIR__ . '/../includes/lib-initialize.php';
if (!isset($session) || !$session->isLoggedIn()) {
    redirectTo(rtrim((string)$url, '/') . '/');
    exit;
}
redirectTo(tasksession_free_role_dashboard_url());
exit;
