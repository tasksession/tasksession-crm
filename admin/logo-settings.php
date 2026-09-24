<?php
ob_start(); 
require_once("../includes/lib-initialize.php");
require_once("../includes/system_helpers.php"); // Add the helper functions
$title = "Logo Settings | ".$syatem_title;
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
$settings->id = 1;
/** @var array{type:string,msg:string}|null assets/js/toast.js reads window.__toastFlash */
$toast_flash = null;
if (isset($_POST['reset_logo_settings'])) {
    $settings->login_page_logo = '';
    $settings->logo = '';
    $settings->favicon_image = '';
    $settings->mobile_logo = '';
    $settings->login_page_image = '';
    $settings->invoice_logo = '';
    $settings->email_template_logo = '';
    $savesettings = $settings->save();
    if ($savesettings) {
        require_once __DIR__ . '/../includes/sync_root_favicon.php';
        comon_sync_root_favicon_file('');
        header('location:logo-settings.php?message=reset');
    } else {
        header('location:logo-settings.php?message=fail');
    }
    exit;
}
if(isset($_POST['update_logo_settings'])) {
    $target_dir = "../uploads/system-uploads/";
    
    // Security: Ensure upload directory exists
    if (!is_dir($target_dir)) {
        if (!mkdir($target_dir, 0755, true)) {
            $toast_flash = array('type' => 'error', 'msg' => 'Failed to create upload directory.');
        }
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
    
    // Light Logo
    if($_FILES['login_page_logo']['name'] != ""){
        $result = secureFileUpload($_FILES['login_page_logo'], 'login', $target_dir);
        if ($result['success']) {
            $login_page_logo_name = $result['filename'];
        } else {
            $toast_flash = array('type' => 'error', 'msg' => 'Login page logo: ' . ($result['error'] ?? 'Upload failed'));
            $login_page_logo_name = $settings->login_page_logo;
        }
    } else {
        $login_page_logo_name = $settings->login_page_logo;
    }
    // Dark Logo
    if($_FILES['logo']['name'] != ""){
        $result = secureFileUpload($_FILES['logo'], 'logo', $target_dir);
        if ($result['success']) {
            $logo_name = $result['filename'];
        } else {
            $toast_flash = array('type' => 'error', 'msg' => 'Logo: ' . ($result['error'] ?? 'Upload failed'));
            $logo_name = $settings->logo;
        }
    } else {
        $logo_name = $settings->logo;
    }
    // Logo Icon
    if($_FILES['favicon_image']['name'] != ""){
        $result = secureFileUpload($_FILES['favicon_image'], 'fav', $target_dir);
        if ($result['success']) {
            $file_name = $result['filename'];
        } else {
            $toast_flash = array('type' => 'error', 'msg' => 'Favicon: ' . ($result['error'] ?? 'Upload failed'));
            $file_name = $settings->favicon_image;
        }
    } else {
        $file_name = $settings->favicon_image;
    }
    // Mobile Logo
    if($_FILES['mobile_logo']['name'] != ""){
        $result = secureFileUpload($_FILES['mobile_logo'], 'mlogo', $target_dir);
        if ($result['success']) {
            $mlogo_name = $result['filename'];
        } else {
            $toast_flash = array('type' => 'error', 'msg' => 'Mobile logo: ' . ($result['error'] ?? 'Upload failed'));
            $mlogo_name = $settings->mobile_logo;
        }
    } else {
        $mlogo_name = $settings->mobile_logo;
    }
    // Login Page Image
    if($_FILES['login_page_image']['name'] != ""){
        $result = secureFileUpload($_FILES['login_page_image'], 'login_img', $target_dir);
        if ($result['success']) {
            $login_image_name = $result['filename'];
        } else {
            $toast_flash = array('type' => 'error', 'msg' => 'Login page image: ' . ($result['error'] ?? 'Upload failed'));
            $login_image_name = $settings->login_page_image;
        }
    } else {
        $login_image_name = $settings->login_page_image;
    }
    // Invoice Logo
    if($_FILES['invoice_logo']['name'] != ""){
        $result = secureFileUpload($_FILES['invoice_logo'], 'invoice', $target_dir);
        if ($result['success']) {
            $invoice_logo_name = $result['filename'];
        } else {
            $toast_flash = array('type' => 'error', 'msg' => 'Invoice logo: ' . ($result['error'] ?? 'Upload failed'));
            $invoice_logo_name = $settings->invoice_logo;
        }
    } else {
        $invoice_logo_name = $settings->invoice_logo;
    }
    // Email template logo (PNG/JPEG only — used by {LOGO} shortcode)
    if (!empty($_FILES['email_template_logo']['name'])) {
        $result = secureFileUpload($_FILES['email_template_logo'], 'email_logo', $target_dir);
        if ($result['success']) {
            $emailLogoExt = strtolower(pathinfo($result['filename'], PATHINFO_EXTENSION));
            if (!in_array($emailLogoExt, array('png', 'jpg', 'jpeg'), true)) {
                @unlink($target_dir . $result['filename']);
                $toast_flash = array('type' => 'error', 'msg' => 'Email template logo: PNG or JPG only.');
                $email_template_logo_name = $settings->email_template_logo;
            } else {
                $email_template_logo_name = $result['filename'];
            }
        } else {
            $toast_flash = array('type' => 'error', 'msg' => 'Email template logo: ' . ($result['error'] ?? 'Upload failed'));
            $email_template_logo_name = $settings->email_template_logo;
        }
    } else {
        $email_template_logo_name = $settings->email_template_logo;
    }
    $settings->login_page_logo = $login_page_logo_name;
    $settings->logo = $logo_name;
    $settings->favicon_image = $file_name;
    $settings->mobile_logo = $mlogo_name;
    $settings->login_page_image = $login_image_name;
    $settings->invoice_logo = $invoice_logo_name;
    $settings->email_template_logo = $email_template_logo_name;
    $savesettings = $settings->save();
    if($savesettings){
        require_once __DIR__ . '/../includes/sync_root_favicon.php';
        comon_sync_root_favicon_file($settings->favicon_image);
        if (!function_exists('setup_guide_mark_visit')) {
            require_once dirname(__DIR__) . '/includes/setup_guide.php';
        }
        setup_guide_mark_visit('logo');
        header('location:logo-settings.php?message=success');
    } else {
        header('location:logo-settings.php?message=fail');
    }
    exit;
}
if (isset($_GET['message'])) {
    if ($_GET['message'] == 'success') {
        $toast_flash = array('type' => 'success', 'msg' => $lang['All logos have been updated successfully!']);
    } elseif ($_GET['message'] == 'reset') {
        $toast_flash = array('type' => 'info', 'msg' => $lang['Logo settings reset success']);
    } elseif ($_GET['message'] == 'fail') {
        $toast_flash = array(
            'type' => 'error',
            'msg' => $lang['Failed to update logo settings'] . '. ' . $lang['Please try again or contact support'] . '.',
        );
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
                        <form method="post" action="#" enctype="multipart/form-data">
                            <h2 class="page-title d-flex justify-content-between align-items-center flex-wrap row-gap-10">
                                <?php echo $lang['Logo Settings']; ?>
                                <div class="d-flex align-items-center col-gap-10">
                                    <button type="submit" name="reset_logo_settings" class="border-btn-a" onclick="return confirm(<?php echo json_encode($lang['Reset logo settings confirm'] ?? 'Reset all logos to default images? Custom uploads will be cleared from settings.', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>);">
                                        <?php echo $lang['Reset to Default']; ?>
                                    </button>
                                    <button type="submit" name="update_logo_settings" class="primary-btn">
                                        <?php echo $lang['save settings']; ?>
                                    </button>
                                </div>
                            </h2>
                            <div class="row general-settings">
                                <div class="col-md-8">
                                    <div class="logo-settings-container">
                                        <div class="logo-grid">
                                    <div class="logo-card">
                                        <div class="logo-card-header">
                                            <div class="logo-icon">
                                                <?php if($settings->favicon_image && file_exists("../uploads/system-uploads/" . $settings->favicon_image)){ ?> 
                                                    <img src="<?php echo getSystemImageUrl($settings->favicon_image); ?>" alt="Favicon Icon" class="logo-icon-img"/>
                                                <?php } else { ?> 
                                                    <img src="<?php echo $url; ?>assets/images/favicon.png" alt="Default Favicon Icon" class="logo-icon-img"/>
                                                <?php } ?>
                                            </div>
                                            <div class="logo-info">
                                                <h4><?php echo $lang['Sidebar Logo']; ?></h4>
                                                <p><?php echo $lang['Upload a logo for the sidebar navigation. Recommended size: 200×50px. This logo appears in the collapsed sidebar and navigation areas.']; ?></p>
                                            </div>
                                        </div>
                                        <div class="logo-card-body">
                                            <div class="logo-preview-area">
                                                <?php if($settings->login_page_logo && file_exists("../uploads/system-uploads/" . $settings->login_page_logo)){ ?> 
                                                    <img src="<?php echo getSystemImageUrl($settings->login_page_logo); ?>" alt="Sidebar Logo" class="logo-preview-img"/>
                                                <?php } else { ?> 
                                                    <img src="<?php echo $url; ?>assets/images/svg/logo.svg" alt="Default Sidebar Logo" class="logo-preview-img"/>
                                                <?php } ?>
                                            </div>
                                            <div class="logo-upload-area">
                                                <input type="file" name="login_page_logo" accept="image/x-png,image/gif,image/jpeg,image/svg+xml" class="form-control-file" id="sidebar-logo-input">
                                                <label for="sidebar-logo-input" class="upload-btn">
                                                    <i class="fas fa-cloud-upload-alt"></i>
                                                    <span><?php echo $lang['Upload']; ?></span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="logo-card">
                                        <div class="logo-card-header">
                                            <div class="logo-icon">
                                                <?php if($settings->favicon_image && file_exists("../uploads/system-uploads/" . $settings->favicon_image)){ ?> 
                                                    <img src="<?php echo getSystemImageUrl($settings->favicon_image); ?>" alt="Favicon Icon" class="logo-icon-img"/>
                                                <?php } else { ?> 
                                                    <img src="<?php echo $url; ?>assets/images/favicon.png" alt="Default Favicon Icon" class="logo-icon-img"/>
                                                <?php } ?>
                                            </div>
                                            <div class="logo-info">
                                                <h4><?php echo $lang['Horizontal Logo']; ?></h4>
                                                <p><?php echo $lang['Upload a horizontal logo for headers and main navigation. Recommended size: 200×50px. This logo appears in the main header and top navigation areas.']; ?></p>
                                            </div>
                                        </div>
                                        <div class="logo-card-body">
                                            <div class="logo-preview-area">
                                                <?php if($settings->logo && file_exists("../uploads/system-uploads/" . $settings->logo)){ ?> 
                                                    <img src="<?php echo getSystemImageUrl($settings->logo); ?>" alt="Horizontal Logo" class="logo-preview-img"/>
                                                <?php } else { ?> 
                                                    <img src="<?php echo $url;?>assets/images/svg/dark-logo.svg" alt="Default Horizontal Logo" class="logo-preview-img"/>
                                                <?php } ?>
                                            </div>
                                            <div class="logo-upload-area">
                                                <input type="file" name="logo" accept="image/x-png,image/gif,image/jpeg,image/svg+xml" class="form-control-file" id="horizontal-logo-input">
                                                <label for="horizontal-logo-input" class="upload-btn">
                                                    <i class="fas fa-cloud-upload-alt"></i>
                                                    <span><?php echo $lang['Upload']; ?></span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="logo-card">
                                        <div class="logo-card-header">
                                            <div class="logo-icon">
                                                <?php if($settings->favicon_image && file_exists("../uploads/system-uploads/" . $settings->favicon_image)){ ?> 
                                                    <img src="<?php echo getSystemImageUrl($settings->favicon_image); ?>" alt="Favicon Icon" class="logo-icon-img"/>
                                                <?php } else { ?> 
                                                    <img src="<?php echo $url; ?>assets/images/favicon.png" alt="Default Favicon Icon" class="logo-icon-img"/>
                                                <?php } ?>
                                            </div>
                                            <div class="logo-info">
                                                <h4><?php echo $lang['Mobile Logo']; ?></h4>
                                                <p><?php echo $lang['Upload a mobile-optimized logo for smaller screens. Recommended size: 150×30px. This logo appears on mobile devices and responsive layouts.']; ?></p>
                                            </div>
                                        </div>
                                        <div class="logo-card-body">
                                            <div class="logo-preview-area">
                                                <?php if($settings->mobile_logo && file_exists("../uploads/system-uploads/" . $settings->mobile_logo)){ ?> 
                                                    <img src="<?php echo getSystemImageUrl($settings->mobile_logo); ?>" alt="Mobile Logo" class="logo-preview-img"/>
                                                <?php } else { ?> 
                                                    <img src="<?php echo $url;?>assets/images/svg/mobile-logo.svg" alt="Default Mobile Logo" class="logo-preview-img"/>
                                                <?php } ?>
                                            </div>
                                            <div class="logo-upload-area">
                                                <input type="file" name="mobile_logo" accept="image/x-png,image/gif,image/jpeg,image/svg+xml" class="form-control-file" id="mobile-logo-input">
                                                <label for="mobile-logo-input" class="upload-btn">
                                                    <i class="fas fa-cloud-upload-alt"></i>
                                                    <span><?php echo $lang['Upload']; ?></span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="logo-card">
                                        <div class="logo-card-header">
                                            <div class="logo-icon">
                                                <?php if($settings->favicon_image && file_exists("../uploads/system-uploads/" . $settings->favicon_image)){ ?> 
                                                    <img src="<?php echo getSystemImageUrl($settings->favicon_image); ?>" alt="Favicon Icon" class="logo-icon-img"/>
                                                <?php } else { ?> 
                                                    <img src="<?php echo $url; ?>assets/images/favicon.png" alt="Default Favicon Icon" class="logo-icon-img"/>
                                                <?php } ?>
                                            </div>
                                            <div class="logo-info">
                                                <h4><?php echo $lang['Invoice Logo']; ?></h4>
                                                <p><?php echo $lang['Upload a professional logo specifically for invoices and billing documents. Recommended formats: PNG or JPG. This logo appears on all generated invoices and financial documents.']; ?></p>
                                            </div>
                                        </div>
                                        <div class="logo-card-body">
                                            <div class="logo-preview-area">
                                                <?php if($settings->invoice_logo && file_exists("../uploads/system-uploads/" . $settings->invoice_logo)){ ?> 
                                                    <img src="<?php echo getSystemImageUrl($settings->invoice_logo); ?>" alt="Invoice Logo" class="logo-preview-img"/>
                                                <?php } else { ?> 
                                                    <img src="<?php echo $url; ?>assets/images/dark-logo.png" alt="Default Invoice Logo" class="logo-preview-img"/>
                                                <?php } ?>
                                            </div>
                                            <div class="logo-upload-area">
                                                <input type="file" name="invoice_logo" accept="image/png,image/jpeg" class="form-control-file" id="invoice-logo-input">
                                                <label for="invoice-logo-input" class="upload-btn">
                                                    <i class="fas fa-cloud-upload-alt"></i>
                                                    <span><?php echo $lang['Upload']; ?></span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="logo-card">
                                        <div class="logo-card-header">
                                            <div class="logo-icon">
                                                <?php if(!empty($settings->email_template_logo) && file_exists("../uploads/system-uploads/" . $settings->email_template_logo)){ ?> 
                                                    <img src="<?php echo getSystemImageUrl($settings->email_template_logo); ?>" alt="Email template logo" class="logo-icon-img"/>
                                                <?php } else { ?> 
                                                    <img src="<?php echo $url; ?>assets/images/dark-logo.png" alt="Default Email template logo" class="logo-icon-img"/>
                                                <?php } ?>
                                            </div>
                                            <div class="logo-info">
                                                <h4><?php echo isset($lang['Email template logo']) ? $lang['Email template logo'] : 'Email template logo'; ?></h4>
                                                <p><?php echo isset($lang['Upload a logo for email templates (PNG or JPG). This logo is inserted with the {LOGO} shortcode. If none is uploaded, the Task Session logo is used.']) ? $lang['Upload a logo for email templates (PNG or JPG). This logo is inserted with the {LOGO} shortcode. If none is uploaded, the Task Session logo is used.'] : 'Upload a logo for email templates (PNG or JPG). This logo is inserted with the {LOGO} shortcode. If none is uploaded, the Task Session logo is used.'; ?></p>
                                            </div>
                                        </div>
                                        <div class="logo-card-body">
                                            <div class="logo-preview-area">
                                                <?php if(!empty($settings->email_template_logo) && file_exists("../uploads/system-uploads/" . $settings->email_template_logo)){ ?> 
                                                    <img src="<?php echo getSystemImageUrl($settings->email_template_logo); ?>" alt="Email template logo" class="logo-preview-img"/>
                                                <?php } else { ?> 
                                                    <img src="<?php echo $url; ?>assets/images/dark-logo.png" alt="Default Email template logo" class="logo-preview-img"/>
                                                <?php } ?>
                                            </div>
                                            <div class="logo-upload-area">
                                                <input type="file" name="email_template_logo" accept="image/png,image/jpeg" class="form-control-file" id="email-template-logo-input">
                                                <label for="email-template-logo-input" class="upload-btn">
                                                    <i class="fas fa-cloud-upload-alt"></i>
                                                    <span><?php echo $lang['Upload']; ?></span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="logo-card">
                                        <div class="logo-card-header">
                                            <div class="logo-icon">
                                                <?php if($settings->favicon_image && file_exists("../uploads/system-uploads/" . $settings->favicon_image)){ ?> 
                                                    <img src="<?php echo getSystemImageUrl($settings->favicon_image); ?>" alt="Favicon Icon" class="logo-icon-img"/>
                                                <?php } else { ?> 
                                                    <img src="<?php echo $url; ?>assets/images/favicon.png" alt="Default Favicon Icon" class="logo-icon-img"/>
                                                <?php } ?>
                                            </div>
                                            <div class="logo-info">
                                                <h4><?php echo $lang['Favicon Icon']; ?></h4>
                                                <p><?php echo $lang['Upload a favicon icon for browser tabs and bookmarks. Recommended size: 32×32px or 16×16px. This icon appears in browser tabs, bookmarks, and when the sidebar is collapsed.']; ?></p>
                                            </div>
                                        </div>
                                        <div class="logo-card-body">
                                            <div class="logo-preview-area">
                                                <?php if($settings->favicon_image && file_exists("../uploads/system-uploads/" . $settings->favicon_image)){ ?> 
                                                    <img src="<?php echo getSystemImageUrl($settings->favicon_image); ?>" alt="Favicon" class="logo-preview-img"/>
                                                <?php } else { ?> 
                                                    <img src="<?php echo $url; ?>assets/images/favicon.png" alt="Default Favicon" class="logo-preview-img"/>
                                                <?php }?>
                                            </div>
                                            <div class="logo-upload-area">
                                                <input type="file" name="favicon_image" accept="image/x-png,image/gif,image/jpeg,image/svg+xml" class="form-control-file" id="favicon-input">
                                                <label for="favicon-input" class="upload-btn">
                                                    <i class="fas fa-cloud-upload-alt"></i>
                                                    <span><?php echo $lang['Upload']; ?></span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                                                        <div class="logo-card">
                                        <div class="logo-card-header">
                                            <div class="logo-icon">
                                                <?php if($settings->favicon_image && file_exists("../uploads/system-uploads/" . $settings->favicon_image)){ ?> 
                                                    <img src="<?php echo getSystemImageUrl($settings->favicon_image); ?>" alt="Favicon Icon" class="logo-icon-img"/>
                                                <?php } else { ?> 
                                                    <img src="<?php echo $url; ?>assets/images/favicon.png" alt="Default Favicon Icon" class="logo-icon-img"/>
                                                <?php } ?>
                                            </div>
                                            <div class="logo-info">
                                                <h4><?php echo $lang['Login Page Image']; ?></h4>
                                                <p><?php echo $lang['Upload a background image for the login page. Recommended size: 1920×1080px or larger. This image appears as the background on the login and authentication pages.']; ?></p>
                                            </div>
                                        </div>
                                        <div class="logo-card-body">
                                            <div class="logo-preview-area">
                                                <?php if($settings->login_page_image && file_exists("../uploads/system-uploads/" . $settings->login_page_image)){ ?> 
                                                    <img src="<?php echo getSystemImageUrl($settings->login_page_image); ?>" alt="Login Page Image" class="logo-preview-img"/>
                                                <?php } else { ?> 
                                                    <img src="<?php echo $url; ?>assets/images/login.jpg" alt="Default Login Image" class="logo-preview-img"/>
                                                <?php } ?>
                                            </div>
                                            <div class="logo-upload-area">
                                                <input type="file" name="login_page_image" accept="image/x-png,image/gif,image/jpeg,image/svg+xml" class="form-control-file" id="login-image-input">
                                                <label for="login-image-input" class="upload-btn">
                                                    <i class="fas fa-cloud-upload-alt"></i>
                                                    <span><?php echo $lang['Upload']; ?></span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    </div>
                                </div> </div>
                                <div class="col-md-4">
                                    <div class="logo-preview-sidebar">
                                        <div class="preview-card">
                                            <h4><i class="fas fa-eye"></i> <?php echo $lang['Logo Preview']; ?></h4>
                                            <div class="preview-section">
                                                <h5><?php echo $lang['Sidebar Logo']; ?></h5>
                                                <div class="preview-box sidebar-preview">
                                                    <?php if($settings->login_page_logo && file_exists("../uploads/system-uploads/" . $settings->login_page_logo)){ ?> 
                                                        <img id="sidebar-preview" src="<?php echo getSystemImageUrl($settings->login_page_logo); ?>" alt="<?php echo $lang['Sidebar Logo']; ?> Preview"/>
                                                    <?php } else { ?> 
                                                        <img id="sidebar-preview" src="<?php echo $url; ?>assets/images/svg/logo.svg" alt="<?php echo $lang['Sidebar Logo']; ?> Preview"/>
                                                    <?php } ?>
                                                </div>
                                            </div>
                                            <div class="preview-section">
                                                <h5><?php echo $lang['Horizontal Logo']; ?></h5>
                                                <div class="preview-box horizontal-preview">
                                                    <?php if($settings->logo && file_exists("../uploads/system-uploads/" . $settings->logo)){ ?> 
                                                        <img id="horizontal-preview" src="<?php echo getSystemImageUrl($settings->logo); ?>" alt="<?php echo $lang['Horizontal Logo']; ?> Preview"/>
                                                    <?php } else { ?> 
                                                        <img id="horizontal-preview" src="<?php echo $url; ?>assets/images/svg/dark-logo.svg" alt="<?php echo $lang['Horizontal Logo']; ?> Preview"/>
                                                    <?php } ?>
                                                </div>
                                            </div>
                                            <div class="preview-section">
                                                <h5><?php echo $lang['Mobile Logo']; ?></h5>
                                                <div class="preview-box mobile-preview">
                                                    <?php if($settings->mobile_logo && file_exists("../uploads/system-uploads/" . $settings->mobile_logo)){ ?> 
                                                        <img id="mobile-preview" src="<?php echo getSystemImageUrl($settings->mobile_logo); ?>" alt="<?php echo $lang['Mobile Logo']; ?> Preview"/>
                                                    <?php } else { ?> 
                                                        <img id="mobile-preview" src="<?php echo $url; ?>assets/images/svg/mobile-logo.svg" alt="<?php echo $lang['Mobile Logo']; ?> Preview"/>
                                                    <?php } ?>
                                                </div>
                                            </div>
                                            <div class="preview-section">
                                                <h5><?php echo $lang['Invoice Logo']; ?></h5>
                                                <div class="preview-box invoice-preview">
                                                    <?php if($settings->invoice_logo && file_exists("../uploads/system-uploads/" . $settings->invoice_logo)){ ?> 
                                                        <img id="invoice-preview" src="<?php echo getSystemImageUrl($settings->invoice_logo); ?>" alt="<?php echo $lang['Invoice Logo']; ?> Preview"/>
                                                    <?php } else { ?> 
                                                        <img id="invoice-preview" src="<?php echo $url; ?>assets/images/dark-logo.png" alt="<?php echo $lang['Invoice Logo']; ?> Preview"/>
                                                    <?php } ?>
                                                </div>
                                            </div>
                                            <div class="preview-section">
                                                <h5><?php echo isset($lang['Email template logo']) ? $lang['Email template logo'] : 'Email template logo'; ?></h5>
                                                <div class="preview-box email-template-preview">
                                                    <?php if(!empty($settings->email_template_logo) && file_exists("../uploads/system-uploads/" . $settings->email_template_logo)){ ?> 
                                                        <img id="email-template-preview" src="<?php echo getSystemImageUrl($settings->email_template_logo); ?>" alt="Email template logo Preview"/>
                                                    <?php } else { ?> 
                                                        <img id="email-template-preview" src="<?php echo $url; ?>assets/images/dark-logo.png" alt="Default Email template logo Preview"/>
                                                    <?php } ?>
                                                </div>
                                            </div>
                                            <div class="preview-section">
                                                <h5><?php echo $lang['Favicon Icon']; ?></h5>
                                                <div class="preview-box favicon-preview">
                                                    <?php if($settings->favicon_image && file_exists("../uploads/system-uploads/" . $settings->favicon_image)){ ?> 
                                                        <img id="favicon-preview" src="<?php echo getSystemImageUrl($settings->favicon_image); ?>" alt="<?php echo $lang['Favicon Icon']; ?> Preview"/>
                                                    <?php } else { ?> 
                                                        <img id="favicon-preview" src="<?php echo $url; ?>assets/images/favicon.png" alt="<?php echo $lang['Favicon Icon']; ?> Preview"/>
                                                    <?php } ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                           
                        </form>
                    </div>
                </div>
            </div>
            <div class="clearfix"></div>
        </div>
    </div>
</div>


<script>
// File upload preview functionality
document.addEventListener('DOMContentLoaded', function() {
    const fileInputs = document.querySelectorAll('input[type="file"]');
    
    fileInputs.forEach(input => {
        input.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                const card = this.closest('.logo-card');
                
                // Add uploading animation
                card.classList.add('uploading');
                
                reader.onload = function(e) {
                    const img = card.querySelector('.logo-preview-img');
                    if (img) {
                        img.src = e.target.result;
                    }
                    
                    // Update preview sidebar based on input type
                    updatePreviewSidebar(input.name, e.target.result);
                    
                    // Remove uploading animation and add success state
                    setTimeout(() => {
                        card.classList.remove('uploading');
                        card.classList.add('uploaded');
                        
                        // Remove success state after 2 seconds
                        setTimeout(() => {
                            card.classList.remove('uploaded');
                        }, 2000);
                    }, 1000);
                };
                
                reader.readAsDataURL(file);
            }
        });
    });
    
    // Function to update preview sidebar
    function updatePreviewSidebar(inputName, imageSrc) {
        let previewId = '';
        
        switch(inputName) {
            case 'login_page_logo':
                previewId = 'sidebar-preview';
                break;
            case 'logo':
                previewId = 'horizontal-preview';
                break;
            case 'mobile_logo':
                previewId = 'mobile-preview';
                break;
            case 'invoice_logo':
                previewId = 'invoice-preview';
                break;
            case 'email_template_logo':
                previewId = 'email-template-preview';
                break;
            case 'favicon_image':
                previewId = 'favicon-preview';
                break;
        }
        
        if (previewId) {
            const previewImg = document.getElementById(previewId);
            if (previewImg) {
                previewImg.src = imageSrc;
            }
        }
    }
});
</script>
<script>window.__toastFlash=<?php echo json_encode($toast_flash, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;</script>
<script src="<?php echo htmlspecialchars(rtrim((string) $url, '/') . '/assets/js/toast.js', ENT_QUOTES, 'UTF-8'); ?>" defer></script>
<?php include("../templates/main-footer.php"); ?>