<?php
require_once __DIR__ . '/../includes/lib-initialize.php';
if (function_exists('tasksession_deny_pro_api')) { tasksession_deny_pro_api('media_vault'); }
http_response_code(403);
header('Content-Type: application/json');
echo json_encode(['success'=>false,'message'=>'Media Vault requires TaskSession Pro.']);
exit;
