<?php
/*
 ================================================================================
   Task Session – Project Management System
   Purpose    : Client 2checkout Payments Success
 ================================================================================
*/
ob_start();
require_once("../includes/lib-initialize.php");
if (!($session->isLoggedIn())) {
    redirectTo($url . "index.php");
}
if ((int) $_SESSION['accountStatus'] === 1) {
    redirectTo($url . "admin/edit");
}
if ((int) $_SESSION['accountStatus'] === 3) {
    redirectTo($url . "staff/edit");
}
$title = "Payments | ". $syatem_title;
include("../templates/header.php");

//condition check for login

$id=$session->userId; //id of the current logged in user 
$user = User::findById((int)$id); //take the record of current user in an object array 	
$username=$user->firstName;
$email=$user->email;
$phone=$user->phone;
$address=$user->address;
$city=$user->city;
$state=$user->state;
$zip=$user->zip;
$country=$user->country;
$account_stat=$user->status;
$user->regDate;

require_once("../payment-api/lib/Twocheckout.php");

Twocheckout::privateKey($checkout_id);
Twocheckout::sellerId($checkout_pk);
try {
    $charge = Twocheckout_Charge::auth(array(
        "merchantOrderId" => $_POST['milestone_id'],
        "token" => $_POST['token'],
        "currency" => $currency ,
        "total" => $_POST['amount1'],
        "billingAddr" => array(
            "name" => $_POST['username1'],
			"addrLine1" => $address,
			"city" => $city,
			"state" => $state,
			"zipCode" => $zip,
			"country" => $country,
			"email" => $email,
			"phoneNumber" => $phone
        ),
        
    ), 'array');
    if ($charge['response']['responseCode'] == 'APPROVED') {
		$milestone_id = $_POST['milestone_id'];
		$projectId = $_POST['proj_Id'] ?? 0;
		$clientId = $_POST['user_Id'] ?? 0;
		
		$mile = milestone::findById($milestone_id);
		if ($mile) {
			$mile->status = 1;
			$mile->releaseDate = date("Y-m-d");
			$saveMile = $mile->save();
			
			if ($saveMile) {
				// Send payment confirmation emails and notifications
				require_once("../includes/email_helper.php");
				require_once("../includes/invoice_item.php");
				require_once("../includes/notification_helper.php");
				$adminSettings = settings::findById(1);
				$emailHelper = new EmailHelper($adminSettings);
				
				// Get project and client info
				$project = projects::findByProjectId($projectId);
				$clientUser = user::findById($clientId);
				
				// Calculate invoice total for display
				$subtotal = 0;
				$invoiceItems = InvoiceItem::findByMilestoneId($milestone_id);
				if ($invoiceItems && count($invoiceItems) > 0) {
					foreach ($invoiceItems as $item) {
						$qty = floatval($item->quantity ?? 1);
						$rate = floatval($item->rate ?? 0);
						$subtotal += $qty * $rate;
					}
				} else {
					$subtotal = floatval($mile->budget ?? 0);
				}
				
				$discount = isset($mile->discount) ? floatval($mile->discount) : 0;
				$discount_type = isset($mile->discount_type) ? $mile->discount_type : 'percentage';
				$discount_amount = 0;
				if ($discount > 0 && $subtotal > 0) {
					if ($discount_type === 'percentage') {
						$discount_amount = ($subtotal * $discount) / 100;
					} else {
						$discount_amount = $discount;
					}
				}
				
				$amount_after_discount = $subtotal - $discount_amount;
				$sales_tax = isset($mile->sales_tax) ? floatval($mile->sales_tax) : 0;
				$sales_tax_type = isset($mile->sales_tax_type) ? $mile->sales_tax_type : 'percentage';
				$tax_amount = 0;
				if ($sales_tax > 0 && $amount_after_discount > 0) {
					if ($sales_tax_type === 'percentage') {
						$tax_amount = ($amount_after_discount * $sales_tax) / 100;
					} else {
						$tax_amount = $sales_tax;
					}
				}
				
				$total_amount = max(0, $amount_after_discount + $tax_amount);
				
				// Get currency symbol
				if (!function_exists('getCurrencySymbolForEmail')) {
					function getCurrencySymbolForEmail($currencyCode) {
						if (empty($currencyCode)) {
							return '$';
						}
						$cleanCurrencyCode = explode(',', $currencyCode)[0];
						$settings = settings::findById(1);
						if (!$settings) {
							return '$';
						}
						$multipleCurrencies = $settings->getMultipleCurrencies();
						foreach ($multipleCurrencies as $currency) {
							$parts = explode(',', $currency);
							if (count($parts) >= 2) {
								$code = trim($parts[0]);
								if ($code === $cleanCurrencyCode) {
									return trim($parts[1]);
								}
							}
						}
						return '$';
					}
				}
				
				$currency_symbol = getCurrencySymbolForEmail($mile->currency);
				$formatted_amount = $currency_symbol . number_format($total_amount, 2);
				
				// Send bell notification
				$invoice_number = $mile->p_id . $mile->id;
				$notifier_id = $clientUser ? $clientUser->id : 0;
				NotificationHelper::invoicePaid($mile->id, $invoice_number, $notifier_id, $mile->p_id);
				
				// Send email to client
				if ($clientUser && filter_var($clientUser->email, FILTER_VALIDATE_EMAIL) && !empty($adminSettings->invoice_paid_email)) {
					$variablesArr = array(
						'{USER_NAME}'             => htmlspecialchars($clientUser->firstName ?? '', ENT_QUOTES, 'UTF-8'),
						'{INVOICE_TITLE}'         => htmlspecialchars($mile->title ?? '', ENT_QUOTES, 'UTF-8'),
						'{INVOICE_AMOUNT}'        => htmlspecialchars($formatted_amount, ENT_QUOTES, 'UTF-8'),
						'{INVOICE_CURRENCY_SYMBOL}' => htmlspecialchars($currency_symbol, ENT_QUOTES, 'UTF-8'),
						'{INVOICE_DUE_DATE}'     => htmlspecialchars($mile->deadline ? date('F d, Y', strtotime($mile->deadline)) : 'N/A', ENT_QUOTES, 'UTF-8'),
						'{PROJECT_NAME}'          => htmlspecialchars($project->project_title ?? 'N/A', ENT_QUOTES, 'UTF-8'),
						'{INVOICE_STATUS}'       => 'Paid',
						'{DASHBOARD_URL}'        => $url,
						'{SIGNATURE}'            => htmlspecialchars($adminSettings->company_name ?? '', ENT_QUOTES, 'UTF-8')
					);
					$templateHTML = $adminSettings->invoice_paid_email;
					$subject = 'Payment Confirmation - Invoice Paid';
					$emailHelper->sendTemplateEmail($clientUser->email, $subject, $templateHTML, $variablesArr);
				}
				
				// Get invoice creator
				$invoiceCreator = null;
				if (!empty($mile->created_by)) {
					$invoiceCreator = user::findById((int)$mile->created_by);
				}
				
				// Send email to invoice creator (if exists and different from superadmin)
				if ($invoiceCreator && $invoiceCreator->id != 1 && !empty($adminSettings->invoice_paid_email_admin) && filter_var($invoiceCreator->email, FILTER_VALIDATE_EMAIL)) {
					$variablesArr = array(
						'{USER_NAME}'             => htmlspecialchars($invoiceCreator->firstName ?? 'User', ENT_QUOTES, 'UTF-8'),
						'{INVOICE_TITLE}'         => htmlspecialchars($mile->title ?? '', ENT_QUOTES, 'UTF-8'),
						'{INVOICE_AMOUNT}'        => htmlspecialchars($formatted_amount, ENT_QUOTES, 'UTF-8'),
						'{INVOICE_CURRENCY_SYMBOL}' => htmlspecialchars($currency_symbol, ENT_QUOTES, 'UTF-8'),
						'{INVOICE_DUE_DATE}'      => htmlspecialchars($mile->deadline ? date('F d, Y', strtotime($mile->deadline)) : 'N/A', ENT_QUOTES, 'UTF-8'),
						'{PROJECT_NAME}'          => htmlspecialchars($project->project_title ?? 'N/A', ENT_QUOTES, 'UTF-8'),
						'{INVOICE_STATUS}'        => 'Paid',
						'{CLIENT_NAME}'           => htmlspecialchars($clientUser->firstName ?? 'N/A', ENT_QUOTES, 'UTF-8'),
						'{CLIENT_EMAIL}'          => htmlspecialchars($clientUser->email ?? 'N/A', ENT_QUOTES, 'UTF-8'),
						'{DASHBOARD_URL}'         => $url,
						'{SIGNATURE}'             => htmlspecialchars($adminSettings->company_name ?? '', ENT_QUOTES, 'UTF-8')
					);
					$templateHTML = $adminSettings->invoice_paid_email_admin;
					$subject = 'Payment Received - Invoice #' . ($mile->p_id . $mile->id) . ' Paid';
					$emailHelper->sendTemplateEmail($invoiceCreator->email, $subject, $templateHTML, $variablesArr);
				}
				
				// Send email to superadmin (user id = 1)
				if (!empty($adminSettings->invoice_paid_email_admin)) {
					$superAdmin = user::findById(1);
					if ($superAdmin && filter_var($superAdmin->email, FILTER_VALIDATE_EMAIL)) {
						$variablesArr = array(
							'{USER_NAME}'             => htmlspecialchars($superAdmin->firstName ?? 'Admin', ENT_QUOTES, 'UTF-8'),
							'{INVOICE_TITLE}'         => htmlspecialchars($mile->title ?? '', ENT_QUOTES, 'UTF-8'),
							'{INVOICE_AMOUNT}'        => htmlspecialchars($formatted_amount, ENT_QUOTES, 'UTF-8'),
							'{INVOICE_CURRENCY_SYMBOL}' => htmlspecialchars($currency_symbol, ENT_QUOTES, 'UTF-8'),
							'{INVOICE_DUE_DATE}'      => htmlspecialchars($mile->deadline ? date('F d, Y', strtotime($mile->deadline)) : 'N/A', ENT_QUOTES, 'UTF-8'),
							'{PROJECT_NAME}'          => htmlspecialchars($project->project_title ?? 'N/A', ENT_QUOTES, 'UTF-8'),
							'{INVOICE_STATUS}'        => 'Paid',
							'{CLIENT_NAME}'           => htmlspecialchars($clientUser->firstName ?? 'N/A', ENT_QUOTES, 'UTF-8'),
							'{CLIENT_EMAIL}'          => htmlspecialchars($clientUser->email ?? 'N/A', ENT_QUOTES, 'UTF-8'),
							'{DASHBOARD_URL}'         => $url,
							'{SIGNATURE}'             => htmlspecialchars($adminSettings->company_name ?? '', ENT_QUOTES, 'UTF-8')
						);
						$templateHTML = $adminSettings->invoice_paid_email_admin;
						$subject = 'Payment Received - Invoice #' . ($mile->p_id . $mile->id) . ' Paid';
						$emailHelper->sendTemplateEmail($superAdmin->email, $subject, $templateHTML, $variablesArr);
					}
				}
				
				header('Location:payments?projectId='.$projectId.'&status=success&clientId='.$clientId.'');
			}
		}
	}
			} catch (Twocheckout_Error $e) {
				$e->getMessage();
			}
?>
	<div class="page-container">
		<div class="container-fluid">
			<div class="row row-eq-height">
				<?php  include("../templates/sidebar.php"); ?>
					<div class="page-content">
						<?php include('../templates/top-header.php'); ?>
							<div class="row">
								<div class="col-md-12 margin-top-10 clients">
									<h2><?php echo $lang['Manage Project']; ?> </h2>
									<div class="clearfix"></div>
									<?php if(isset($message) && (!empty($message))){echo $message;} ?>
								</div>
							</div>
						</div>
				  </div>
		     </div>
	   </div>
	<?php
?>
<?php  include("../templates/payment-footer.php");