<?php
/*
 ================================================================================
   Task Session â€“ Project Management System
   File    : add-new-project.php
   Purpose : Handles creation and setup of new projects
 ================================================================================
 */
ob_start();
require_once("../includes/lib-initialize.php");
require_once("../includes/email_helper.php");
$title = "Add New Project | " . $syatem_title;
include("../templates/header.php");

require_once('../includes/notification_helper.php');

if (!($session->isLoggedIn())) {
    redirectTo($url . "index.php");
}
if ($_SESSION['accountStatus'] == 2) {
    redirectTo($url . "client/index.php");
}
if ($_SESSION['accountStatus'] == 3) {
    redirectTo($url . "staff/index.php");
}

$id = $session->userId; // id of the current logged in user
$user = User::findById((int)$id);
$username = $user->firstName;
$email = $user->email;
$account_stat = $user->status;
$settings = settings::findById(1); // Use system settings (admin settings) for email templates

// Initialize EmailHelper
$emailHelper = new EmailHelper($settings);

$message = "";

// CSRF token generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

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

if (isset($_POST['add-project'])) {
    // CSRF token validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid CSRF token');
    }

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
    if ($budget_raw !== '' && (!is_numeric($budget_raw) || (float)$budget_raw < 0)) {
        $validationErrors[] = 'Budget must be a valid non-negative number.';
    }
    if ($main_client_raw <= 0) {
        $validationErrors[] = 'Main Client is required.';
    }
    // Require at least one staff/admin assigned
    if ($staff_raw === '') {
        $validationErrors[] = 'Please select at least one team member or admin.';
    }
    if ($start_raw === '') {
        $validationErrors[] = 'Start Date is required.';
    }
    if ($end_raw === '') {
        $validationErrors[] = 'End Date is required.';
    }

    // Validate date format + ordering
    if ($start_raw !== '' && $end_raw !== '') {
        $startDt = DateTime::createFromFormat('Y-m-d', $start_raw);
        $endDt = DateTime::createFromFormat('Y-m-d', $end_raw);
        $todayDt = new DateTime('today');

        if (!$startDt || $startDt->format('Y-m-d') !== $start_raw) {
            $validationErrors[] = 'Start Date is invalid.';
        }
        if (!$endDt || $endDt->format('Y-m-d') !== $end_raw) {
            $validationErrors[] = 'End Date is invalid.';
        }
        if ($startDt && $startDt < $todayDt) {
            $validationErrors[] = 'Start Date cannot be in the past.';
        }
        if ($endDt && $endDt < $todayDt) {
            $validationErrors[] = 'End Date cannot be in the past.';
        }
        if ($startDt && $endDt && $endDt < $startDt) {
            $validationErrors[] = 'End Date cannot be before Start Date.';
        }
    }

    if (!empty($validationErrors)) {
        $message = "<div class='alert alert-danger'><strong>Validation Error:</strong><ul>";
        foreach ($validationErrors as $err) {
            $message .= "<li>" . htmlspecialchars($err, ENT_QUOTES, 'UTF-8') . "</li>";
        }
        $message .= "</ul></div>";
    } else {
    $project = new Projects();
    $s_idsa = $_POST['staff'];

    $project->project_title = $project_title_raw;
    $project->p_id          = (int)NULL;
    $project->c_id          = $_POST['main_client']; // Keep for backward compatibility
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
    $project->c_ids         = $clients_raw; // All clients
    $project->main_client_id = $main_client_raw; // Main client
    $project->s_ids         = $s_idsa;
    $project->project_desc  = sanitize_tinymce_content($_POST['description'] ?? '');
    $project->budget        = ($budget_raw === '' ? 0 : $budget_raw);
    $project->status        = $_POST['status'];
    $project->archive       = $_POST['archive'];
    $project->trash         = 0;
    $project->start_time    = $_POST['startTime'];
    $project->end_time      = $_POST['endTime'];

    $saveProject = $project->save();
    $notmessagea = $lang['Project has been created successfully!'];
    $notmessageb = $lang['Project could not created at this time. Please Try Again Later . Thanks'];

    if ($saveProject) {
        require_once __DIR__ . '/../includes/custom-fields/project_task_values.php';
        $postedCf = isset($_POST['custom_fields']) && is_array($_POST['custom_fields']) ? $_POST['custom_fields'] : null;
        save_project_custom_field_values($connect, (int) $project->p_id, $postedCf);

        require_once __DIR__ . '/../includes/project_activity.php';
        project_activity_log(
            $connect,
            (int) $project->p_id,
            (int) $session->userId,
            'project_created',
            'project',
            ['project_title' => $project_title_raw]
        );

        $project_title = $_POST['projectTite'];
        $main_client_id = $_POST['main_client'];
        NotificationHelper::projectCreated($project->p_id, $project_title, $session->userId, $main_client_id);

        if (isset($_POST['notifyClient'])) {
            // Notify main client via email
            $user = user::findById($_POST['main_client']);
            $to = $user->email;
            if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                die('Invalid client email address');
            }
            $subject = 'New Project Created';
            $variablesArr = array(
                '{USER_NAME}'    => htmlspecialchars($user->firstName, ENT_QUOTES, 'UTF-8'),
                '{SIGNATURE}'    => $company_name,
                '{DASHBOARD_URL}'=> $url,
                '{PROJECT_NAME}' => htmlspecialchars($project_title, ENT_QUOTES, 'UTF-8')
            );
            $templateHTML = $settings->project_assign_email;
            $messageBody = strtr($templateHTML, $variablesArr);

            // Use centralized email helper
            $emailSent = $emailHelper->sendTemplateEmail($to, $subject, $templateHTML, $variablesArr);
            if ($emailSent) {
                $message = "<p class='alert alert-success'>Project has been created successfully!</p>";
            } else {
                echo "Project has been created successfully! but Error sending the Email please contact site administrator";
            }

            // Notify all other clients
            $all_client_ids = array_filter(explode(',', $_POST['clients']));
            foreach ($all_client_ids as $client_id) {
                if ($client_id != $main_client_id) {
                    $clientUser = user::findById($client_id);
                    if ($clientUser && filter_var($clientUser->email, FILTER_VALIDATE_EMAIL)) {
                        $variablesArr = array(
                            '{USER_NAME}'    => htmlspecialchars($clientUser->firstName, ENT_QUOTES, 'UTF-8'),
                            '{SIGNATURE}'    => $company_name,
                            '{DASHBOARD_URL}'=> $url,
                            '{PROJECT_NAME}' => htmlspecialchars($project_title, ENT_QUOTES, 'UTF-8')
                        );
                        $templateHTML = $settings->project_assign_email;
                        
                        // Check if template is empty
                        if (empty($templateHTML)) {
                            error_log("Email template is empty for additional client: " . $clientUser->email);
                            continue;
                        }
                        
                        $clientMessage = strtr($templateHTML, $variablesArr);

                        // Use centralized email helper
                        $emailSent = $emailHelper->sendTemplateEmail($clientUser->email, $subject, $templateHTML, $variablesArr);
                        if (!$emailSent) {
                            error_log("Failed to send email to additional client: " . $clientUser->email);
                        }
                    }
                }
            }

            // Notify staff members and admins
            $all_users = user::findBySql("SELECT * FROM users");
            foreach ($all_users as $recentlyRegisteredUser) {
                if ($recentlyRegisteredUser->accountStatus == 3 || $recentlyRegisteredUser->accountStatus == 1) {
                    $s_all = array_map('intval', explode(',', $_POST['staff']));
                    if (in_array((int)$recentlyRegisteredUser->id, $s_all)) {
                        $staffUser = user::findById($recentlyRegisteredUser->id);
                        $to  = $staffUser->email;
                        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                            continue; // skip invalid email
                        }
                        $subject = 'Project assignment notification';
                        $variablesArr = array(
                            '{USER_NAME}'    => htmlspecialchars($staffUser->firstName, ENT_QUOTES, 'UTF-8'),
                            '{SIGNATURE}'    => $company_name,
                            '{DASHBOARD_URL}'=> $url,
                            '{PROJECT_NAME}' => htmlspecialchars($project_title, ENT_QUOTES, 'UTF-8')
                        );
                        $templateHTML = $settings->assign_staff_email;
                        
                        // Check if template is empty
                        if (empty($templateHTML)) {
                            error_log("Email template is empty for staff/admin project assignment: " . $staffUser->email);
                            continue;
                        }
                        
                        $staffMessage = strtr($templateHTML, $variablesArr);

                        // Use centralized email helper
                        $emailSent = $emailHelper->sendTemplateEmail($to, $subject, $templateHTML, $variablesArr);
                        if (!$emailSent) {
                            error_log("Failed to send project assignment email to staff/admin: " . $staffUser->email);
                        }
                    }
                }
            }
        } else {
            $message = "<p class='alert alert-success'>" . $notmessagea . "</p>";
        }

        header("Location: projects.php?message=created");
        exit;
    } else {
        $message = "<p class='alert alert-danger'>" . $notmessageb . "</p>";
    }
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
</script>
<div class="page-container">
    <div class="container-fluid">
        <div class="row row-eq-height">
            <?php include("../templates/sidebar.php"); ?>

            <div class="page-content">
                <?php include('../templates/top-header.php'); ?>

                <div class="row">
                    <div class="col-md-12 margin-top-10">
                        <?php if (isset($message) && (!empty($message))) {
                            echo $message;
                        } ?>

                        <div class="add-project">
                            <form
                                id="addProjectForm"
                                method="post"
                                action=""
                                enctype="multipart/form-data"
                                novalidate
                                data-custom-fields-guard="1"
                            >
                                <!-- Ensure POST handler triggers even if submit button is disabled by spinner JS -->
                                <input type="hidden" name="add-project" value="1" />
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>" />
                                <div class="row">
                                    <div class="col-md-12 center-col">
                                        <div class="project-header page-title">
                                            <h2><?php echo $lang['Create New Project']; ?></h2>
                                            <p>
                                                <?php echo $lang['Start a New Project and Assign Team Members']; ?>
                                            </p>
                                        </div>

                                        <!-- Project Title -->
                                        <div class="form-group field-label">
                                            <label for="projectTite">
                                                <?php echo $lang['Project Title*']; ?>
                                            </label>
                                            <input
                                                type="text"
                                                id="projectTite"
                                                name="projectTite"
                                                class="form-control"
                                                placeholder="<?php echo $lang['Enter the name of the project']; ?>"
                                                required
                                            />
                                        </div>
                                        <!-- Project Description -->
                                        <div class="form-group field-label">
                                            <div class="field-label">
                                                <label for="description">
                                                    <?php echo $lang['Write a project description here']; ?>
                                                </label>
                                                <p class="small text-muted mb-2 mt-0"><?php echo htmlspecialchars(isset($lang['Write a clear description, or use AI to generate, improve, or rewrite it.']) ? $lang['Write a clear description, or use AI to generate, improve, or rewrite it.'] : 'Write a clear description, or use AI to generate, improve, or rewrite it.', ENT_QUOTES, 'UTF-8'); ?></p>
                                            </div>
                                            <textarea
                                                id="description"
                                                name="description"
                                                class="form-control"
                                                placeholder="Describe the project overview"
                                            ></textarea>
                                            <?php
                                            $aiFieldComposeTarget = 'textarea[name="description"]';
                                            $aiFieldComposeField = 'project_description';
                                            $aiFieldComposeTitle = '#projectTite';
                                            include dirname(__DIR__) . '/templates/partials/ai-field-compose.php';
                                            ?>
                                        </div>
                                        <!-- Budget & Dates -->
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group field-label">
                                                    <label for="budget">
                                                        <?php echo $lang['Budget']; ?>
                                                    </label>
                                                    <input
                                                        type="number"
                                                        id="budget"
                                                        name="budget"
                                                        placeholder="<?php echo $lang['Budget']; ?>"
                                                        class="form-control"
                                                    />
                                                    <input type="hidden" name="status" value="0" />
                                                    <input type="hidden" name="archive" value="0" />
                                                </div>

                                                <div class="form-group field-label">
                                                    <label for="startTime">
                                                        <?php echo $lang['Start Date*']; ?>
                                                    </label>
                                                    <input
                                                        type="date"
                                                        id="startTime"
                                                        name="startTime"
                                                        class="form-control"
                                                        placeholder="<?php echo $lang['Start Time']; ?>"
                                                        required
                                                        min="<?php echo date('Y-m-d'); ?>"
                                                    />
                                                </div>
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
                                                                    <?php echo htmlspecialchars($lang['Select main client'] ?? 'Select client or company', ENT_QUOTES, 'UTF-8'); ?>
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

                                                <div class="form-group field-label">
                                                    <label for="endTime">
                                                        <?php echo $lang['Expected close date']; ?>
                                                    </label>
                                                    <input
                                                        type="date"
                                                        id="endTime"
                                                        name="endTime"
                                                        class="form-control"
                                                        placeholder="End Time"
                                                        required
                                                        min="<?php echo date('Y-m-d'); ?>"
                                                    />
                                                </div>
                                            </div>
                                        </div>
                                        <!-- Assign Staff -->
                                        <div class="row">
                                            <div class="col-md-12">
                                                <div class="form-group">
                                                    <div class="staff-heading">
                                                        <h4><?php echo $lang['Assign teammates & admins']; ?></h4>
                                                        <span><?php echo htmlspecialchars($lang['Choose any team member for this project'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
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
                                                        <small id="project-staff-error" class="text-danger d-none"><?php echo isset($lang['Please select at least one team member or admin']) ? $lang['Please select at least one team member or admin'] : 'Please select at least one team member or admin.'; ?></small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <!-- Assign Additional Clients -->
                                        <div class="row">
                                            <div class="col-md-12">
                                                <div class="form-group">
                                                    <div class="staff-heading">
                                                        <h4><?php echo $lang['Assign Additional Clients']; ?></h4>
                                                        <span><?php echo $lang['Choose additional clients for this project (optional)']; ?></span>
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
                                                                <span id="additionalClientsDropdownBtnText"><?php echo $lang['Select additional clients']; ?></span>
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
                                        </div>

                                        <div id="userProfileCustomFieldsSection" class="form-group full-grid js-user-profile-cf-section d-none" aria-hidden="true">
                                            <div class="staff-heading mt-3"><h4><?php echo htmlspecialchars($lang['Custom fields'] ?? 'Custom fields', ENT_QUOTES, 'UTF-8'); ?></h4></div>
                                            <div id="userProfileCustomFieldsContainer"></div>
                                        </div>

                                        <!-- Email Notification Toggle & Submit -->
                                        <div class="input-notify">
                                            <div class="form-group d-block d-md-flex justify-content-between">
                                                <div class="d-flex col-gap align-items-center">
                                                    <div class="checkbox-wrapper-6">
                                                        <input class="tgl tgl-light" id="notifyClient" name="notifyClient" type="checkbox" />
                                                        <label class="tgl-btn" for="notifyClient"></label>
                                                    </div>
                                                    <div>
                                                        <label for="notifyClient" class="permission-label d-flex align-items-center col-gap-5 mb-0">
                                                            <?php echo $lang['Email Notification']; ?>
                                                            <span class="flex-shrink-0" data-bs-toggle="tooltip" data-bs-placement="top" title="<?php echo htmlspecialchars($lang['Notify to client and staff project has been created'], ENT_QUOTES, 'UTF-8'); ?>" onclick="event.preventDefault(); event.stopPropagation();"><?php echo ts_icon('info', 'w-2 text-muted'); ?></span>
                                                        </label>
                                                    </div>
                                                </div>
                                                <button
                                                    type="submit"
                                                    id="create-project-btn"
                                                    class="btn new-btnblue"
                                                ><?php echo $lang['add new project']; ?></button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <!-- add-project -->
                    </div>
                </div>
                <!-- row -->
            </div>
            <div class="clearfix"></div>
        </div>
    </div>
</div>

<?php include("../templates/main-footer.php"); ?>
<script>
window.USER_PROFILE_CF_CONFIG = {
  entityType: 'project',
  listUrl: '../includes/custom-fields/list.php',
  containerSelector: '#userProfileCustomFieldsContainer',
  initialValues: [],
  i18n: {
    fieldRequired: <?php echo json_encode(isset($lang['This field is required.']) ? $lang['This field is required.'] : 'This field is required.', JSON_HEX_TAG | JSON_HEX_APOS); ?>
  }
};
</script>
<script src="../assets/js/user-profile-custom-fields-form.js"></script>
<script src="../assets/js/custom-fields-form-guard.js"></script>
<script src="../assets/js/features.js"></script>
<script src="../assets/js/rich-editor.js"></script>
