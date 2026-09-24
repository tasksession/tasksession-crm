<?php 
/*
================================================================================
     Task Session – Project Management System
     File    : index.php
     Purpose : Main login page for user authentication
================================================================================
*/
ob_start();

/**
 * True when includes/config.php was written by the installer (or manual setup).
 * Empty files created by an old redirect stub must not block fresh install.
 */
function ts_has_valid_config(string $configPath): bool
{
    if (!is_file($configPath) || !is_readable($configPath)) {
        return false;
    }
    if (@filesize($configPath) < 32) {
        return false;
    }
    $contents = (string) @file_get_contents($configPath);
    return (bool) preg_match('/define\s*\(\s*["\']DB_SERVER["\']/i', $contents);
}

$configPath = __DIR__ . '/includes/config.php';
if (!ts_has_valid_config($configPath)) {
    header('Location: install/index.php');
    exit();
}

// Login GET/POST must not wait on license API, email helper, or other global init.
if (!defined('CRM_LIGHTWEIGHT_INIT')) {
    define('CRM_LIGHTWEIGHT_INIT', true);
}

require_once __DIR__ . '/includes/lib-initialize.php';
   require_once("./includes/auth_helper.php");
   
   $settings = isset($dash_settings) ? $dash_settings : Settings::findById(1);
   $loginCompany = trim((string) ($company_name ?? ''));
   $loginTagline = trim((string) ($syatem_title ?? ''));
   if ($loginCompany !== '' && $loginTagline !== '') {
       $title = 'Login | ' . $loginCompany . ' - ' . $loginTagline;
   } elseif ($loginCompany !== '') {
       $title = 'Login | ' . $loginCompany;
   } elseif ($loginTagline !== '') {
       $title = 'Login | ' . $loginTagline;
   } else {
       $title = 'Login';
   }
   date_default_timezone_set($time_zone);

   $loginHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
       || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443)
       || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
   $loginHost = (string) ($_SERVER['HTTP_HOST'] ?? '');
   $loginDir = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/')));
   if ($loginDir === '/' || $loginDir === '\\' || $loginDir === '.') {
       $loginDir = '';
   }
   $loginStayBase = ($loginHost !== '')
       ? (($loginHttps ? 'https://' : 'http://') . $loginHost . $loginDir . '/')
       : (string) $url;
   
   // Flag for templates before any extra work
   $is_login_page = true;

   // Check if Google Login is enabled (flag only — do not load OAuth client)
   $googleLoginEnabled = false;
   if (!(function_exists('tasksession_is_free_edition') && tasksession_is_free_edition())
       && isset($connect) && $connect instanceof mysqli) {
       $googleFlag = @$connect->query('SELECT is_enabled FROM google_login_settings WHERE id = 1 LIMIT 1');
       if ($googleFlag && ($googleRow = $googleFlag->fetch_assoc())) {
           $googleLoginEnabled = !empty($googleRow['is_enabled']);
       }
   }
   
   // Initialize auth helper
   $authHelper = new AuthHelper($session);

   $isAjaxLogin = false;
   if (isset($_POST['ajax']) && (string) $_POST['ajax'] === '1') {
       $isAjaxLogin = true;
   } elseif (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
       $isAjaxLogin = true;
   } elseif (strpos(strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json') !== false
       && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
       $isAjaxLogin = true;
   }

   $tsLoginJson = static function (array $payload): void {
       while (ob_get_level() > 0) {
           ob_end_clean();
       }
       header('Content-Type: application/json; charset=utf-8');
       header('Cache-Control: no-store, no-cache, must-revalidate');
       echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
       exit;
   };

   if (!function_exists('comon_post_login_redirect_path')) {
       require_once __DIR__ . '/includes/sidebar_navigation.php';
   }
   
   // Check if user is already logged in
   if(isset($_SESSION['accountStatus'])){
       $loggedInUser = User::findById((int)$session->userId);
       $redirectPath = comon_post_login_redirect_path($loggedInUser, $settings);
       if ($isAjaxLogin) {
           $tsLoginJson([
               'success' => true,
               'redirect' => $redirectPath,
           ]);
       }
       redirectTo($loginStayBase . $redirectPath);
   }
   
       // Check for remember me token
    if (!$session->isLoggedIn()) {
        $rememberMeUser = $authHelper->attemptRememberMeLogin();
        if (is_array($rememberMeUser) && (!empty($rememberMeUser['needs_2fa']) || !empty($rememberMeUser['needs_2fa_setup']))) {
            $mfaPath = !empty($rememberMeUser['needs_2fa_setup']) ? 'setup-2fa.php' : 'verify-2fa.php';
            if ($isAjaxLogin) {
                $tsLoginJson([
                    'success' => true,
                    'redirect' => $mfaPath,
                ]);
            }
            redirectTo($loginStayBase . $mfaPath);
        } elseif ($rememberMeUser && !is_array($rememberMeUser)) {
            $redirectUrl = comon_post_login_redirect_path($rememberMeUser, $settings);
            if ($isAjaxLogin) {
                $tsLoginJson([
                    'success' => true,
                    'redirect' => $redirectUrl,
                ]);
            }
            redirectTo($loginStayBase . $redirectUrl);
        }
    }
   
   $message = "";
   // surface external errors (e.g., OAuth) on main page
   if (isset($_GET['err'])) {
       $message = $_GET['err'];
   }
   
   // Handle form submission (works for both button click and Enter key)
   if ($_SERVER['REQUEST_METHOD'] === 'POST') {
       $email = trim($_POST['username'] ?? '');
       $password = trim($_POST['password'] ?? '');
       $rememberMe = isset($_POST['remember']);
   				
       if (!empty($email) && !empty($password)) {
           $result = $authHelper->attemptLogin($email, $password, $rememberMe);
           
           if ($result['success']) {
               if ($isAjaxLogin) {
                   $tsLoginJson([
                       'success' => true,
                       'redirect' => $result['redirect'] ?? 'admin/index.php',
                   ]);
               }
               redirectTo($loginStayBase . $result['redirect']);
           } elseif (!empty($result['needs_2fa']) || !empty($result['needs_2fa_setup'])) {
               $mfaPath = !empty($result['needs_2fa_setup']) ? 'setup-2fa.php' : 'verify-2fa.php';
               if ($isAjaxLogin) {
                   $tsLoginJson([
                       'success' => true,
                       'redirect' => $mfaPath,
                   ]);
               }
               redirectTo($loginStayBase . $mfaPath);
           } else {
               if ($isAjaxLogin) {
                   $tsLoginJson([
                       'success' => false,
                       'message' => $result['message'] ?? 'Login failed',
                   ]);
               }
               $message = $result['message'];
           }
       } else {
           if ($isAjaxLogin) {
               $tsLoginJson([
                   'success' => false,
                   'message' => 'Please enter Email and password',
               ]);
           }
           $message = "Please enter Email and password";
       }
   } 
   
  ?>
<?php include("templates/frontend-header.php"); ?>
<div class="login-area">
	<div class="content">
		<div class="row">
			<div class="col-12 col-lg-7 login-col">
				<div class="wrapper-400">
					<div class="logo">
						<a class="signLogo" href="<?php echo $url; ?>">
						<img src="<?php echo htmlspecialchars($login_brand_logo_url ?? crm_default_asset_image_url('assets/images/svg/dark-logo.svg'), ENT_QUOTES, 'UTF-8'); ?>" alt="logo" fetchpriority="high" loading="eager" decoding="async" />
						</a>
					</div>
				</div>
				<div class="wrapper-400 align-center">
				<form class="login-form w-100" action="" method="post">
					<h3 class="card-title mb-4 font-size-24"><?php if($login_page_title){echo htmlspecialchars($login_page_title);} else { ?>Sign in to your account<?php } ?></h3>
						<div class="row" id="loginErrorRow" style="<?php echo (!empty($message) ? '' : 'display:none;'); ?>">
							<div class="col-sm-12">
								<div class="alert alert-danger static-alerts">
								<button class="close" data-close="alert"></button> <span id="loginErrorText" style="display:block;"><?php echo htmlspecialchars($message);?></span> </div>
							</div>
						</div>
							<div class="form-group">
								<div class="input-icon">
									<label class="control-label">
										<?php echo htmlspecialchars($lang['Email Address']); ?>
									</label>
									<input class="form-control placeholder-no-fix logemail" type="text" name="username" value="<?php echo htmlspecialchars(isset($remembered_email) ? $remembered_email : ''); ?>" /> </div>
								<div class="clearfix"></div>
							</div>
							<div class="form-group">
								<div class="input-icon">
									<label class="control-label"><?php echo htmlspecialchars($lang['Password']); ?></label>
									<div class="eyes-row">
										<input class="form-control placeholder-no-fix pass" type="password" name="password" id="loginPassword" />
											<span class="eye" id="toggleLoginPassword" style="cursor:pointer;" role="button" tabindex="0" aria-label="<?php echo htmlspecialchars($lang['Show password'] ?? 'Show password'); ?>">
												<span id="eyeOpen" aria-hidden="true" style="display:none;"><?php echo ts_icon_inline('eye'); ?></span>
												<span id="eyeClosed" aria-hidden="true" style="display:inline;"><?php echo ts_icon_inline('eye-slash'); ?></span>
										</span>
									</div>
								<button type="submit" name="submit" class="btn primary-btn" id="loginSubmitBtn">
									<svg class="spinner-icon login-spinner" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" style="display:none;">
										<circle class="spinner-circle-animated" cx="12" cy="12" r="10" stroke-dasharray="24" stroke-dashoffset="24"></circle>
									</svg>
									<span class="login-btn-text"><?php echo htmlspecialchars($lang['LOG IN']); ?></span>
								</button>
									<div class="d-flex justify-content-between align-items-center">
										<label class="checkbox col-6 pd-0 d-flex col-gap-5 align-items-center">
											<input type="checkbox" name="remember" value="1" /> 
											<?php echo htmlspecialchars($lang['Remember me']); ?>
										</label>
										<div class="col-6 grey">
											<a href="forgot-password.php" id="forget-password"><?php echo htmlspecialchars($lang['Forgot your password?']); ?></a>
											
										</div></div>
											<?php if ($googleLoginEnabled): ?>
											<div class="d-block">
										     <div class="mt-3">
                            <div class="divider text-center my-3" style="display: flex; align-items: center; text-align: center; margin: 20px 0;">
                                <div style="flex: 1;height: 1px;background-color: var(--border-color);"></div>
                                <span style="padding: 0 15px; font-size: 14px;">or</span>
                                <div style="flex: 1;height: 1px;background-color: var(--border-color);"></div>
                            </div>
                            <a class="primary-btn w-100 d-flex align-items-center justify-content-center gap-2 pd-15 light-btn" href="<?php echo $url; ?>vendor/google/g-login/auth/google_start.php" style="column-gap:10px;">
                               <img src="<?php echo $url; ?>assets/images/g-logo.png" alt="main logo" / style="width: 20px;">
                                <span>Continue with Google</span>
                            </a>
										</div>
										
									</div>
											<?php endif; ?>
								</div>
							</div>
						</form>
                   
					</div>
				</div>
			<div class="col-12 col-lg-5 login-right"> 
				<img src="<?php echo htmlspecialchars($login_page_image_url ?? crm_default_asset_image_url('assets/images/login.jpg'), ENT_QUOTES, 'UTF-8'); ?>" alt="Login Page Image" loading="eager" decoding="async" />
				<a class="login-powered-by" href="https://www.tasksession.com/" target="_blank" rel="noopener noreferrer">Powered by TaskSession</a>
			</div>
		</div>
	</div>
</div>
<!-- login-area-->
<?php include("templates/frontend-footer.php");?>
