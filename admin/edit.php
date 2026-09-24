<?php
/*
 ================================================================================
   Task Session – Project Management System
   File    : edit.php
   Purpose : General purpose editing functionality
 ================================================================================
 */
ob_start(); 
require_once("../includes/lib-initialize.php");
$title = ($lang['Edit Profile'] ?? 'Edit profile') . " | ". $syatem_title;
include("../templates/header.php");

// Initialize variables
$error = "";
$message = "";

 if(!($session->isLoggedIn())){
		redirectTo($url."index");
	}
if($_SESSION['accountStatus'] == 2){
	redirectTo($url."client/index");
}
if($_SESSION['accountStatus'] == 3){
	redirectTo($url."staff/index");
} 
$settingsForModules = settings::findById(1);
$invoiceModuleEnabled = !empty($settingsForModules->module_invoices);
$moduleAttendanceEnabled = $settingsForModules && !empty($settingsForModules->module_attendance);
$moduleIpEnabled = $settingsForModules && !empty($settingsForModules->module_ip_restriction);
// Free edition: attendance module UI/helpers are not shipped.
if (function_exists('tasksession_is_free_edition') && tasksession_is_free_edition()) {
    $moduleAttendanceEnabled = false;
}
$__att = __DIR__ . '/../includes/attendance/helpers.php';
if (is_file($__att)) { require_once $__att; }

if (!function_exists('admin_edit_profile_url')) {
    function admin_edit_profile_url(int $userId, string $status = '', string $queryKey = 'edit'): string
    {
        $allowedKeys = array('edit', 'editprofile', 'editClient');
        if (!in_array($queryKey, $allowedKeys, true)) {
            $queryKey = 'edit';
        }
        $target = 'edit?' . rawurlencode($queryKey) . '=' . $userId;
        if ($status === 'success') {
            return $target . '&profile-update';
        }
        if ($status === 'fail') {
            return $target . '&profile-update-fail';
        }
        if ($status === 'error') {
            return $target . '&profile-update-error';
        }
        return $target;
    }
}

// Accept edit, editprofile, and editClient as valid query parameters
$edit_client = null;
$editProfileQueryKey = 'edit';
if (isset($_GET['edit'])) {
    $edit_client = (int)$_GET['edit'];
} elseif (isset($_GET['editprofile'])) {
    $edit_client = (int)$_GET['editprofile'];
    $editProfileQueryKey = 'editprofile';
} elseif (isset($_GET['editClient'])) {
    $edit_client = (int)$_GET['editClient'];
    $editProfileQueryKey = 'editClient';
}

if ($edit_client)
{
$message = "";
	if(isset($_POST['add-client']))
	{
		require_once(__DIR__ . '/../includes/csrf-middleware.php');
		csrf_require_for_request('html'); 
		$user = user::findById($edit_client); 
		
		$flag=0;
		
		if($user->accountStatus == 2) {
			// client permissions logic for clients only
			require_once("../includes/task_permission.php");
			$permission = TaskPermission::getOrCreate($edit_client);
			$permission->can_create_task = 0;
			$permission->can_delete_task = 0;
			$permission->can_change_status = 0;
			$permission->can_update_task = 0;
			$permission->can_assign_members = 0;
			$permission->can_view_milestones = 0;
			if(isset($_POST['permissions'])) {
				if(isset($_POST['permissions']['can_create_task'])) $permission->can_create_task = 1;
				if(isset($_POST['permissions']['can_delete_task'])) $permission->can_delete_task = 1;
				if(isset($_POST['permissions']['can_change_status'])) $permission->can_change_status = 1;
				if(isset($_POST['permissions']['can_update_task'])) $permission->can_update_task = 1;
				if(isset($_POST['permissions']['can_assign_members'])) $permission->can_assign_members = 1;
				if(isset($_POST['permissions']['can_view_milestones'])) $permission->can_view_milestones = 1;
			}
			    $permission->save();
    $permissionsUpdated = true;
}

if($flag==0)
		{
			$user->id = $edit_client;
			$user->firstName = $_POST['firstName'] ?? '';
			$user->email = $_POST['email'] ?? '';
			
			// Keep the old password if no new one is entered
			$passwordChanged = false;
			if (!empty($_POST['password'])) {
				$user->password = password_hash($_POST['password'], PASSWORD_BCRYPT);
				$passwordChanged = true;
			} else {
				// Keep the target user's original password if no new one is entered
				$user->password = $user->password; // Keep the original password
			}	
			
			$user->address = $_POST['address'] ?? '';
			$user->title = $_POST['title'] ?? '';
			$user->phone = $_POST['phone'] ?? '';
			$user->website = $_POST['website'] ?? '';
			$user->teams_id = $_POST['teams_id'] ?? '';
			$user->fb = $_POST['fb'] ?? '';
			$user->city = $_POST['city'] ?? '';
			$user->state = $_POST['state'] ?? '';
			$user->zip = $_POST['zip'] ?? '';
			$user->country = $_POST['country'] ?? '';
			if ($invoiceModuleEnabled) {
				$user->currency = $_POST['currency'] ?? '';
			}

            $canChangeUserFunction = in_array((int)$user->accountStatus, array(1, 3), true) && (int)$edit_client !== 1;
            if ($canChangeUserFunction && isset($_POST['user_function'])) {
                $newFunction = (int)$_POST['user_function'];
                if (in_array($newFunction, array(1, 3), true)) {
                    $user->accountStatus = $newFunction;
                }
            }

            if ((int)$user->accountStatus === 3 && isset($_POST['role_id'])) {
                $user->role_id = intval($_POST['role_id']);
            } elseif ((int)$user->accountStatus === 1) {
                $user->role_id = null;
            }

            $allowAttendanceFieldsSave = $moduleAttendanceEnabled
                && in_array((int)$user->accountStatus, array(1, 3), true)
                && (
                    (int)$user->accountStatus === 1
                    || (
                        function_exists('attendance_role_id_has_any_permission')
                        && attendance_role_id_has_any_permission((int)($user->role_id ?? 0))
                    )
                );

            $shift_id = 0;
            if (in_array((int)$user->accountStatus, array(1, 3), true)) {
                if ($moduleIpEnabled) {
                    $user->login_ip_restriction_enabled = isset($_POST['login_ip_restriction_enabled']) ? 1 : 0;
                    $user->allowed_login_ips = trim((string)($_POST['allowed_login_ips'] ?? ''));
                }
                if ($allowAttendanceFieldsSave) {
                    $user->attendance_disabled = isset($_POST['attendance_disabled']) ? 1 : 0;
                    $shift_id = isset($_POST['shift_id']) ? (int)$_POST['shift_id'] : 0;
                    $user->base_salary = isset($_POST['base_salary']) ? (float)$_POST['base_salary'] : 0;
                    $deductionMode = trim((string)($_POST['attendance_deduction_mode'] ?? ''));
                    if (!in_array($deductionMode, array('fixed', 'percentage'), true)) {
                        $deductionMode = null;
                    }
                    $user->attendance_deduction_mode = $deductionMode;
                    $user->attendance_deduction_value = ($deductionMode !== null && isset($_POST['attendance_deduction_value']))
                        ? (float)$_POST['attendance_deduction_value']
                        : null;
                }
            }
			
			// Handle assigned team for clients (comma-separated string from hidden input)
			if($user->accountStatus == 2) {
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
				$user->assigned_team = $assigned_team;
			}
			
			$saveUser = $user->save();
            $shiftUpdated = false;
            if ($allowAttendanceFieldsSave
                && in_array((int)$user->accountStatus, array(1, 3), true)
                && (int)$user->attendance_disabled !== 1
                && isset($shift_id) && $shift_id > 0) {
                $effectiveFrom = date('Y-m-d');
                $assignedBy = (int)$session->userId;
                $reason = 'Assigned from edit profile';
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
                    $checkShiftStmt->bind_param('iss', $edit_client, $effectiveFrom, $effectiveFrom);
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
                            $shiftUpdated = $updateShiftStmt->execute();
                            $updateShiftStmt->close();
                        }
                    } else {
                        $insertShiftStmt = $connect->prepare(
                            "INSERT INTO attendance_shift_assignments (user_id, shift_id, effective_from, assigned_by, reason)
                             VALUES (?, ?, ?, ?, ?)"
                        );
                        if ($insertShiftStmt) {
                            $insertShiftStmt->bind_param('iisis', $edit_client, $shift_id, $effectiveFrom, $assignedBy, $reason);
                            $shiftUpdated = $insertShiftStmt->execute();
                            $insertShiftStmt->close();
                        }
                    }
                }
            }
				
			if($saveUser)
				{
					if (isset($edit_client) && in_array((int)$user->accountStatus, [1, 2, 3], true)) {
						require_once(__DIR__ . '/../includes/custom-fields/user_profile_values.php');
						$cfEntitySave = ((int)$user->accountStatus === 2)
							? 'client'
							: (((int)$user->accountStatus === 3) ? 'staff' : 'admin');
						save_user_profile_custom_field_values(
							$connect,
							(int)$edit_client,
							$cfEntitySave,
							isset($_POST['custom_fields']) && is_array($_POST['custom_fields']) ? $_POST['custom_fields'] : null
						);
					}
					// Log password change if password was updated
					if ($passwordChanged) {
						require_once('../includes/activity_logger.php');
						ActivityLogger::logPasswordChange($edit_client, $session->userId);
					}

					$picture = new profilePicture();
					$picture->fkUserId = $edit_client;
					$profilePicAlreadyExists = $picture->findByfkUserId($picture->fkUserId);
					$profilePicAlreadyExistsId = null;
					foreach ($profilePicAlreadyExists as $record) {
						$profilePicAlreadyExistsId = $record->id;
						$picture->fileToUnlink = $record->filename;
						break;
					}
					if (isset($profilePicAlreadyExistsId)) {
						$picture->id = $profilePicAlreadyExistsId;
					}

					$proPicFile = $_FILES['pro-pic'] ?? null;
					$hasNewProfilePic = is_array($proPicFile)
						&& isset($proPicFile['error'])
						&& (int) $proPicFile['error'] === UPLOAD_ERR_OK
						&& !empty($proPicFile['tmp_name']);

					if ($hasNewProfilePic && $picture->attachFile($proPicFile)) {
						$picture->createdDate = strftime('%Y-%m-%d %H:%M:%S', time());
						if ($picture->save()) {
							unset($picture);
							header('Location:' . admin_edit_profile_url((int) $edit_client, 'success', $editProfileQueryKey));
							exit;
						}
						header('Location:' . admin_edit_profile_url((int) $edit_client, 'error', $editProfileQueryKey));
						exit;
					}

					header('Location:' . admin_edit_profile_url((int) $edit_client, 'success', $editProfileQueryKey));
					exit;
				}
				else
				{
					// Check if permissions were updated successfully but no user data changed
					if((isset($permissionsUpdated) && $permissionsUpdated) || $shiftUpdated) {
						header("Location:" . admin_edit_profile_url((int)$edit_client, 'success', $editProfileQueryKey));
					} else {
						header("Location:" . admin_edit_profile_url((int)$edit_client, 'fail', $editProfileQueryKey));
					}
					exit;
				}
								
		}
				
	}
$toast_flash = null;
if (isset($_GET['profile-update'])) {
    $toast_flash = array('type' => 'success', 'msg' => $lang['Record updated successfully']);
} elseif (isset($_GET['profile-update-fail'])) {
    $toast_flash = array('type' => 'error', 'msg' => $lang['Same record was updated.']);
} elseif (isset($_GET['profile-update-error'])) {
    $toast_flash = array('type' => 'error', 'msg' => $lang['Picture could not be uploaded due to following error']);
} elseif(isset($_GET['message'])){
$msgstatus = $_GET['message'];
if($msgstatus == 'success'){
    $toast_flash = array('type' => 'success', 'msg' => $lang['Record updated successfully']);
}
if($msgstatus == 'fail'){
    $toast_flash = array('type' => 'error', 'msg' => $lang['Same record was updated.']);
}
if($msgstatus == 'error'){
    $toast_flash = array('type' => 'error', 'msg' => $lang['Picture could not be uploaded due to following error']);
}
}
//condition check for login

$id=$session->userId; //id of the current client 
$user = User::findById((int)$session->userId); //take the record of current user in an object array 	
$username=$user->firstName;;
$email=$user->email;;
$account_stat=$user->status;;
$user->regDate;


$id1=$edit_client; //id of the current client 
$user1 = User::findById($id1); //take the record of current user in an object array 	
$username1=$user1->firstName;;
$email1=$user1->email;;
$account_stat1=$user1->status;;
$user1->regDate;
$showAttendanceProfileSection = !empty($moduleAttendanceEnabled)
    && function_exists('attendance_user_show_profile_attendance_section')
    && attendance_user_show_profile_attendance_section($user1);
$attendanceShifts = array();
$currentAssignedShiftId = 0;
if ($showAttendanceProfileSection) {
    $shiftRes = $connect->query("SELECT id, name, code FROM attendance_shifts WHERE is_active = 1 ORDER BY name ASC");
    if ($shiftRes) {
        while ($shiftRow = $shiftRes->fetch_assoc()) {
            $attendanceShifts[] = $shiftRow;
        }
    }
    $effectiveFrom = date('Y-m-d');
    $activeShiftStmt = $connect->prepare(
        "SELECT shift_id
         FROM attendance_shift_assignments
         WHERE user_id = ?
           AND effective_from <= ?
           AND (effective_to IS NULL OR effective_to >= ?)
         ORDER BY effective_from DESC, id DESC
         LIMIT 1"
    );
    if ($activeShiftStmt) {
        $activeShiftStmt->bind_param('iss', $id1, $effectiveFrom, $effectiveFrom);
        $activeShiftStmt->execute();
        $activeShiftRow = $activeShiftStmt->get_result()->fetch_assoc();
        $activeShiftStmt->close();
        if ($activeShiftRow) {
            $currentAssignedShiftId = (int)$activeShiftRow['shift_id'];
        }
    }
}

// Get all admin and staff users for "Assign Team" dropdown (for clients only)
$staffListForAssignment = [];
$existingAssignedIds = [];
if ($user1->accountStatus == 2) {
	require_once("../includes/functions.php");
	$allStaffAndAdmin = User::findBySql("SELECT * FROM users WHERE (accountStatus = 1 OR accountStatus = 3) AND status = 0 ORDER BY firstName ASC");
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
	
	// Get existing assigned team IDs - check database directly if property doesn't exist
	$existingAssignedTeam = '';
	if (isset($user1->assigned_team) && $user1->assigned_team !== null) {
		$existingAssignedTeam = $user1->assigned_team;
	} else {
		// Fallback: query database directly
		global $database;
		$checkQuery = $database->query("SELECT assigned_team FROM users WHERE id = " . (int)$user1->id);
		if ($checkQuery && $checkRow = $database->fetchArray($checkQuery)) {
			$existingAssignedTeam = isset($checkRow['assigned_team']) ? $checkRow['assigned_team'] : '';
		}
	}
	$existingAssignedIds = !empty($existingAssignedTeam) ? array_map('trim', explode(',', $existingAssignedTeam)) : [];
}

$editProfileBackUrl = 'clients';
if (isset($user1) && in_array((int)$user1->accountStatus, [1, 3], true)) {
	$editProfileBackUrl = 'members';
}

$memberRoles = array();
if (isset($user1) && in_array((int)$user1->accountStatus, array(1, 3), true)) {
	$rolesRes = $connect->query("SELECT id, name FROM roles ORDER BY name ASC");
	if ($rolesRes) {
		while ($roleRow = $rolesRes->fetch_assoc()) {
			$memberRoles[] = $roleRow;
		}
	}
}
$canEditUserFunction = isset($user1) && in_array((int)$user1->accountStatus, array(1, 3), true) && (int)$user1->id !== 1;

$userProfileCfInitialValues = [];
$userProfileCfEntityTypeJs = '';
if (isset($user1, $id1) && $user1 && $id1 > 0 && in_array((int)$user1->accountStatus, [1, 2, 3], true)) {
	$userProfileCfEntityTypeJs = ((int)$user1->accountStatus === 2)
		? 'client'
		: (((int)$user1->accountStatus === 3) ? 'staff' : 'admin');
	$cfUid = (int)$id1;
	$cfEnt = $userProfileCfEntityTypeJs;
	$cfStmt = @$connect->prepare(
		'SELECT cf.id, cf.field_type, cfv.field_value FROM custom_fields cf
		 INNER JOIN user_custom_field_values cfv ON cf.id = cfv.custom_field_id AND cfv.user_id = ?
		 WHERE cf.entity_type = ?'
	);
	if ($cfStmt) {
		$cfStmt->bind_param('is', $cfUid, $cfEnt);
		if ($cfStmt->execute()) {
			$cfRes = $cfStmt->get_result();
			if ($cfRes) {
				while ($cfRow = $cfRes->fetch_assoc()) {
					$fid = (int)$cfRow['id'];
					$ft = (string)$cfRow['field_type'];
					$fv = $cfRow['field_value'];
					$val = $fv;
					if ($ft === 'multiple_select' && $fv !== null && $fv !== '') {
						$d = json_decode($fv, true);
						$val = is_array($d) ? $d : [];
					}
					$userProfileCfInitialValues[] = ['id' => $fid, 'field_type' => $ft, 'value' => $val];
				}
			}
		}
		$cfStmt->close();
	}
}

$profilePictureObj1=profilePicture::findByfkUserId($id1);
if($profilePictureObj1){
	foreach($profilePictureObj1 as $displayPicture1)
	{
	 $profilePic1=$displayPicture1->filename;
	}
}

// Allow admins to edit admin profiles (including their own)
// Only block if trying to edit super admin (id=1) from a non-super admin account
if ($user1->id == 1 && $session->userId != 1) {
    $toast_flash = array(
        'type' => 'error',
        'msg' => $lang['Only the super admin can edit the super admin profile.'] ?? 'Only the super admin can edit the super admin profile.',
    );
    include('../templates/main-footer.php');
    ?>
<script>window.__toastFlash=<?php echo json_encode($toast_flash, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;</script>
<script src="<?php echo htmlspecialchars(rtrim((string) $url, '/') . '/assets/js/toast.js', ENT_QUOTES, 'UTF-8'); ?>"></script>
    <?php
    exit;
}
?>
	<div class="page-container">
		<div class="container-fluid">
			<div class="row row-eq-height">
				<?php  include("../templates/sidebar.php"); ?>
					<div class="page-content">
						<?php include('../templates/top-header.php'); ?>
							
							<div class="add-client center-col">
													<h2 class="page-title"><?php echo $lang['Edit Profile']; ?></h2>
													<form method="post" action="#" enctype="multipart/form-data" data-custom-fields-guard="1" autocomplete="off">
														<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
														<!-- Ensure POST handler triggers even if submit button is disabled by spinner JS -->
														<input type="hidden" name="add-client" value="1" />
												 <div class="mb-4 d-flex align-items-center col-gap dp-box upload-profile-pic">
											       <div  style="position: relative; display: inline-block;">
													<div class="img-uploadwrap">
														<?php if(isset($profilePic1)){ ?> <img src="<?php echo getProfilePicUrl($profilePic1, 150, 150); ?>" class="img-responsive" alt="Profile Picture" style="width: 100%; height: 100%; object-fit: cover;"/>
															<?php } else { ?> <img src="../assets/images/upload-img.jpg" class="img-responsive" style="width: 100%; height: 100%; object-fit: cover;"/>
															<?php } ?>
													</div>
													<div class="input-btn alert-savestn pro-pic">  
												   <span style="font-size: 24px;">+</span>
													<input type="file" name="pro-pic" class="pro-pic" data-clientid="<?php echo (int)$edit_client; ?>" id="imgInp" style="opacity: 0; position: absolute; width: 100%; height: 100%; cursor: pointer;"/> </div>  
													</div>
													  <div class="mt-3 max-width-300">
														<h4><?php echo $lang['Change profile image']; ?></h4>
														<p style="color: #757575; font-size: 12px;"><?php echo $lang['Profile image must be a .jpg .png file smaller than 10MB and at least 400px by 400px.']; ?></p>
													</div>
												</div>
										<!-- Tab Navigation -->
										<ul class="nav nav-tabs" id="userTabs" role="tablist">
											<li class="nav-item" style="margin-right: 5px;">
												<a class="nav-link active" id="account-tab" data-toggle="tab" href="#account" role="tab" aria-controls="account" aria-selected="true">
													<?php echo $lang['Account Information']; ?>
												</a>
											</li>
											<?php if($user1->accountStatus == 2): ?>
											<li class="nav-item">
												<a class="nav-link" id="permissions-tab" data-toggle="tab" href="#permissions" role="tab" aria-controls="permissions" aria-selected="false">
													<?php echo $lang['Permissions']; ?>
												</a>
											</li>
											<?php endif; ?>
										</ul>
										
										<!-- Tab Content -->
										<div class="tab-content" id="userTabsContent">
											<!-- Account Information Tab -->
											<div class="tab-pane fade show active" id="account" role="tabpanel" aria-labelledby="account-tab">
												<div class="user-info">
													<div class="display-grid">
														<!-- Full Name -->
														<div class="form-group">
															<label for="firstName"><?php echo $lang['Full name*']; ?></label>
															<input type="text" name="firstName" class="form-control only-alpha" required value="<?php echo $user1->firstName;?>" autocomplete="off">
														</div>
														
														<!-- Password -->
														<div class="form-group">
															<label for="password"><?php echo $lang['Password*']; ?></label>
															<div class="eyes-row">
																<input type="password" name="password" class="form-control passwordfield" placeholder="New password (blank to keep)" value="" autocomplete="new-password">
																<?php echo ts_password_toggle_html($lang['Show password'] ?? 'Show password'); ?>
															</div>
														</div>
														
														<!-- Phone -->
														<div class="form-group">
															<label for="phone"><?php echo $lang['Phone']; ?></label>
															<input type="tel" name="phone" class="form-control only-alpha" value="<?php echo $user1->phone;?>">
														</div>
														
														<!-- Teams ID -->
														<div class="form-group">
															<label for="teams_id"><?php echo $lang['Teams ID']; ?></label>
															<input type="text" name="teams_id" class="form-control only-alpha" value="<?php echo $user1->teams_id;?>">
														</div>
														
														<?php if($user1->accountStatus == 2): ?>
														<!-- City -->
														<div class="form-group">
															<label for="city"><?php echo $lang['City']; ?></label>
															<input type="text" name="city" value="<?php echo $user1->city;?>" class="form-control only-alpha">
														</div>
														
														<!-- Zip -->
														<div class="form-group">
															<label for="zip"><?php echo $lang['Zip']; ?></label>
															<input type="text" name="zip" value="<?php echo $user1->zip;?>" class="form-control only-alpha">
														</div>
														<?php endif; ?>
													
														<!-- Email -->
														<div class="form-group">
															<label for="email"><?php echo $lang['Email*']; ?></label>
															<input type="email" name="email" class="form-control" required value="<?php echo $user1->email;?>" autocomplete="off">
														</div>
														
														<!-- Address -->
														<div class="form-group">
															<label for="address"><?php echo $lang['Address']; ?></label>
															<input type="text" name="address" class="form-control only-alpha" value="<?php echo $user1->address;?>">
														</div>
														
														<?php if($user1->accountStatus == 2): ?>
														<!-- Website -->
														<div class="form-group">
															<label for="website"><?php echo $lang['Website Url*']; ?></label>
															<input type="url" name="website" class="form-control" value="<?php echo $user1->website;?>">
														</div>
														<?php else: ?>
														<!-- Job Title -->
														<div class="form-group">
															<label for="Job Title"><?php echo $lang['Job Title']; ?></label>
															<input type="text" name="title" class="form-control only-alpha" required value="<?php echo $user1->title;?>">
														</div>
														<?php endif; ?>
														
														<!-- Facebook URL -->
														<div class="form-group">
															<label for="Facebook"><?php echo $lang['Facebook Url']; ?></label>
															<input type="url" name="fb" placeholder="https://www.facebook.com/User Id" pattern=".*\.facebook\..*" class="form-control" value="<?php echo $user1->fb;?>">
														</div>
														<?php if (in_array((int)$user1->accountStatus, array(1, 3), true) && !empty($moduleIpEnabled)): ?>
														<div class="form-group full-grid">
															<div class="permission-item d-flex col-gap align-items-center mb-3">
																<div class="checkbox-wrapper-6">
																	<input class="tgl tgl-light" id="login_ip_restriction_enabled_edit" name="login_ip_restriction_enabled" type="checkbox" value="1" <?php echo !empty($user1->login_ip_restriction_enabled) ? 'checked' : ''; ?>>
																	<label class="tgl-btn" for="login_ip_restriction_enabled_edit"></label>
																</div>
																<div class="flex-grow-1">
																	<label for="login_ip_restriction_enabled_edit" class="permission-label fw-bold"><?php echo $lang['Enable Account IP Restriction']; ?></label>
																</div>
															</div>
															<input type="text" name="allowed_login_ips" class="form-control" value="<?php echo htmlspecialchars((string)($user1->allowed_login_ips ?? '')); ?>" placeholder="111.88.7.28, 192.168.1.10">
															<small class="form-text text-muted"><?php echo $lang['Allowed account IPs (comma separated)']; ?></small>
														</div>
														<?php endif; ?>
														
														<?php if($user1->accountStatus == 2): ?>
														<!-- State -->
														<div class="form-group">
															<label for="state"><?php echo $lang['State']; ?></label>
															<input type="text" name="state" value="<?php echo $user1->state;?>" class="form-control only-alpha">
														</div>
														
														<!-- Country -->
														<div class="form-group">
															<label for="country"><?php echo $lang['Country']; ?></label>
															<select name="country" class="form-control">
																<option value=""><?php echo $lang['Select Country']; ?></option>
																<?php foreach($countries as $countrie){
																	echo '<option ';
																	if($user1->country == $countrie){echo 'selected ';}
																	echo 'value="'.$countrie.'">'.$countrie.'</option>';
																} ?>
															</select>
														</div>
														
                                                        <?php if ($invoiceModuleEnabled): ?>
														<!-- Currency -->
														<div class="form-group full-grid m">
															<label for="currency"><?php echo $lang['Currency']; ?></label>
															<select name="currency" class="form-control">
																<option value=""><?php echo $lang['Select Currency']; ?></option>
																<?php 
																$enabledCurrencies = $settingsForModules->getMultipleCurrencies();
																foreach($settingsForModules->currency_symbols as $currency => $symbol ){
																	if(in_array($currency, $enabledCurrencies)) {
																		echo '<option ';
																		if($user1->currency == $currency){echo 'selected ';}
																		echo 'value="'.$currency.'">'.$symbol.'</option>';
																	}
																} ?>
															</select>
															<small class="form-text text-muted mb-3"><?php echo $lang['Select the currency this client prefers for payments']; ?></small>
														</div>
                                                        <?php endif; ?>
														<?php endif; ?>
														<?php if (in_array((int)$user1->accountStatus, [1, 2, 3], true)): ?>
														<div id="userProfileCustomFieldsSection" class="form-group full-grid js-user-profile-cf-section d-none" aria-hidden="true">
															<div class="staff-heading"><h4>Custom fields</h4></div>
															<div id="userProfileCustomFieldsContainer"></div>
														</div>
														<?php endif; ?>
													</div>
												</div>
											</div>
											
											<?php if($user1->accountStatus == 2): ?>
											<!-- Permissions Tab for clients only -->
											<div class="tab-pane fade" id="permissions" role="tabpanel" aria-labelledby="permissions-tab">
												<div class="row">
													<div class="col-md-12">
														<div class="card permission-box" style="border: none; box-shadow: 0 1px 3px rgba(0,0,0,0.12);">
															<div class="card-body">
																<?php
																// Load task permissions
																require_once("../includes/task_permission.php");
																$permissions = TaskPermission::getOrCreate($edit_client);
																?>
																<div class="row">
																	<div class="col-md-12">
																		<div class="permission-item d-flex col-gap">
																			<div class="checkbox-wrapper-6">
																				<input class="tgl tgl-light" id="can_create_task" name="permissions[can_create_task]" type="checkbox" <?php echo $permissions->can_create_task ? 'checked' : ''; ?>/>
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
																				<input class="tgl tgl-light" id="can_delete_task" name="permissions[can_delete_task]" type="checkbox" <?php echo $permissions->can_delete_task ? 'checked' : ''; ?>/>
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
																				<input class="tgl tgl-light" id="can_change_status" name="permissions[can_change_status]" type="checkbox" <?php echo $permissions->can_change_status ? 'checked' : ''; ?>/>
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
																				<input class="tgl tgl-light" id="can_update_task" name="permissions[can_update_task]" type="checkbox" <?php echo $permissions->can_update_task ? 'checked' : ''; ?>/>
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
																				<input class="tgl tgl-light" id="can_assign_members" name="permissions[can_assign_members]" type="checkbox" <?php echo $permissions->can_assign_members ? 'checked' : ''; ?>/>
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
																				<input class="tgl tgl-light" id="can_view_milestones" name="permissions[can_view_milestones]" type="checkbox" <?php echo $permissions->can_view_milestones ? 'checked' : ''; ?>/>
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
											<?php endif; ?>
										</div>
										
										<?php if ($canEditUserFunction): ?>
											<div class="user-info form-group mt-4">
												<label for="user_function"><?php echo $lang['User Role*']; ?></label>
												<select name="user_function" id="user_function" class="form-control" required>
													<option value="3" <?php echo (int)$user1->accountStatus === 3 ? 'selected' : ''; ?>><?php echo $lang['Staff']; ?></option>
													<option value="1" <?php echo (int)$user1->accountStatus === 1 ? 'selected' : ''; ?>><?php echo $lang['Admin']; ?></option>
												</select>
												<small class="form-text text-muted"><?php echo $lang['Select whether this user is Staff or Admin']; ?></small>
											</div>
											<div class="user-info form-group mt-2" id="rolePermissionSection" style="<?php echo (int)$user1->accountStatus === 1 ? 'display:none;' : ''; ?>">
												<label for="role_id"><?php echo $lang['Access Permission*']; ?></label>
												<select name="role_id" id="role_id" class="form-control" <?php echo (int)$user1->accountStatus === 3 ? 'required' : ''; ?>>
													<option value=""><?php echo $lang['Select Role']; ?></option>
													<?php foreach ($memberRoles as $role): ?>
														<option value="<?php echo (int)$role['id']; ?>" <?php echo ((int)$user1->role_id === (int)$role['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($role['name']); ?></option>
													<?php endforeach; ?>
												</select>
											</div>
										<?php endif; ?>
                                        <?php if ($showAttendanceProfileSection): ?>
                                            <div class="form-group full-grid mt-3">
                                                <div class="permission-item d-flex col-gap align-items-center mb-0">
                                                    <div class="checkbox-wrapper-6">
                                                        <input class="tgl tgl-light" id="attendance_disabled_edit" name="attendance_disabled" type="checkbox" value="1" <?php echo !empty($user1->attendance_disabled) ? 'checked' : ''; ?>>
                                                        <label class="tgl-btn" for="attendance_disabled_edit"></label>
                                                    </div>
                                                    <div class="flex-grow-1">
                                                        <label for="attendance_disabled_edit" class="permission-label fw-bold mb-0"><?php echo $lang['Disable Attendance'] ?? 'Disable Attendance'; ?></label>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php if (empty($user1->attendance_disabled)): ?>
                                            <div class="display-grid mt-3">
                                                <div class="form-group">
                                                    <label for="shift_id"><?php echo $lang['Shifts'] ?? 'Shift'; ?></label>
                                                    <select name="shift_id" class="form-control">
                                                        <option value=""><?php echo $lang['Select'] ?? 'Select'; ?></option>
                                                        <?php foreach ($attendanceShifts as $shift): ?>
                                                            <option value="<?php echo (int)$shift['id']; ?>" <?php echo $currentAssignedShiftId === (int)$shift['id'] ? 'selected' : ''; ?>>
                                                                <?php echo htmlspecialchars($shift['name'] . (!empty($shift['code']) ? ' (' . $shift['code'] . ')' : '')); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="form-group">
                                                    <label for="base_salary"><?php echo $lang['Base Salary']; ?></label>
                                                    <input type="number" name="base_salary" class="form-control" step="0.01" min="0" value="<?php echo (float)($user1->base_salary ?? 0); ?>">
                                                </div>
                                                <div class="form-group">
                                                    <label for="attendance_deduction_mode"><?php echo $lang['Staff Deduction Override Mode']; ?></label>
                                                    <select name="attendance_deduction_mode" class="form-control">
                                                        <option value="" <?php echo empty($user1->attendance_deduction_mode) ? 'selected' : ''; ?>><?php echo $lang['Use Global Default'] ?? 'Use Global Default'; ?></option>
                                                        <option value="fixed" <?php echo (($user1->attendance_deduction_mode ?? '') === 'fixed') ? 'selected' : ''; ?>><?php echo $lang['Fixed Amount'] ?? 'Fixed Amount'; ?></option>
                                                        <option value="percentage" <?php echo (($user1->attendance_deduction_mode ?? '') === 'percentage') ? 'selected' : ''; ?>><?php echo $lang['Percentage'] ?? 'Percentage'; ?></option>
                                                    </select>
                                                </div>
                                                <div class="form-group">
                                                    <label for="attendance_deduction_value"><?php echo $lang['Staff Deduction Override Value']; ?></label>
                                                    <input type="number" name="attendance_deduction_value" class="form-control" step="0.01" min="0" value="<?php echo isset($user1->attendance_deduction_value) ? (float)$user1->attendance_deduction_value : 0; ?>">
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                        <?php endif; ?>
										
										<?php if ($user1->accountStatus == 2): ?>
											<div class="form-group full-grid">
												<label for="staffDropdownBtn">Assign team members & admins</label>
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
														value="<?php echo htmlspecialchars($existingAssignedTeam, ENT_QUOTES, 'UTF-8'); ?>"
													/>
												</div>
											</div>
										<?php endif; ?>
										
										<div class="form-group submit-box">
											<div class="d-flex align-items-center flex-wrap col-gap-10">
												<button type="submit" name="add-client" id="create-staff-btn" class="primary-btn"><?php echo $lang['Save Changes']; ?></button>
												<?php if (isset($_GET['profile-update']) || (isset($_GET['message']) && $_GET['message'] === 'success')): ?>
													<a href="profile?user_id=<?php echo (int)$id1; ?>" class="primary-btn"><?php echo htmlspecialchars($lang['View Profile'], ENT_QUOTES, 'UTF-8'); ?></a>
												<?php else: ?>
													<a href="<?php echo htmlspecialchars($editProfileBackUrl, ENT_QUOTES, 'UTF-8'); ?>" class="secondary-btn-a" style="text-decoration:none;display:inline-flex;align-items:center;"><?php echo htmlspecialchars($lang['Back'] ?? 'Back', ENT_QUOTES, 'UTF-8'); ?></a>
												<?php endif; ?>
											</div>
										</div>
									</form>
								</div>
							</div>
					<div class="clearfix"></div>
			</div>
		</div>
	</div>
	<?php if (in_array((int)$user1->accountStatus, [1, 2, 3], true)): ?>
<script>
window.USER_PROFILE_CF_CONFIG = {
	entityType: <?php echo json_encode($userProfileCfEntityTypeJs, JSON_HEX_TAG | JSON_HEX_APOS); ?>,
	listUrl: '../includes/custom-fields/list.php',
	containerSelector: '#userProfileCustomFieldsContainer',
	initialValues: <?php echo json_encode($userProfileCfInitialValues, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE); ?>
};
</script>
<script src="../assets/js/user-profile-custom-fields-form.js"></script>
<script src="../assets/js/custom-fields-form-guard.js"></script>
<?php endif; ?>
	<?php if ($canEditUserFunction): ?>
<script>
(function() {
    var functionSelect = document.getElementById('user_function');
    var roleSection = document.getElementById('rolePermissionSection');
    var roleSelect = document.getElementById('role_id');
    if (!functionSelect || !roleSection || !roleSelect) {
        return;
    }
    function toggleRoleSection() {
        var isStaff = functionSelect.value === '3';
        roleSection.style.display = isStaff ? '' : 'none';
        roleSelect.required = isStaff;
        if (!isStaff) {
            roleSelect.removeAttribute('required');
        }
    }
    functionSelect.addEventListener('change', toggleRoleSection);
    toggleRoleSection();
})();
</script>
	<?php endif; ?>
	<?php if ($user1->accountStatus == 2): ?>
<script>
// Staff dropdown functionality for Assign Team
(function() {
    const staffList = <?php echo json_encode($staffListForAssignment); ?>;
    const selectedStaffIds = <?php echo json_encode($existingAssignedIds); ?>;
    let selectedStaff = [];
    
    // Initialize with preselected staff
    if (selectedStaffIds && Array.isArray(selectedStaffIds) && selectedStaffIds.length > 0) {
        selectedStaff = selectedStaffIds.map(id => {
            const staff = staffList.find(staff => String(staff.id) === String(id));
            if (staff) {
                return {
                    id: staff.id,
                    name: staff.name,
                    image: staff.image,
                    accountStatus: staff.accountStatus
                };
            }
            return null;
        }).filter(Boolean);
    }
    
    const staffMenu = document.getElementById('staffDropdownMenu');
    const staffBtn = document.getElementById('staffDropdownBtn');
    const staffBtnText = document.getElementById('staffDropdownBtnText');
    const selectedStaffInput = document.getElementById('selectedStaffInput');
    
    if (!staffMenu || !staffBtn || !staffBtnText || !selectedStaffInput) {
        return;
    }
    
    // Render staff dropdown
    function renderStaffDropdown() {
        staffMenu.innerHTML = '';
        staffList.forEach(staff => {
            const li = document.createElement('li');
            // Use the avatar HTML directly (it's already formatted by getUserAvatarHtml)
            const avatarHtml = staff.image;
            const adminBadge = staff.accountStatus === 1 ? '<span class="badge color-inprogress inprogress-bg-op ms-2 align-self-start">ADMIN</span>' : '';
            
            const isSelected = selectedStaff.some(s => String(s.id) === String(staff.id));
            li.innerHTML = `
                <a href="#" class="dropdown-item d-flex align-items-center ${isSelected ? 'active' : ''}" data-id="${staff.id}">
                    <div class="me-2">${avatarHtml}</div>
                    <span>${staff.name}</span>
                    ${adminBadge}
                </a>
            `;
            staffMenu.appendChild(li);
        });
    }
    
    // Update selected staff display
    function updateSelectedStaff() {
        selectedStaffInput.value = selectedStaff.map(s => s.id).join(',');
        
        if (selectedStaff.length) {
            const staffHtml = selectedStaff.map(s => {
                // Adjust avatar size for display (from 28px to 24px)
                let avatarHtml = s.image;
                // If it's an img tag, adjust the size
                if (avatarHtml.includes('<img')) {
                    avatarHtml = avatarHtml.replace(/width=["']?\d+px["']?/g, 'width="24px"')
                                           .replace(/height=["']?\d+px["']?/g, 'height="24px"')
                                           .replace(/me-2/g, 'me-1');
                } else {
                    // For initials, adjust size classes
                    avatarHtml = avatarHtml.replace(/width:28px;height:28px/g, 'width:24px;height:24px')
                                           .replace(/me-2/g, 'me-1');
                }
                
                const adminBadge = s.accountStatus === 1 ? '<span class="badge color-inprogress inprogress-bg-op">ADMIN</span>' : '';
                
                return `
                    <div class="d-inline-flex align-items-center active-user">
                        <div>${avatarHtml}</div>
                        <span class="ms-1">${s.name}</span>
                        ${adminBadge}
                        <button type="button" class="btn-close btn-close-sm ms-2" aria-label="Remove" 
                                onclick="removeStaffFromClient('${s.id}')" style="font-size: 8px; padding: 1px;">×</button>
                    </div>
                `;
            }).join('');
            
            staffBtnText.innerHTML = staffHtml;
        } else {
            staffBtnText.textContent = 'Select Staff members';
        }
        
        // Update dropdown items to show selected state
        renderStaffDropdown();
    }
    
    // Remove staff function
    window.removeStaffFromClient = function(staffId) {
        selectedStaff = selectedStaff.filter(s => String(s.id) !== String(staffId));
        updateSelectedStaff();
    };
    
    // Handle staff selection/deselection
    staffMenu.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const a = e.target.closest('a[data-id]');
        if (!a) {
            return;
        }
        
        const id = a.getAttribute('data-id');
        const nameElement = a.querySelector('span');
        const name = nameElement ? nameElement.textContent : '';
        const imgDiv = a.querySelector('div');
        const img = imgDiv ? imgDiv.innerHTML : '';
        
        const staffObj = staffList.find(s => String(s.id) === String(id));
        
        const idx = selectedStaff.findIndex(s => String(s.id) === String(id));
        if (idx === -1) {
            selectedStaff.push({ 
                id, 
                name, 
                image: img,
                accountStatus: staffObj ? staffObj.accountStatus : 3
            });
        } else {
            selectedStaff.splice(idx, 1);
        }
        updateSelectedStaff();
        
        // Prevent dropdown from closing
        e.stopImmediatePropagation();
    });
    
    // Initialize
    renderStaffDropdown();
    updateSelectedStaff();
})();
</script>
	<?php endif; ?>
<script>window.__toastFlash=<?php echo json_encode($toast_flash, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;</script>
<script>window.__profilePicToast=<?php echo json_encode(array(
    'success' => $lang['Profile Picture Updated'] ?? 'Profile picture updated.',
    'fail' => $lang['Image formate not Supported or image is too big'] ?? 'Image format not supported or image is too big.',
    'error' => $lang['An error occurred during upload'] ?? 'An error occurred during upload.',
), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;</script>
<script src="<?php echo htmlspecialchars(rtrim((string) $url, '/') . '/assets/js/toast.js', ENT_QUOTES, 'UTF-8'); ?>"></script>
	<?php  include("../templates/main-footer.php"); ?>
	<script>
document.addEventListener('DOMContentLoaded', function() {
    if (window.history && window.history.replaceState && /[?&]profile-update(?:-fail|-error)?(?:&|$)/.test(window.location.search)) {
        var cleanUrl = window.location.pathname + '?' + <?php echo json_encode($editProfileQueryKey); ?> + '=' + encodeURIComponent(<?php echo json_encode((string)(int)$edit_client); ?>);
        window.history.replaceState({}, document.title, cleanUrl);
    }
    var tabLinks = document.querySelectorAll('.nav-link');
    var tabContents = document.querySelectorAll('.tab-pane');

    function activateTab(tabLink) {
        var targetId = tabLink.getAttribute('href');
        if (!targetId) return;
        var contentId = targetId.startsWith('#') ? targetId.substring(1) : targetId;
        var contentElement = document.getElementById(contentId);
        if (!contentElement) return;
        tabLinks.forEach(function(tab) { tab.classList.remove('active'); });
        tabContents.forEach(function(content) { content.classList.remove('show', 'active'); });
        tabLink.classList.add('active');
        contentElement.classList.add('show', 'active');
    }

    tabLinks.forEach(function(link) {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            activateTab(this);
        });
    });

    // Handle direct linking to tabs from URL hash
    if(window.location.hash) {
        var hash = window.location.hash.substring(1);
        var tabLink = document.querySelector('a[href="#' + hash + '"]');
        if(tabLink) {
            activateTab(tabLink);
        }
    }
});
</script>
<?php } ?>
