<?php
/**
 * Shared post-payment handling for public pay link (Stripe).
 */
function pay_link_finalize_stripe_payment($invoice, $payment_token, $payment_method_display, $adminSettings, $url, $connect)
{
    require_once __DIR__ . '/../email_helper.php';
    $emailHelper = new EmailHelper($adminSettings);

    $project = null;
    $clientUser = null;

    if ($invoice->p_id) {
        $project = projects::findByProjectId($invoice->p_id);
        if ($project && isset($project->c_id)) {
            $clientUser = user::findById($project->c_id);
        }
    }

    if (!$clientUser && $invoice->c_id) {
        $clientUser = user::findById($invoice->c_id);
    }

    require_once __DIR__ . '/../invoice_item.php';
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

    $invoice->status = 1;
    $invoice->releaseDate = date('Y-m-d');
    if ($invoice->paid_total === null || $invoice->paid_total === '' || (float) $invoice->paid_total <= 0) {
        $invoice->paid_total = $total_amount;
    }
    $invoice->save();

    $updateSql = 'UPDATE invoice_payment_links SET used = 1, used_at = NOW() WHERE token = ?';
    $updateStmt = $connect->prepare($updateSql);
    $updateStmt->bind_param('s', $payment_token);
    $updateStmt->execute();

    if (!isset($_SESSION)) {
        session_start();
    }
    $_SESSION['payment_method_' . $payment_token] = $payment_method_display;

    if (!function_exists('getCurrencySymbolForEmail')) {
        function getCurrencySymbolForEmail($currencyCode)
        {
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

    require_once __DIR__ . '/../notification_helper.php';
    $invoice_number = $invoice->p_id . $invoice->id;
    $notifier_id = $clientUser ? $clientUser->id : 0;
    NotificationHelper::invoicePaid($invoice->id, $invoice_number, $notifier_id, $invoice->p_id);

    if ($clientUser && filter_var($clientUser->email, FILTER_VALIDATE_EMAIL) && !empty($adminSettings->invoice_paid_email)) {
        $variablesArr = [
            '{USER_NAME}' => htmlspecialchars($clientUser->firstName ?? '', ENT_QUOTES, 'UTF-8'),
            '{INVOICE_TITLE}' => htmlspecialchars($invoice->title ?? '', ENT_QUOTES, 'UTF-8'),
            '{INVOICE_AMOUNT}' => htmlspecialchars($formatted_amount, ENT_QUOTES, 'UTF-8'),
            '{INVOICE_CURRENCY_SYMBOL}' => htmlspecialchars($currency_symbol, ENT_QUOTES, 'UTF-8'),
            '{INVOICE_DUE_DATE}' => htmlspecialchars($invoice->deadline ? date('F d, Y', strtotime($invoice->deadline)) : 'N/A', ENT_QUOTES, 'UTF-8'),
            '{PROJECT_NAME}' => htmlspecialchars($project->project_title ?? 'N/A', ENT_QUOTES, 'UTF-8'),
            '{INVOICE_STATUS}' => 'Paid',
            '{DASHBOARD_URL}' => $url,
            '{SIGNATURE}' => htmlspecialchars($adminSettings->company_name ?? '', ENT_QUOTES, 'UTF-8'),
        ];
        $templateHTML = $adminSettings->invoice_paid_email;
        $subject = 'Payment Confirmation - Invoice Paid';
        $emailHelper->sendTemplateEmail($clientUser->email, $subject, $templateHTML, $variablesArr);
    }

    $invoiceCreator = null;
    if (!empty($invoice->created_by)) {
        $invoiceCreator = user::findById((int) $invoice->created_by);
    }

    if ($invoiceCreator && $invoiceCreator->id != 1 && !empty($adminSettings->invoice_paid_email_admin) && filter_var($invoiceCreator->email, FILTER_VALIDATE_EMAIL)) {
        $variablesArr = [
            '{USER_NAME}' => htmlspecialchars($invoiceCreator->firstName ?? 'User', ENT_QUOTES, 'UTF-8'),
            '{INVOICE_TITLE}' => htmlspecialchars($invoice->title ?? '', ENT_QUOTES, 'UTF-8'),
            '{INVOICE_AMOUNT}' => htmlspecialchars($formatted_amount, ENT_QUOTES, 'UTF-8'),
            '{INVOICE_CURRENCY_SYMBOL}' => htmlspecialchars($currency_symbol, ENT_QUOTES, 'UTF-8'),
            '{INVOICE_DUE_DATE}' => htmlspecialchars($invoice->deadline ? date('F d, Y', strtotime($invoice->deadline)) : 'N/A', ENT_QUOTES, 'UTF-8'),
            '{PROJECT_NAME}' => htmlspecialchars($project->project_title ?? 'N/A', ENT_QUOTES, 'UTF-8'),
            '{INVOICE_STATUS}' => 'Paid',
            '{CLIENT_NAME}' => htmlspecialchars($clientUser->firstName ?? 'N/A', ENT_QUOTES, 'UTF-8'),
            '{CLIENT_EMAIL}' => htmlspecialchars($clientUser->email ?? 'N/A', ENT_QUOTES, 'UTF-8'),
            '{DASHBOARD_URL}' => $url,
            '{SIGNATURE}' => htmlspecialchars($adminSettings->company_name ?? '', ENT_QUOTES, 'UTF-8'),
        ];
        $templateHTML = $adminSettings->invoice_paid_email_admin;
        $subject = 'Payment Received - Invoice #' . ($invoice->p_id . $invoice->id) . ' Paid';
        $emailHelper->sendTemplateEmail($invoiceCreator->email, $subject, $templateHTML, $variablesArr);
    }

    if (!empty($adminSettings->invoice_paid_email_admin)) {
        $superAdmin = user::findById(1);
        if ($superAdmin && filter_var($superAdmin->email, FILTER_VALIDATE_EMAIL)) {
            $variablesArr = [
                '{USER_NAME}' => htmlspecialchars($superAdmin->firstName ?? 'Admin', ENT_QUOTES, 'UTF-8'),
                '{INVOICE_TITLE}' => htmlspecialchars($invoice->title ?? '', ENT_QUOTES, 'UTF-8'),
                '{INVOICE_AMOUNT}' => htmlspecialchars($formatted_amount, ENT_QUOTES, 'UTF-8'),
                '{INVOICE_CURRENCY_SYMBOL}' => htmlspecialchars($currency_symbol, ENT_QUOTES, 'UTF-8'),
                '{INVOICE_DUE_DATE}' => htmlspecialchars($invoice->deadline ? date('F d, Y', strtotime($invoice->deadline)) : 'N/A', ENT_QUOTES, 'UTF-8'),
                '{PROJECT_NAME}' => htmlspecialchars($project->project_title ?? 'N/A', ENT_QUOTES, 'UTF-8'),
                '{INVOICE_STATUS}' => 'Paid',
                '{CLIENT_NAME}' => htmlspecialchars($clientUser->firstName ?? 'N/A', ENT_QUOTES, 'UTF-8'),
                '{CLIENT_EMAIL}' => htmlspecialchars($clientUser->email ?? 'N/A', ENT_QUOTES, 'UTF-8'),
                '{DASHBOARD_URL}' => $url,
                '{SIGNATURE}' => htmlspecialchars($adminSettings->company_name ?? '', ENT_QUOTES, 'UTF-8'),
            ];
            $templateHTML = $adminSettings->invoice_paid_email_admin;
            $subject = 'Payment Received - Invoice #' . ($invoice->p_id . $invoice->id) . ' Paid';
            $emailHelper->sendTemplateEmail($superAdmin->email, $subject, $templateHTML, $variablesArr);
        }
    }
}
