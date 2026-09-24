<?php
/*
 * PayPal Payment Return Handler for Payment Links
 * Handles PayPal return URL for payment links
 */
ob_start();
require_once(__DIR__ . "/../lib-initialize.php");

// Get custom parameter (payment token) from PayPal return
$payment_token = $_GET['custom'] ?? '';

if (empty($payment_token)) {
    global $url;
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
    global $url;
    header("Location: " . $url . "pay/" . urlencode($payment_token) . "?error=expired");
    exit;
}

$link_data = $result->fetch_assoc();

// Get invoice
$invoice = milestone::findById($link_data['invoice_id']);
if (!$invoice || $invoice->status == 1) {
    global $url;
    header("Location: " . $url . "pay/" . urlencode($payment_token) . "?error=expired");
    exit;
}

// Update invoice status to paid
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
$_SESSION['payment_method_' . $payment_token] = 'PayPal';

// Send payment confirmation emails
require_once(__DIR__ . '/../email_helper.php');
$settings = settings::findById(1);
$emailHelper = new EmailHelper($settings);

// Get project and client info
$project = projects::findByProjectId($invoice->p_id);
$clientUser = null;
if ($project && isset($project->c_id)) {
    $clientUser = user::findById($project->c_id);
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
if ($clientUser && filter_var($clientUser->email, FILTER_VALIDATE_EMAIL) && !empty($settings->invoice_paid_email)) {
    global $url, $company_name;
    $variablesArr = array(
        '{USER_NAME}'             => htmlspecialchars($clientUser->firstName ?? '', ENT_QUOTES, 'UTF-8'),
        '{INVOICE_TITLE}'         => htmlspecialchars($invoice->title ?? '', ENT_QUOTES, 'UTF-8'),
        '{INVOICE_AMOUNT}'        => htmlspecialchars($formatted_amount, ENT_QUOTES, 'UTF-8'),
        '{INVOICE_CURRENCY_SYMBOL}' => htmlspecialchars($currency_symbol, ENT_QUOTES, 'UTF-8'),
        '{INVOICE_DUE_DATE}'     => htmlspecialchars($invoice->deadline ? date('F d, Y', strtotime($invoice->deadline)) : 'N/A', ENT_QUOTES, 'UTF-8'),
        '{PROJECT_NAME}'          => htmlspecialchars($project->project_title ?? 'N/A', ENT_QUOTES, 'UTF-8'),
        '{INVOICE_STATUS}'       => 'Paid',
        '{DASHBOARD_URL}'        => $url,
        '{SIGNATURE}'            => htmlspecialchars($company_name ?? '', ENT_QUOTES, 'UTF-8')
    );
    $templateHTML = $settings->invoice_paid_email;
    $subject = 'Payment Confirmation - Invoice Paid';
    $emailHelper->sendTemplateEmail($clientUser->email, $subject, $templateHTML, $variablesArr);
}

// Send bell notification
require_once(__DIR__ . '/../notification_helper.php');
$invoice_number = $invoice->p_id . $invoice->id;
$notifier_id = $clientUser ? $clientUser->id : 0;
NotificationHelper::invoicePaid($invoice->id, $invoice_number, $notifier_id, $invoice->p_id);

// Get invoice creator
$invoiceCreator = null;
if (!empty($invoice->created_by)) {
    $invoiceCreator = user::findById((int)$invoice->created_by);
}

// Send email to invoice creator (if exists and different from superadmin)
if ($invoiceCreator && $invoiceCreator->id != 1 && !empty($settings->invoice_paid_email_admin) && filter_var($invoiceCreator->email, FILTER_VALIDATE_EMAIL)) {
    global $url, $company_name;
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
        '{SIGNATURE}'             => htmlspecialchars($company_name ?? '', ENT_QUOTES, 'UTF-8')
    );
    $templateHTML = $settings->invoice_paid_email_admin;
    $subject = 'Payment Received - Invoice #' . ($invoice->p_id . $invoice->id) . ' Paid';
    $emailHelper->sendTemplateEmail($invoiceCreator->email, $subject, $templateHTML, $variablesArr);
}

// Send email to superadmin (user id = 1)
if (!empty($settings->invoice_paid_email_admin)) {
    global $url, $company_name;
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
            '{SIGNATURE}'             => htmlspecialchars($company_name ?? '', ENT_QUOTES, 'UTF-8')
        );
        $templateHTML = $settings->invoice_paid_email_admin;
        $subject = 'Payment Received - Invoice #' . ($invoice->p_id . $invoice->id) . ' Paid';
        $emailHelper->sendTemplateEmail($superAdmin->email, $subject, $templateHTML, $variablesArr);
    }
}

// Redirect to success page
global $url;
header("Location: " . $url . "pay/" . urlencode($payment_token) . "?status=success");
exit;

