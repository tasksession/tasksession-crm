<?php
/*
================================================================================
     Task Session – Project Management System
     File    : forgot-password.php
     Purpose : Password recovery page for user account access
================================================================================
*/
ob_start(); 
require_once("includes/lib-initialize.php");
require_once("includes/system_helpers.php"); // Add the helper functions
require_once("./includes/initialize.php");
   $title = "Forgot Password | ". $syatem_title;	 
   $settings = settings::findById(1);
   	if($session->isLoggedIn()) {
   		redirectTo($url."index.php");
   	}
   	
   	$message = "";
   	$message_type = "error";
   	
   	// Remember to give your form's submit tag a name="submit" attribute!
   	//condtions for checking empty values
   	if(!empty($_POST["forgot-password"])){
   		
   		if(!empty($_POST["email"])) {
   			
   			$email = trim($_POST["email"]);
   			$foundUser = User::findByEmail($email);
   			
   			if(!empty($foundUser)) {
   				// Create a secure reset token
   				$resetToken = PasswordResetToken::createToken($foundUser->id);
   				
   				if($resetToken) {
   					$to = $foundUser->email;
   					$subject = 'Password Reset Request';
   					
   					// Create secure reset URL with token
   					$reset_url = $url . 'reset_password.php?token=' . $resetToken;
   					$variablesArr = array(
   						'{USER_NAME}' => $foundUser->firstName, 
   						'{SIGNATURE}' => $company_name, 
   						'{DASHBOARD_URL}' => $url, 
   						'{RESET_URL}' => $reset_url
   					);
   					$templateHTML = $settings->forget_email;
   					$message_body = strtr($templateHTML, $variablesArr);

   					require_once __DIR__ . '/includes/email_helper.php';
   					$emailHelper = new EmailHelper($settings);
   					$emailSent = $emailHelper->sendEmail($to, $subject, $message_body);
   					if($emailSent){
   						$message = "Password reset instructions have been sent to your email address. Please check your inbox and follow the link to reset your password.";
   						$message_type = "success";
   					} else {
   						$message = "There was a problem sending the password reset email. Please try again later.";
   						$message_type = "error";
   					}
   				} else {
   					$message = "There was a problem generating the reset token. Please try again later.";
   					$message_type = "error";
   				}
   			} else {
   				// Don't reveal if email exists or not for security
   				$message = "If an account with that email address exists, password reset instructions have been sent.";
   				$message_type = "success";
   			}
   		} else {
   			$message = "Please enter your email address.";
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
							</div>
						<?php if(!empty($success_message)) { ?>
							<div class="success_message">
								<?php echo htmlspecialchars($success_message); ?>
							</div>
							<?php } ?>
								<div class="wrapper-400 align-center">
								<form class="login-form grey forgetpage w-100" action="forgot-password.php" method="post" name="frmForgot">
									<h3 class="card-title mb-4 font-size-24"><?php echo htmlspecialchars($lang['Reset your password']); ?></h3>
									<?php if(isset($message)&&(!empty($message))){ ?>
									  <div class="row">
										<div class="col-sm-12">
										  <div class="alert alert-<?php echo htmlspecialchars($message_type); ?> static-alerts">
											<button class="close" data-close="alert"></button> <span style="display:block;"><?php echo htmlspecialchars($message);?></span> </div>
										</div>
									</div>
									<?php } ?>
								<div class="form-group">
									<div class="input-icon">
										<label class="control-label">
											<?php echo htmlspecialchars($lang['Email Address']); ?>
										</label>
											<input class="form-control placeholder-no-fix" type="text" autocomplete="off" name="email" />
												<div class="d-flex justify-content-between align-items-center">
												 <input type="submit" name="forgot-password" class="btn primary-btn" value="Forget Password"> 
													<a href="index.php" id="forget-password"><?php echo htmlspecialchars($lang['Back to Login']); ?></a>
												</div>
											</div>
									   </form>
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
<!-- login-area-->
<?php include("templates/frontend-footer.php"); ?>