<?php
/*
 ================================================================================
   Task Session – Project Management System
   File    : system-settings.php
   Purpose : Allows administrators to view and update general system settings.
 ================================================================================
 */
ob_start(); 
require_once("../includes/lib-initialize.php");
$title = "System Setting | ". $syatem_title;
include("../templates/header.php");

if(!($session->isLoggedIn())){
    redirectTo($url."index.php");
}
if($_SESSION['accountStatus'] == 2){
    redirectTo($url."client/index.php");
}
if($_SESSION['accountStatus'] == 3){
    redirectTo($url."staff/index.php");
}

// load current logged-in user
$id = $session->userId;
$user = User::findById((int)$id);
$username = $user->firstName;
$email = $user->email;
$account_stat = $user->status;

// Load global settings
$settings = settings::findById(1);

$message = "";
/** @var array{type:string,msg:string}|null Shown via assets/js/toast.js after ?message= */
$toast_flash = null;
	if(isset($_POST['update_settings']))
	{

$flag=0;//determines if all posted values are not empty includ
		
		if($flag==0)
		{
				 $company_name	= $_POST['company_name'];
				$syatem_title	=$_POST['syatem_title'];
				$login_page_title		=$_POST['login_page_title'];
				$copy_rights		= $_POST['copy_rights'];
				$system_email		= $_POST['system_email'];
				// Free edition: module toggles removed — keep existing DB flags (all forced ON).
				$module_lead_board = !empty($settings->module_lead_board) ? 1 : 0;
				$module_invoices   = !empty($settings->module_invoices) ? 1 : 0;
				$module_projects = !empty($settings->module_projects) ? 1 : 0;
				$module_tasks    = !empty($settings->module_tasks) ? 1 : 0;
				$module_email = !empty($settings->module_email) ? 1 : 0;
				$module_file_management = !empty($settings->module_file_management) ? 1 : 0;
				$module_notes_documents = !empty($settings->module_notes_documents) ? 1 : 0;
				$module_discussions = !empty($settings->module_discussions) ? 1 : 0;
				$module_attendance = !empty($settings->module_attendance) ? 1 : 0;
				$module_ip_restriction = !empty($settings->module_ip_restriction) ? 1 : 0;
				$module_ecommerce = !empty($settings->module_ecommerce) ? 1 : 0;
				$module_time_tracking = !empty($settings->module_time_tracking) ? 1 : 0;
				$module_reports = !isset($settings->module_reports) ? (!empty($settings->module_reports) ? 1 : 0) : 1;
				if (function_exists('tasksession_is_free_edition') && tasksession_is_free_edition()) {
					$module_lead_board = 1;
					$module_invoices = 1;
					$module_projects = 1;
					$module_tasks = 1;
					$module_email = 1;
					$module_file_management = 1;
					$module_notes_documents = 1;
					$module_discussions = 1;
					$module_attendance = 1;
					$module_ip_restriction = 1;
					$module_ecommerce = 0;
					$module_time_tracking = 1;
					$module_reports = 1;
				}
				$time_zone		=$_POST['time_zone'];
				$system_language		=$_POST['system_language'];
			
$target_dir = "../uploads/system-uploads/";
// Ensure upload directory exists
if (!is_dir($target_dir)) {
    if (!mkdir($target_dir, 0755, true)) {
        $message = "Error: Cannot create upload directory. Please create the directory manually: " . $target_dir;
        $messageType = "danger";
        header('location:system-settings.php?message=error&error_msg=' . urlencode($message));
        exit;
    }
}

// Check if directory is writable
if (!is_writable($target_dir)) {
    $message = "Error: Upload directory is not writable. Please check permissions for: " . $target_dir;
    $messageType = "danger";
    header('location:system-settings.php?message=error&error_msg=' . urlencode($message));
    exit;
}

    // Security: Helper function for secure file upload
function secureFileUpload($file, $prefix, $target_dir) {
    if (empty($file['name'])) {
        return ['success' => false, 'filename' => null];
    }
    
    // Security: Sanitize filename to prevent path traversal
    $originalFilename = $file['name'];
    // Remove any path components
    $originalFilename = basename($originalFilename);
    // Remove any null bytes
    $originalFilename = str_replace("\0", '', $originalFilename);
    // Remove directory traversal attempts
    $originalFilename = str_replace(['../', '..\\', '/', '\\'], '', $originalFilename);
    
    // Security: Validate file extension
    $ext = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico'];
    if (!in_array($ext, $allowedExtensions)) {
        return ['success' => false, 'error' => 'Invalid file type. Only image files are allowed.'];
    }
    
    // Security: Validate file size (max 10MB for logos)
    $maxSize = 10 * 1024 * 1024; // 10MB
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'error' => 'File too large. Maximum size is 10MB.'];
    }
    
    // Security: Additional MIME type validation
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    $allowedMimeTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml', 'image/x-icon', 'image/vnd.microsoft.icon'];
    if (!in_array($mimeType, $allowedMimeTypes)) {
        return ['success' => false, 'error' => 'Invalid file type detected.'];
    }
    
    // Generate secure filename
    $newFileName = time() . '_' . $prefix . '_' . uniqid() . '.' . $ext;
    $dest = $target_dir . $newFileName;
    
    // Security: Verify resolved path is within upload directory (prevent path traversal)
    $resolvedDir = realpath($target_dir);
    if ($resolvedDir === false) {
        return ['success' => false, 'error' => 'Invalid upload directory.'];
    }
    
    // Normalize the destination path
    $destNormalized = rtrim($target_dir, '/\\') . '/' . basename($newFileName);
    $resolvedDest = realpath(dirname($destNormalized));
    if ($resolvedDest === false || strpos($resolvedDest, $resolvedDir) !== 0) {
        return ['success' => false, 'error' => 'Invalid file path detected.'];
    }
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $dest)) {
        return ['success' => true, 'filename' => $newFileName];
    } else {
        return ['success' => false, 'error' => 'Failed to upload file.'];
    }
}

if(isset($_FILES['favicon_image']['name']) && $_FILES['favicon_image']['name'] != ""){
    $result = secureFileUpload($_FILES['favicon_image'], 'fav', $target_dir);
    if ($result['success']) {
        $file_name = $result['filename'];
    } else {
        $file_name = isset($settings->favicon_image) ? $settings->favicon_image : '';
    }
} else {
    $file_name = isset($settings->favicon_image) ? $settings->favicon_image : '';
}

if(isset($_FILES['login_page_logo']['name']) && $_FILES['login_page_logo']['name'] != ""){
    $result = secureFileUpload($_FILES['login_page_logo'], 'login', $target_dir);
    if ($result['success']) {
        $login_page_logo_name = $result['filename'];
    } else {
        $login_page_logo_name = isset($settings->login_page_logo) ? $settings->login_page_logo : '';
    }
} else {
    $login_page_logo_name = isset($settings->login_page_logo) ? $settings->login_page_logo : '';
}

if(isset($_FILES['logo']['name']) && $_FILES['logo']['name'] != ""){
    $result = secureFileUpload($_FILES['logo'], 'logo', $target_dir);
    if ($result['success']) {
        $logo_name = $result['filename'];
    } else {
        $logo_name = isset($settings->logo) ? $settings->logo : '';
    }
} else {
    $logo_name = isset($settings->logo) ? $settings->logo : '';
}

if(isset($_FILES['mobile_logo']['name']) && $_FILES['mobile_logo']['name'] != ""){
    $result = secureFileUpload($_FILES['mobile_logo'], 'mlogo', $target_dir);
    if ($result['success']) {
        $mlogo_name = $result['filename'];
    } else {
        $mlogo_name = isset($settings->mobile_logo) ? $settings->mobile_logo : '';
    }
} else {
    $mlogo_name = isset($settings->mobile_logo) ? $settings->mobile_logo : '';
}

if(isset($_FILES['login_page_image']['name']) && $_FILES['login_page_image']['name'] != ""){
    $result = secureFileUpload($_FILES['login_page_image'], 'login_img', $target_dir);
    if ($result['success']) {
        $login_image_name = $result['filename'];
    } else {
        $login_image_name = isset($settings->login_page_image) ? $settings->login_page_image : '';
    }
} else {
    $login_image_name = isset($settings->login_page_image) ? $settings->login_page_image : '';
}
			
if ($company_name && $syatem_title && $login_page_title && $copy_rights && $system_email && $time_zone && $system_language) {
    try {
        $settings = settings::findById(1);
        if (!$settings) {
            throw new Exception("Settings record not found in database. Please run the installer first.");
        }
        
        $settings->id = 1;
        $settings->company_name = $_POST['company_name'];
        $settings->syatem_title = $_POST['syatem_title'];
        $settings->login_page_title = $_POST['login_page_title'];
        $settings->copy_rights = $_POST['copy_rights'];
        $settings->system_email = $_POST['system_email'];
		// Modules toggles (safe defaults if columns don't exist yet)
		$settings->module_lead_board = $module_lead_board;
		$settings->module_invoices = $module_invoices;
		$settings->module_projects = $module_projects;
		$settings->module_tasks = $module_tasks;
		$settings->module_email = $module_email;
		$settings->module_file_management = $module_file_management;
		$settings->module_notes_documents = $module_notes_documents;
		$settings->module_discussions = $module_discussions;
		$settings->module_attendance = $module_attendance;
		$settings->module_ip_restriction = $module_ip_restriction;
		$settings->module_ecommerce = $module_ecommerce;
		$settings->module_time_tracking = $module_time_tracking;
		$settings->module_reports = $module_reports;
		if (function_exists('tasksession_is_free_edition') && tasksession_is_free_edition()) {
			$settings->module_marketing = 0;
			$settings->module_ai = 0;
			$settings->module_ecommerce = 0;
		}
        $settings->time_zone = $_POST['time_zone'];
        $settings->system_language = $_POST['system_language'];
        $settings->login_page_logo = $login_page_logo_name;
        $settings->logo = $logo_name;
        $settings->mobile_logo = $mlogo_name;
        $settings->login_page_image = $login_image_name;
        $settings->favicon_image = $file_name;
        
        $savesettings = $settings->save();
        
        if($savesettings){
            require_once __DIR__ . '/../includes/sync_root_favicon.php';
            comon_sync_root_favicon_file($settings->favicon_image);
            if (!function_exists('setup_guide_mark_visit')) {
                require_once dirname(__DIR__) . '/includes/setup_guide.php';
            }
            setup_guide_mark_visit('general');
            header('location:system-settings.php?message=success');
            exit;
        } else {
            header('location:system-settings.php?message=fail');
            exit;
        }
    } catch (Exception $e) {
        $error_msg = "Error saving settings: " . $e->getMessage();
        header('location:system-settings.php?message=error&error_msg=' . urlencode($error_msg));
        exit;
    }
} else {
    header('location:system-settings.php?message=required');
    exit;
}

		}

	}
if (isset($_GET['message'])) {
    $msgstatus = $_GET['message'];
    $notmessagea = $lang['System Settings has been saved successfully!'];
    $notmessageb = $lang['Same values will not be updated, please make changes and save settings again, Thanks'];
    $notmessagec = $lang['All fields are required'];
    if ($msgstatus == 'success') {
        $toast_flash = array('type' => 'success', 'msg' => $notmessagea);
    }
    if ($msgstatus == 'fail') {
        $toast_flash = array('type' => 'error', 'msg' => $notmessageb);
    }
    if ($msgstatus == 'required') {
        $toast_flash = array('type' => 'error', 'msg' => $notmessagec);
    }
    if ($msgstatus == 'error') {
        $error_msg = isset($_GET['error_msg']) ? urldecode((string) $_GET['error_msg']) : 'An error occurred while processing your request.';
        $toast_flash = array('type' => 'error', 'msg' => $error_msg);
    }
}
?>
	<div class="page-container">
		<div class="container-fluid">
			<div class="row row-eq-height">
				<?php  include("../templates/sidebar.php"); ?>
					<div class="page-content" style="padding-bottom:0;">
						<?php include('../templates/top-header.php'); ?>
							<div class="row system-wrap">
                    <?php include("../templates/system-nav.php"); ?>
                    <div class="col-md-9 ss-right">
                        <div class="system-settings-container">
                            <div class="settings-header">
                                <h2 class="page-title">
                                    <?php echo $lang['General Settings']; ?>
                                </h2>
                             </div>

                            <form method="post" action="#" enctype="multipart/form-data" class="settings-form">
                                <div class="settings-grid">
                                    <div class="settings-main">
                                        <div class="settings-card">
                                            <div class="card-header">
                                                <h4><?php echo $lang['Company Information']; ?></h4>
                                                <p><?php echo $lang['Basic company and system details']; ?></p>
                                            </div>
                                            <div class="card-body">
                                                <div class="form-group">
                                                    <label for="system_url">
                                                        <?php echo $lang['System Url']; ?>
                                                    </label>
                                                    <input type="text" disabled required value="<?php echo $settings->url; ?>" class="form-control" name="url" placeholder="Url">
                                                    <small class="form-text text-muted"><?php echo $lang['System URL cannot be changed']; ?></small>
                                                </div>

                                                <div class="form-group">
                                                    <label for="company_name">
                                                        <?php echo $lang['Company Name*']; ?>
                                                    </label>
                                                    <input type="text" required value="<?php echo $settings->company_name; ?>" class="form-control" name="company_name" placeholder="<?php echo $lang['Company Name']; ?>">
                                                </div>

                                                <div class="form-group">
                                                    <label for="system_title">
                                                        <?php echo $lang['Company Title*']; ?>
                                                    </label>
                                                    <input type="text" required value="<?php echo $settings->syatem_title; ?>" class="form-control" name="syatem_title" placeholder="<?php echo $lang['System Title']; ?>">
                                                </div>

                                                <div class="form-group">
                                                    <label for="login_title">
                                                        <?php echo $lang['Login Page Title*']; ?>
                                                    </label>
                                                    <input type="text" required value="<?php echo $settings->login_page_title; ?>" class="form-control" name="login_page_title" placeholder="Login page title">
                                                </div>

                                                <div class="form-group">
                                                    <label for="copyrights">
                                                        <?php echo $lang['Copyrights*']; ?>
                                                    </label>
                                                    <input type="text" required value="<?php echo $settings->copy_rights; ?>" class="form-control" name="copy_rights" placeholder="Copyrights">
                                                </div>

                                                <div class="form-group">
                                                    <label for="system_email">
                                                        <?php echo $lang['System Email*']; ?>
                                                    </label>
                                                    <input type="email" required value="<?php echo $settings->system_email; ?>" class="form-control" name="system_email" placeholder="System Email">
                                                </div>

                                                <div class="form-group">
                                                    <label for="timezone">
                                                        <?php echo $lang['Select Time Zone']; ?>
                                                    </label>
                                                    <?php
														if (!function_exists('crm_timezone_list')) {
															require_once __DIR__ . '/../includes/system_helpers.php';
														}
														$timezoneList = crm_timezone_list();
														$selectedTimezone = (string) $settings->time_zone;
														$selectedTimezoneLabel = $selectedTimezone;
														foreach ($timezoneList as $tzRow) {
															if ($tzRow['zone'] === $selectedTimezone) {
																$selectedTimezoneLabel = $tzRow['diff_from_GMT'] . ' - ' . $tzRow['zone'];
																break;
															}
														}
														?>
                                                    <div class="dropup w-100" id="timezoneDropdown">
                                                        <button class="btn border-btn dropdown-toggle w-100 text-start" type="button" id="timezoneDropdownBtn" data-bs-toggle="dropdown" aria-expanded="false">
                                                            <span id="selectedTimezoneText"><?php echo htmlspecialchars($selectedTimezoneLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                                                        </button>
                                                        <ul class="dropdown-menu dropdown-menu-start w-100 text-start" style="max-height:320px;overflow-y:auto;" id="timezoneDropdownMenu">
                                                            <li class="px-3 py-2" style="position: sticky; top: 0; z-index: 10; border-bottom: 1px solid var(--border-color);">
                                                                <input type="text" id="timezoneSearchInput" class="form-control" placeholder="<?php echo htmlspecialchars($lang['Search timezones...'] ?? 'Search timezones...', ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
                                                            </li>
                                                            <li class="px-3 py-1 text-start" id="noTimezoneFound" style="display: none; color: #999; font-style: italic;">
                                                                <?php echo htmlspecialchars($lang['No timezones match search'] ?? 'No timezones match your search', ENT_QUOTES, 'UTF-8'); ?>
                                                            </li>
                                                            <div id="timezoneOptionsList">
                                                            <?php foreach ($timezoneList as $tzRow) {
																$zone = $tzRow['zone'];
																$label = $tzRow['diff_from_GMT'] . ' - ' . $zone;
																$searchText = strtolower($label . ' ' . $zone);
																$activeClass = ($selectedTimezone === $zone) ? ' active' : '';
															?>
                                                                <li class="timezone-option-item" data-search-text="<?php echo htmlspecialchars($searchText, ENT_QUOTES, 'UTF-8'); ?>">
                                                                    <a class="dropdown-item timezone-option text-start<?php echo $activeClass; ?>" href="#" data-value="<?php echo htmlspecialchars($zone, ENT_QUOTES, 'UTF-8'); ?>">
                                                                        <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                                                                    </a>
                                                                </li>
                                                            <?php } ?>
                                                            </div>
                                                        </ul>
                                                        <input type="hidden" name="time_zone" id="timezone" value="<?php echo htmlspecialchars($selectedTimezone, ENT_QUOTES, 'UTF-8'); ?>" required>
                                                    </div>
													</div>
                                                <div class="form-group">
                                                    <label for="language">
                                                        <?php echo $lang['Select Default language']; ?>
                                                    </label>
                                                    <select class="form-control" required name="system_language" id="language">
                                                        <option <?php if($settings->system_language == 'en'){echo 'selected';}?> value="en">English</option>
                                                        <option <?php if($settings->system_language == 'fr'){echo 'selected';}?> value="fr">French</option>
                                                        <option <?php if($settings->system_language == 'it'){echo 'selected';}?> value="it">Italian</option>
                                                        <option <?php if($settings->system_language == 'sp'){echo 'selected';}?> value="sp">Spanish</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>

										<div class="settings-card">
                                            <div class="card-header">
                                                <h4><?php echo $lang['Modules']; ?></h4>
                                                <p><?php echo $lang['Enable or disable modules across the system']; ?></p>
                                            </div>
                                            <div class="card-body">
												<style>.settings-card .ts-pro-upgrade-wrap.ts-pro-upgrade-wrap--embedded{min-height:280px!important;padding:32px 16px!important;}</style>
												<?php
												if (function_exists('tasksession_render_pro_upgrade_embedded')) {
													tasksession_render_pro_upgrade_embedded('modules');
												} elseif (function_exists('tasksession_render_pro_upgrade')) {
													tasksession_render_pro_upgrade('modules');
												}
												?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="settings-sidebar">
                                        <div class="settings-card">
                                            <div class="card-header">
                                                <h4><?php echo $lang['Save Settings']; ?></h4>
                                                <p><?php echo $lang['Update your system configuration']; ?></p>
                                            </div>
                                            <div class="card-body">
                                                <div class="save-actions">
                                                    <button type="submit" name="update_settings" class="btn primary-btn btn-save">
                                                        <?php echo $lang['Save Settings']; ?>
                                                    </button>
                                                    <button type="reset" class="btn outline-btn btn-reset">
                                                        <?php echo $lang['reset_system_settings']; ?>
                                                    </button>
                                                </div>
                                                
                                                <div class="settings-info">
                                                    <div class="info-item">
                                                        <i class="fas fa-info-circle text-info"></i>
                                                        <span><?php echo $lang['All fields marked with * are required']; ?></span>
                                                    </div>
                                                    <div class="info-item">
                                                        <i class="fas fa-clock text-warning"></i>
                                                        <span><?php echo $lang['Settings will be applied immediately']; ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<script>window.__toastFlash=<?php echo json_encode($toast_flash, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;</script>
<script src="<?php echo htmlspecialchars(rtrim((string) $url, '/') . '/assets/js/toast.js', ENT_QUOTES, 'UTF-8'); ?>" defer></script>
<?php include("../templates/main-footer.php"); ?>
<script>
(function () {
    var searchInput = document.getElementById('timezoneSearchInput');
    var menu = document.getElementById('timezoneDropdownMenu');
    var hidden = document.getElementById('timezone');
    var selectedText = document.getElementById('selectedTimezoneText');
    var dropdown = document.getElementById('timezoneDropdown');

    if (!searchInput || !menu || !hidden) {
        return;
    }

    var searchSticky = menu.querySelector('li.px-3.py-2');
    if (searchSticky) {
        searchSticky.addEventListener('click', function (e) {
            e.stopPropagation();
        });
    }

    searchInput.addEventListener('click', function (e) {
        e.stopPropagation();
    });

    searchInput.addEventListener('input', function (e) {
        var searchTerm = e.target.value.toLowerCase().trim();
        var items = document.querySelectorAll('#timezoneOptionsList .timezone-option-item');
        var visibleCount = 0;

        items.forEach(function (option) {
            var searchText = (option.getAttribute('data-search-text') || '').toLowerCase();
            if (searchText.indexOf(searchTerm) !== -1) {
                option.style.display = '';
                visibleCount++;
            } else {
                option.style.display = 'none';
            }
        });

        var noTimezoneFound = document.getElementById('noTimezoneFound');
        if (noTimezoneFound) {
            noTimezoneFound.style.display = (visibleCount === 0 && searchTerm !== '') ? 'block' : 'none';
        }
    });

    if (dropdown) {
        var dropdownBtn = document.getElementById('timezoneDropdownBtn');
        if (dropdownBtn && window.bootstrap && bootstrap.Dropdown) {
            bootstrap.Dropdown.getOrCreateInstance(dropdownBtn, {
                popperConfig: function (defaultConfig) {
                    defaultConfig.placement = 'top-start';
                    return defaultConfig;
                }
            });
        }

        dropdown.addEventListener('hidden.bs.dropdown', function () {
            searchInput.value = '';
            document.querySelectorAll('#timezoneOptionsList .timezone-option-item').forEach(function (option) {
                option.style.display = '';
            });
            var noTimezoneFound = document.getElementById('noTimezoneFound');
            if (noTimezoneFound) {
                noTimezoneFound.style.display = 'none';
            }
        });
    }

    document.querySelectorAll('.timezone-option').forEach(function (option) {
        option.addEventListener('click', function (e) {
            e.preventDefault();
            var value = option.getAttribute('data-value');
            if (!value) {
                return;
            }
            hidden.value = value;
            if (selectedText) {
                selectedText.textContent = (option.textContent || '').trim();
            }
            document.querySelectorAll('.timezone-option').forEach(function (item) {
                item.classList.remove('active');
            });
            option.classList.add('active');
        });
    });
})();
</script>