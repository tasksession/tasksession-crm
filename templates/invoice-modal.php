<?php
/*
 ================================================================================
   Task Session – Project Management System
   File    : invoice-modal.php
   Purpose : Global invoice modal template for reuse across pages
 ================================================================================
 */

require_once(__DIR__ . '/../includes/invoice_item.php');
require_once(__DIR__ . '/../includes/invoice_company_helper.php');

// This template expects the following variables to be set:
// $edit_id1 - milestone ID
// $latestMile1 - milestone object
// $latestProj1 - project object  
// $latestUser1 - client user object
// $adminUser1 - admin user object
// $company_name - company name
// $currency_symbol - currency symbol
// $url - system URL
// $lang - language array

// Enhanced logic for invoice type
$internalProjectIds = [17]; // Add all your internal project IDs here
// An invoice is internal if:
// 1. It has no project AND no client (external invoice)
// 2. OR it's in the internal project IDs list
// 3. OR the project title indicates it's internal
// Note: Client invoices (with c_id but no p_id) are NOT internal
$isInternal = (
    (empty($latestMile1->p_id) && empty($latestMile1->c_id)) || // External invoice (no project, no client)
    ($latestMile1->p_id == 0 && empty($latestMile1->c_id)) ||
    ($latestMile1->p_id && in_array($latestMile1->p_id, $internalProjectIds)) ||
    (isset($latestProj1->project_title) && strtolower(trim($latestProj1->project_title)) == 'internal invoice') ||
    (isset($latestProj1->project_title) && strtolower(trim($latestProj1->project_title)) == 'internal invoices')
);

// Get invoice logo from settings with error handling
try {
    $settings = settings::findById(1);
    $invoice_logo = $settings ? $settings->invoice_logo : null;
    
    // Check if custom invoice logo exists, otherwise use default
    if ($invoice_logo && file_exists(dirname(__DIR__) . '/uploads/system-uploads/' . $invoice_logo)) {
        $logo_src = getSystemImageUrl($invoice_logo);
    } else {
        $logo_src = $url . 'assets/images/dark-logo.png';
    }
} catch (Exception $e) {
    // Fallback to default logo if there's any error
    $logo_src = $url . 'assets/images/dark-logo.png';
}

// Calculate subtotal from invoice items or fallback to budget
$subtotal = 0;
$invoiceItems = InvoiceItem::findByMilestoneId($latestMile1->id);
if ($invoiceItems && count($invoiceItems) > 0) {
    foreach ($invoiceItems as $item) {
        $subtotal += floatval($item->rate) * floatval($item->quantity);
    }
} else {
    $subtotal = floatval($latestMile1->budget);
}
$sales_tax = isset($latestMile1->sales_tax) ? floatval($latestMile1->sales_tax) : ($settings->default_sales_tax ?? 0);
$sales_tax_type = isset($latestMile1->sales_tax_type) ? $latestMile1->sales_tax_type : 'percentage';
$discount = isset($latestMile1->discount) ? floatval($latestMile1->discount) : 0;
$discount_type = isset($latestMile1->discount_type) ? $latestMile1->discount_type : 'percentage';

// Calculate discount amount
$discount_amount = 0;
if ($discount > 0) {
    if ($discount_type === 'percentage') {
        $discount_amount = ($subtotal * $discount) / 100;
    } else {
        $discount_amount = $discount;
    }
}

// Calculate tax on amount after discount
$amount_after_discount = $subtotal - $discount_amount;
$tax_amount = 0;
if ($sales_tax > 0) {
    if ($sales_tax_type === 'percentage') {
        $tax_amount = ($amount_after_discount * $sales_tax) / 100;
    } else {
        $tax_amount = $sales_tax;
    }
}

// Calculate total
$total = $amount_after_discount + $tax_amount;

$billResolved = invoice_resolve_bill_to($latestMile1, isset($latestProj1) ? $latestProj1 : null);
if (!empty($billResolved['client_user'])) {
    $latestUser1 = $billResolved['client_user'];
}

$billToName = '';
$billToAddressHtml = '';
if (!empty($isInternal)) {
    $billToName = (string) ($latestMile1->bill_from ?? '');
    $billToAddressHtml = htmlspecialchars((string) ($latestMile1->bill_from_address ?? ''), ENT_QUOTES, 'UTF-8');
} elseif (!empty($billResolved['snapshot']['name'])) {
    $billToFormatted = invoice_format_bill_to_lines($billResolved['snapshot'], false);
    $billToName = (string) ($billToFormatted['title'] ?? '');
    $billToAddressHtml = nl2br(htmlspecialchars(implode("\n", $billToFormatted['lines'] ?? []), ENT_QUOTES, 'UTF-8'));
} elseif (!empty($latestMile1->bill_from)) {
    $billToName = (string) $latestMile1->bill_from;
    $billToAddressHtml = nl2br(htmlspecialchars((string) ($latestMile1->bill_from_address ?? ''), ENT_QUOTES, 'UTF-8'));
} elseif (!empty($billResolved['client_user'])) {
    $billToClient = $billResolved['client_user'];
    $billToName = (string) ($billToClient->firstName ?? '');
    $billToAddrParts = [];
    foreach (['address', 'city', 'state', 'country', 'zip'] as $billToField) {
        if (!empty($billToClient->$billToField)) {
            $billToAddrParts[] = (string) $billToClient->$billToField;
        }
    }
    $billToAddressHtml = htmlspecialchars(implode(', ', $billToAddrParts), ENT_QUOTES, 'UTF-8');
}
?>

<div>
    <div id="editor"></div>
    <table id="html-2-pdfwrapper" width="100%">
        <tr>
            <td colspan="2">
                <img src="<?php echo $logo_src; ?>" class="img-fluid" /><br><br>
            </td>
            <td class="font-size-16 black" style="text-align: right;">
                <br><font size="5"><b><?php echo $lang['INVOICE']; ?></b></font>
            </td>
        </tr>
        <tr>
            <td style="width:38%;vertical-align:top;padding-right:20px;">
                <div class="mb-2 bold" style="font-size:12px;font-weight:700;"><?php echo $lang['BILL FROM:']; ?></div>
                <b class="mb-2 d-block bold black" style="font-size:16px;"><?php echo $company_name; ?></b>
                <div><?php echo !empty($settings->company_address) ? nl2br(htmlspecialchars($settings->company_address)) : (isset($adminUser1->address) ? $adminUser1->address : ''); ?></div>
            </td>
            <td style="width:38%;vertical-align:top;padding-right:20px;">
                <div class="mb-2 bold" style="font-size:12px;font-weight:700;"><?php echo $lang['BILL TO:']; ?></div>
                <b class="mb-2 d-block bold black" style="font-size:16px;"><?php echo htmlspecialchars($billToName, ENT_QUOTES, 'UTF-8'); ?></b>
                <div><?php echo $billToAddressHtml; ?></div>
            </td>
            <td style="text-align: right;width:24%;vertical-align:top;min-width: 170px;">
                <?php if (!empty($latestMile1->issue_date)): ?>
                    <strong><?php echo $lang['Date of Issue']; ?>:</strong> <?php echo $latestMile1->issue_date; ?><br>
                <?php endif; ?>
                <strong><?php echo $lang['Due Date']; ?>:</strong> <?php echo $latestMile1->deadline; ?><br>
                <?php echo $lang['Invoice No']; ?>: <?php echo $latestMile1->p_id . $latestMile1->id; ?><br>
                <strong><?php echo $lang['Status']; ?>:</strong>
                <?php if ($latestMile1->status == 0): ?>
                    <font color="#ff0000"><?php echo $lang['Unpaid']; ?></font>
                <?php elseif ($latestMile1->status == 2): ?>
                    <font color="red"><?php echo $lang['Cancel']; ?></font>
                <?php else: ?>
                    <font color="#00c82a"><?php echo $lang['Paid']; ?></font>
                <?php endif; ?>
            </td>
        </tr>
   
        <?php 
        // Show Project field only if invoice has a project (not for internal/external/direct client invoices)
        $hasProject = !empty($latestMile1->p_id) && $latestMile1->p_id != 0 && isset($latestProj1->project_title);
        if ($hasProject && !$isInternal): ?>
        <tr>
            <td colspan="3"><br><b class="black"><?php echo $lang['Project']; ?>:</b>
                <?php echo isset($latestProj1->project_title) ? $latestProj1->project_title : ''; ?>
            </td>
        </tr>
        <?php endif; ?>
        <?php if (!empty($latestMile1->memo)): ?>
        <tr>
            <td colspan="3" style="padding-top: 10px;">
                <div style="margin-top: 5px; color: #666; white-space: pre-wrap;"><?php echo htmlspecialchars($latestMile1->memo); ?></div>
            </td>
        </tr>
        <?php endif; ?>
        <tr>
            <td colspan="3">
                <br>
                <table width="100%" cellpadding="15" cellspacing="15" >
                    <!-- Header row with black background -->
                    <tr style="background-color:#000000; color:#ffffff;">
                        <td style="padding: 8px 10px;font-weight:bold;width: 50%;"><?php echo $lang['Items']; ?></td>
                        <td style="text-align: right; padding:8px 6px; font-weight:bold;"><?php echo $lang['Qty']; ?></td>
                        <td style="text-align: right; padding:8px 6px; font-weight:bold;"><?php echo $lang['Unit Price']; ?></td>
                        <td style="text-align: right;padding:8px 10px;font-weight:bold;max-width: 120px;width: 100px;"><?php echo $lang['Amount']; ?></td>
                    </tr>
                    <?php 
                    // Get invoice items
                    $invoiceItems = InvoiceItem::findByMilestoneId($latestMile1->id);
                    if ($invoiceItems && count($invoiceItems) > 0):
                        foreach ($invoiceItems as $item):
                            $itemTotal = floatval($item->rate) * floatval($item->quantity);
                    ?>
                    <tr class="item-row">
                        <td>
                            <font class="black font-size-14"><b><?php echo htmlspecialchars($item->description); ?></b></font>
                            <?php if (!empty($item->item_description)): ?>
                                <br><font size="2" style="color: #666;"><?php echo nl2br(htmlspecialchars($item->item_description)); ?></font>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: right;"><font size="3"><?php echo number_format($item->quantity, 0); ?></font></td>
                        <td style="text-align: right;"><font class="font-size-14"><?php echo $currency_symbol . number_format($item->rate, 2); ?></font></td>
                        <td style="text-align: right;"><font color="#000000" class="font-size-14"><b><?php echo $currency_symbol . number_format($itemTotal, 2); ?></b></font></td>
                    </tr>
                    <!-- Horizontal separator for each item -->
                    <tr><td colspan="4" style="border-bottom:#e7e7e7 1px solid;padding: 0;"></td></tr>
                    <?php 
                        endforeach;
                    else:
                        // Fallback to old single item display
                    ?>
                    <tr>
                        <td><font class="font-size-14"><b><?php echo $latestMile1->title; ?></b></font></td>
                        <td style="text-align: right;"><font class="font-size-14">1</font></td>
                        <td style="text-align: right;"><font class="font-size-14"><?php echo $currency_symbol . number_format($latestMile1->budget, 2); ?></font></td>
                        <td style="text-align: right;"><font color="#000000" class="font-size-14"><b><?php echo $currency_symbol . number_format($latestMile1->budget, 2); ?></b></font></td>
                    </tr>
                    <!-- Separator after single fallback item -->
                    <?php endif; ?>
                  
                </table>

                <!-- Totals block in a separate right-aligned table -->
                <table width="100%" cellpadding="4" cellspacing="0">
                    <tr class="item-row">
                        <td style="width: 55%;"></td>
                        <td style="width: 45%;">
                            <table width="100%" cellpadding="0" cellspacing="0" class="total-table">
                                <tr>
                                    <td style="text-align:right;" class="font-size-14">
                                        <?php echo $lang['Sub Total:']; ?>
                                    </td>
                                    <td style="text-align:right; width: 100px;" class="font-size-14">
                                        <?php echo $currency_symbol . number_format($subtotal, 2); ?>
                                    </td>
                                </tr>
                                <?php if ($discount_amount > 0): ?>
                                <tr>
                                    <td style="text-align:right;" class="font-size-14">
                                      <?php echo $lang['Discount']; ?><?php echo $discount_type === 'percentage' ? ' (' . number_format($discount, 2) . '%)' : ''; ?>
                                    </td>
                                    <td style="text-align:right;" class="font-size-14">
                                        -<?php echo $currency_symbol . number_format($discount_amount, 2); ?>
                                    </td>
                                </tr>
                                <?php endif; ?>
                                <?php if ($tax_amount > 0): ?>
                                <tr>
                                    <td style="text-align:right;" class="font-size-14">
                                        <?php echo $lang['Sales Tax']; ?><?php echo $sales_tax_type === 'percentage' ? ' (' . number_format($sales_tax, 2) . '%)' : ''; ?>:
                                    </td>
                                    <td style="text-align:right;" class="font-size-14">
                                      <?php echo $currency_symbol . number_format($tax_amount, 2); ?>
                                    </td>
                                </tr>
                                <?php endif; ?>
                                <tr>
                                    <td style="text-align:right; padding-top:6px;" class="font-size-14 black">
                                        <b><?php echo $lang['Total:']; ?></b>
                                    </td>
                                    <td style="text-align:right; padding-top:6px;" class="font-size-14 black">
                                        <b><?php echo $currency_symbol . number_format($total, 2); ?></b>
                                    </td>
                                </tr>
                                <?php
                                $showInvoicePayNow = false;
                                $payNowProjectId = 0;
                                $payNowUrl = '';
                                if (isset($latestMile1->status) && (int) $latestMile1->status === 0) {
                                    $acct = isset($_SESSION['accountStatus']) ? (int) $_SESSION['accountStatus'] : 0;
                                    // Client invoice view modal only (not admin/staff)
                                    if ($acct === 2) {
                                        $showInvoicePayNow = true;
                                        $payNowProjectId = !empty($latestMile1->p_id) ? (int) $latestMile1->p_id : 0;
                                        if ($payNowProjectId <= 0) {
                                            if (!function_exists('client_outstanding_invoice_pay_url')) {
                                                require_once __DIR__ . '/../includes/client_outstanding_invoices_helper.php';
                                            }
                                            $payClientId = !empty($latestMile1->c_id)
                                                ? (int) $latestMile1->c_id
                                                : (int) ($_SESSION['userId'] ?? 0);
                                            $payNowUrl = client_outstanding_invoice_pay_url(
                                                (int) $edit_id1,
                                                0,
                                                $payClientId
                                            );
                                        }
                                    }
                                }
                                if ($showInvoicePayNow):
                                ?>
                                <tr>
                                    <td colspan="2" style="text-align:right; padding-top:28px; padding-right:8px;">
                                        <button type="button"
                                                class="btn btn-success invoice-modal-pay-now"
                                                style="display:inline-flex; margin-left:auto; margin-top:4px;"
                                                data-invoice-id="<?php echo (int) $edit_id1; ?>"
                                                data-project-id="<?php echo (int) $payNowProjectId; ?>"
                                                data-pay-url="<?php echo htmlspecialchars($payNowUrl, ENT_QUOTES, 'UTF-8'); ?>"
                                                onclick="openInvoicePayNow(<?php echo (int) $edit_id1; ?>, <?php echo (int) $payNowProjectId; ?>, this); return false;">
                                            <?php echo isset($lang['Make Payment']) ? $lang['Make Payment'] : 'Pay now'; ?>
                                        </button>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </table>
                        </td>
                    </tr>
                </table>

                <br><br>
            </td>
        </tr>
        <?php if (!empty($latestMile1->footer)): ?>
        <tr>
            <td>
                <font size="2"><?php echo nl2br(htmlspecialchars($latestMile1->footer)); ?></font>
            </td>
        </tr>
        <?php endif; ?>
    </table>
</div> 