<?php
/*
 * Payment Processing for Public Payment Links
 * Handles payment processing from pay.php
 */
ob_start();
require_once(__DIR__ . "/../lib-initialize.php");

// Include Stripe SDK
require_once(__DIR__ . '/../../payment-api/stripe-php/init.php');

// Get payment link token
$payment_token = $_POST['payment_token'] ?? $_POST['token'] ?? '';

if (empty($payment_token)) {
    header("Location: " . $url . "pay.php?error=invalid_token");
    exit;
}

// Validate payment link token
global $connect;
$sql = "SELECT * FROM invoice_payment_links WHERE token = ? AND used = 0 LIMIT 1";
$stmt = $connect->prepare($sql);
$stmt->bind_param("s", $payment_token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header("Location: " . $url . "pay/" . urlencode($payment_token) . "?error=expired");
    exit;
}

$link_data = $result->fetch_assoc();

// Check if link is expired
if ($link_data['expires_at'] && strtotime($link_data['expires_at']) < time()) {
    header("Location: " . $url . "pay/" . urlencode($payment_token) . "?error=expired");
    exit;
}

// Get invoice
$invoice = milestone::findById($link_data['invoice_id']);
if (!$invoice || $invoice->status == 1) {
    header("Location: " . $url . "pay/" . urlencode($payment_token) . "?error=expired");
    exit;
}

// Get project and client info
$project = null;
$client = null;

// Check if invoice has a project
if ($invoice->p_id) {
    $project = projects::findByProjectId($invoice->p_id);
    if ($project && isset($project->c_id)) {
        $client = user::findById($project->c_id);
    }
}

// If no client from project, check if invoice has direct client (c_id) - for client invoices
if (!$client && $invoice->c_id) {
    $client = user::findById($invoice->c_id);
}

// Load admin's Stripe keys from settings table
$adminSettings = settings::findById(1);
$stripe_sk_encrypted = $adminSettings->stripe_sk ?? '';
$stripe_pk_encrypted = $adminSettings->stripe_pk ?? '';
$stripe_sk = !empty($stripe_sk_encrypted) ? decryptString($stripe_sk_encrypted) : '';
$stripe_pk = !empty($stripe_pk_encrypted) ? decryptString($stripe_pk_encrypted) : '';

require_once(__DIR__ . '/../stripe_wallet_payment.php');

// Currency and amount always from invoice (never trust POST alone)
$currency = stripe_currency_from_invoice_field($invoice->currency);

// Calculate invoice total (matching pay.php logic)
require_once(__DIR__ . '/../invoice_item.php');
$subtotal = 0;
$invoiceItems = InvoiceItem::findByMilestoneId($invoice->id);
if ($invoiceItems && count($invoiceItems) > 0) {
    foreach ($invoiceItems as $item) {
        $qty = floatval($item->quantity ?? 1);
        $rate = floatval($item->rate ?? 0);
        $subtotal += $qty * $rate;
    }
} else {
    $subtotal = floatval($invoice->budget ?? 0);
}

// Calculate discount
$discount = isset($invoice->discount) ? floatval($invoice->discount) : 0;
$discount_type = isset($invoice->discount_type) ? $invoice->discount_type : 'percentage';

$discount_amount = 0;
if ($discount > 0 && $subtotal > 0) {
    if ($discount_type === 'percentage') {
        $discount_amount = ($subtotal * $discount) / 100;
    } else {
        $discount_amount = $discount;
    }
}

// Calculate amount after discount
$amount_after_discount = $subtotal - $discount_amount;

// Calculate tax on amount after discount
$sales_tax = isset($invoice->sales_tax) ? floatval($invoice->sales_tax) : 0;
$sales_tax_type = isset($invoice->sales_tax_type) ? $invoice->sales_tax_type : 'percentage';

$tax_amount = 0;
if ($sales_tax > 0 && $amount_after_discount > 0) {
    if ($sales_tax_type === 'percentage') {
        $tax_amount = ($amount_after_discount * $sales_tax) / 100;
    } else {
        $tax_amount = $sales_tax;
    }
}

// Total = subtotal - discount + tax
$total_amount = max(0, $amount_after_discount + $tax_amount);

require_once(__DIR__ . '/pay-link-stripe-success.php');
require_once(__DIR__ . '/../stripe_saved_payment.php');

function pay_process_respond_stripe($isWalletRequest, $success, $redirectUrl, $message = '')
{
    if ($isWalletRequest) {
        stripe_wallet_json_response($success, $redirectUrl, $message);
    }
    header('Location: ' . $redirectUrl);
    exit;
}

// Process Stripe wallet payment (Apple Pay / Google Pay) — not card Element checkout
if (!empty($_POST['stripePaymentMethodId']) && stripe_is_wallet_payment_request()) {
    $isWalletRequest = true;
    $paymentMethodId = $_POST['stripePaymentMethodId'];
    $amount = $total_amount;
    $invoice_number = $invoice->p_id . $invoice->id;
    $invoice_description = !empty($invoice->title) ? $invoice->title : 'Invoice Payment';
    $itemPrice = stripe_amount_to_cents($amount, $currency);
    $successUrl = $url . 'pay/' . urlencode($payment_token) . '?status=success';
    $failUrl = $url . 'pay/' . urlencode($payment_token) . '?error=payment_failed';
    $custEmail = $_POST['custEmail'] ?? ($client ? $client->email : '');
    $clientUserId = (int) stripe_resolve_pay_link_client_user_id($invoice, $custEmail);
    $saveCard = stripe_pay_link_should_save_card($invoice, !empty($_POST['save_card']));
    $stripeCustomerId = null;
    $setupFuture = null;
    if ($clientUserId > 0) {
        $stripeCustomerId = stripe_get_or_create_customer_for_user($clientUserId, $custEmail, $stripe_sk);
        if ($saveCard) {
            $setupFuture = 'off_session';
        }
    }

    try {
        if ($currency === 'pkr' && $amount < 1400) {
            $tooSmallUrl = $url . 'pay/' . urlencode($payment_token) . '?error=amount_too_small';
            pay_process_respond_stripe($isWalletRequest, false, $tooSmallUrl, 'Payment amount is too small.');
        }

        $walletResult = stripe_confirm_wallet_payment(
            $stripe_sk,
            $paymentMethodId,
            $itemPrice,
            $currency,
            $invoice_description,
            ['order_id' => $invoice_number, 'payment_token' => $payment_token],
            $stripeCustomerId,
            $setupFuture
        );

        if (!empty($walletResult['success'])) {
            stripe_pay_link_persist_payment_method($invoice, $paymentMethodId, $adminSettings, $saveCard);
            pay_link_finalize_stripe_payment(
                $invoice,
                $payment_token,
                $walletResult['payment_method_display'] ?? 'Wallet',
                $adminSettings,
                $url,
                $connect
            );
            pay_process_respond_stripe($isWalletRequest, true, $successUrl);
        }

        $errorMessage = $walletResult['error'] ?? 'Payment failed.';
        $failWithMsg = $url . 'pay/' . urlencode($payment_token) . '?error=' . urlencode($errorMessage);
        pay_process_respond_stripe($isWalletRequest, false, $failWithMsg, $errorMessage);
    } catch (Exception $e) {
        error_log('Wallet payment error in pay-process.php: ' . $e->getMessage());
        $failWithMsg = $url . 'pay/' . urlencode($payment_token) . '?error=' . urlencode($e->getMessage());
        pay_process_respond_stripe($isWalletRequest, false, $failWithMsg, $e->getMessage());
    }
}

// Card payment via PaymentMethod (pay.php Stripe Elements)
if (!empty($_POST['stripePaymentMethodId']) && !stripe_is_wallet_payment_request()) {
    $isWalletRequest = false;
    $paymentMethodId = $_POST['stripePaymentMethodId'];
    $custEmail = $_POST['custEmail'] ?? ($client ? $client->email : '');
    $clientUserId = (int) stripe_resolve_pay_link_client_user_id($invoice, $custEmail);
    $saveCard = stripe_pay_link_should_save_card($invoice, !empty($_POST['save_card']));
    $successUrl = $url . 'pay/' . urlencode($payment_token) . '?status=success';
    $failUrl = $url . 'pay/' . urlencode($payment_token) . '?error=payment_failed';

    try {
        if ($currency === 'pkr' && $total_amount < 1400) {
            header('Location: ' . $url . 'pay/' . urlencode($payment_token) . '?error=amount_too_small');
            exit;
        }

        if ($clientUserId <= 0) {
            error_log('pay-process: no CRM client for invoice ' . (int) $invoice->id . ' email ' . $custEmail);
            header('Location: ' . $failUrl);
            exit;
        }

        $customerId = stripe_get_or_create_customer_for_user($clientUserId, $custEmail, $stripe_sk);
        $itemPrice = stripe_amount_to_cents($total_amount, $currency);
        $invoice_description = !empty($invoice->title) ? $invoice->title : 'Invoice Payment';
        $invoice_number = $invoice->p_id . $invoice->id;

        $setupFuture = $saveCard ? 'off_session' : null;
        $chargeResult = stripe_charge_with_payment_method(
            $stripe_sk,
            $customerId,
            $paymentMethodId,
            $itemPrice,
            $currency,
            $invoice_description,
            ['order_id' => $invoice_number, 'payment_token' => $payment_token],
            false,
            $setupFuture
        );

        if (!empty($chargeResult['success'])) {
            stripe_pay_link_persist_payment_method($invoice, $paymentMethodId, $adminSettings, $saveCard);
            $payment_method_display = $chargeResult['payment_method_display'] ?? 'Card';
            pay_link_finalize_stripe_payment($invoice, $payment_token, $payment_method_display, $adminSettings, $url, $connect);
            header('Location: ' . $successUrl);
            exit;
        }

        $errorMessage = $chargeResult['error'] ?? 'Payment failed.';
        header('Location: ' . $url . 'pay/' . urlencode($payment_token) . '?error=' . urlencode($errorMessage));
        exit;
    } catch (Exception $e) {
        error_log('PaymentMethod pay link error: ' . $e->getMessage());
        header('Location: ' . $url . 'pay/' . urlencode($payment_token) . '?error=' . urlencode($e->getMessage()));
        exit;
    }
}

// Process Stripe payment (legacy token — still supported)
if (!empty($_POST['stripeToken'])) {
    \Stripe\Stripe::setApiKey($stripe_sk);
    
    $stripeToken = $_POST['stripeToken'];
    $custName = $_POST['custName'] ?? ($client ? $client->firstName : '');
    $custEmail = $_POST['custEmail'] ?? ($client ? $client->email : '');
    $amount = $total_amount;
    $invoice_number = $invoice->p_id . $invoice->id;
    
    try {
        // Check if amount is too small for international payments
        if ($currency === 'pkr' && $amount < 1400) {
            header("Location: " . $url . "pay/" . urlencode($payment_token) . "?error=amount_too_small");
            exit;
        }
        
        // Charge using CRM Stripe customer when invoice maps to a client (enables saving card for auto-charge)
        $clientUserId = (int) stripe_resolve_pay_link_client_user_id($invoice, $custEmail);
        $saveCard = stripe_pay_link_should_save_card($invoice, !empty($_POST['save_card']));
        if ($clientUserId > 0) {
            $customerId = stripe_get_or_create_customer_for_user($clientUserId, $custEmail, $stripe_sk);
            try {
                \Stripe\Customer::createSource($customerId, ['source' => $stripeToken]);
            } catch (Exception $e) {
                error_log('pay-process attach source: ' . $e->getMessage());
            }
        } else {
            $customer = \Stripe\Customer::create([
                'email' => $custEmail,
                'source' => $stripeToken
            ]);
            $customerId = $customer->id;
        }
        
        // Charge amount (in cents) - ensure it's an integer
        // Round first to handle floating point precision, then cast to int
        $itemPrice = stripe_amount_to_cents($amount, $currency);
        
        // Use invoice title as description, fallback to "Invoice Payment" if empty
        $invoice_description = !empty($invoice->title) ? $invoice->title : 'Invoice Payment';
        
        $charge = \Stripe\Charge::create([
            'customer' => $customerId,
            'amount' => $itemPrice,
            'currency' => $currency,
            'description' => $invoice_description,
            'metadata' => ['order_id' => $invoice_number, 'payment_token' => $payment_token],
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
            $card_last4 = '';
            $card_brand = 'Card';
            if (isset($paymentResponse['payment_method_details']['card']['last4'])) {
                $card_last4 = $paymentResponse['payment_method_details']['card']['last4'];
            }
            if (isset($paymentResponse['payment_method_details']['card']['brand'])) {
                $card_brand = ucfirst($paymentResponse['payment_method_details']['card']['brand']);
            }
            $payment_method_display = $card_brand . ($card_last4 ? ' .... ' . $card_last4 : '');
            if (!empty($paymentResponse['payment_method'])) {
                stripe_pay_link_persist_payment_method($invoice, $paymentResponse['payment_method'], $adminSettings, $saveCard);
            }
            pay_link_finalize_stripe_payment($invoice, $payment_token, $payment_method_display, $adminSettings, $url, $connect);

            // Redirect to success page
            header("Location: " . $url . "pay/" . urlencode($payment_token) . "?status=success");
            exit;
        } else {
            header("Location: " . $url . "pay/" . urlencode($payment_token) . "?error=payment_failed");
            exit;
        }
    } catch (\Stripe\Exception\CardException $e) {
        error_log("Stripe CardException in pay-process.php: " . $e->getMessage());
        header("Location: " . $url . "pay/" . urlencode($payment_token) . "?error=" . urlencode($e->getMessage()));
        exit;
    } catch (\Stripe\Exception\ApiErrorException $e) {
        error_log("Stripe ApiErrorException in pay-process.php: " . $e->getMessage());
        $errorMessage = $e->getMessage();
        
        // Handle specific error cases
        if (strpos($errorMessage, 'Amount must convert to at least 50 cents') !== false) {
            $errorMessage = "Payment amount is too small. The minimum amount for international payments is approximately PKR 1,400 (equivalent to $0.50 USD). Please contact the administrator for assistance.";
        } elseif (strpos($errorMessage, 'Invalid currency') !== false) {
            $errorMessage = "Currency not supported for international payments. Please contact the administrator.";
        } else {
            $errorMessage = "Payment failed: " . $errorMessage;
        }
        
        header("Location: " . $url . "pay/" . urlencode($payment_token) . "?error=" . urlencode($errorMessage));
        exit;
    } catch (Exception $e) {
        error_log("Exception in pay-process.php: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
        header("Location: " . $url . "pay/" . urlencode($payment_token) . "?error=" . urlencode("Payment error: " . $e->getMessage()));
        exit;
    }
}

// Process 2Checkout payment
if (!empty($_POST['token']) && !empty($_POST['payment_token'])) {
    require_once(__DIR__ . '/../../payment-api/lib/Twocheckout.php');
    
    $checkout_id = $adminSettings->checkout_id ?? '';
    $checkout_pk = $adminSettings->checkout_pk ?? '';
    
    Twocheckout::privateKey($checkout_pk);
    Twocheckout::sellerId($checkout_id);
    
    try {
        $charge = Twocheckout_Charge::auth([
            "merchantOrderId" => $invoice->id,
            "token" => $_POST['token'],
            "currency" => $currency,
            "total" => $total_amount,
            "billingAddr" => [
                "name" => $_POST['custName'] ?? ($client ? $client->firstName : ''),
                "addrLine1" => $client->address ?? '',
                "city" => $client->city ?? '',
                "state" => $client->state ?? '',
                "zipCode" => $client->zip ?? '',
                "country" => $client->country ?? '',
                "email" => $_POST['custEmail'] ?? ($client ? $client->email : ''),
                "phoneNumber" => $client->phone ?? ''
            ],
        ], 'array');
        
        if ($charge['response']['responseCode'] == 'APPROVED') {
            // Update milestone status
            $invoice->status = 1;
            $invoice->releaseDate = date("Y-m-d");
            $invoice->save();
            
            // Mark payment link as used
            $updateSql = "UPDATE invoice_payment_links SET used = 1, used_at = NOW() WHERE token = ?";
            $updateStmt = $connect->prepare($updateSql);
            $updateStmt->bind_param("s", $payment_token);
            $updateStmt->execute();
            
            // Store payment method in session for thank you page
            if (!isset($_SESSION)) {
                session_start();
            }
            $payment_method_display = '2Checkout';
            if (isset($charge['response']['paymentMethod']) && isset($charge['response']['paymentMethod']['cardNumber'])) {
                $card_last4 = substr($charge['response']['paymentMethod']['cardNumber'], -4);
                $payment_method_display = 'Card .... ' . $card_last4;
            }
            $_SESSION['payment_method_' . $payment_token] = $payment_method_display;
            
            // Send payment confirmation emails
            require_once(__DIR__ . '/../email_helper.php');
            $emailHelper = new EmailHelper($adminSettings);
            
            // Get project and client info
            $project = null;
            $clientUser = null;
            
            // Check if invoice has a project
            if ($invoice->p_id) {
                $project = projects::findByProjectId($invoice->p_id);
                if ($project && isset($project->c_id)) {
                    $clientUser = user::findById($project->c_id);
                }
            }
            
            // If no client from project, check if invoice has direct client (c_id) - for client invoices
            if (!$clientUser && $invoice->c_id) {
                $clientUser = user::findById($invoice->c_id);
            }
            
            // Calculate invoice total for display
            require_once(__DIR__ . '/../invoice_item.php');
            $subtotal = 0;
            $invoiceItems = InvoiceItem::findByMilestoneId($invoice->id);
            if ($invoiceItems && count($invoiceItems) > 0) {
                foreach ($invoiceItems as $item) {
                    $qty = floatval($item->quantity ?? 1);
                    $rate = floatval($item->rate ?? 0);
                    $subtotal += $qty * $rate;
                }
            } else {
                $subtotal = floatval($invoice->budget ?? 0);
            }
            
            $discount = isset($invoice->discount) ? floatval($invoice->discount) : 0;
            $discount_type = isset($invoice->discount_type) ? $invoice->discount_type : 'percentage';
            $discount_amount = 0;
            if ($discount > 0 && $subtotal > 0) {
                if ($discount_type === 'percentage') {
                    $discount_amount = ($subtotal * $discount) / 100;
                } else {
                    $discount_amount = $discount;
                }
            }
            
            $amount_after_discount = $subtotal - $discount_amount;
            $sales_tax = isset($invoice->sales_tax) ? floatval($invoice->sales_tax) : 0;
            $sales_tax_type = isset($invoice->sales_tax_type) ? $invoice->sales_tax_type : 'percentage';
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
            
            $currency_symbol = getCurrencySymbolForEmail($invoice->currency);
            $formatted_amount = $currency_symbol . number_format($total_amount, 2);
            
            // Send bell notification
            require_once(__DIR__ . '/../notification_helper.php');
            $invoice_number = $invoice->p_id . $invoice->id;
            $notifier_id = $clientUser ? $clientUser->id : 0;
            NotificationHelper::invoicePaid($invoice->id, $invoice_number, $notifier_id, $invoice->p_id);
            
            // Send email to client
            if ($clientUser && filter_var($clientUser->email, FILTER_VALIDATE_EMAIL) && !empty($adminSettings->invoice_paid_email)) {
                $variablesArr = array(
                    '{USER_NAME}'             => htmlspecialchars($clientUser->firstName ?? '', ENT_QUOTES, 'UTF-8'),
                    '{INVOICE_TITLE}'         => htmlspecialchars($invoice->title ?? '', ENT_QUOTES, 'UTF-8'),
                    '{INVOICE_AMOUNT}'        => htmlspecialchars($formatted_amount, ENT_QUOTES, 'UTF-8'),
                    '{INVOICE_CURRENCY_SYMBOL}' => htmlspecialchars($currency_symbol, ENT_QUOTES, 'UTF-8'),
                    '{INVOICE_DUE_DATE}'     => htmlspecialchars($invoice->deadline ? date('F d, Y', strtotime($invoice->deadline)) : 'N/A', ENT_QUOTES, 'UTF-8'),
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
            if (!empty($invoice->created_by)) {
                $invoiceCreator = user::findById((int)$invoice->created_by);
            }
            
            // Send email to invoice creator (if exists and different from superadmin)
            if ($invoiceCreator && $invoiceCreator->id != 1 && !empty($adminSettings->invoice_paid_email_admin) && filter_var($invoiceCreator->email, FILTER_VALIDATE_EMAIL)) {
                $variablesArr = array(
                    '{USER_NAME}'             => htmlspecialchars($invoiceCreator->firstName ?? 'User', ENT_QUOTES, 'UTF-8'),
                    '{INVOICE_TITLE}'         => htmlspecialchars($invoice->title ?? '', ENT_QUOTES, 'UTF-8'),
                    '{INVOICE_AMOUNT}'        => htmlspecialchars($formatted_amount, ENT_QUOTES, 'UTF-8'),
                    '{INVOICE_CURRENCY_SYMBOL}' => htmlspecialchars($currency_symbol, ENT_QUOTES, 'UTF-8'),
                    '{INVOICE_DUE_DATE}'      => htmlspecialchars($invoice->deadline ? date('F d, Y', strtotime($invoice->deadline)) : 'N/A', ENT_QUOTES, 'UTF-8'),
                    '{PROJECT_NAME}'          => htmlspecialchars($project->project_title ?? 'N/A', ENT_QUOTES, 'UTF-8'),
                    '{INVOICE_STATUS}'        => 'Paid',
                    '{CLIENT_NAME}'           => htmlspecialchars($clientUser->firstName ?? 'N/A', ENT_QUOTES, 'UTF-8'),
                    '{CLIENT_EMAIL}'          => htmlspecialchars($clientUser->email ?? 'N/A', ENT_QUOTES, 'UTF-8'),
                    '{DASHBOARD_URL}'         => $url,
                    '{SIGNATURE}'             => htmlspecialchars($adminSettings->company_name ?? '', ENT_QUOTES, 'UTF-8')
                );
                $templateHTML = $adminSettings->invoice_paid_email_admin;
                $subject = 'Payment Received - Invoice #' . ($invoice->p_id . $invoice->id) . ' Paid';
                $emailHelper->sendTemplateEmail($invoiceCreator->email, $subject, $templateHTML, $variablesArr);
            }
            
            // Send email to superadmin (user id = 1)
            if (!empty($adminSettings->invoice_paid_email_admin)) {
                $superAdmin = user::findById(1);
                if ($superAdmin && filter_var($superAdmin->email, FILTER_VALIDATE_EMAIL)) {
                    $variablesArr = array(
                        '{USER_NAME}'             => htmlspecialchars($superAdmin->firstName ?? 'Admin', ENT_QUOTES, 'UTF-8'),
                        '{INVOICE_TITLE}'         => htmlspecialchars($invoice->title ?? '', ENT_QUOTES, 'UTF-8'),
                        '{INVOICE_AMOUNT}'        => htmlspecialchars($formatted_amount, ENT_QUOTES, 'UTF-8'),
                        '{INVOICE_CURRENCY_SYMBOL}' => htmlspecialchars($currency_symbol, ENT_QUOTES, 'UTF-8'),
                        '{INVOICE_DUE_DATE}'      => htmlspecialchars($invoice->deadline ? date('F d, Y', strtotime($invoice->deadline)) : 'N/A', ENT_QUOTES, 'UTF-8'),
                        '{PROJECT_NAME}'          => htmlspecialchars($project->project_title ?? 'N/A', ENT_QUOTES, 'UTF-8'),
                        '{INVOICE_STATUS}'        => 'Paid',
                        '{CLIENT_NAME}'           => htmlspecialchars($clientUser->firstName ?? 'N/A', ENT_QUOTES, 'UTF-8'),
                        '{CLIENT_EMAIL}'          => htmlspecialchars($clientUser->email ?? 'N/A', ENT_QUOTES, 'UTF-8'),
                        '{DASHBOARD_URL}'         => $url,
                        '{SIGNATURE}'             => htmlspecialchars($adminSettings->company_name ?? '', ENT_QUOTES, 'UTF-8')
                    );
                    $templateHTML = $adminSettings->invoice_paid_email_admin;
                    $subject = 'Payment Received - Invoice #' . ($invoice->p_id . $invoice->id) . ' Paid';
                    $emailHelper->sendTemplateEmail($superAdmin->email, $subject, $templateHTML, $variablesArr);
                }
            }
            
            // Redirect to success page
            header("Location: " . $url . "pay/" . urlencode($payment_token) . "?status=success");
            exit;
        } else {
            header("Location: " . $url . "pay/" . urlencode($payment_token) . "?error=payment_failed");
            exit;
        }
    } catch (Exception $e) {
        header("Location: " . $url . "pay/" . urlencode($payment_token) . "?error=payment_error");
        exit;
    }
}

// If no payment method matched, redirect back
header("Location: " . $url . "pay/" . urlencode($payment_token) . "?error=invalid_method");
exit;

