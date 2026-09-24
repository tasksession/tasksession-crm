<?php
/*
================================================================================
     Task Session – Project Management System
     File    : reset_password.php
     Purpose : Password reset page for secure account recovery
================================================================================
*/
ob_start(); 
require_once("includes/lib-initialize.php");
require_once("includes/system_helpers.php"); // Add the helper functions

   require_once("./includes/initialize.php"); 
      $title = "Reset Password | ". $syatem_title;	 
    $settings = settings::findById(1);
   	if($session->isLoggedIn()) {
   		$userId = isset($_SESSION['userId']) ? $_SESSION['userId'] : 1;
   		redirectTo("/profile.php?user_id=" . $userId);
   	}
   	
   	$message = "";
   	$message_type = "error";
   	$token_valid = false;
   	$user = null;
   	
   	// Check if token is provided and valid
   	if(isset($_GET['token']) && !empty($_GET['token'])) {
   		$token = trim($_GET['token']);
   		$user = PasswordResetToken::getUserByToken($token);
   		
   		if($user) {
   			$token_valid = true;
   		} else {
   			$message = "Invalid or expired reset link. Please request a new password reset.";
   			$message_type = "error";
   		}
   	} else {
   		$message = "Invalid reset link. Please request a new password reset.";
   		$message_type = "error";
   	}
   	
   	// Handle password reset form submission
   	if(!empty($_POST["reset-password"]) && $token_valid){
   		if(!empty($_POST["password"])) {
   			$password = trim($_POST["password"]);
   			
   			// Validate password strength (minimum 8 characters)
   			if(strlen($password) < 8) {
   				$message = "Password must be at least 8 characters long.";
   				$message_type = "error";
   			} else {
   				// Hash the new password
   				$hashedPassword = password_hash($password, PASSWORD_BCRYPT);
   				
   				// Update user password
   				$sql = "UPDATE users SET password = ? WHERE id = ?";
   				$stmt = $connect->prepare($sql);
   				$stmt->bind_param("si", $hashedPassword, $user->id);
   				
				if ($stmt->execute()) {
					PasswordResetToken::markTokenAsUsed($token);
                    if (function_exists('auth_bump_session_epoch')) {
                        auth_bump_session_epoch((int) $user->id);
                    } else {
                        User::revokeRememberMeTokens((int) $user->id);
                    }
					
					// Log password change
					require_once('includes/activity_logger.php');
					ActivityLogger::logPasswordChange($user->id);
					
					$message = "<div class='static-alerts' style='margin-top:20px;'>\n <span>Password updated successfully!</span><br>\n <a href='index.php' class='btn primary-btn' style='margin-top:10px;'>Login with new password</a>\n    </div>";
					$message_type = "success";
					$token_valid = false; // Hide the form after successful reset
   				} else {
   					$message = "Something went wrong. Please try again.";
   					$message_type = "error";
   				}
   			}
   		} else {
   			$message = "Password cannot be empty.";
   			$message_type = "error";
   		}
   	}
   	
   // Set flag to indicate this is a public page
   $is_login_page = true;
   ?>
	<?php include("templates/frontend-header.php"); ?>
		<div class="login-area">
			<div class="content">
				<div class="row">
					<div class="col-12 col-lg-7 login-col">
						<div class="wrapper-400">
							<div class="logo">
								<a class="signLogo" href="<?php echo $url; ?>">
								  <?php if($logo && file_exists("uploads/system-uploads/" . $logo)){ ?> <img src="<?php echo getSystemImageUrl($logo); ?>" alt="logo" />
								  <?php } else { ?> <img src="<?php echo $url; ?>assets/images/svg/dark-logo.svg" alt="main logo" />
								  <?php } ?>
								</a>
							</div>
						<?php if(!empty($success_message)) { ?>
							<div class="success_message">
								<?php echo $success_message; ?>
							</div>
							  <?php } ?>
								<div class="wrapper-400 align-center">
								 <?php if(isset($message)&&(!empty($message))){ ?>
									<div class="rows w-100">
										<div class="">
											<?php echo $message; ?> 
										</div>
								
								<?php } ?>
							<?php if($token_valid): ?>
								<form class="login-form w-100" action="#" method="post" name="frmForgot" onsubmit="return validatePasswordResetForm()">
									<h3 class="form-title"><?php echo htmlspecialchars($lang['Reset Password']); ?></h3>
									  <p class="text-muted">Hello <?php echo htmlspecialchars($user->firstName); ?>, please enter your new password below.</p>
											<div class="form-group row mt-4">
												<div class="input-icon col-sm-12">
													<label class="control-label"><?php echo htmlspecialchars($lang['Enter New Password']); ?></label>
														<div class="eyes-row">
															<input class="form-control placeholder-no-fix pass" type="password" name="password" id="loginPassword" minlength="8" required />
															<span class="eye" id="toggleLoginPassword" style="cursor:pointer;" role="button" tabindex="0" aria-label="<?php echo htmlspecialchars($lang['Show password'] ?? 'Show password'); ?>">
																<span id="eyeOpen" aria-hidden="true" style="display:none;"><?php echo ts_icon_inline('eye'); ?></span>
																<span id="eyeClosed" aria-hidden="true" style="display:inline;"><?php echo ts_icon_inline('eye-slash'); ?></span>
															</span>
														</div>
														<div id="password-strength" class="password-strength" aria-live="polite" style="display: none;"></div>
														<small class="form-text text-muted">Password must be at least 8 characters long.</small>
															<div class="d-flex justify-content-between align-items-center">
															  <input type="submit" name="reset-password" class="btn primary-btn" value="Reset Now"> 
															    <a href="index.php" class="grey" id="forget-password"><?php echo htmlspecialchars($lang['Back to Login']); ?></a>
															</div>
												       </div>
								                  </form>
								        <?php else: ?>
								    <div class="text-center">
									  <a href="forgot-password.php" class="btn primary-btn">Request New Password Reset</a>
										<a href="index.php" class="btn outline-btn"><?php echo htmlspecialchars($lang['Back to Login']); ?></a>
								    </div>
								<?php endif; ?>
							</div>
						</div>
					</div>
				</div>
			<div class="col-12 col-lg-5 login-right"> 
				<?php if($settings->login_page_image != ""){ ?>
					<img src="<?php echo getSystemImageUrl($settings->login_page_image); ?>" alt="Login Page Image" />
						<?php } else { ?>
							<img src="<?php echo $url; ?>assets/images/login.jpg" alt="Login Page Image" />
						<?php } ?>
				    </div>	
				</div>
			</div>
		</div>
<!-- login-area-->
<?php include("templates/frontend-footer.php"); ?>