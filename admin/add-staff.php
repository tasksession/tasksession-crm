<?php
/*
 ================================================================================
   Task Session – Project Management System
   File    : add-staff.php
   Purpose : Handles staff member registration and management
 ================================================================================
 */
ob_start();
require_once("../includes/lib-initialize.php");
require_once("../includes/email_helper.php");
$title = "Add Staff | ". $syatem_title;
include("../templates/header.php");

// Authentication check
if(!($session->isLoggedIn())){
    redirectTo($url."index");
}
if($_SESSION['accountStatus'] == 2){
    redirectTo($url."client/index");
}
if($_SESSION['accountStatus'] == 3){
    redirectTo($url."staff/index");
} 

$id = $session->userId;
$user = User::findById((int)$id);
$username = $user->firstName;
$email = $user->email;
$account_stat = $user->status;
$settings = settings::findById(1); // Use system settings (admin settings) for email templates
$settingsForModules = $settings;
$moduleAttendanceEnabled = $settingsForModules && !empty($settingsForModules->module_attendance);
$moduleIpEnabled = $settingsForModules && !empty($settingsForModules->module_ip_restriction);

// Initialize EmailHelper with settings
$emailHelper = new EmailHelper($settings);

$message = "";

// Fetch roles for dropdown
$roles = [];
$res = $connect->query("SELECT id, name FROM roles ORDER BY name ASC");
while ($row = $res->fetch_assoc()) {
    $roles[] = $row;
}

// Check if roles exist
$hasRoles = count($roles) > 0;
$attendanceShifts = array();
$shiftRes = $connect->query("SELECT id, name, code FROM attendance_shifts WHERE is_active = 1 ORDER BY name ASC");
if ($shiftRes) {
    while ($shiftRow = $shiftRes->fetch_assoc()) {
        $attendanceShifts[] = $shiftRow;
    }
}

if(isset($_POST['add-client'])) {
    $flag = 0;
    if($flag == 0) {
        $user = new User();
        $id = (int)NULL;
        $Projects_ids = "";
        $rawPassword = $_POST['password'] ?? '';
        $hashedPassword = password_hash($rawPassword, PASSWORD_BCRYPT);
        $email = $_POST['email'] ?? '';
        $accountStatus = 3;
        $firstName = $_POST['firstName'] ?? '';
        $title = $_POST['title'] ?? '';
        $address = $_POST['address'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $website = $_POST['website'] ?? '';
        $teams_id = $_POST['teams_id'] ?? '';
        $fb = $_POST['fb'] ?? '';
        $regDate = date("Y-m-d H:i:s");
        $type_status = "";
        $last_seen = time();
        $session_status = "";
        $status = 0;
        $note = "";
        $city = "";
        $state = "";
        $zip = "";
        $country = "";
        $user_language = '';
        $role_id = $_POST['role_id'] ?? null;
        $shift_id = isset($_POST['shift_id']) ? (int)$_POST['shift_id'] : 0;
        $base_salary = isset($_POST['base_salary']) ? (float)$_POST['base_salary'] : 0;
        $attendance_deduction_mode = $_POST['attendance_deduction_mode'] ?? '';
        if (!in_array($attendance_deduction_mode, array('fixed', 'percentage'), true)) {
            $attendance_deduction_mode = null;
        }
        $attendance_deduction_value = ($attendance_deduction_mode !== null && isset($_POST['attendance_deduction_value']))
            ? (float)$_POST['attendance_deduction_value']
            : 0;
        $login_ip_restriction_enabled = isset($_POST['login_ip_restriction_enabled']) ? 1 : 0;
        $allowed_login_ips = trim((string)($_POST['allowed_login_ips'] ?? ''));
        $attendance_disabled = isset($_POST['attendance_disabled']) ? 1 : 0;
        $send_account_email = isset($_POST['email_notification']);
        // Security: Use prepared statements to prevent SQL injection
        $sql = "INSERT INTO users (Projects_ids, password, email, accountStatus, firstName, title, address, phone, website, teams_id, fb, regDate, type_status, last_seen, session_status, status, note, city, state, zip, country, user_language, role_id, base_salary, attendance_deduction_mode, attendance_deduction_value, login_ip_restriction_enabled, allowed_login_ips, attendance_disabled)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $emailAlreadyExists = user::findByEmail($email);
        if($emailAlreadyExists) {
            $message = "<p class='alert alert-danger'>This email address has already registered. Try a different one.</p>";
        } else {
            $stmt = $connect->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("sssisssssssssisissssssidsdisi", 
                    $Projects_ids, $hashedPassword, $email, $accountStatus, $firstName, $title, $address,
                    $phone, $website, $teams_id, $fb, $regDate, $type_status, $last_seen, $session_status,
                    $status, $note, $city, $state, $zip, $country, $user_language, $role_id, $base_salary,
                    $attendance_deduction_mode, $attendance_deduction_value, $login_ip_restriction_enabled, $allowed_login_ips,
                    $attendance_disabled
                );
                if ($stmt->execute()) {
                    $lastUser = $user->findLastRecord();
                    $last_id = $stmt->insert_id;
                    $stmt->close();
                if (!$attendance_disabled && $shift_id > 0 && $last_id > 0) {
                    $effectiveFrom = date('Y-m-d');
                    $assignedBy = (int)$session->userId;
                    $reason = 'Assigned from staff profile';
                    $checkShiftStmt = $connect->prepare(
                        "SELECT id
                         FROM attendance_shift_assignments
                         WHERE user_id = ?
                           AND effective_from <= ?
                           AND (effective_to IS NULL OR effective_to >= ?)
                         ORDER BY effective_from DESC, id DESC
                         LIMIT 1"
                    );
                    if ($checkShiftStmt) {
                        $checkShiftStmt->bind_param('iss', $last_id, $effectiveFrom, $effectiveFrom);
                        $checkShiftStmt->execute();
                        $activeShift = $checkShiftStmt->get_result()->fetch_assoc();
                        $checkShiftStmt->close();
                        if ($activeShift) {
                            $updateShiftStmt = $connect->prepare(
                                "UPDATE attendance_shift_assignments
                                 SET shift_id = ?, assigned_by = ?, reason = ?
                                 WHERE id = ?"
                            );
                            if ($updateShiftStmt) {
                                $activeAssignmentId = (int)$activeShift['id'];
                                $updateShiftStmt->bind_param('iisi', $shift_id, $assignedBy, $reason, $activeAssignmentId);
                                $updateShiftStmt->execute();
                                $updateShiftStmt->close();
                            }
                        } else {
                            $insertShiftStmt = $connect->prepare(
                                "INSERT INTO attendance_shift_assignments (user_id, shift_id, effective_from, assigned_by, reason)
                                 VALUES (?, ?, ?, ?, ?)"
                            );
                            if ($insertShiftStmt) {
                                $insertShiftStmt->bind_param('iisis', $last_id, $shift_id, $effectiveFrom, $assignedBy, $reason);
                                $insertShiftStmt->execute();
                                $insertShiftStmt->close();
                            }
                        }
                    }
                }
                $message = "";
                if ($_FILES['pro-pic']['tmp_name'] != '') {
                    $picture = new profilePicture();	
                    $id = (int)NULL;
                    $fkUserId = $last_id;
                    $fileName = $_FILES['pro-pic']['name'];
                    $fileType = $_FILES['pro-pic']['type'];
                    $filesize = $_FILES['pro-pic']['size'];
                    $fileError = $_FILES['pro-pic']['error'];

                    // Security: Sanitize filename to prevent path traversal
                    $originalFilename = $_FILES['pro-pic']['name'];
                    // Remove any path components
                    $originalFilename = basename($originalFilename);
                    // Remove any null bytes
                    $originalFilename = str_replace("\0", '', $originalFilename);
                    // Remove directory traversal attempts
                    $originalFilename = str_replace(['../', '..\\', '/', '\\'], '', $originalFilename);
                    
                    // Security: Validate file extension
                    $ext = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
                    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                    if (!in_array($ext, $allowedExtensions)) {
                        $message = "<p class='alert alert-danger'>Invalid file type. Only image files (jpg, jpeg, png, gif, webp) are allowed.</p>";
                    } else {
                        // Security: Validate file size (max 10MB for profile pictures)
                        $maxSize = 10 * 1024 * 1024; // 10MB
                        if ($filesize > $maxSize) {
                            $message = "<p class='alert alert-danger'>File too large. Maximum size is 10MB.</p>";
                        } else {
                            // Security: Additional MIME type validation
                            $finfo = finfo_open(FILEINFO_MIME_TYPE);
                            $mimeType = finfo_file($finfo, $_FILES['pro-pic']['tmp_name']);
                            finfo_close($finfo);
                            $allowedMimeTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                            if (!in_array($mimeType, $allowedMimeTypes)) {
                                $message = "<p class='alert alert-danger'>Invalid file type detected.</p>";
                            } else {
                                $fileContent = file_get_contents($_FILES['pro-pic']['tmp_name']);
                                $tmpImageFolder = dirname(__DIR__).'/uploads/profile-pics/';
                                
                                // Security: Ensure upload directory exists
                                if (!is_dir($tmpImageFolder)) {
                                    if (!mkdir($tmpImageFolder, 0755, true)) {
                                        $message = "<p class='alert alert-danger'>Failed to create upload directory.</p>";
                                    }
                                }
                                
                                if (empty($message)) {
                                    $newFileName = microtime(true).'.'.$ext;
                                    $source = $_FILES['pro-pic']['tmp_name'];
                                    $dest = $tmpImageFolder.$newFileName;
                                    
                                    // Security: Verify resolved path is within upload directory (prevent path traversal)
                                    $resolvedDir = realpath($tmpImageFolder);
                                    if ($resolvedDir === false) {
                                        $message = "<p class='alert alert-danger'>Invalid upload directory.</p>";
                                    } else {
                                        // Normalize the destination path
                                        $destNormalized = rtrim($tmpImageFolder, '/\\') . '/' . basename($newFileName);
                                        $resolvedDest = realpath(dirname($destNormalized));
                                        if ($resolvedDest === false || strpos($resolvedDest, $resolvedDir) !== 0) {
                                            $message = "<p class='alert alert-danger'>Invalid file path detected.</p>";
                                        } else {
                                            if (move_uploaded_file($source, $dest)) {
                                                $image = json_encode($newFileName);
                                                $filename = str_replace('"', "", $image);
                                                $createdDate = date("Y-m-d H:i:s");
                                                // Security: Use prepared statement to prevent SQL injection
                                                $sqlb = "INSERT INTO profile_pics (id, fkUserId, filename, type, size, createdDate) 
                                                         VALUES(?, ?, ?, ?, ?, ?)"; 
                                                $stmtb = $connect->prepare($sqlb);
                                                if ($stmtb) {
                                                    $stmtb->bind_param("iissis", $id, $fkUserId, $filename, $fileType, $filesize, $createdDate);
                                                    if ($stmtb->execute()) {
                                                        unset($picture);
                                                    } else {
                                                        $message = "<p class='alert alert-danger'>Picture couldn't be uploaded due to database error.</p>";
                                                    }
                                                    $stmtb->close();
                                                } else {
                                                    $message = "<p class='alert alert-danger'>Failed to prepare profile picture insert statement.</p>";
                                                }
                                            } else {
                                                $message = "<p class='alert alert-danger'>Failed to upload profile picture.</p>";
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                if ($send_account_email) {
                $to  = $email;
                $subject = 'Your Account has been Created';
                $variablesArr = array(
                    '{USER_NAME}' => $firstName,
                    '{SIGNATURE}' => $company_name,
                    '{DASHBOARD_URL}' => $url,
                    '{USER_LOGIN_PASSWORD}' => $rawPassword,
                    '{USER_LOGIN_EMAIL}' => $email,
                    '{RESET_PASSWORD_URL}' => '',
                    '{PROJECT_NAME}' => ''
                );
                $templateHTML = $settings->create_account_email;
                if (empty($templateHTML)) {
                    error_log("Email template is empty for staff account creation: " . $email);
                    header('location: add-staff.php?message=fail');
                    exit;
                }
                $emailSent = $emailHelper->sendTemplateEmail($to, $subject, $templateHTML, $variablesArr);
                if (!$emailSent) {
                    header('location: add-staff.php?message=fail');
                    exit;
                }
                }
                // Assign permissions from role (always after successful user insert)
                $role_permissions = [];
                $res = $connect->query("SELECT permission_key, value FROM role_permissions WHERE role_id=".intval($role_id));
                while ($row = $res->fetch_assoc()) {
                    $role_permissions[$row['permission_key']] = $row['value'];
                }
                require_once("../includes/task_permission.php");
                $permission = new TaskPermission();
                $permission->user_id = $last_id;
                $permission->can_create_task = $role_permissions['task_create'] ?? 0;
                $permission->can_delete_task = $role_permissions['task_delete'] ?? 0;
                $permission->can_change_status = $role_permissions['task_status_update'] ?? 0;
                $permission->can_update_task = $role_permissions['task_edit'] ?? 0;
                $permission->can_assign_members = $role_permissions['task_assign_members'] ?? 0;
                $permission->can_view_milestones = $role_permissions['milestone_view'] ?? 0;
                $permission->save();

                require_once(__DIR__ . '/../includes/custom-fields/user_profile_values.php');
                save_user_profile_custom_field_values(
                    $connect,
                    (int)$last_id,
                    'staff',
                    isset($_POST['custom_fields']) && is_array($_POST['custom_fields']) ? $_POST['custom_fields'] : null
                );

                header('location: members.php?message=add_success');
                exit;
                } else {
                    $error = $stmt->error;
                    $stmt->close();
                    header('location: add-staff.php?message=error');  
                }
            } else {
                $error = $connect->error;
                header('location: add-staff.php?message=error');  
            }
        }
    }
}

if(isset($_GET['message'])){
    $msgstatus = $_GET['message'];
    $notmessagea = $lang['Staff has been registered successfully!'];
    $notmessageb = $lang['Staff has been registered, but there was an error sending the email. Please contact the site administrator or check the system email settings'];
    if($msgstatus == 'success'){
        $message = "<p class='alert alert-success'>".$notmessagea."</p>";
    }
    if($msgstatus == 'fail'){
        $message = "<p class='alert alert-danger'>".$notmessageb."</p>";
    }
    if($msgstatus == 'error'){
        $message = "<p class='alert alert-danger'>".$lang['Error registering user.'] .$sql. $lang['Please Try Again later.Error:']. $error."</p>";
    }
}

// condition check for login
$id = $session->userId; // id of the current logged in user 
$user = User::findById((int)$id); // take the record of current user in an object array 	
$username = $user->firstName;
$email = $user->email;
$account_stat = $user->status;
?>
<div class="page-container">
    <div class="container-fluid">
        <div class="row row-eq-height">
            <?php include("../templates/sidebar.php"); ?>
            <div class="page-content">
                <?php include('../templates/top-header.php'); ?>
                <?php if(isset($message) && (!empty($message))){ echo $message; } ?>
                
                <div class="add-client center-col">
                    <h2 class="page-title"><?php echo $lang['Add New Staff Member']; ?></h2>
                    
                    <?php if (!$hasRoles): ?>
                    <div class="card text-center" style="padding: 50px 20px;">
                        <i class="fas fa-users-cog fa-4x mb-4"></i>
                        <h3 class="text-muted">No Roles Available</h3>
                        <p class="mb-4">You need to create at least one role before adding staff members.</p>
                        <div class="d-flex justify-content-center col-gap-10">
                            <a href="roles" class="btn primary-btn">
                                <i class="fas fa-plus"></i> Create Roles
                            </a>
                            <a href="members" class="btn secondary-btn-a">
                                <i class="fas fa-arrow-left"></i> Back to Members
                            </a>
                        </div>
                    </div>
                    <?php else: ?>
                    <form method="post" action="#" enctype="multipart/form-data" id="add-staff-form" novalidate data-accounts-js="1" data-attendance-module="<?php echo $moduleAttendanceEnabled ? '1' : '0'; ?>" autocomplete="off">
                        <!-- Ensure POST handler triggers even if submit button is disabled by spinner JS -->
                        <input type="hidden" name="add-client" value="1" />
                        <div class="mb-4 d-flex align-items-center col-gap dp-box">
                            <div  style="position: relative; display: inline-block;">
                                <div class="img-uploadwrap">
                                    <img src="../assets/images/upload-img.jpg" class="img-fluid" id="blah" style="width: 100%; height: 100%; object-fit: cover;" />
                                </div>
                                <div class="pro-pic">
                                    <span style="font-size: 24px;">+</span>
                                    <input type="file" name="pro-pic" class="pro-pic" id="imgInpb" style="opacity: 0; position: absolute; width: 100%; height: 100%; cursor: pointer;" />
                                </div>
                            </div>
                            <div class="max-width-300">
                                <h4><?php echo $lang['Change profile image']; ?></h4>
                                <p style="color: #757575; font-size: 12px;"><?php echo $lang['Profile image must be a .jpg .png file smaller than 10MB and at least 400px by 400px.']; ?></p>
                            </div>
                        </div>

                        <!-- Tab Navigation -->
                        <ul class="nav nav-tabs" id="staffTabs" role="tablist">
                            <li class="nav-item" style="margin-right: 5px;">
                                <a class="nav-link active" id="staff-account-tab" data-toggle="tab" href="#account" role="tab" aria-controls="account" aria-selected="true">
                                    <?php echo $lang['Account Information']; ?>
                                </a>
                            </li>
                            <?php if ($moduleAttendanceEnabled): ?>
                            <li class="nav-item">
                                <a class="nav-link" id="attendance-settings-tab" data-toggle="tab" href="#attendance-settings" role="tab" aria-controls="attendance-settings" aria-selected="false">
                                    <?php echo $lang['Attendance Settings'] ?? 'Attendance Settings'; ?>
                                </a>
                            </li>
                            <?php endif; ?>
                        </ul>
                        <div class="tab-content" id="staffTabsContent">
                            <div class="tab-pane fade show active" id="account" role="tabpanel" aria-labelledby="staff-account-tab">
                                <div class="user-info">
										<div class="display-grid">
                                        <!-- Full Name -->
                                        <div class="form-group">
                                            <label for="firstName"><?php echo $lang['Full name*']; ?></label>
                                            <input type="text" name="firstName" class="form-control only-alpha" required> 
                                        </div>
                                        
                                        <!-- Email -->
                                        <div class="form-group">
                                            <label for="email"><?php echo $lang['Email*']; ?></label>
                                            <input type="email" name="email" class="form-control" required> 
                                        </div>
                                        
                                        <!-- Job Title -->
                                        <div class="form-group">
                                            <label for="title"><?php echo $lang['Job Title']; ?>*</label>
                                            <input type="text" name="title" class="form-control only-alpha" required> 
                                        </div>
                                        
                                        <!-- Phone -->
                                        <div class="form-group">
                                            <label for="phone"><?php echo $lang['Phone']; ?></label>
                                            <input type="tel" name="phone" class="form-control only-alpha"> 
                                        </div>
                                  
                                        <!-- Teams ID -->
                                        <div class="form-group">
                                            <label for="teams_id"><?php echo $lang['Teams ID']; ?></label>
                                            <input type="text" name="teams_id" class="form-control only-alpha"> 
                                        </div>
                                        
                                        <!-- Password -->
                                        <div class="form-group">
                                            <label for="password"><?php echo $lang['Password*']; ?></label>
										<div class="eyes-row">
                                                <input type="password" name="password" class="form-control passwordfield" required autocomplete="new-password">
											<?php echo ts_password_toggle_html($lang['Show password'] ?? 'Show password'); ?>
                                            </div>
                                        </div>
                                        <!-- Facebook URL -->
                                        <div class="form-group">
                                            <label for="Facebook"><?php echo $lang['Facebook Url']; ?></label>
                                            <input type="url" name="fb" placeholder="https://www.facebook.com/User Id" pattern=".*\.facebook\..*" class="form-control"> 
                                        </div>
                                        <!-- Role Dropdown -->
                                        <div class="form-group">
                                            <label for="role_id"><?php echo $lang['Assign Role Permission*']; ?></label>
                                            <select name="role_id" class="form-control" required>
                                                <option value=""><?php echo $lang['Select Role']; ?></option>
                                                <?php foreach($roles as $role): ?>
                                                    <option value="<?= $role['id'] ?>"><?= htmlspecialchars($role['name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
										 <!-- Address -->
                                        <div class="form-group full-grid">
                                            <label for="address"><?php echo $lang['Address']; ?></label>
                                            <input type="text" name="address" class="form-control only-alpha"> 
                                        </div>
                                        <?php if ($moduleIpEnabled): ?>
                                        <div class="form-group full-grid">
                                            <div class="permission-item d-flex col-gap align-items-center mb-3">
                                                <div class="checkbox-wrapper-6">
                                                    <input class="tgl tgl-light" id="login_ip_restriction_enabled_add" name="login_ip_restriction_enabled" type="checkbox" value="1">
                                                    <label class="tgl-btn" for="login_ip_restriction_enabled_add"></label>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <label for="login_ip_restriction_enabled_add" class="permission-label fw-bold"><?php echo $lang['Enable Account IP Restriction']; ?></label>
                                                </div>
                                            </div>
                                            <input type="text" name="allowed_login_ips" class="form-control" placeholder="111.88.7.28, 192.168.1.10">
                                            <small class="form-text text-muted"><?php echo $lang['Allowed account IPs (comma separated)']; ?></small>
                                        </div>
                                        <?php endif; ?>
                                        <div id="userProfileCustomFieldsSection" class="form-group full-grid js-user-profile-cf-section d-none" aria-hidden="true">
                                            <div class="staff-heading mt-3"><h4><?php echo htmlspecialchars($lang['Custom fields'] ?? 'Custom fields', ENT_QUOTES, 'UTF-8'); ?></h4></div>
                                            <div id="userProfileCustomFieldsContainer"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php if ($moduleAttendanceEnabled): ?>
                            <div class="tab-pane fade" id="attendance-settings" role="tabpanel" aria-labelledby="attendance-settings-tab">
                                <div class="user-info">
                                    <div class="form-group full-grid mt-3">
                                        <div class="permission-item d-flex col-gap align-items-center mb-0">
                                            <div class="checkbox-wrapper-6">
                                                <input class="tgl tgl-light" id="attendance_disabled_add_staff" name="attendance_disabled" type="checkbox" value="1">
                                                <label class="tgl-btn" for="attendance_disabled_add_staff"></label>
                                            </div>
                                            <div class="flex-grow-1">
                                                <label for="attendance_disabled_add_staff" class="permission-label fw-bold mb-0"><?php echo $lang['Disable Attendance'] ?? 'Disable Attendance'; ?></label>
                                            </div>
                                        </div>
                                    </div>
                                    <fieldset id="add-staff-attendance-fields" class="border-0 p-0 m-0 min-width-0">
                                        <div class="display-grid mt-3">
                                        <div class="form-group">
                                            <label for="shift_id"><?php echo $lang['Shifts'] ?? 'Shift'; ?></label>
                                            <select name="shift_id" id="shift_id" class="form-control">
                                                <option value=""><?php echo $lang['Select'] ?? 'Select'; ?></option>
                                                <?php foreach ($attendanceShifts as $shift): ?>
                                                    <option value="<?php echo (int)$shift['id']; ?>">
                                                        <?php echo htmlspecialchars($shift['name'] . (!empty($shift['code']) ? ' (' . $shift['code'] . ')' : '')); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label for="base_salary"><?php echo $lang['Base Salary']; ?></label>
                                            <input type="number" name="base_salary" id="base_salary" class="form-control" step="0.01" min="0" value="0">
                                        </div>
                                        <div class="form-group">
                                            <label for="attendance_deduction_mode"><?php echo $lang['Staff Deduction Override Mode']; ?></label>
                                            <select name="attendance_deduction_mode" id="attendance_deduction_mode" class="form-control">
                                                <option value=""><?php echo $lang['Use Global Default'] ?? 'Use Global Default'; ?></option>
                                                <option value="fixed"><?php echo $lang['Fixed Amount'] ?? 'Fixed Amount'; ?></option>
                                                <option value="percentage"><?php echo $lang['Percentage'] ?? 'Percentage'; ?></option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label for="attendance_deduction_value"><?php echo $lang['Staff Deduction Override Value']; ?></label>
                                            <input type="number" name="attendance_deduction_value" id="attendance_deduction_value" class="form-control" step="0.01" min="0" value="0">
                                        </div>
                                        </div>
                                    </fieldset>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                          <div class="form-group submit-box" id="staff-account-submit-box">
                            <div class="d-flex col-gap align-items-center">
                                <div class="checkbox-wrapper-6">
                                    <input class="tgl tgl-light" id="emailNotification" name="email_notification" type="checkbox" value="1" checked/>
                                    <label class="tgl-btn" for="emailNotification"></label>
                                </div>
								<div>
                                <label for="emailNotification" class="permission-label d-flex align-items-center col-gap-5 mb-0">
                                    <?php echo $lang['Email Notification']; ?>
                                    <span class="flex-shrink-0" data-bs-toggle="tooltip" data-bs-placement="top" title="<?php echo htmlspecialchars($lang['Notify the staff of account creation'], ENT_QUOTES, 'UTF-8'); ?>" onclick="event.preventDefault(); event.stopPropagation();"><?php echo ts_icon('info', 'w-2 text-muted'); ?></span>
                                </label>
                            </div></div>
                            <div>
                                <?php if ($moduleAttendanceEnabled): ?>
                                <button type="button" id="next-to-attendance-settings" class="primary-btn"><?php echo $lang['Next'] ?? 'Next'; ?></button>
                                <?php else: ?>
                                <button type="submit" name="add-client" id="create-staff-btn" class="primary-btn"><?php echo $lang['Create Account']; ?></button>
                                <?php endif; ?>
                            </div>
                          </div>
                          <?php if ($moduleAttendanceEnabled): ?>
                          <div class="form-group submit-box" id="staff-attendance-submit-box" style="display:none;">
							 <div>
                            <button type="submit" name="add-client" id="create-staff-btn-attendance" class="primary-btn"><?php echo $lang['Create Account']; ?></button>
							</div>
						  </div>
                          <?php endif; ?>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
window.USER_PROFILE_CF_CONFIG = {
  entityType: 'staff',
  listUrl: '../includes/custom-fields/list.php',
  containerSelector: '#userProfileCustomFieldsContainer',
  initialValues: [],
  i18n: {
    fieldRequired: <?php echo json_encode(isset($lang['This field is required.']) ? $lang['This field is required.'] : 'This field is required.', JSON_HEX_TAG | JSON_HEX_APOS); ?>
  }
};
</script>
<script src="../assets/js/user-profile-custom-fields-form.js"></script>
<script src="../assets/js/accounts.js"></script>
<?php include("../templates/main-footer.php"); ?>
