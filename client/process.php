<?php
/*
 ================================================================================
   Task Session – Project Management System
   Purpose    : Stripe payment API Success Template
 ================================================================================
 */
ob_start();
require_once("../includes/lib-initialize.php");
// Don't include header.php for payment processing - it's not needed and causes variable issues

// Include Stripe SDK FIRST
require_once(__DIR__ . '/../payment-api/stripe-php/init.php');

if (!($session->isLoggedIn())) {
    redirectTo($url . "index.php");
}
if ((int) $_SESSION['accountStatus'] === 1) {
    redirectTo($url . "admin/edit");
}
if ((int) $_SESSION['accountStatus'] === 3) {
    redirectTo($url . "staff/edit");
}

$id = $session->userId;
$user = User::findById((int)$id);
$username = $user->firstName;
$email = $user->email;

// Load admin's Stripe keys from settings table (admin user ID = 1)
$adminSettings = settings::findById(1);
$stripe_sk_encrypted = $adminSettings->stripe_sk;
$stripe_pk_encrypted = $adminSettings->stripe_pk;
$stripe_sk = decryptString($stripe_sk_encrypted);
$stripe_pk = decryptString($stripe_pk_encrypted);

require_once(__DIR__ . '/../includes/stripe_wallet_payment.php');
require_once(__DIR__ . '/../includes/client-stripe-payment-success.php');
require_once(__DIR__ . '/../includes/client_payment_modal_helper.php');

// Use the decrypted key for Stripe
\Stripe\Stripe::setApiKey($stripe_sk);

function client_process_redirect(string $result, int $projectId, int $milestoneId, string $message = '', ?string $returnTo = null): void
{
    header('Location: ' . client_payment_redirect_url($result, $projectId, $milestoneId, $message, $returnTo));
    exit;
}

function client_process_respond_stripe($isWalletRequest, $success, $redirectUrl, $message = '')
{
    if ($isWalletRequest) {
        stripe_wallet_json_response($success, $redirectUrl, $message);
    }
    header('Location: ' . $redirectUrl);
    exit;
}

// Process Stripe wallet payment (Apple Pay / Google Pay)
if (!empty($_POST['stripePaymentMethodId']) && stripe_is_wallet_payment_request()) {
    $isWalletRequest = stripe_is_wallet_payment_request();
    $paymentMethodId = $_POST['stripePaymentMethodId'];
    $milestone_id = (int) ($_POST['milestone_id'] ?? 0);
    $project_id = (int) ($_POST['proj_Id'] ?? 0);
    $client_id = (int) ($_POST['user_Id'] ?? 0);
    $invoice_number = $_POST['invoice_number'] ?? '';
    $paymentReturnTo = isset($_POST['return_to']) ? (string) $_POST['return_to'] : null;
    $successUrl = client_payment_redirect_url('success', $project_id, $milestone_id, 'Payment successful.', $paymentReturnTo);
    $failUrl = client_payment_redirect_url('fail', $project_id, $milestone_id, 'Payment failed.', $paymentReturnTo);

    try {
        $mile = milestone::findById($milestone_id);
        if (!$mile) {
            $notFound = client_payment_redirect_url('fail', $project_id, $milestone_id, 'Invoice not found. Payment failed.', $paymentReturnTo);
            client_process_respond_stripe($isWalletRequest, false, $notFound, 'Invoice not found.');
        }

        $currency = stripe_currency_from_invoice_field($mile->currency);
        $amount = stripe_invoice_total_amount($mile);
        $invoice_number = $mile->p_id . $mile->id;

        if ($currency === 'pkr' && $amount < 1400) {
            $tooSmall = client_payment_redirect_url('fail', $project_id, $milestone_id, 'Payment amount is too small. The minimum amount for international payments is approximately PKR 1,400. Please contact the administrator for assistance.', $paymentReturnTo);
            client_process_respond_stripe($isWalletRequest, false, $tooSmall, 'Payment amount is too small.');
        }

        $invoice_description = !empty($mile->title) ? $mile->title : 'Milestone Invoice Payment';
        $itemPrice = stripe_amount_to_cents($amount, $currency);

        if ($itemPrice < 50 && $currency !== 'pkr' && $currency !== 'jpy') {
            $tooSmall = client_payment_redirect_url('fail', $project_id, $milestone_id, 'Payment amount is too small.', $paymentReturnTo);
            client_process_respond_stripe($isWalletRequest, false, $tooSmall, 'Payment amount is too small.');
        }

        $walletResult = stripe_confirm_wallet_payment(
            $stripe_sk,
            $paymentMethodId,
            $itemPrice,
            $currency,
            $invoice_description,
            ['order_id' => $invoice_number]
        );

        if (!empty($walletResult['success'])) {
            client_finalize_stripe_payment($mile, $project_id, $milestone_id, $client_id, $adminSettings, $url);
            client_process_respond_stripe($isWalletRequest, true, $successUrl);
        }

        $errorMessage = $walletResult['error'] ?? 'Payment failed.';
        $failWithMsg = client_payment_redirect_url('fail', $project_id, $milestone_id, $errorMessage, $paymentReturnTo);
        client_process_respond_stripe($isWalletRequest, false, $failWithMsg, $errorMessage);
    } catch (Exception $e) {
        error_log('Wallet payment error in client/process.php: ' . $e->getMessage());
        $failWithMsg = client_payment_redirect_url('fail', $project_id, $milestone_id, $e->getMessage(), $paymentReturnTo);
        client_process_respond_stripe($isWalletRequest, false, $failWithMsg, $e->getMessage());
    }
}

require_once(__DIR__ . '/../includes/client_payment_modal_helper.php');
require_once(__DIR__ . '/../includes/client_payment_gateway_registry.php');

// Bulk payment (gateway dispatcher)
if (!empty($_POST['bulk_pay'])) {
    client_payment_dispatch($_POST, $session, $adminSettings, $url, $lang);
}

require_once(__DIR__ . '/../includes/stripe_saved_payment.php');

// Saved card or new PaymentMethod (client portal)
if (!empty($_POST['stripePaymentMethodId']) && empty($_POST['stripeToken'])) {
    $paymentMethodId = $_POST['stripePaymentMethodId'];
    $milestone_id = (int) ($_POST['milestone_id'] ?? 0);
    $project_id = (int) ($_POST['proj_Id'] ?? 0);
    $client_id = (int) ($_POST['user_Id'] ?? 0);
    $useSaved = !empty($_POST['use_saved']);
    $saveCard = !empty($_POST['save_card']);
    $paymentReturnTo = isset($_POST['return_to']) ? (string) $_POST['return_to'] : null;

    $mile = milestone::findById($milestone_id);
    if (!$mile) {
        client_process_redirect('fail', $project_id, $milestone_id, 'Invoice not found.', $paymentReturnTo);
    }
    if ((int) $client_id !== (int) $session->userId) {
        client_process_redirect('fail', $project_id, $milestone_id, 'Unauthorized.', $paymentReturnTo);
    }

    $chargeResult = stripe_client_charge_milestone($mile, $client_id, $paymentMethodId, $adminSettings, $saveCard, $useSaved);
    if (!empty($chargeResult['success'])) {
        client_finalize_stripe_payment($mile, $project_id, $milestone_id, $client_id, $adminSettings, $url);
        client_process_redirect('success', $project_id, $milestone_id, 'Payment successful.', $paymentReturnTo);
    }
    client_process_redirect('fail', $project_id, $milestone_id, $chargeResult['error'] ?? 'Payment failed.', $paymentReturnTo);
}

// Check if Stripe token is posted
if (!empty($_POST['stripeToken'])) {
    // Get form data
    $stripeToken = $_POST['stripeToken'];
    $custName = $_POST['custName'];
    $custEmail = $_POST['custEmail'];
    $milestone_id = (int) $_POST['milestone_id'];
    $project_id = (int) $_POST['proj_Id'];
    $client_id = (int) $_POST['user_Id'];
    $paymentReturnTo = isset($_POST['return_to']) ? (string) $_POST['return_to'] : null;

    try {
        $mile = milestone::findById($milestone_id);
        if (!$mile) {
            client_process_redirect('fail', $project_id, $milestone_id, 'Invoice not found. Payment failed.', $paymentReturnTo);
        }

        $currency = stripe_currency_from_invoice_field($mile->currency);
        $amount = stripe_invoice_total_amount($mile);
        $invoice_number = $mile->p_id . $mile->id;

        // Check if amount is too small for international payments
        if ($currency === 'pkr' && $amount < 1400) {
            client_process_redirect('fail', $project_id, $milestone_id, 'Payment amount is too small. The minimum amount for international payments is approximately PKR 1,400. Please contact the administrator for assistance.', $paymentReturnTo);
        }
        
        // Check minimum amount for other currencies (Stripe minimum is 50 cents = 0.50)
        $minAmounts = [
            'usd' => 0.50,
            'eur' => 0.50,
            'gbp' => 0.30,
            'cad' => 0.50,
            'inr' => 0.50,
            'jpy' => 50,
            'pkr' => 1400
        ];
        
        $minAmount = $minAmounts[$currency] ?? 0.50;
        if ($amount < $minAmount) {
            $currencyUpper = strtoupper($currency);
            client_process_redirect('fail', $project_id, $milestone_id, "Payment amount is too small. The minimum amount for {$currencyUpper} payments is {$minAmount} {$currencyUpper}. Please contact the administrator for assistance.", $paymentReturnTo);
        }
        
        // Get milestone/invoice to use its title for description
        $invoice_description = !empty($mile->title) ? $mile->title : 'Milestone Invoice Payment';
        
        // Create customer
        $customer = \Stripe\Customer::create([
            'email' => $custEmail,
            'source' => $stripeToken
        ]);

        // Charge amount in Stripe smallest currency unit
        $itemPrice = stripe_amount_to_cents($amount, $currency);
        
        // Double check minimum (Stripe minimum units vary by currency)
        if ($itemPrice < 50 && !in_array($currency, ['pkr', 'jpy'], true)) {
            client_process_redirect('fail', $project_id, $milestone_id, 'Payment amount is too small. The minimum amount is $0.50 USD (or equivalent in other currencies). Please contact the administrator for assistance.', $paymentReturnTo);
        }

        $charge = \Stripe\Charge::create([
            'customer' => $customer->id,
            'amount' => $itemPrice,
            'currency' => $currency,
            'description' => $invoice_description,
            'metadata' => ['order_id' => $invoice_number]
        ]);

        $paymentResponse = $charge->jsonSerialize();

        // Check for success
        if (
            $paymentResponse['amount_refunded'] == 0 &&
            empty($paymentResponse['failure_code']) &&
            $paymentResponse['paid'] == 1 &&
            $paymentResponse['captured'] == 1 &&
            $paymentResponse['status'] == 'succeeded'
        ) {
            client_finalize_stripe_payment($mile, $project_id, $milestone_id, $client_id, $adminSettings, $url);
            client_process_redirect('success', $project_id, $milestone_id, 'Payment successful.', $paymentReturnTo);
        } else {
            client_process_redirect('fail', $project_id, $milestone_id, 'Payment failed.', $paymentReturnTo);
        }
    } catch (\Stripe\Exception\CardException $e) {
        // Card was declined
        $errorMessage = $e->getMessage();
        if (strpos($errorMessage, 'Your card was declined') !== false) {
            $errorMessage = "Your card was declined. Please check your card details or try a different payment method.";
        } else {
            $errorMessage = "Payment failed: " . $errorMessage;
        }
        client_process_redirect('fail', $project_id, $milestone_id, $errorMessage, $paymentReturnTo);
    } catch (\Stripe\Exception\ApiErrorException $e) {
        // Stripe API error
        $errorMessage = $e->getMessage();
        
        // Handle specific error cases
        if (strpos($errorMessage, 'Amount must convert to at least 50 cents') !== false || 
            strpos($errorMessage, 'Amount must be at least') !== false) {
            $errorMessage = "Payment amount is too small. The minimum amount is $0.50 USD (or equivalent in other currencies). Please contact the administrator for assistance.";
        } elseif (strpos($errorMessage, 'Invalid currency') !== false) {
            $errorMessage = "Currency not supported for international payments. Please contact the administrator.";
        } elseif (strpos($errorMessage, 'Invalid integer') !== false) {
            $errorMessage = "Invalid payment amount. Please contact the administrator.";
        } else {
            $errorMessage = "Payment failed: " . $errorMessage;
        }
        
        client_process_redirect('fail', $project_id, $milestone_id, $errorMessage, $paymentReturnTo);
    } catch (Exception $e) {
        error_log("Payment error in client/process.php: " . $e->getMessage());
        client_process_redirect('fail', $project_id, $milestone_id, 'An error occurred while processing your payment. Please try again or contact support.', $paymentReturnTo);
    }
} else {
    $project_id = (int) ($_POST['proj_Id'] ?? 0);
    $milestone_id = (int) ($_POST['milestone_id'] ?? 0);
    $paymentReturnTo = isset($_POST['return_to']) ? (string) $_POST['return_to'] : null;
    client_process_redirect('fail', $project_id, $milestone_id, 'Invalid payment submission.', $paymentReturnTo);
}
?>
