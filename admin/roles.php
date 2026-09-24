<?php
if (isset($_GET['edit']) && isset($_GET['modal']) && $_GET['modal'] == 1) {
    require_once("../includes/lib-initialize.php");
    $edit_id = intval($_GET['edit']);
    $res = $connect->query("SELECT * FROM roles WHERE id=$edit_id");
    $edit_role = $res->fetch_assoc();
    // Get ALL permissions for this role (including value = 0)
    $res2 = $connect->query("SELECT permission_key, value FROM role_permissions WHERE role_id=$edit_id");
    $edit_permissions = [];
    if ($res2) {
        while ($row = $res2->fetch_assoc()) {
            // Ensure value is cast to int (0 or 1) for proper comparison
            // Store both 0 and 1 values so JavaScript can properly set checkboxes
            $edit_permissions[$row['permission_key']] = (int)$row['value'];
        }
    }
    // Debug: Log what permissions were loaded
    error_log("Edit Role - Role ID: $edit_id");
    error_log("Edit Role - Permissions loaded: " . json_encode($edit_permissions));
    header('Content-Type: application/json');
    echo json_encode([
        'id' => $edit_role['id'],
        'name' => $edit_role['name'],
        'description' => $edit_role['description'],
        'permissions' => $edit_permissions
    ]);
    exit;
}

ob_start();
include("../includes/lib-initialize.php");
require_once __DIR__ . '/../includes/addon_registry.php';
$ecommerceModuleEnabledForRoles = comon_ecommerce_module_enabled();
require_once __DIR__ . '/../includes/marketing_module_gate.php';
$marketingModuleEnabledForRoles = comon_marketing_module_enabled();
$__aiGate = __DIR__ . '/../includes/ai_module_gate.php';
$aiModuleEnabledForRoles = false;
if (is_file($__aiGate)) {
    require_once $__aiGate;
    $aiModuleEnabledForRoles = function_exists('comon_ai_module_enabled') && comon_ai_module_enabled();
}
$title = "Roles & Permissions | ".$syatem_title;
include("../templates/header.php");

if (! $session->isLoggedIn()) {
    redirectTo($url . "index");
}
if ($_SESSION['accountStatus'] == 2) {
    redirectTo($url . "client/index");
}
if ($_SESSION['accountStatus'] == 3) {
    redirectTo($url . "staff/index");
}

// load current logged-in user
$id = $session->userId;
$user = User::findById((int)$id);
$username = $user->firstName;
$email = $user->email;
$account_stat = $user->status;
// --- Permission keys grouped with descriptions ---
$permission_groups = [
    'Project Permissions' => [
        'project_create' => [
            'label' => 'Create new projects',
            'description' => 'Allow user to create new projects in the system'
        ],
        'project_edit' => [
            'label' => 'Edit project',
            'description' => 'Allow user to modify existing project details and settings'
        ],
        'project_delete' => [
            'label' => 'Delete project',
            'description' => 'Allow user to permanently remove projects from the system'
        ],
        'project_view_all' => [
            'label' => 'View other projects',
            'description' => 'Allow user to see and access projects they are not assigned to'
        ],
        'project_media_view' => [
            'label' => 'View files & media',
            'description' => 'Allow user to view files and media in project media'
        ],
        'project_media_download' => [
            'label' => 'Download files',
            'description' => 'Allow user to download files from project media'
        ],
        'project_media_upload' => [
            'label' => 'Upload files',
            'description' => 'Allow user to upload files to project media'
        ],
        'project_media_edit' => [
            'label' => 'Edit / rename',
            'description' => 'Allow user to edit or rename their own files'
        ],
        'project_media_edit_others' => [
            'label' => "Edit / rename other users' files",
            'description' => 'Allow user to edit or rename files uploaded by others'
        ],
        'project_media_delete' => [
            'label' => 'Delete files',
            'description' => 'Allow user to delete their own files'
        ],
        'project_media_delete_others' => [
            'label' => "Delete other users' files",
            'description' => 'Allow user to delete files uploaded by others'
        ],
        'project_media_edit_folder_permissions' => [
            'label' => 'Edit folder permissions',
            'description' => 'Allow user to open Edit Permission modal and manage folder-level media permission overrides'
        ],
    ],
    'Milestone Permissions' => [
        'milestone_view' => [
            'label' => 'View payments',
            'description' => 'Allow user to view payment history and invoice details'
        ],
        'milestone_create' => [
            'label' => 'Create new invoices',
            'description' => 'Allow user to create new payment milestones and invoices'
        ],
        'milestone_delete' => [
            'label' => 'Delete invoice',
            'description' => 'Allow user to remove invoices and payment records'
        ],
        'milestone_update' => [
            'label' => 'Update invoice',
            'description' => 'Allow user to modify existing invoice details and payment status'
        ],
    ],
    'Task Permissions' => [
        'task_create' => [
            'label' => 'Create new tasks',
            'description' => 'Allow user to create new tasks within projects'
        ],
        'task_delete' => [
            'label' => 'Delete task',
            'description' => 'Allow user to permanently remove tasks from projects'
        ],
        'task_status_update' => [
            'label' => 'Change task status',
            'description' => 'Allow user to update task progress (todo, in progress, review, done)'
        ],
        'task_edit' => [
            'label' => 'Edit task',
            'description' => 'Allow user to modify task details, descriptions, and settings'
        ],
        'task_assign_members' => [
            'label' => 'Assign members',
            'description' => 'Allow user to assign team members to tasks'
        ],
        'task_duplicate' => [
            'label' => 'Duplicate task',
            'description' => 'Allow user to create copies of existing tasks'
        ],
        'task_view_all' => [
            'label' => 'View other tasks',
            'description' => 'Allow user to see tasks they are not assigned to'
        ],
        'timer_log_view_others' => [
            'label' => "Show other users' time logs",
            'description' => 'Allow staff to see time entries logged by other users on the task (sidebar timer tab)'
        ],
        'timer_log_edit_own' => [
            'label' => 'Edit own timer log',
            'description' => "Allow staff to edit their own time entries on tasks they can access"
        ],
        'timer_log_delete_own' => [
            'label' => 'Delete own timer log',
            'description' => "Allow staff to delete their own time entries on tasks they can access"
        ],
        'timer_log_show_manual' => [
            'label' => 'Show manual time entry',
            'description' => "Allow staff to use the Manual entry tab when logging or completing time (From timer tab remains available)"
        ],
        'timer_log_edit' => [
            'label' => "Edit other users' timer log",
            'description' => "Allow staff to edit another user's time entry (not their own)"
        ],
        'timer_log_delete' => [
            'label' => "Delete other users' timer log",
            'description' => "Allow staff to delete another user's time entry (not their own)"
        ],
    ],
    'Lead Permissions' => [
        'lead_create' => [
            'label' => 'Create new lead',
            'description' => 'Allow user to create new leads in the system'
        ],
        'lead_delete' => [
            'label' => 'Delete lead',
            'description' => 'Allow user to permanently remove leads from the system'
        ],
        'lead_status_update' => [
            'label' => 'Change lead status',
            'description' => 'Allow user to update lead status through drag-and-drop or status changes'
        ],
        'lead_edit' => [
            'label' => 'Edit lead',
            'description' => 'Allow user to modify lead details, descriptions, and settings'
        ],
        'lead_view_all' => [
            'label' => 'View other user leads',
            'description' => 'Allow user to see leads they are not assigned to'
        ],
        'lead_custom_columns' => [
            'label' => 'Create custom columns',
            'description' => 'Allow user to create and manage custom lead status columns'
        ],
        'lead_export' => [
            'label' => 'Export leads',
            'description' => 'Allow user to export leads to CSV format'
        ],
        'lead_import' => [
            'label' => 'Import leads',
            'description' => 'Allow user to import leads from CSV files'
        ],
    ],
    'Account Permissions' => [
        'staff_create' => [
            'label' => 'Create new staff members',
            'description' => 'Allow user to add new staff accounts to the system'
        ],
        'staff_profile_update' => [
            'label' => 'Update staff profile',
            'description' => 'Allow user to modify staff member information and settings'
        ],
        'staff_profile_delete' => [
            'label' => 'Delete staff members',
            'description' => 'Allow user to remove staff accounts from the system'
        ],
        'staff_view' => [
            'label' => 'View all staff members',
            'description' => 'Allow user to see the list of all staff members'
        ],
        'client_create' => [
            'label' => 'Create new client',
            'description' => 'Allow user to add new client accounts to the system'
        ],
        'client_delete' => [
            'label' => 'Delete client',
            'description' => 'Allow user to remove client accounts from the system'
        ],
        'client_profile_update' => [
            'label' => 'Update client profile',
            'description' => 'Allow user to modify client information and settings'
        ],
        'client_view' => [
            'label' => 'View all clients',
            'description' => 'Allow user to see the list of all client accounts'
        ],
    ],
    'Email Permissions' => [
        'email_inbox_view' => [
            'label' => 'Show inbox',
            'description' => 'Allow user to view their email inbox'
        ],
        'email_send' => [
            'label' => 'Send new email',
            'description' => 'Allow user to compose and send new emails'
        ],
        'email_delete' => [
            'label' => 'Delete email',
            'description' => 'Allow user to delete emails from their account'
        ],
        'email_archive' => [
            'label' => 'Archive email',
            'description' => 'Allow user to archive emails'
        ],
        'email_forward' => [
            'label' => 'Forward email',
            'description' => 'Allow user to forward emails to other recipients'
        ],
        'email_reply' => [
            'label' => 'Reply to email',
            'description' => 'Allow user to reply to received emails'
        ],
        'email_attach' => [
            'label' => 'Attach files',
            'description' => 'Allow user to attach files to emails'
        ],
        'email_account_manage' => [
            'label' => 'Manage email accounts',
            'description' => 'Allow user to add, edit, or remove email accounts'
        ],
        'email_settings' => [
            'label' => 'Email settings',
            'description' => 'Allow user to configure email account settings'
        ],
    ],
    'Attendance Permissions' => [
        'attendance_view' => [
            'label' => 'View attendance',
            'description' => 'Allow user to open attendance pages and view attendance data'
        ],
        'attendance_manage' => [
            'label' => 'Manage attendance',
            'description' => 'Allow user to create and update attendance records manually'
        ],
        'attendance_checkin' => [
            'label' => 'Attendance check in',
            'description' => 'Allow user to mark attendance check-in'
        ],
        'attendance_checkout' => [
            'label' => 'Attendance check out',
            'description' => 'Allow user to mark attendance check-out'
        ],
        'attendance_shift_manage' => [
            'label' => 'Manage attendance shifts',
            'description' => 'Allow user to create shifts and assign them to staff'
        ],
        'attendance_leave_request' => [
            'label' => 'Request leave',
            'description' => 'Allow user to submit attendance leave requests'
        ],
        'attendance_leave_approve' => [
            'label' => 'Approve leave',
            'description' => 'Allow user to approve or reject leave requests'
        ],
        'attendance_regularization_request' => [
            'label' => 'Request attendance regularization',
            'description' => 'Allow user to request missed punch or time correction'
        ],
        'attendance_regularization_approve' => [
            'label' => 'Approve attendance regularization',
            'description' => 'Allow user to approve or reject attendance regularization requests'
        ],
        'attendance_holiday_manage' => [
            'label' => 'Manage attendance holidays',
            'description' => 'Allow user to create and manage holiday calendar entries'
        ],
        'attendance_overtime_view' => [
            'label' => 'View overtime',
            'description' => 'Allow user to access overtime records and placeholder page'
        ],
        'attendance_reports_view' => [
            'label' => 'View attendance reports',
            'description' => 'Allow user to access attendance reports and analytics'
        ],
        'attendance_payroll_export' => [
            'label' => 'Export attendance payroll',
            'description' => 'Allow user to generate payroll-ready attendance exports'
        ],
        'attendance_settings_manage' => [
            'label' => 'Manage attendance settings',
            'description' => 'Allow user to configure attendance policies and locks'
        ],
    ],
];

if (!empty($ecommerceModuleEnabledForRoles)) {
    $permission_groups['Ecommerce Permissions'] = [
        'ecommerce_access' => [
            'label' => 'permission_ecommerce_access',
            'description' => 'permission_ecommerce_access_desc',
        ],
        'ecommerce_dashboard_view' => [
            'label' => 'permission_ecommerce_dashboard_view',
            'description' => 'permission_ecommerce_dashboard_view_desc',
        ],
        'ecommerce_orders_view' => [
            'label' => 'permission_ecommerce_orders_view',
            'description' => 'permission_ecommerce_orders_view_desc',
        ],
        'ecommerce_orders_edit' => [
            'label' => 'permission_ecommerce_orders_edit',
            'description' => 'permission_ecommerce_orders_edit_desc',
        ],
        'ecommerce_orders_sync' => [
            'label' => 'permission_ecommerce_orders_sync',
            'description' => 'permission_ecommerce_orders_sync_desc',
        ],
        'ecommerce_customers_view' => [
            'label' => 'permission_ecommerce_customers_view',
            'description' => 'permission_ecommerce_customers_view_desc',
        ],
        'ecommerce_customers_edit' => [
            'label' => 'permission_ecommerce_customers_edit',
            'description' => 'permission_ecommerce_customers_edit_desc',
        ],
        'ecommerce_products_view' => [
            'label' => 'permission_ecommerce_products_view',
            'description' => 'permission_ecommerce_products_view_desc',
        ],
        'ecommerce_products_edit' => [
            'label' => 'permission_ecommerce_products_edit',
            'description' => 'permission_ecommerce_products_edit_desc',
        ],
        'ecommerce_settings_manage' => [
            'label' => 'permission_ecommerce_settings_manage',
            'description' => 'permission_ecommerce_settings_manage_desc',
        ],
        'ecommerce_logs_view' => [
            'label' => 'permission_ecommerce_logs_view',
            'description' => 'permission_ecommerce_logs_view_desc',
        ],
        'ecommerce_queue_manage' => [
            'label' => 'permission_ecommerce_queue_manage',
            'description' => 'permission_ecommerce_queue_manage_desc',
        ],
        'ecommerce_field_mapping_manage' => [
            'label' => 'permission_ecommerce_field_mapping_manage',
            'description' => 'permission_ecommerce_field_mapping_manage_desc',
        ],
        'ecommerce_custom_fields_manage' => [
            'label' => 'permission_ecommerce_custom_fields_manage',
            'description' => 'permission_ecommerce_custom_fields_manage_desc',
        ],
        'ecommerce_webhooks_manage' => [
            'label' => 'permission_ecommerce_webhooks_manage',
            'description' => 'permission_ecommerce_webhooks_manage_desc',
        ],
        'ecommerce_cron_manage' => [
            'label' => 'permission_ecommerce_cron_manage',
            'description' => 'permission_ecommerce_cron_manage_desc',
        ],
        'ecommerce_api_manage' => [
            'label' => 'permission_ecommerce_api_manage',
            'description' => 'permission_ecommerce_api_manage_desc',
        ],
        'ecommerce_debug_manage' => [
            'label' => 'permission_ecommerce_debug_manage',
            'description' => 'permission_ecommerce_debug_manage_desc',
        ],
    ];
}

if (!empty($marketingModuleEnabledForRoles)) {
    $permission_groups['Marketing Permissions'] = [
        'marketing_access' => [
            'label' => 'permission_marketing_access',
            'description' => 'permission_marketing_access_desc',
        ],
        'marketing_dashboard_view' => [
            'label' => 'permission_marketing_dashboard_view',
            'description' => 'permission_marketing_dashboard_view_desc',
        ],
        'marketing_campaigns_view' => [
            'label' => 'permission_marketing_campaigns_view',
            'description' => 'permission_marketing_campaigns_view_desc',
        ],
        'marketing_campaigns_edit' => [
            'label' => 'permission_marketing_campaigns_edit',
            'description' => 'permission_marketing_campaigns_edit_desc',
        ],
        'marketing_campaigns_send' => [
            'label' => 'permission_marketing_campaigns_send',
            'description' => 'permission_marketing_campaigns_send_desc',
        ],
        'marketing_templates_manage' => [
            'label' => 'permission_marketing_templates_manage',
            'description' => 'permission_marketing_templates_manage_desc',
        ],
        'marketing_recipients_manage' => [
            'label' => 'permission_marketing_recipients_manage',
            'description' => 'permission_marketing_recipients_manage_desc',
        ],
        'marketing_settings_manage' => [
            'label' => 'permission_marketing_settings_manage',
            'description' => 'permission_marketing_settings_manage_desc',
        ],
    ];
}

if (!empty($aiModuleEnabledForRoles)) {
    $permission_groups['AI Assistant Permissions'] = [
        'ai_access' => [
            'label' => 'permission_ai_access',
            'description' => 'permission_ai_access_desc',
        ],
        'ai_settings_manage' => [
            'label' => 'permission_ai_settings_manage',
            'description' => 'permission_ai_settings_manage_desc',
        ],
        'ai_workspace_search' => [
            'label' => 'permission_ai_workspace_search',
            'description' => 'permission_ai_workspace_search_desc',
        ],
        'ai_speech' => [
            'label' => 'permission_ai_speech',
            'description' => 'permission_ai_speech_desc',
        ],
        'ai_voice_chat' => [
            'label' => 'permission_ai_voice_chat',
            'description' => 'permission_ai_voice_chat_desc',
        ],
        'ai_voice' => [
            'label' => 'permission_ai_voice',
            'description' => 'permission_ai_voice_desc',
        ],
        'ai_upload' => [
            'label' => 'permission_ai_upload',
            'description' => 'permission_ai_upload_desc',
        ],
        'ai_generate_images' => [
            'label' => 'permission_ai_generate_images',
            'description' => 'permission_ai_generate_images_desc',
        ],
        'ai_image_gen' => [
            'label' => 'permission_ai_image_gen',
            'description' => 'permission_ai_image_gen_desc',
        ],
        'ai_email_tools' => [
            'label' => 'permission_ai_email_tools',
            'description' => 'permission_ai_email_tools_desc',
        ],
        'ai_email_send' => [
            'label' => 'permission_ai_email_send',
            'description' => 'permission_ai_email_send_desc',
        ],
        'ai_task_tools' => [
            'label' => 'permission_ai_task_tools',
            'description' => 'permission_ai_task_tools_desc',
        ],
        'ai_project_tools' => [
            'label' => 'permission_ai_project_tools',
            'description' => 'permission_ai_project_tools_desc',
        ],
        'ai_client_tools' => [
            'label' => 'permission_ai_client_tools',
            'description' => 'permission_ai_client_tools_desc',
        ],
        'ai_invoice_tools' => [
            'label' => 'permission_ai_invoice_tools',
            'description' => 'permission_ai_invoice_tools_desc',
        ],
        'ai_lead_tools' => [
            'label' => 'permission_ai_lead_tools',
            'description' => 'permission_ai_lead_tools_desc',
        ],
        'ai_lead_send' => [
            'label' => 'permission_ai_lead_send',
            'description' => 'permission_ai_lead_send_desc',
        ],
        'ai_chat_history' => [
            'label' => 'permission_ai_chat_history',
            'description' => 'permission_ai_chat_history_desc',
        ],
        'ai_pin_chats' => [
            'label' => 'permission_ai_pin_chats',
            'description' => 'permission_ai_pin_chats_desc',
        ],
        'ai_archive_chats' => [
            'label' => 'permission_ai_archive_chats',
            'description' => 'permission_ai_archive_chats_desc',
        ],
        'ai_temporary_chat' => [
            'label' => 'permission_ai_temporary_chat',
            'description' => 'permission_ai_temporary_chat_desc',
        ],
        'ai_agents' => [
            'label' => 'permission_ai_agents',
            'description' => 'permission_ai_agents_desc',
        ],
        'ai_agents_manage' => [
            'label' => 'permission_ai_agents_manage',
            'description' => 'permission_ai_agents_manage_desc',
        ],
        'ai_fields_view' => [
            'label' => 'permission_ai_fields_view',
            'description' => 'permission_ai_fields_view_desc',
        ],
        'ai_daily_brief' => [
            'label' => 'permission_ai_daily_brief',
            'description' => 'permission_ai_daily_brief_desc',
        ],
        'ai_deep_search' => [
            'label' => 'permission_ai_deep_search',
            'description' => 'permission_ai_deep_search_desc',
        ],
        'ai_external_integration' => [
            'label' => 'permission_ai_external_integration',
            'description' => 'permission_ai_external_integration_desc',
        ],
    ];
}

// --- Handle CRUD ---
/** @var array{type:string,msg:string}|null assets/js/toast.js reads window.__toastFlash */
$toast_flash = null;
$edit_mode = false;
$edit_role = null;
$edit_permissions = [];

// Add or update role
if (isset($_POST['save_role'])) {
    $role_name = trim($_POST['role_name'] ?? '');
    $role_desc = trim($_POST['role_description'] ?? '');
    $permissions = $_POST['permissions'] ?? [];
    $role_id = $_POST['role_id'] ?? null;
    
    // Debug: Log what permissions were received
    error_log("=== ROLE SAVE DEBUG ===");
    error_log("Role Save - Role ID: " . $role_id);
    error_log("Role Save - Permissions received (checked only): " . json_encode($permissions));
    error_log("Role Save - Total checked permissions: " . count($permissions));
    if ($role_name == '') {
        $toast_flash = array('type' => 'error', 'msg' => 'Role name is required.');
    } else {
        if ($role_id) {
            // Update
            $stmt = $connect->prepare("UPDATE roles SET name=?, description=? WHERE id=?");
            $stmt->bind_param('ssi', $role_name, $role_desc, $role_id);
            $stmt->execute();
            $stmt->close();
            // Remove old permissions - CRITICAL: Must delete before inserting new ones
            // First, check how many permissions exist before deletion
            $checkBefore = $connect->query("SELECT COUNT(*) as count FROM role_permissions WHERE role_id=".intval($role_id));
            $beforeCount = 0;
            if ($checkBefore) {
                $row = $checkBefore->fetch_assoc();
                $beforeCount = (int)$row['count'];
                error_log("Before DELETE: Found {$beforeCount} existing permissions for role {$role_id}");
            }
            
            $deleteResult = $connect->query("DELETE FROM role_permissions WHERE role_id=".intval($role_id));
            if (!$deleteResult) {
                error_log("ERROR: Failed to delete old permissions for role {$role_id}: " . $connect->error);
            } else {
                $deletedCount = $connect->affected_rows;
                error_log("After DELETE: Removed {$deletedCount} old permissions for role {$role_id}");
            }
            $new_id = $role_id;
            // Insert new permissions - INSERT ALL permissions (both checked=1 and unchecked=0)
            // Use INSERT ... ON DUPLICATE KEY UPDATE to handle any duplicates
            foreach ($permission_groups as $group => $perms) {
                foreach ($perms as $key => $perm_data) {
                    // Check if checkbox was checked (exists in POST array)
                    // IMPORTANT: Unchecked checkboxes don't send anything in POST, so isset() will be false
                    // This means $val will be 0 for unchecked checkboxes
                    $val = isset($permissions[$key]) ? 1 : 0;
                    
                    // Use INSERT ... ON DUPLICATE KEY UPDATE to handle duplicates
                    // This ensures we update if duplicate exists, or insert if new
                    $stmt = $connect->prepare("INSERT INTO role_permissions (role_id, permission_key, value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE value = ?");
                    if ($stmt) {
                        $stmt->bind_param('isii', $new_id, $key, $val, $val);
                        if (!$stmt->execute()) {
                            error_log("ERROR: Failed to insert/update permission {$key} for role {$new_id} with value {$val}: " . $stmt->error . " (Error code: " . $stmt->errno . ")");
                        } else {
                            error_log("Successfully inserted/updated permission {$key} for role {$new_id} with value {$val}");
                        }
                        $stmt->close();
                    } else {
                        error_log("ERROR: Failed to prepare statement for permission {$key}: " . $connect->error);
                    }
                }
            }
            // Redirect after update
            if (!function_exists('setup_guide_mark_visit')) {
                require_once dirname(__DIR__) . '/includes/setup_guide.php';
            }
            setup_guide_mark_visit('roles');
            header('Location: roles?message=updated');
            exit;
        } else {
            // Insert
            $stmt = $connect->prepare("INSERT INTO roles (name, description) VALUES (?, ?)");
            $stmt->bind_param('ss', $role_name, $role_desc);
            $stmt->execute();
            $new_id = $stmt->insert_id;
            $stmt->close();
            // Insert permissions - INSERT ALL permissions (both checked=1 and unchecked=0)
            // Use INSERT ... ON DUPLICATE KEY UPDATE to handle any duplicates
            foreach ($permission_groups as $group => $perms) {
                foreach ($perms as $key => $perm_data) {
                    // Check if checkbox was checked (exists in POST array)
                    // IMPORTANT: Unchecked checkboxes don't send anything in POST, so isset() will be false
                    // This means $val will be 0 for unchecked checkboxes
                    $val = isset($permissions[$key]) ? 1 : 0;
                    
                    // Use INSERT ... ON DUPLICATE KEY UPDATE to handle duplicates
                    $stmt = $connect->prepare("INSERT INTO role_permissions (role_id, permission_key, value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE value = ?");
                    if ($stmt) {
                        $stmt->bind_param('isii', $new_id, $key, $val, $val);
                        if (!$stmt->execute()) {
                            error_log("ERROR: Failed to insert/update permission {$key} for role {$new_id} with value {$val}: " . $stmt->error . " (Error code: " . $stmt->errno . ")");
                        } else {
                            error_log("Successfully inserted/updated permission {$key} for role {$new_id} with value {$val}");
                        }
                        $stmt->close();
                    } else {
                        error_log("ERROR: Failed to prepare statement for permission {$key}: " . $connect->error);
                    }
                }
            }
            // Redirect after insert
            if (!function_exists('setup_guide_mark_visit')) {
                require_once dirname(__DIR__) . '/includes/setup_guide.php';
            }
            setup_guide_mark_visit('roles');
            header('Location: roles?message=created');
            exit;
        }
    }
}
// Show notifications based on GET param
if (isset($_GET['message'])) {
    if ($_GET['message'] === 'created') {
        $toast_flash = array('type' => 'success', 'msg' => 'Role created successfully.');
    } elseif ($_GET['message'] === 'updated') {
        $toast_flash = array('type' => 'success', 'msg' => 'Role updated successfully.');
    } elseif ($_GET['message'] === 'deleted') {
        $toast_flash = array('type' => 'success', 'msg' => 'Role deleted.');
    }
}
// Delete role
if (isset($_GET['delete'])) {
    $del_id = intval($_GET['delete']);
    $connect->query("DELETE FROM roles WHERE id=$del_id");
    $connect->query("DELETE FROM role_permissions WHERE role_id=$del_id");
    // Redirect after delete
    header('Location: roles?message=deleted');
    exit;
}
// Fetch all roles
$roles = [];
$res = $connect->query("SELECT * FROM roles ORDER BY id DESC");
while ($row = $res->fetch_assoc()) {
    $roles[] = $row;
}

?>
	<div class="page-container">
		<div class="container-fluid">
			<div class="row row-eq-height">
				<?php  include("../templates/sidebar.php"); ?>
					<div class="page-content" style="padding-bottom:0;">
						<?php include('../templates/top-header.php'); ?>
							<div class="row system-wrap h-100">
								<?php include("../templates/system-nav.php"); ?>
							<div class="col-md-9 ss-right h-100">
                <h2 class="page-title mb-4"><?php echo $lang['Role Management']; ?>
				<button type="button" class="bigbutton ss-btn alert-savestn" id="createRoleBtn"><?php echo $lang['Create New']; ?></button></h2>
                <div class="row">
                    <div class="col-md-12">
                        <div>
                            <div>
							<div class="scroll-x">
                                <table class="table table-fancy">
                                    <thead>
                                        <tr>
                                            <th class="min-width-50"><?php echo $lang['No.']; ?></th>
                                            <th><?php echo $lang['Role']; ?></th>
                                            <th><?php echo $lang['Description']; ?></th>
                                            <th class="text-align-right"><?php echo $lang['Actions']; ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($roles)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center"><?php echo $lang['No roles found']; ?>.</td>
                                        </tr>
                                        <?php else: ?>
                                        <?php $no = 1; foreach($roles as $role): ?>
                                        <tr>
                                            <td><?= $no++ ?></td>
                                            <td><?= htmlspecialchars($role['name']) ?></td>
                                            <td class="max-width-250"><?= htmlspecialchars($role['description']) ?></td>
                                            <td class="d-flex col-gap-10 align-items-center justify-content-end">
                                             <div class="border-btn d-flex align-items-center flex-shrink-0"><a href="roles?delete=<?= $role['id'] ?>" onclick="return confirm('<?php echo $lang['Delete']; ?> this role?')"><?php echo $lang['Delete']; ?> </a></div>
                                             <div class="d-flex align-items-center flex-shrink-0"><a href="#" class="edit-role-btn secondary-btn-a" data-role-id="<?= $role['id'] ?>"><?php echo $lang['Edit Role']; ?></a></div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
						 </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="clearfix"></div>
        </div>
    </div>
</div>

<!-- Role Modal -->
<div id="roleModal" class="modal fade" tabindex="-1" aria-labelledby="roleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header d-flex align-items-center justify-content-between">
        <h4 class="card-title" id="roleModalLabel">Role</h4>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
		<?php echo ts_icon('close'); ?>
		</button>
      </div>
      <div class="modal-body modal-max-height">
        <form id="roleForm" method="post" action="roles">
          <input type="hidden" name="role_id" id="role_id">
		  <div class="bg-grey pd-20">
          <div class="form-group">
            <label><?php echo $lang['Role Name*']; ?></label>
            <input type="text" name="role_name" id="role_name" class="form-control" required>
          </div>
          <div class="form-group">
            <label><?php echo $lang['Description']; ?></label>
            <textarea name="role_description" id="role_description" class="form-control" style="min-height: 120px;"></textarea>
          </div>
		   </div>
		  <div class="pd-20 pt-0">
          <?php
          // Short tab labels for the role modal (group key => tab title)
          $role_tab_labels = [
              'Project Permissions' => $lang['Projects'] ?? 'Projects',
              'Milestone Permissions' => $lang['Invoices'] ?? 'Invoices',
              'Task Permissions' => $lang['Tasks'] ?? 'Tasks',
              'Lead Permissions' => $lang['Leads'] ?? 'Leads',
              'Email Permissions' => $lang['Email'] ?? 'Email',
              'Attendance Permissions' => $lang['Attendance'] ?? 'Attendance',
              'Ecommerce Permissions' => $lang['Ecommerce'] ?? 'Ecommerce',
              'Marketing Permissions' => $lang['Marketing'] ?? 'Marketing',
              'AI Assistant Permissions' => $lang['AI Assistant'] ?? 'AI',
          ];
          // Desired order: Clients → Projects → Tasks → Leads → Invoices → Email → Staff → Attendance → …
          $role_tab_order = [
              'clients',
              'Project Permissions',
              'Task Permissions',
              'Lead Permissions',
              'Milestone Permissions',
              'Email Permissions',
              'staff',
              'Attendance Permissions',
              'Ecommerce Permissions',
              'Marketing Permissions',
              'AI Assistant Permissions',
          ];
          $role_tabs_by_key = [];
          foreach ($permission_groups as $group => $perms) {
              if ($group === 'Account Permissions') {
                  continue;
              }
              $role_tabs_by_key[$group] = [
                  'id' => 'role-tab-' . preg_replace('/[^a-z0-9]+/i', '-', strtolower($group)),
                  'label' => $role_tab_labels[$group] ?? preg_replace('/\s+Permissions$/i', '', $group),
                  'group' => $group,
                  'perms' => $perms,
                  'kind' => 'group',
              ];
          }
          $role_tabs_by_key['staff'] = [
              'id' => 'role-tab-staff',
              'label' => $lang['Staff'] ?? 'Staff',
              'kind' => 'staff',
              'keys' => ['staff_view', 'staff_create', 'staff_profile_update', 'staff_profile_delete'],
          ];
          $role_tabs_by_key['clients'] = [
              'id' => 'role-tab-clients',
              'label' => $lang['Clients'] ?? 'Clients',
              'kind' => 'client',
              'keys' => ['client_view', 'client_create', 'client_delete', 'client_profile_update'],
          ];
          $role_tabs = [];
          foreach ($role_tab_order as $key) {
              if (!empty($role_tabs_by_key[$key])) {
                  $role_tabs[] = $role_tabs_by_key[$key];
                  unset($role_tabs_by_key[$key]);
              }
          }
          // Any remaining groups (future) append at end
          foreach ($role_tabs_by_key as $tab) {
              $role_tabs[] = $tab;
          }
          ?>
          <ul class="nav nav-tabs role-permission-tabs mb-3" id="rolePermissionTabs" role="tablist">
            <?php foreach ($role_tabs as $i => $tab): ?>
              <li class="nav-item" role="presentation">
                <button class="nav-link<?= $i === 0 ? ' active' : '' ?>"
                        id="<?= htmlspecialchars($tab['id'], ENT_QUOTES, 'UTF-8') ?>-btn"
                        data-bs-toggle="tab"
                        data-bs-target="#<?= htmlspecialchars($tab['id'], ENT_QUOTES, 'UTF-8') ?>"
                        type="button"
                        role="tab"
                        aria-controls="<?= htmlspecialchars($tab['id'], ENT_QUOTES, 'UTF-8') ?>"
                        aria-selected="<?= $i === 0 ? 'true' : 'false' ?>">
                  <?= htmlspecialchars($tab['label'], ENT_QUOTES, 'UTF-8') ?>
                </button>
              </li>
            <?php endforeach; ?>
          </ul>
          <div class="tab-content role-permission-tab-content" id="rolePermissionTabContent">
            <?php foreach ($role_tabs as $i => $tab): ?>
              <div class="tab-pane fade<?= $i === 0 ? ' show active' : '' ?>"
                   id="<?= htmlspecialchars($tab['id'], ENT_QUOTES, 'UTF-8') ?>"
                   role="tabpanel"
                   aria-labelledby="<?= htmlspecialchars($tab['id'], ENT_QUOTES, 'UTF-8') ?>-btn"
                   tabindex="0">
                <?php if (($tab['kind'] ?? '') === 'group'): ?>
                  <?php foreach ($tab['perms'] as $key => $perm_data): ?>
                    <div class="permission-item d-flex col-gap align-items-start mb-3">
                      <div class="checkbox-wrapper-6 mt-1">
                        <input class="tgl tgl-light" id="perm_<?= $key ?>" name="permissions[<?= $key ?>]" type="checkbox" />
                        <label class="tgl-btn" for="perm_<?= $key ?>"></label>
                      </div>
                      <div class="flex-grow-1">
                        <label for="perm_<?= $key ?>" class="permission-label fw-bold"><?php echo $lang[$perm_data['label']] ?? $perm_data['label']; ?></label>
                        <div class="permission-description text-muted small mt-1"><?php echo $lang[$perm_data['description']] ?? $perm_data['description']; ?></div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                <?php else: ?>
                  <?php foreach ($tab['keys'] as $key): ?>
                    <?php
                    $perm_data = $permission_groups['Account Permissions'][$key] ?? null;
                    if (!$perm_data) {
                        continue;
                    }
                    ?>
                    <div class="permission-item d-flex col-gap align-items-start mb-3">
                      <div class="checkbox-wrapper-6 mt-1">
                        <input class="tgl tgl-light" id="perm_<?= $key ?>" name="permissions[<?= $key ?>]" type="checkbox" />
                        <label class="tgl-btn" for="perm_<?= $key ?>"></label>
                      </div>
                      <div class="flex-grow-1">
                        <label for="perm_<?= $key ?>" class="permission-label fw-bold"><?php echo $lang[$perm_data['label']] ?? $perm_data['label']; ?></label>
                        <div class="permission-description text-muted small mt-1"><?php echo $lang[$perm_data['description']] ?? $perm_data['description']; ?></div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
          <button type="submit" name="save_role" class="primary-btn mt-2"><?php echo $lang['Save Role']; ?></button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<style>
#roleModal .modal-dialog {
  max-width: 800px; /* same as Bootstrap modal-lg (project has no modal-xl) */
  width: calc(100% - 1.5rem);
}
#roleModal .role-permission-tabs {
  display: flex;
  flex-wrap: nowrap;
  flex-direction: row;
  gap: 0;
  border-bottom: 1px solid var(--border-color, #dee2e6);
  overflow-x: auto;
  overflow-y: hidden;
  -webkit-overflow-scrolling: touch;
  scrollbar-width: thin;
}
#roleModal .role-permission-tabs .nav-item {
  flex: 0 0 auto;
}
#roleModal .role-permission-tabs .nav-link {
  white-space: nowrap;
  font-size: 13px;
  padding: 0.45rem 0.85rem;
}
#roleModal .role-permission-tabs::-webkit-scrollbar {
  height: 6px;
}
#roleModal .role-permission-tabs::-webkit-scrollbar-thumb {
  background: var(--border-color, #888);
  border-radius: 4px;
}
#roleModal .role-permission-tab-content {
  min-height: 0;
  max-height: none;
  overflow: visible;
  padding-top: 8px;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
  // Store roles and permissions in JS for quick access
  var rolesData = <?php echo json_encode($roles); ?>;
  var permissionsData = <?php echo json_encode($roles); ?>;
  var editPermissions = <?php echo json_encode($edit_permissions); ?>;

  function resetRolePermissionTabs() {
    var firstBtn = document.querySelector('#rolePermissionTabs .nav-link');
    if (firstBtn && typeof bootstrap !== 'undefined' && bootstrap.Tab) {
      bootstrap.Tab.getOrCreateInstance(firstBtn).show();
    }
  }

  // Handle Create New
  document.getElementById('createRoleBtn').addEventListener('click', function() {
    document.getElementById('roleModalLabel').textContent = 'Create New Role';
    document.getElementById('roleForm').reset();
    document.getElementById('role_id').value = '';
    // Uncheck all permissions
    document.querySelectorAll('#roleForm input[type=checkbox]').forEach(function(cb) { cb.checked = false; });
    ['timer_log_edit_own', 'timer_log_delete_own', 'timer_log_show_manual'].forEach(function (k) {
      var el = document.getElementById('perm_' + k);
      if (el) el.checked = true;
    });
    resetRolePermissionTabs();
    var modal = new bootstrap.Modal(document.getElementById('roleModal'));
    modal.show();
  });

  // Handle Edit
  document.querySelectorAll('.edit-role-btn').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      var roleId = this.getAttribute('data-role-id');
      // Fetch role data via AJAX or from rolesData
      fetch('roles?edit=' + roleId + '&modal=1')
        .then(response => response.json())
        .then(data => {
          console.log('Role data loaded:', data);
          console.log('Permissions from database:', data.permissions);
          document.getElementById('roleModalLabel').textContent = 'Edit Role';
          document.getElementById('role_id').value = data.id;
          document.getElementById('role_name').value = data.name;
          document.getElementById('role_description').value = data.description;
          // Set permissions - explicitly check if permission exists and equals 1
          // First, uncheck all checkboxes to ensure clean state
          document.querySelectorAll('#roleForm input[type=checkbox]').forEach(function(cb) {
            cb.checked = false;
          });
          // Then check only the ones that are enabled (value = 1)
          document.querySelectorAll('#roleForm input[type=checkbox]').forEach(function(cb) {
            var key = cb.name.replace('permissions[','').replace(']','');
            var defaultOnIfMissing = { timer_log_edit_own: 1, timer_log_delete_own: 1, timer_log_show_manual: 1 };
            var permissionValue =
              data.permissions && data.permissions.hasOwnProperty(key)
                ? parseInt(data.permissions[key], 10)
                : defaultOnIfMissing[key]
                  ? defaultOnIfMissing[key]
                  : 0;
            if (permissionValue === 1) {
              cb.checked = true;
              console.log('Checked permission:', key);
            } else {
              console.log('Unchecked permission:', key, 'value:', permissionValue);
            }
          });
          var modal = new bootstrap.Modal(document.getElementById('roleModal'));
          resetRolePermissionTabs();
          modal.show();
        })
        .catch(error => {
          console.error('Error loading role data:', error);
        });
    });
  });
});
</script>
<script>window.__toastFlash=<?php echo json_encode($toast_flash, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;</script>
<script src="<?php echo htmlspecialchars(rtrim((string) $url, '/') . '/assets/js/toast.js', ENT_QUOTES, 'UTF-8'); ?>" defer></script>
<?php include("../templates/main-footer.php"); ?>