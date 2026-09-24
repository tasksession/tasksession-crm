<?php 
/*
 ================================================================================
   Task Session – Project Management System
   File    : add-client.php
   Purpose : Handles client registration and management in the system
 ================================================================================
 */
ob_start(); 
require_once("../includes/lib-initialize.php");
require_once("../includes/email_helper.php");
$title = "Add Client | ". $syatem_title;
include("../templates/header.php");
if(!($session->isLoggedIn())){
    redirectTo($url."index.php");
}
if($_SESSION['accountStatus'] == 2){
    redirectTo($url."client/index.php");
}
if($_SESSION['accountStatus'] == 1){
    redirectTo($url."staff/index.php");
} 
$id = $session->userId; // id of the current logged in user 
$userb = User::findById((int)$id); 
$username = $userb->firstName;
$email    = $userb->email;

$settings = settings::findById(1);
$emailHelper = new EmailHelper($settings);
$invoiceModuleEnabled = !empty($settings->module_invoices);
$account_stat = $userb->status;
$message = "";

require_once("../includes/permissions.php");
ensure_user_permissions($connect);
if (!has_permission('client_create')) {
    header('Location: clients.php');
    exit;
}

if(isset($_POST['add-client']))
{
    $flag = 0;
    if($flag == 0)
    {
        $user = new User();

        $id            = (int)NULL;
        $Projects_ids  = "";
        
        // Retrieve the raw password from the form
        $rawPassword   = $_POST['password'];
        // Hash the password using BCRYPT
        $hashedPassword = password_hash($rawPassword, PASSWORD_BCRYPT);
        
        $email         = $_POST['email'];
        $accountStatus = 2;
        $firstName     = $_POST['firstName'];
        $title         = "";
        $address       = $_POST['address'];
        $phone         = $_POST['phone'];
        $website       = $_POST['website'];
        $teams_id      = $_POST['teams_id'];
        $fb            = $_POST['fb'];
        $regDate       = strftime("%Y-%m-%d %H:%M:%S", time());
        $type_status   = "";
        $last_seen     = time();
        $session_status= "";
        $status        = 0;
        $note          = "";
        $city          = $_POST['city'];
        $state         = $_POST['state'];
        $zip           = $_POST['zip'];
        $country       = $_POST['country'];
        $user_language = '';
        if ($invoiceModuleEnabled) {
            $currency = isset($_POST['currency']) ? $_POST['currency'] : '';
        } else {
            $currency = !empty($settings->system_currency) ? $settings->system_currency : 'USD,$';
        }
        
        // Handle assigned team (comma-separated string from hidden input)
        $assigned_team = '';
        if (isset($_POST['assigned_team']) && !empty($_POST['assigned_team'])) {
            // Handle both array (old format) and comma-separated string (new format)
            if (is_array($_POST['assigned_team'])) {
                $teamIds = $_POST['assigned_team'];
            } else {
                // Comma-separated string from hidden input
                $teamIds = explode(',', $_POST['assigned_team']);
            }
            
            // Filter and validate team member IDs (must be admin or staff)
            $validTeamIds = [];
            foreach ($teamIds as $teamId) {
                $teamId = trim($teamId);
                $teamId = (int)$teamId;
                if ($teamId > 0) {
                    $teamUser = User::findById($teamId);
                    // Only allow admin (1) or staff (3) to be assigned
                    if ($teamUser && in_array($teamUser->accountStatus, [1, 3])) {
                        $validTeamIds[] = $teamId;
                    }
                }
            }
            $assigned_team = implode(',', $validTeamIds);
        }

        $send_account_email = isset($_POST['email_notification']);

        // Use the hashed password in the INSERT query
        $sql = "INSERT INTO users (
                    id, Projects_ids, password, email, accountStatus, firstName, title, address, phone, website, teams_id, fb, regDate, type_status, last_seen, session_status, status, note, city, state, zip, country, user_language, currency, assigned_team
                ) VALUES (
                    '$id', '$Projects_ids', '$hashedPassword', '$email', '$accountStatus', '$firstName', '$title', '$address', '$phone', '$website', '$teams_id', '$fb', '$regDate', '$type_status', '$last_seen', '$session_status', '$status', '$note', '$city', '$state', '$zip', '$country', '$user_language', '$currency', '$assigned_team'
                )";
                
        $emailAlreadyExists = user::findByEmail($email);
        if($emailAlreadyExists)
        {
            $message = "<p class='alert alert-danger'>" . $lang['This email address has already registered. Try a different one.'] . "</p>";
        }
        else
        {
            if ($connect->query($sql) === TRUE) {
                $lastUser = $user->findLastRecord();
                $last_id  = $connect->insert_id;
                
                // Add task permissions for the new client
                require_once("../includes/task_permission.php");
                $permissions = new TaskPermission();
                $permissions->user_id = $last_id;
                $permissions->can_create_task = isset($_POST['permissions']['can_create_task']) ? 1 : 0;
                $permissions->can_delete_task = isset($_POST['permissions']['can_delete_task']) ? 1 : 0;
                $permissions->can_change_status = isset($_POST['permissions']['can_change_status']) ? 1 : 0;
                $permissions->can_update_task = isset($_POST['permissions']['can_update_task']) ? 1 : 0;
                $permissions->can_assign_members = isset($_POST['permissions']['can_assign_members']) ? 1 : 0;
                $permissions->can_view_milestones = isset($_POST['permissions']['can_view_milestones']) ? 1 : 0;
                $permissions->save();

                require_once(__DIR__ . '/../includes/custom-fields/user_profile_values.php');
                save_user_profile_custom_field_values(
                    $connect,
                    (int)$last_id,
                    'client',
                    isset($_POST['custom_fields']) && is_array($_POST['custom_fields']) ? $_POST['custom_fields'] : null
                );
                
                $message  = "";
                if ($_FILES['pro-pic']['tmp_name'] != '') {
                    $picture = new profilePicture();	
                    $id = (int)NULL;
                    $fkUserId = $last_id;
                    $fileName = $_FILES['pro-pic']['name'];
                    $fileType = $_FILES['pro-pic']['type'];
                    $filesize = $_FILES['pro-pic']['size'];
                    $fileError = $_FILES['pro-pic']['error'];

                    $fileContent = file_get_contents($_FILES['pro-pic']['tmp_name']);
                    $tmpImageFolder = dirname(__DIR__).'/uploads/profile-pics/';
                    if (!is_dir($tmpImageFolder)) {
                        mkdir($tmpImageFolder, 0755, true);
                    }
                    $ext = strtolower(pathinfo($_FILES['pro-pic']['name'], PATHINFO_EXTENSION));
                    $allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                    $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;
                    $mime = $finfo ? finfo_file($finfo, $_FILES['pro-pic']['tmp_name']) : '';
                    if ($finfo) {
                        finfo_close($finfo);
                    }
                    $allowedMime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                    if (!in_array($ext, $allowedExt, true) || ($mime !== '' && !in_array($mime, $allowedMime, true))) {
                        $message = "<p class='alert alert-danger'>Invalid image file.</p>";
                    } else {
                    $newFileName = microtime(true).'.'.$ext;
                    $source = $_FILES['pro-pic']['tmp_name'];
                    $dest = $tmpImageFolder.'/'.$newFileName;
                    move_uploaded_file($source, $dest);

                    $image = json_encode($newFileName);
                    $filename = str_replace('"', "", $image);
                    $createdDate = strftime("%Y-%m-%d %H:%M:%S", time());
                    $sqlb = "INSERT INTO profile_pics (id, fkUserId, filename, type, size, createdDate) 
                             VALUES ('$id', '$fkUserId', '$filename', '$fileType', '$filesize', '$createdDate')"; 
                    if ($connect->query($sqlb) === TRUE) {
                        unset($picture);
                    }
                    else {
                        $message = "<p class='alert alert-danger'>" . join("Picture couldn't be uploaded due to following error<br />", $picture->errors ) . "</p>";
                    }
                    }

                }

                if ($send_account_email) {
                $to  = $email;
                $subject = $lang['Your Account has been Created'];
                $variablesArr = array(
                    '{USER_NAME}'           => $firstName,
                    '{SIGNATURE}'           => $company_name,
                    '{DASHBOARD_URL}'       => $url,
                    '{USER_LOGIN_PASSWORD}' => $rawPassword,
                    '{USER_LOGIN_EMAIL}'    => $email,
                    '{RESET_PASSWORD_URL}'  => '',
                    '{PROJECT_NAME}'        => ''
                );
                $templateHTML = $settings->create_account_email;
                if (empty($templateHTML)) {
                    error_log("Email template is empty for client account creation: " . $email);
                    header('location: add-client.php?message=fail');
                    exit;
                }
                $message = strtr($templateHTML, $variablesArr);
                $emailSent = $emailHelper->sendTemplateEmail($to, $subject, $templateHTML, $variablesArr);
                if (!$emailSent) {
                    header('location: add-client.php?message=fail');
                    exit;
                }
                }
                header('location: clients.php?message=add_success');
                exit;

            } else {
                $error = $connect->error;
                header('location: add-client.php?message=error');  
            }
        }
    }
}

if (isset($_GET['message'])) {
    $msgstatus = $_GET['message'];
    if ($msgstatus == 'success') {
        $message = "<p class='alert alert-success'>" . $lang["Client has been registered successfully!"] . "</p>";
    }
    if ($msgstatus == 'fail') {
        $message = "<p class='alert alert-danger'>" . $lang["Client has been registered, but there was an error sending the email. Please contact the site administrator or check the system email settings"] . "</p>";
    }
    if ($msgstatus == 'error') {
        $message = "<p class='alert alert-danger'>" . $lang['Error registering user.'] . $sql . $lang['Please Try Again later.Error:'] . $error . "</p>";
    }
}

// Get all admin and staff users for "Assign Team" dropdown
require_once("../includes/functions.php");
$allStaffAndAdmin = User::findBySql("SELECT * FROM users WHERE (accountStatus = 1 OR accountStatus = 3) AND status = 0 ORDER BY firstName ASC");
$staffListForAssignment = [];
foreach ($allStaffAndAdmin as $staffUser) {
    $fullName = trim($staffUser->firstName . ' ' . ($staffUser->lastName ?? ''));
    if (empty($fullName)) {
        $fullName = $staffUser->email ?? 'User #' . $staffUser->id;
    }
    $filenamePicture = getUserAvatarHtml($staffUser->id, $staffUser->firstName, $staffUser->lastName ?? '', 28, 28, 'rounded-circle', $staffUser->firstName);
    $staffListForAssignment[] = [
        'id' => (int)$staffUser->id,
        'name' => htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8'),
        'image' => $filenamePicture,
        'accountStatus' => (int)$staffUser->accountStatus,
        'role' => ($staffUser->accountStatus == 1) ? 'Admin' : 'Staff'
    ];
}
?>
<div class="page-container">
    <div class="container-fluid">
        <div class="row row-eq-height">
            <?php include("../templates/sidebar.php"); ?>
            <div class="page-content">
                <?php include('../templates/top-header.php'); ?>
				                    <?php if(isset($message) && (!empty($message))){ echo $message; } ?>

                <div class="add-client center-col">
                    <h2 class="page-title"><?php echo $lang["Add Client"]; ?></h2>
                    
                    <form method="post" action="#" enctype="multipart/form-data" id="add-client-form" novalidate data-accounts-js="1" autocomplete="off">
                        <input type="hidden" name="add-client" value="1" />
                        <div class="mb-4 d-flex align-items-center col-gap dp-box">
                            <div style="position: relative; display: inline-block;">
                                <div class="img-uploadwrap">
                                    <img src="../assets/images/upload-img.jpg" class="img-fluid" id="blah" style="width: 100%; height: 100%; object-fit: cover;" />
                                </div>
                                <div class="pro-pic">
                                    <span style="font-size: 24px;">+</span>
                                    <input type="file" name="pro-pic" class="pro-pic" id="imgInpb" style="opacity: 0; position: absolute; width: 100%; height: 100%; cursor: pointer;" />
                                </div>
                            </div>
                            <div class="max-width-300">
                                <h4><?php echo $lang["Change profile image"]; ?></h4>
                                <p style="color: #757575; font-size: 12px;"><?php echo $lang["Profile image must be a .jpg .png file smaller than 10MB and at least 400px by 400px."]; ?></p>
                            </div>
                        </div>

                        <!-- Tab Navigation -->
                        <ul class="nav nav-tabs" id="clientTabs" role="tablist">
                            <li class="nav-item" style="margin-right: 5px;">
                                <a class="nav-link active" id="account-tab" data-toggle="tab" href="#account" role="tab" aria-controls="account" aria-selected="true">
                                    <?php echo $lang['Account Information']; ?>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="permissions-tab" data-toggle="tab" href="#permissions" role="tab" aria-controls="permissions" aria-selected="false">
                                    <?php echo $lang['Permissions']; ?>
                                </a>
                            </li>
                        </ul>
                        
                        <!-- Tab Content -->
                        <div class="tab-content" id="clientTabsContent">
                            <!-- Account Information Tab -->
                            <div class="tab-pane fade show active" id="account" role="tabpanel" aria-labelledby="account-tab">
                                <div class="user-info">
                                    <div class="display-grid">
                                        <div class="form-group">
                                            <label for="firstName"><?php echo $lang["Full name*"]; ?></label>
                                            <input type="text" name="firstName" class="form-control only-alpha" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="email"><?php echo $lang["Email*"]; ?></label>
                                            <input type="email" name="email" class="form-control" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="password"><?php echo $lang["Password"]; ?>*</label>
                                            <div class="eyes-row">
                                                <input type="password" name="password" class="form-control passwordfield" required autocomplete="new-password">
                                                <?php echo ts_password_toggle_html($lang['Show password'] ?? 'Show password'); ?>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label for="phone"><?php echo $lang["Phone"]; ?></label>
                                            <input type="tel" name="phone" class="form-control only-alpha">
                                        </div>
                                        <div class="form-group">
                                            <label for="teams_id"><?php echo $lang['Teams ID']; ?></label>
                                            <input type="text" name="teams_id" class="form-control only-alpha">
                                        </div>
                                        <div class="form-group">
                                            <label for="address"><?php echo $lang["Address"]; ?></label>
                                            <input type="text" name="address" class="form-control only-alpha">
                                        </div>
                                        <div class="form-group">
                                            <label for="website"><?php echo $lang["Website URL"]; ?></label>
                                            <input type="url" name="website" class="form-control">
                                        </div>
                                        <div class="form-group">
                                            <label for="Facebook"><?php echo $lang['Facebook Url']; ?></label>
                                            <input type="url" name="fb" placeholder="https://www.facebook.com/User Id" pattern=".*\.facebook\..*" class="form-control">
                                        </div>
                                        <div class="form-group">
                                            <label for="city"><?php echo $lang["City"]; ?></label>
                                            <input type="text" name="city" class="form-control only-alpha">
                                        </div>
                                        <div class="form-group">
                                            <label for="zip"><?php echo $lang["Zip"]; ?></label>
                                            <input type="text" name="zip" class="form-control only-alpha">
                                        </div>
                                        <div class="form-group">
                                            <label for="state"><?php echo $lang["State"]; ?></label>
                                            <input type="text" name="state" class="form-control only-alpha">
                                        </div>
                                        <div class="form-group">
                                            <label for="country"><?php echo $lang["Country"]; ?></label>
                                            <select name="country" class="form-control">
                                                <option value=""><?php echo $lang["Select Country"]; ?></option>
                                                <?php foreach ($countries as $countrie) {
                                                    echo '<option value="' . htmlspecialchars($countrie, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($countrie, ENT_QUOTES, 'UTF-8') . '</option>';
                                                } ?>
                                            </select>
                                        </div>
                                        <?php if ($invoiceModuleEnabled): ?>
                                        <div class="form-group full-grid">
                                            <label for="currency"><?php echo $lang["Currency"]; ?> <span class="text-danger">*</span></label>
                                            <select name="currency" id="currency" class="form-control" required>
                                                <option value=""><?php echo $lang["Select Currency"]; ?></option>
                                                <?php
                                                $enabledCurrencies = $settings->getMultipleCurrencies();
                                                foreach ($settings->currency_symbols as $currency => $symbol) {
                                                    if (in_array($currency, $enabledCurrencies)) {
                                                        echo '<option value="' . htmlspecialchars($currency, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($symbol, ENT_QUOTES, 'UTF-8') . '</option>';
                                                    }
                                                }
                                                ?>
                                            </select>
                                            <small class="form-text text-muted"><?php echo $lang['Select the currency this client prefers for payments']; ?></small>
                                            <small id="currency-error" class="text-danger d-none"><?php echo $lang['Select Currency']; ?></small>
                                        </div>
                                        <?php endif; ?>
                                        <div class="form-group full-grid">
                                            <label for="staffDropdownBtn">Assign team members & admins <span class="text-danger">*</span></label>
                                            <div class="mb-3">
                                                <div class="dropdown">
                                                    <button
                                                        class="field-btn dropdown-toggle w-100 text-start"
                                                        type="button"
                                                        id="staffDropdownBtn"
                                                        data-bs-toggle="dropdown"
                                                        aria-expanded="false"
                                                    >
                                                        <span id="staffDropdownBtnText"><?php echo $lang['Select Staff members']; ?></span>
                                                    </button>
                                                    <ul
                                                        class="dropdown-menu w-100"
                                                        aria-labelledby="staffDropdownBtn"
                                                        id="staffDropdownMenu"
                                                        style="max-height: 250px; overflow-y: auto;"
                                                    ></ul>
                                                </div>
                                                <input
                                                    type="hidden"
                                                    name="assigned_team"
                                                    id="selectedStaffInput"
                                                    value=""
                                                />
                                                <small id="staff-error" class="text-danger d-none"><?php echo isset($lang['Please select at least one team member or admin']) ? $lang['Please select at least one team member or admin'] : 'Please select at least one team member or admin.'; ?></small>
                                            </div>
                                        </div>
                                        <div id="userProfileCustomFieldsSection" class="form-group full-grid js-user-profile-cf-section d-none" aria-hidden="true">
                                            <div class="staff-heading"><h4><?php echo htmlspecialchars($lang['Custom fields'] ?? 'Custom fields', ENT_QUOTES, 'UTF-8'); ?></h4></div>
                                            <div id="userProfileCustomFieldsContainer"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Permissions Tab -->
                            <div class="tab-pane fade" id="permissions" role="tabpanel" aria-labelledby="permissions-tab">
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="card permission-box" style="border: none; box-shadow: 0 1px 3px rgba(0,0,0,0.12);">
                                            <div class="card-body">
                                                <div class="row">
                                                    <div class="col-md-12">
                                                        <div class="permission-item d-flex col-gap">
                                                            <div class="checkbox-wrapper-6">
                                                                <input class="tgl tgl-light" id="can_create_task" name="permissions[can_create_task]" type="checkbox"/>
                                                                <label class="tgl-btn" for="can_create_task"></label>
                                                            </div>
                                                            <div>
                                                                <label for="can_create_task" class="permission-label d-flex align-items-center col-gap-5 mb-0">
                                                                    <?php echo $lang['Create Tasks']; ?>
                                                                    <span class="flex-shrink-0" data-bs-toggle="tooltip" data-bs-placement="top" title="<?php echo htmlspecialchars($lang['Client can create new tasks in their projects'], ENT_QUOTES, 'UTF-8'); ?>" onclick="event.preventDefault(); event.stopPropagation();"><?php echo ts_icon('info', 'w-2 text-muted'); ?></span>
                                                                </label>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="permission-item d-flex col-gap">
                                                            <div class="checkbox-wrapper-6">
                                                                <input class="tgl tgl-light" id="can_delete_task" name="permissions[can_delete_task]" type="checkbox"/>
                                                                <label class="tgl-btn" for="can_delete_task"></label>
                                                            </div>
                                                            <div>
                                                                <label for="can_delete_task" class="permission-label d-flex align-items-center col-gap-5 mb-0">
                                                                    <?php echo $lang['Delete Tasks']; ?>
                                                                    <span class="flex-shrink-0" data-bs-toggle="tooltip" data-bs-placement="top" title="<?php echo htmlspecialchars($lang['Client can remove tasks from their projects'], ENT_QUOTES, 'UTF-8'); ?>" onclick="event.preventDefault(); event.stopPropagation();"><?php echo ts_icon('info', 'w-2 text-muted'); ?></span>
                                                                </label>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="permission-item d-flex col-gap">
                                                            <div class="checkbox-wrapper-6">
                                                                <input class="tgl tgl-light" id="can_change_status" name="permissions[can_change_status]" type="checkbox"/>
                                                                <label class="tgl-btn" for="can_change_status"></label>
                                                            </div>
                                                            <div>
                                                                <label for="can_change_status" class="permission-label d-flex align-items-center col-gap-5 mb-0">
                                                                    <?php echo $lang['Change Task Status']; ?>
                                                                    <span class="flex-shrink-0" data-bs-toggle="tooltip" data-bs-placement="top" title="<?php echo htmlspecialchars($lang['Client can move tasks between columns (To Do, In Progress, etc.)'], ENT_QUOTES, 'UTF-8'); ?>" onclick="event.preventDefault(); event.stopPropagation();"><?php echo ts_icon('info', 'w-2 text-muted'); ?></span>
                                                                </label>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="permission-item d-flex col-gap">
                                                            <div class="checkbox-wrapper-6">
                                                                <input class="tgl tgl-light" id="can_update_task" name="permissions[can_update_task]" type="checkbox"/>
                                                                <label class="tgl-btn" for="can_update_task"></label>
                                                            </div>
                                                            <div>
                                                                <label for="can_update_task" class="permission-label d-flex align-items-center col-gap-5 mb-0">
                                                                    <?php echo $lang['Update Tasks']; ?>
                                                                    <span class="flex-shrink-0" data-bs-toggle="tooltip" data-bs-placement="top" title="<?php echo htmlspecialchars($lang['Client can edit task details, titles, and descriptions'], ENT_QUOTES, 'UTF-8'); ?>" onclick="event.preventDefault(); event.stopPropagation();"><?php echo ts_icon('info', 'w-2 text-muted'); ?></span>
                                                                </label>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="permission-item d-flex col-gap">
                                                            <div class="checkbox-wrapper-6">
                                                                <input class="tgl tgl-light" id="can_assign_members" name="permissions[can_assign_members]" type="checkbox"/>
                                                                <label class="tgl-btn" for="can_assign_members"></label>
                                                            </div>
                                                            <div>
                                                                <label for="can_assign_members" class="permission-label d-flex align-items-center col-gap-5 mb-0">
                                                                    <?php echo $lang['Assign Members']; ?>
                                                                    <span class="flex-shrink-0" data-bs-toggle="tooltip" data-bs-placement="top" title="<?php echo htmlspecialchars($lang['Client can assign tasks to staff members'], ENT_QUOTES, 'UTF-8'); ?>" onclick="event.preventDefault(); event.stopPropagation();"><?php echo ts_icon('info', 'w-2 text-muted'); ?></span>
                                                                </label>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="permission-item d-flex col-gap">
                                                            <div class="checkbox-wrapper-6">
                                                                <input class="tgl tgl-light" id="can_view_milestones" name="permissions[can_view_milestones]" type="checkbox"/>
                                                                <label class="tgl-btn" for="can_view_milestones"></label>
                                                            </div>
                                                            <div>
                                                                <label for="can_view_milestones" class="permission-label d-flex align-items-center col-gap-5 mb-0">
                                                                    <?php echo $lang['View Milestones']; ?>
                                                                    <span class="flex-shrink-0" data-bs-toggle="tooltip" data-bs-placement="top" title="<?php echo htmlspecialchars($lang['Client can view project milestones and payment information'], ENT_QUOTES, 'UTF-8'); ?>" onclick="event.preventDefault(); event.stopPropagation();"><?php echo ts_icon('info', 'w-2 text-muted'); ?></span>
                                                                </label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Account Information Tab Submit Box -->
                        <div class="form-group submit-box" id="account-submit-box">
                            <div class="d-flex col-gap align-items-center">
                                <div class="checkbox-wrapper-6">
                                    <input class="tgl tgl-light" id="emailNotification" name="email_notification" type="checkbox" value="1" checked/>
                                    <label class="tgl-btn" for="emailNotification"></label>
                                </div>
                                <div>
                                    <label for="emailNotification" class="permission-label d-flex align-items-center col-gap-5 mb-0">
                                        <?php echo $lang["Email Notification"]; ?>
                                        <span class="flex-shrink-0" data-bs-toggle="tooltip" data-bs-placement="top" title="<?php echo htmlspecialchars($lang["Notify the client of account creation"], ENT_QUOTES, 'UTF-8'); ?>" onclick="event.preventDefault(); event.stopPropagation();"><?php echo ts_icon('info', 'w-2 text-muted'); ?></span>
                                    </label>
                                </div>
                            </div>
                            
                            <div>
                                <button type="button" id="next-to-permissions" class="primary-btn"><?php echo $lang["Next"]; ?></button>
                            </div>
                        </div>
                        
                        <!-- Permissions Tab Submit Box -->
                        <div class="form-group submit-box" id="permissions-submit-box" style="display: none;">
                            <div class="d-flex col-gap align-items-center">
                                <div class="checkbox-wrapper-6">
                                    <input class="tgl tgl-light" id="emailNotification2" name="email_notification" type="checkbox" value="1" checked/>
                                    <label class="tgl-btn" for="emailNotification2"></label>
                                </div>
                                <div>
                                    <label for="emailNotification2" class="permission-label d-flex align-items-center col-gap-5 mb-0">
                                        <?php echo $lang["Email Notification"]; ?>
                                        <span class="flex-shrink-0" data-bs-toggle="tooltip" data-bs-placement="top" title="<?php echo htmlspecialchars($lang["Notify the client of account creation"], ENT_QUOTES, 'UTF-8'); ?>" onclick="event.preventDefault(); event.stopPropagation();"><?php echo ts_icon('info', 'w-2 text-muted'); ?></span>
                                    </label>
                                </div>
                            </div>
                            <div>
                                <button type="submit" name="add-client" id="create-client-btn" class="primary-btn"><?php echo $lang["Create Account"]; ?></button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <div class="clearfix"></div>
        </div>
    </div>
</div>
<textarea id="staffListForAssignmentJson" class="d-none"><?php echo htmlspecialchars(json_encode($staffListForAssignment), ENT_QUOTES, 'UTF-8'); ?></textarea>
<script>
window.USER_PROFILE_CF_CONFIG = {
  entityType: 'client',
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
