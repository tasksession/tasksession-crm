<?php
/*
 ================================================================================
   Task Session â€“ Project Management System
   File    : edit-project.php
   Purpose : Handles project editing and updates
 ================================================================================
 */

ob_start();
require_once("../includes/lib-initialize.php");
require_once("../includes/email_helper.php");
$title = "Edit Project | ". $syatem_title;
include("../templates/header.php");

require_once('../includes/notification_helper.php');

// Authentication check
if(!($session->isLoggedIn())){
		redirectTo($url."index.php");
	}

// Initialize EmailHelper
$settings = settings::findById(1);
$emailHelper = new EmailHelper($settings);
if($_SESSION['accountStatus'] == 2){
	redirectTo($url."client/index.php");
}
if($_SESSION['accountStatus'] == 3){
	redirectTo($url."staff/index.php");
} 
//condition check for login

$id=$session->userId; //id of the current logged in user 
$user = User::findById((int)$id); //take the record of current user in an object array 	
$username=$user->firstName;;
$email=$user->email;;
$account_stat=$user->status;;
$user->regDate;

$settings = settings::findById(1); // Use system settings (admin settings) for email templates

	// Validate and sanitize $pro_id (GET pretty URL or legacy POST open)
	$pro_id = 0;
	if (!empty($_POST['p_id'])) {
		$pro_id = (int) $_POST['p_id'];
	} elseif (!empty($_GET['id'])) {
		$pro_id = (int) $_GET['id'];
	} elseif (!empty($_GET['p_id'])) {
		$pro_id = (int) $_GET['p_id'];
	}
if($pro_id <= 0){
	redirectTo($url . 'admin/projects');
	exit;
}
$_SESSION['pro_id'] = $pro_id;
$pro_id_u = $_SESSION['pro_id'];

$returnRaw = '';
if (!empty($_POST['project_edit_return'])) {
	$returnRaw = (string) $_POST['project_edit_return'];
} elseif (!empty($_GET['project_edit_return'])) {
	$returnRaw = (string) $_GET['project_edit_return'];
}
if ($pro_id > 0 && $returnRaw !== '') {
	$rv = project_edit_return_validate($returnRaw, $pro_id);
	if ($rv) {
		if (!isset($_SESSION['project_edit_return_uripath']) || !is_array($_SESSION['project_edit_return_uripath'])) {
			$_SESSION['project_edit_return_uripath'] = [];
		}
		$_SESSION['project_edit_return_uripath'][$pro_id] = $rv;
	}
}

// Legacy POST openers â†’ pretty GET URL
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['update-project'])) {
	redirectTo($url . 'admin/edit-project?id=' . $pro_id);
	exit;
}

	if(isset($_POST['update-project'])){

        // Server-side validation (required fields)
        $validationErrors = [];
        $project_title_raw = trim($_POST['projectTite'] ?? '');
        $budget_raw = $_POST['budget'] ?? '';
        $main_client_raw = (int)($_POST['main_client'] ?? 0);
        $staff_raw = trim($_POST['staff'] ?? '');
        $start_raw = $_POST['startTime'] ?? '';
        $end_raw = $_POST['endTime'] ?? '';

        if ($project_title_raw === '') {
            $validationErrors[] = 'Project Title is required.';
        }
        if ($budget_raw === '' || !is_numeric($budget_raw) || (float)$budget_raw < 0) {
            $validationErrors[] = 'Budget is required.';
        }
        if ($main_client_raw <= 0) {
            $validationErrors[] = 'Main Client is required.';
        }
        if ($staff_raw === '') {
            $validationErrors[] = 'Please select at least one team member or admin.';
        }
        if ($start_raw === '') {
            $validationErrors[] = 'Start Date is required.';
        }
        if ($end_raw === '') {
            $validationErrors[] = 'End Date is required.';
        }

        // Validate date format + ordering (allow past dates for existing projects; enforce end >= start)
        if ($start_raw !== '' && $end_raw !== '') {
            $startDt = DateTime::createFromFormat('Y-m-d', $start_raw);
            $endDt = DateTime::createFromFormat('Y-m-d', $end_raw);
            if (!$startDt || $startDt->format('Y-m-d') !== $start_raw) {
                $validationErrors[] = 'Start Date is invalid.';
            }
            if (!$endDt || $endDt->format('Y-m-d') !== $end_raw) {
                $validationErrors[] = 'End Date is invalid.';
            }
            if ($startDt && $endDt && $endDt < $startDt) {
                $validationErrors[] = 'End Date cannot be before Start Date.';
            }
        }

        // Ensure clients list always includes main client
        $clients_raw = trim($_POST['clients'] ?? '');
        if ($clients_raw === '') {
            $clients_raw = (string)$main_client_raw;
        } else {
            $clientPieces = array_filter(array_map('trim', explode(',', $clients_raw)));
            if (!in_array((string)$main_client_raw, $clientPieces, true)) {
                array_unshift($clientPieces, (string)$main_client_raw);
            }
            $clients_raw = implode(',', array_unique($clientPieces));
        }
        $_POST['clients'] = $clients_raw;

        if (!empty($validationErrors)) {
            $message = "<div class='alert alert-danger'><strong>Validation Error:</strong><ul>";
            foreach ($validationErrors as $err) {
                $message .= "<li>" . htmlspecialchars($err, ENT_QUOTES, 'UTF-8') . "</li>";
            }
            $message .= "</ul></div>";
        } else {

        require_once __DIR__ . '/../includes/project_activity.php';
        require_once __DIR__ . '/../includes/custom-fields/project_custom_field_values.php';
        $oldProjectSnapshot = projects::findByProjectId($pro_id);
        $oldCfMap = fetch_project_custom_field_values_map($connect, $pro_id);

$flag=0;//determines if all posted values are not empty includ
		
		if($flag==0)
		{
			// $project = new Projects();
			$cont_desc = sanitize_tinymce_content($_POST['description'] ?? '');
			// For prepared statements, we don't need mysqli_real_escape_string - just sanitize the content
			$projectTite = trim($project_title_raw);
			$s_idsa = $_POST['staff'];
				 $pp_id	= $pro_id; // Already validated as integer
				$pp_title	= $projectTite;
				$pc_id          = (int)$_POST['main_client']; // Keep for backward compatibility
				// Sanitize comma-separated client IDs
				$pc_ids_raw = $_POST['clients'];
				$pc_ids_parts = array_filter(array_map('trim', explode(',', $pc_ids_raw)));
				$pc_ids_sanitized = array_map('intval', $pc_ids_parts);
				$pc_ids = implode(',', $pc_ids_sanitized);
				$pmain_client_id = (int)$_POST['main_client']; // Main client
				// Sanitize comma-separated staff IDs
				$ps_ids_parts = array_filter(array_map('trim', explode(',', $s_idsa)));
				$ps_ids_sanitized = array_map('intval', $ps_ids_parts);
				$ps_ids = implode(',', $ps_ids_sanitized);
				// Use sanitized content directly - prepared statements handle escaping
				$pp_desc		= $cont_desc;
				$p_budget		= trim($budget_raw);
				$p_status		= (int)$_POST['status'];
				$p_archive		= (int)$_POST['archive'];
				$ps_time		= trim($start_raw);
				$pe_time		= trim($end_raw);
				
				 // Use prepared statement for UPDATE query
				// Parameter types: c_id(i), c_ids(s), main_client_id(i), s_ids(s), project_title(s), project_desc(s), budget(s), status(i), archive(i), start_time(s), end_time(s), p_id(i)
				$stmt = $connect->prepare("UPDATE `projects` SET `c_id`=?, `c_ids`=?, `main_client_id`=?, `s_ids`=?, `project_title`=?, `project_desc`=?, `budget`=?, `status`=?, `archive`=?, `start_time`=?, `end_time`=? WHERE `p_id`=?");
				$stmt->bind_param('isissssiissi', $pc_id, $pc_ids, $pmain_client_id, $ps_ids, $pp_title, $pp_desc, $p_budget, $p_status, $p_archive, $ps_time, $pe_time, $pp_id);
				
				if ($stmt->execute()){
					require_once __DIR__ . '/../includes/custom-fields/project_task_values.php';
					$postedCf = isset($_POST['custom_fields']) && is_array($_POST['custom_fields']) ? $_POST['custom_fields'] : null;
					save_project_custom_field_values($connect, (int) $pp_id, $postedCf);

					if ($oldProjectSnapshot) {
						project_activity_log_save_changes(
							$connect,
							(int) $pp_id,
							(int) $session->userId,
							$oldProjectSnapshot,
							[
								'project_title' => $pp_title,
								'project_desc' => $pp_desc,
								'budget' => $p_budget,
								'status' => $p_status,
								'start_time' => $ps_time,
								'end_time' => $pe_time,
								's_ids' => $ps_ids,
							],
							$oldCfMap,
							$postedCf
						);
					}

					$project_title = $_POST['projectTite'];
					  	if(isset($_POST['notifyClient'])){
							// Get settings from admin user (ID 1) instead of current user
							$adminSettings = settings::findById(1);
							
							// Notify main client via email
							$user = user::findById($_POST['main_client']); 
							// send verification email
							$to  = $user->email;
				  			$subject = 'Project Updated';
							$variablesArr = array('{USER_NAME}' => $user->firstName, '{SIGNATURE}' => $company_name, '{DASHBOARD_URL}' => $url, '{PROJECT_NAME}' => $project_title);
							$templateHTML = $adminSettings->project_update_email;
							$message = strtr($templateHTML, $variablesArr);
						  // To send HTML mail, the Content-type header must be set (don't change this section)
						  $headers  = 'MIME-Version: 1.0' . "\r\n";
						  $headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
						  
						  $headers .= 'From: '.$company_name.' <'.$system_email.'>' . "\r\n";
						  $emailSent = $emailHelper->sendTemplateEmail($to, $subject, $templateHTML, $variablesArr);
						  if($emailSent){ 
					  			$message="<p class='alert alert-success'>Project has been created successfully!</p>";
						  }
						  else{
							  echo "Project has been created successfully! but Error sending the Email please contact site administrator";
						 }

						  // Notify additional clients
						  $all_client_ids = array_filter(explode(',', $_POST['clients']));
						  foreach ($all_client_ids as $client_id) {
						      if ($client_id != $_POST['main_client']) {
						          $additionalClientUser = user::findById($client_id);
						          if ($additionalClientUser && filter_var($additionalClientUser->email, FILTER_VALIDATE_EMAIL)) {
						              $variablesArr = array(
						                  '{USER_NAME}'    => htmlspecialchars($additionalClientUser->firstName, ENT_QUOTES, 'UTF-8'),
						                  '{SIGNATURE}'    => $company_name,
						                  '{DASHBOARD_URL}'=> $url,
						                  '{PROJECT_NAME}' => htmlspecialchars($project_title, ENT_QUOTES, 'UTF-8')
						              );
						              $templateHTML = $adminSettings->project_update_email;
						              
						              // Check if template is empty
						              if (empty($templateHTML)) {
						                  error_log("Email template is empty for additional client: " . $additionalClientUser->email);
						                  continue;
						              }
						              
						              $additionalClientMessage = strtr($templateHTML, $variablesArr);

						              $headers  = 'MIME-Version: 1.0' . "\r\n";
						              $headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
						              $headers .= 'From: ' . $company_name . ' <' . $system_email . '>' . "\r\n";

						              $emailSent = $emailHelper->sendTemplateEmail($additionalClientUser->email, $subject, $templateHTML, $variablesArr);
						              if (!$emailSent) {
						                  error_log("Failed to send email to additional client: " . $additionalClientUser->email);
						              }
						          }
						      }
						  }
/* Staff Email */
 $all_users=user::findBySql("select * from users");
 foreach($all_users as $recentlyRegisteredUser){ 
				if($recentlyRegisteredUser->accountStatus ==3){  
					$s_all = array_map('intval', explode(',', $_POST['staff']));
					if(in_array((int)$recentlyRegisteredUser->id, $s_all)){
		$user = user::findById($recentlyRegisteredUser->id); 
		// send verification email
		$to  = $user->email; 
		$subject = 'Project Updated';
		$variablesArr = array('{USER_NAME}' => $user->firstName, '{SIGNATURE}' => $company_name, '{DASHBOARD_URL}' => $url, '{PROJECT_NAME}' => $pp_title);
						$templateHTML = $adminSettings->project_update_email;
						$message = strtr($templateHTML, $variablesArr);
						  // To send HTML mail, the Content-type header must be set (don't change this section)
						  $headers  = 'MIME-Version: 1.0' . "\r\n";
						  $headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
						  
						  $headers .= 'From: '.$company_name.' <'.$system_email.'>' . "\r\n";
						  $emailSent = $emailHelper->sendTemplateEmail($to, $subject, $templateHTML, $variablesArr);
						  
						  if($emailSent){ 
						  
						  }else{
							redirectTo($url . 'admin/projects?message=error_email');
						 }
				}
				}
 }
/* Staff Email End */
	}
$returnPath = null;
if (!empty($_POST['project_edit_return'])) {
	$returnPath = project_edit_return_validate($_POST['project_edit_return'], $pro_id);
}
if ($returnPath === null && !empty($_SESSION['project_edit_return_uripath'][$pro_id])) {
	$returnPath = project_edit_return_validate($_SESSION['project_edit_return_uripath'][$pro_id], $pro_id);
}
unset($_SESSION['project_edit_return_uripath'][$pro_id]);

if ($returnPath) {
	$loc = project_edit_return_absolute_location($returnPath);
	if ($loc) {
		$sep = (strpos($loc, '?') !== false) ? '&' : '?';
		redirectTo($loc . $sep . 'message=success');
		exit;
	}
}
$referer = isset($_SERVER['HTTP_REFERER']) ? (string) $_SERVER['HTTP_REFERER'] : '';
if ($referer !== '' && (strpos($referer, 'projects.php') !== false || preg_match('#/projects(?:\?|$)#', $referer))) {
    redirectTo($url . 'admin/projects?message=success');
} else {
    redirectTo($url . 'admin/overview?projectId=' . (int)$pro_id);
}
exit;
				} else {
redirectTo($url . 'admin/projects?message=fail');
				}
$stmt->close();
		}
}
        } // end validation success
if(isset($_GET['message'])){
$msgstatus = $_GET['message'];
$notmessagea = $lang['Record updated successfully'];
$notmessageb = $lang['Error! Please Try Again later.'];
if($msgstatus == 'success'){
					$message="<p class='alert alert-success'>".$notmessagea."</p>";
}
if($msgstatus == 'fail'){		 
					$message="<p class='alert alert-success'>".$notmessageb."</p>"; 
}
}

// Build client list for dropdown
$clientList = [];
$staffList = [];
$recentlyRegisteredUsers = user::findBySql("SELECT * FROM users ORDER BY id DESC");
foreach ($recentlyRegisteredUsers as $recentlyRegisteredUser) {
    if ($recentlyRegisteredUser->accountStatus == 2) {
        $filenamePicture = getUserAvatarHtml($recentlyRegisteredUser->id, $recentlyRegisteredUser->firstName, $recentlyRegisteredUser->lastName ?? '', 28, 28, 'rounded-circle', $recentlyRegisteredUser->firstName);
        $clientList[] = [
            'id'    => (int)$recentlyRegisteredUser->id,
            'name'  => htmlspecialchars($recentlyRegisteredUser->firstName, ENT_QUOTES, 'UTF-8'),
            'image' => $filenamePicture
        ];
    }
    
    // Include both staff (accountStatus == 3) and admins (accountStatus == 1) in staff list
    if ($recentlyRegisteredUser->accountStatus == 3 || $recentlyRegisteredUser->accountStatus == 1) {
        $filenamePicture = getUserAvatarHtml($recentlyRegisteredUser->id, $recentlyRegisteredUser->firstName, $recentlyRegisteredUser->lastName ?? '', 28, 28, 'rounded-circle', $recentlyRegisteredUser->firstName);
        $staffList[] = [
            'id'    => (int)$recentlyRegisteredUser->id,
            'name'  => htmlspecialchars($recentlyRegisteredUser->firstName, ENT_QUOTES, 'UTF-8'),
            'image' => $filenamePicture,
            'accountStatus' => (int)$recentlyRegisteredUser->accountStatus
        ];
    }
}

require_once __DIR__ . '/../includes/add-project-dropdown-data.php';

$projectCfInitialValues = [];
if ($pro_id > 0) {
	$cfUid = (int) $pro_id;
	$cfStmt = @$connect->prepare(
		'SELECT cf.id, cf.field_type, cfv.field_value FROM custom_fields cf
		 INNER JOIN project_custom_field_values cfv ON cf.id = cfv.custom_field_id AND cfv.project_id = ?
		 WHERE cf.entity_type = ?'
	);
	if ($cfStmt) {
		$cfEnt = 'project';
		$cfStmt->bind_param('is', $cfUid, $cfEnt);
		if ($cfStmt->execute()) {
			$cfRes = $cfStmt->get_result();
			if ($cfRes) {
				while ($cfRow = $cfRes->fetch_assoc()) {
					$fid = (int) $cfRow['id'];
					$ft = (string) $cfRow['field_type'];
					$fv = $cfRow['field_value'];
					$val = $fv;
					if ($ft === 'multiple_select' && $fv !== null && $fv !== '') {
						$d = json_decode($fv, true);
						$val = is_array($d) ? $d : [];
					}
					$projectCfInitialValues[] = ['id' => $fid, 'field_type' => $ft, 'value' => $val];
				}
			}
		}
		$cfStmt->close();
	}
}
?>
<script>
    window.clientList = <?php echo json_encode($clientList); ?>;
    window.staffList = <?php echo json_encode($staffList); ?>;
    window.projectCompanyList = <?php echo json_encode($projectCompanyList); ?>;
    window.projectTeamGroupList = <?php echo json_encode($projectTeamGroupList); ?>;
    window.addProjectLang = <?php echo json_encode([
        'search' => $lang['Search'] ?? 'Search',
        'companies' => $lang['Companies'] ?? 'Companies',
        'clients' => $lang['Clients'] ?? 'Clients',
        'teamGroups' => $lang['Team groups'] ?? 'Team groups',
        'staffAdmins' => $lang['Staff and admins'] ?? 'Staff and admins',
        'noMatches' => $lang['No dropdown matches'] ?? 'No matches',
        'selectMainPlaceholder' => $lang['Select main client'] ?? 'Select client or company',
        'selectStaffPlaceholder' => $lang['Select Staff members'] ?? 'Select staff members',
        'selectMoreClientsPlaceholder' => $lang['Select more clients'] ?? 'Select more clients',
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
    window.projectAssetsBaseUrl = <?php echo json_encode(rtrim($url, '/')); ?>;
    window.USER_PROFILE_CF_CONFIG = {
      entityType: 'project',
      listUrl: '../includes/custom-fields/list.php',
      containerSelector: '#userProfileCustomFieldsContainer',
      initialValues: <?php echo json_encode($projectCfInitialValues, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE); ?>,
      i18n: {
        fieldRequired: <?php echo json_encode(isset($lang['This field is required.']) ? $lang['This field is required.'] : 'This field is required.', JSON_HEX_TAG | JSON_HEX_APOS); ?>
      }
    };
</script>
<script src="../assets/js/user-profile-custom-fields-form.js"></script>
<script src="../assets/js/custom-fields-form-guard.js"></script>
	<div class="page-container">
		<div class="container-fluid">
			<div class="row row-eq-height">
				<?php  include("../templates/sidebar.php"); ?>
					<div class="page-content">
						<?php include('../templates/top-header.php'); ?>
							<div class="row">
								<div class="col-md-12">
									<div class="add-project">
										<?php 
											// Use prepared statement instead of findBySql with string interpolation
											$stmt = $connect->prepare("SELECT * FROM projects WHERE p_id = ?");
											$stmt->bind_param("i", $pro_id);
											$stmt->execute();
											$result = $stmt->get_result();
											$qur_pro = [];
											while ($row = $result->fetch_assoc()) {
												$qur_pro[] = (object)$row;
											}
											$stmt->close();
											foreach($qur_pro as $qur_ar){
											 $main_client_id = $qur_ar->main_client_id ?: $qur_ar->c_id;
											 $staff_id = $qur_ar->s_ids;
											 $project_title = $qur_ar->project_title;
											 $project_desc = $qur_ar->project_desc;
											 $budget = $qur_ar->budget;
											 $status = $qur_ar->status;
											 $archive = $qur_ar->archive;
											 $start_time = $qur_ar->start_time;
											 $end_time = $qur_ar->end_time;
											 $st_ids = explode(',', $staff_id);
											 
											 // Get additional client IDs
											 $additionalClientIds = [];
											 if (!empty($qur_ar->c_ids)) {
											     $allClientIds = array_filter(explode(',', $qur_ar->c_ids));
											     $additionalClientIds = array_values(array_filter($allClientIds, function($id) use ($main_client_id) {
											         return $id != $main_client_id;
											     }));
											 }
											?>
											<form method="post" action="#" enctype="multipart/form-data" id="editProjectForm" novalidate data-custom-fields-guard="1">
                                                <!-- Ensure POST handler triggers even if submit button is disabled by spinner JS -->
                                                <input type="hidden" name="update-project" value="1" />
												<?php
												$__perPersist = '';
												if (!empty($_SESSION['project_edit_return_uripath'][$pro_id])) {
													$__perPersist = project_edit_return_validate($_SESSION['project_edit_return_uripath'][$pro_id], $pro_id) ?: '';
												}
												if ($__perPersist !== ''): ?>
												<input type="hidden" name="project_edit_return" value="<?php echo htmlspecialchars($__perPersist, ENT_QUOTES, 'UTF-8'); ?>" />
												<?php endif; ?>
												<div class="row">
													<div class="col-md-12 margin-top-10 clients">
														<?php if(isset($message) && (!empty($message))){echo $message;} ?>
													</div>
													
													<div class="col-md-12 center-col">
													
                                        <div class="project-header page-title">
															<h2><?php echo $lang['Edit Project']; ?> </h2> 
														<p><?php echo $lang['Update Project Details and Manage Staff Assignments']; ?></p>
                                                     </div>
																											<div class="form-group">
															<div class="field-label">
																<label for="firstName">
																	<?php echo $lang['Project Title*']; ?>
																</label>
															</div>
															<input type="text" id="projectTite" name="projectTite" class="form-control only-alpha" value="<?php echo $project_title; ?>" required> </div>
														<div class="form-group">
															<div class="field-label">
																<label for="description"><?php echo $lang['Write a project description here']; ?></label>
																<p class="small text-muted mb-2 mt-0"><?php echo htmlspecialchars(isset($lang['Write a clear description, or use AI to generate, improve, or rewrite it.']) ? $lang['Write a clear description, or use AI to generate, improve, or rewrite it.'] : 'Write a clear description, or use AI to generate, improve, or rewrite it.', ENT_QUOTES, 'UTF-8'); ?></p>
															</div>
															<textarea id="description"
                                                name="description"
                                                class="form-control"
                                                placeholder="Describe the project overview"><?php echo !empty($project_desc) ? $project_desc : '';?></textarea>
															<?php
															$aiFieldComposeTarget = 'textarea[name="description"]';
															$aiFieldComposeField = 'project_description';
															$aiFieldComposeTitle = '#projectTite';
															include dirname(__DIR__) . '/templates/partials/ai-field-compose.php';
															?>
														</div>
														<div class="row">
															<div class="col-md-6">
																<div class="form-group">
																	<div class="field-label">
																		<label for="firstName">
																			<?php echo $lang['Budget*']; ?>
																		</label>
																	</div>
																	<input type="number" id="budget" name="budget" placeholder="<?php echo $currency_symbol . $budget;?>" class="form-control" value="<?php echo $budget;?>" required>
																	<input type="hidden" name="status" value="<?php echo $status;?>">
																	<input type="hidden" name="archive" value="<?php echo $archive;?>"> </div>
																<div class="form-group">
																	<div class="field-label">
																		<label for="firstName">
																			<?php echo $lang['Start Date*']; ?>
																		</label>
																	</div>
																	<input type="date" id="startTime" name="startTime" placeholder="Start Time" class="form-control" value="<?php echo $start_time;?>" required min="<?php echo date('Y-m-d'); ?>" /> </div>
															</div>
															<div class="col-md-6">
																<!-- Main Client Dropdown -->
																<div class="form-group field-label">
																	<label for="mainClientDropdownBtn">
																		<?php echo htmlspecialchars($lang['Select Main Client*'] ?? 'Select clients or company*', ENT_QUOTES, 'UTF-8'); ?>
																	</label>
																	<div class="mb-3">
																		<div class="dropdown">
																			<button
																				class="field-btn dropdown-toggle w-100 text-start"
																				type="button"
																				id="mainClientDropdownBtn"
																				data-bs-toggle="dropdown"
																				aria-expanded="false"
																			>
																				<span id="mainClientDropdownBtnText">
																					<?php echo $lang['Select main client']; ?>
																				</span>
																			</button>
																			<ul
																				class="dropdown-menu w-100 dropdown-scroll"
																				aria-labelledby="mainClientDropdownBtn"
																				id="mainClientDropdownMenu"
																			></ul>
																		</div>
																		<input
																			type="hidden"
																			name="main_client"
																			id="selectedMainClientInput"
																			required
																		/>
																	</div>
																</div>
																<div class="form-group">
																	<div class="field-label">
																		<label for="firstName">
																			<?php echo $lang['End Date*']; ?>
																		</label>
																	</div>
																	<input type="date" id="endTime" name="endTime" placeholder="<?php echo $lang['End Time']; ?>" class="form-control" value="<?php echo $end_time;?>" required min="<?php echo date('Y-m-d'); ?>" /> </div>
															</div>
															<div class="col-md-12">
																<div class="form-group field-label">
																	<div class="staff-heading">
																		<h4><?php echo $lang['Assign teammates & admins']; ?></h4>
																		<span><?php echo $lang['Choose any team member for this project']; ?></span>
																	</div>
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
																				class="dropdown-menu w-100 dropdown-scroll"
																				aria-labelledby="staffDropdownBtn"
																				id="staffDropdownMenu"
																			></ul>
																		</div>
																		<input
																			type="hidden"
																			name="staff"
																			id="selectedStaffInput"
																		/>
																	</div>
																</div>
															</div>
															<!-- Add more clients -->
															<div class="col-md-12">
																<div class="form-group field-label">
																	<div class="staff-heading">
																		<h4><?php echo $lang['Add more clients']; ?></h4>
																		<span><?php echo $lang['Choose more clients for this project (optional)']; ?></span>
																	</div>
																	<div class="mb-3">
																		<div class="dropdown">
																			<button
																				class="field-btn dropdown-toggle w-100 text-start"
																				type="button"
																				id="additionalClientsDropdownBtn"
																				data-bs-toggle="dropdown"
																				aria-expanded="false"
																			>
																				<span id="additionalClientsDropdownBtnText"><?php echo $lang['Select more clients']; ?></span>
																			</button>
																			<ul
																				class="dropdown-menu w-100 dropdown-scroll"
																				aria-labelledby="additionalClientsDropdownBtn"
																				id="additionalClientsDropdownMenu"
																			></ul>
																		</div>
																		<input
																			type="hidden"
																			name="clients"
																			id="selectedClientsInput"
																		/>
																	</div>
																</div>
															</div>
															<div class="col-md-12">
																<div id="userProfileCustomFieldsSection" class="form-group full-grid js-user-profile-cf-section d-none" aria-hidden="true">
																	<div class="staff-heading mt-3"><h4>Custom fields</h4></div>
																	<div id="userProfileCustomFieldsContainer"></div>
																</div>
															</div>
															<div class="col-md-12 input-notify">
																<div class="form-group d-block d-md-flex justify-content-between">
																	<div class="d-flex col-gap align-items-center">
																		<div class="checkbox-wrapper-6">
																			<input
																				class="tgl tgl-light"
																				id="notifyClient"
																				name="notifyClient"
																				type="checkbox"
																				<?php if (!empty($project['notifyClient'])) echo 'checked'; ?>
																			/>
																			<label class="tgl-btn" for="notifyClient"></label>
																		</div>
																		<div>
																			<label for="notifyClient" class="permission-label d-flex align-items-center col-gap-5 mb-0">
																				<?php echo $lang['Email Notification']; ?>
																				<span class="flex-shrink-0" data-bs-toggle="tooltip" data-bs-placement="top" title="<?php echo htmlspecialchars($lang['Notify to client and staff project has been Updated'], ENT_QUOTES, 'UTF-8'); ?>" onclick="event.preventDefault(); event.stopPropagation();"><?php echo ts_icon('info', 'w-2 text-muted'); ?></span>
																			</label>
																		</div>
																	</div>
																	<div>
																		<input type="hidden" value="<?php echo $pro_id_u; ?>" name="p_id" />
                                                                        <button
                                                                            type="submit"
                                                                            id="edit-project-btn"
                                                                            class="btn new-btnblue"
                                                                        ><?php echo $lang['Update Project']; ?></button>
																	</div>
																</div>
															</div>
														</div>
													</div>
												</div>
												<div class="clearfix"></div>
											</form>
											<?php } ?>
									</div>
									<!--add-project -->
								</div>
							</div>
							<!-- row -->
					</div>
					<div class="clearfix"></div>
			</div>
		</div>
	</div>
	<?php  include("../templates/main-footer.php"); ?>
	
<script>
    window.selectedClientId = <?php echo json_encode($main_client_id); ?>;
    window.selectedStaffIds = <?php echo json_encode($st_ids); ?>;
    window.selectedAdditionalClientIds = <?php echo json_encode($additionalClientIds); ?>;
</script>

<!-- Include global features JavaScript -->
<script src="../assets/js/features.js"></script>
<script src="../assets/js/rich-editor.js"></script>
<?php
if (!empty($_GET['id'])) {
    $__aiCtx = dirname(__DIR__) . '/includes/ai_contextual_snippet.php';
if (is_file($__aiCtx)) { require_once $__aiCtx; if (function_exists('ai_contextual_emit')) { ai_contextual_emit(['type' => 'project', 'id' => (int) $_GET['id']]); } }
}
?>
