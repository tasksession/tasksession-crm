<?php
/*
 ================================================================================
   Task Session – Project Management System
   File    : download_pdf.php
   Purpose : Generates and handles PDF downloads for various documents
 ================================================================================
 */

// Start output buffering to prevent any output before PDF
ob_start();

require_once("../includes/lib-initialize.php");
require_once('../includes/TCPDF/tcpdf.php');
require_once('../includes/invoice_item.php');
require_once('../includes/invoice_company_helper.php');

/**
 * Helper function to get currency symbol (same logic as in admin/invoices.php)
 */
function getCurrencySymbol($currencyCode) {
    if (empty($currencyCode)) {
        return '$'; // Default to dollar sign
    }
    
    // Extract symbol from currency code (e.g., "PKR,Rs" -> "Rs" or "USD,$" -> "$")
    $parts = explode(',', $currencyCode);
    if (count($parts) >= 2) {
        $symbol = trim($parts[1]);
        // Return just the symbol without colon and space
        return $symbol;
    }
    
    return '$'; // Default fallback
}

/**
 * Convert SVG to PNG using ImageMagick or fallback method
 */
function convertSvgToPng($svgPath, $outputPath = null) {
    // Try ImageMagick first (most reliable)
    if (extension_loaded('imagick')) {
        try {
            $imagick = new Imagick();
            $imagick->readImage($svgPath);
            $imagick->setImageFormat('png');
            $imagick->setImageBackgroundColor('white');
            
            if ($outputPath) {
                $imagick->writeImage($outputPath);
                return $outputPath;
            } else {
                return $imagick->getImageBlob();
            }
        } catch (Exception $e) {
            // ImageMagick failed, try fallback
        }
    }
    
    // Fallback: Use default logo
    return false;
}

// Validate milestone_id from POST
$edit_id1 = isset($_POST['milestone_id']) ? (int)$_POST['milestone_id'] : 0;
if ($edit_id1 <= 0) {
    die('Invalid invoice ID');
}

/**
 * Fetch data for invoice generation
 */
function fetch_data($edit_id1) {
    // Retrieve settings with error handling
    try {
        $dash_settings   = settings::findById(1);
        $company_namea   = $dash_settings ? $dash_settings->company_name : '';
        $system_currency = $dash_settings ? $dash_settings->system_currency : 'USD,$';
        $url             = $dash_settings ? $dash_settings->url : '';
        $logo_check      = $dash_settings ? $dash_settings->logo : null;
        $invoice_logo    = $dash_settings ? $dash_settings->invoice_logo : null;
    } catch (Exception $e) {
        // Fallback values if settings fail
        $company_namea = 'Company Name';
        $system_currency = 'USD,$';
        
        // Dynamic URL detection as fallback
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'];
        $script_name = $_SERVER['SCRIPT_NAME'];
        
        // Extract the subfolder path from the script name
        $subfolder = dirname($script_name);
        if ($subfolder === '/') {
            $subfolder = '';
        }
        
        $url = $protocol . $host . $subfolder . '/';
        
        $logo_check = null;
        $invoice_logo = null;
    }

    // Fetch related records
    $latestMile1 = milestone::findByMilestoneId($edit_id1);
    $latestProj1 = null;
    $latestUser1 = null;
    $adminUser1  = user::findById(1);

    // Check if invoice has a project
    if ($latestMile1->p_id) {
        $latestProj1 = projects::findByProjectId($latestMile1->p_id);
    }

    // Enhanced client fetch logic - use main client for billing
    if ($latestProj1 && isset($latestProj1->main_client_id)) {
        $latestUser1 = user::findById($latestProj1->main_client_id);
    } elseif ($latestProj1 && isset($latestProj1->c_id)) {
        $latestUser1 = user::findById($latestProj1->c_id);
    } elseif (!empty($latestMile1->c_id)) {
        // Client invoice (c_id set but no p_id)
        $latestUser1 = user::findById($latestMile1->c_id);
    }

    // Dynamic currency handling - use invoice currency if available, otherwise fallback to system currency
    $currency_symbol = '$'; // Default fallback
    
    if (!empty($latestMile1->currency)) {
        // Use the invoice's specific currency
        $currency_symbol = getCurrencySymbol($latestMile1->currency);
    } elseif (!empty($latestUser1->currency)) {
        // Fallback to client's preferred currency
        $currency_symbol = getCurrencySymbol($latestUser1->currency);
    } else {
        // Fallback to system currency
        $sc_arr = explode(",", $system_currency);
        $currency_symbol = isset($sc_arr[1]) ? $sc_arr[1] : '$';
    }
    
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
    $isClientOnly = (empty($latestMile1->p_id) && !empty($latestMile1->c_id));
    $isProject = ($latestMile1->p_id > 0);

    $billToBlockHtml = '';
    $mainClientBlockHtml = '';
    // BILL TO output
    if ($isInternal) {
        $firstName = htmlspecialchars($latestMile1->bill_from ?? '');
        $address = htmlspecialchars($latestMile1->bill_from_address ?? '');
        $billToBlockHtml = '<div style="font-size: 11pt; font-weight: bold; color: #333;">' . $firstName
            . '<div style="font-size: 9pt !important; color: #666; line-height: 1.5; font-weight: normal;">' . nl2br($address) . '</div></div>';
    } else {
        $billResolved = invoice_resolve_bill_to($latestMile1, $latestProj1);
        if (!empty($billResolved['client_user'])) {
            $latestUser1 = $billResolved['client_user'];
        }
        $billToBlockHtml = $billResolved['bill_to_html'];
        $mainClientBlockHtml = $billResolved['main_client_html'];
        $firstName = '';
        $address = '';
    }

    // Assign fields
    $deadline   = $latestMile1->deadline;
    // Invoice number: use p_id if available, otherwise just use id
    $InvoiceNo  = $latestMile1->p_id ? ($latestMile1->p_id . $latestMile1->id) : $latestMile1->id;
    global $lang;

    // Determine status display
    if ($latestMile1->status == 0) {
        $statusa = '<font color="#ff0000">'.$lang['Unpaid'].'</font>';
    } elseif ($latestMile1->status == 2) {
        $statusa = '<font color="red">'.$lang['Cancel'].'</font>';
    } else {
        $statusa = '<font color="#00c82a">'.$lang['Paid'].'</font>';
    }

    // Get company address from settings
    $companyAddress = '';
    if ($dash_settings && !empty($dash_settings->company_address)) {
        $companyAddress = $dash_settings->company_address;
    } else {
        $companyAddress = $adminUser1->address ?? '';
    }

    $adminAddress  = $companyAddress;
    $project_title = ($latestProj1 && isset($latestProj1->project_title)) ? $latestProj1->project_title : '';
    $miletitle     = $latestMile1->title;
    
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
    
    // Get tax and discount values
    $sales_tax = isset($latestMile1->sales_tax) ? floatval($latestMile1->sales_tax) : ($dash_settings->default_sales_tax ?? 0);
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

    // ------------------------------------------------------------------
    // Invoice logo handling with guaranteed fallback
    // ------------------------------------------------------------------
    $logoPath = null;
    $extension = 'png';
    
    // First priority: Check if custom invoice logo exists
    if ($invoice_logo && file_exists(dirname(__DIR__) . '/uploads/system-uploads/' . $invoice_logo)) {
        $logoPath = dirname(__DIR__) . '/uploads/system-uploads/' . $invoice_logo;
        $extension = strtolower(pathinfo($invoice_logo, PATHINFO_EXTENSION));
    } 
    // Second priority: Check if regular logo exists (fallback)
    elseif ($logo_check && file_exists(dirname(__DIR__) . '/uploads/system-uploads/' . $logo_check)) {
        $logoPath = dirname(__DIR__) . '/uploads/system-uploads/' . $logo_check;
        $extension = strtolower(pathinfo($logo_check, PATHINFO_EXTENSION));
    } 
    // Third priority: Use default dark logo
    else {
        $defaultPngPath = dirname(__DIR__) . '/assets/images/dark-logo.png';
        
        if (file_exists($defaultPngPath)) {
            $logoPath = $defaultPngPath;
            $extension = 'png';
        } else {
            // If no PNG exists, try to convert SVG
            $defaultSvgPath = dirname(__DIR__) . '/assets/images/svg/dark-logo.svg';
            if (file_exists($defaultSvgPath)) {
                $convertedPng = convertSvgToPng($defaultSvgPath, $defaultPngPath);
                if ($convertedPng && file_exists($defaultPngPath)) {
                    $logoPath = $defaultPngPath;
                    $extension = 'png';
                } else {
                    // If conversion fails, create a fallback logo
                    $logoPath = null;
                }
            } else {
                // No logo files found
                $logoPath = null;
            }
        }
    }

    // Load image data
    $imapePath = ''; // Default/fallback if loading fails
    if ($logoPath && file_exists($logoPath)) {
        $imageData = file_get_contents($logoPath);
        if ($imageData !== false) {
            $base64 = base64_encode($imageData);
            // Infer MIME type from extension
            $mime = 'image/png'; 
            if ($extension === 'jpg' || $extension === 'jpeg') {
                $mime = 'image/jpeg';
            } elseif ($extension === 'gif') {
                $mime = 'image/gif';
            } elseif ($extension === 'svg') {
                $mime = 'image/svg+xml';
            }
            $imapePath = 'data:' . $mime . ';base64,' . $base64;
        }
    }
    
    // If we still don't have an image, create one with company name
    if (empty($imapePath)) {
        $fallbackImage = createFallbackLogo();
        if ($fallbackImage) {
            $base64 = base64_encode($fallbackImage);
            $imapePath = 'data:image/png;base64,' . $base64;
        }
    }

    // ------------------------------------------------------------------
    // Build the HTML output - Professional Clean Design
    // ------------------------------------------------------------------
    $output  = '';
    $output .= '<style>
                    body { font-size: 12pt; }
                    .invoice-header { padding: 10px 0; }
                    .invoice-section { padding: 15px 0; }
                    .invoice-table { width: 100%; border-collapse: separate; border-spacing: 0; }
                    .invoice-table th { background-color: #000000; color: #ffffff; padding: 15px 12px; text-align: left; font-weight: bold; font-size: 11pt; border-radius: 5px!important; }
                    .invoice-table td { padding: 15px 12px; font-size: 10pt; border-bottom: 1px solid #ddd; }
                    .invoice-table tr { border-bottom: 1px solid #ddd; }
                    .invoice-table .desc-col { width: 50%; }
                    .invoice-table .qty-col { width: 15%; text-align: right; }
                    .invoice-table .price-col { width: 17.5%; text-align: right; }
                    .invoice-table .amount-col { width: 17.5%; text-align: right; }
                    .totals-section { padding-top: 25px; }
                    .totals-row { padding: 10px 0; }
                </style>';

    // Header Section
    $output .= '<table cellpadding="0" cellspacing="0" style="width: 100%;">';
    $output .= '<tr>';
    $output .= '<td style="width: 50%; padding: 15px 0;">';
    if (!empty($imapePath)) {
        $output .= '<img src="' . $imapePath . '" width="180" />';
    } else {
        $output .= '<h2 style="color: #333; font-size: 24pt; margin: 0; font-weight: bold;">' . htmlspecialchars($company_namea) . '</h2>';
    }
    $output .= '</td>';
    $output .= '<td style="width: 50%; text-align: right; padding: 15px 0; vertical-align: top;">';
    $output .= '<div style="font-size: 20pt; font-weight: bold; color: #000;">'.$lang['INVOICE'].'</div>';
    $output .= '</td>';
    $output .= '</tr>';
    $output .= '</table>';
    // Spacer after header table
    $output .= '<div style="height: 50px;"></div>';

    // BILL FROM, TO, and Date of Issue on same row (4columns)
    $output .= '<table cellpadding="0" cellspacing="0" style="width: 100%;">';
    $output .= '<tr>';
    
    // Column 1: BILL FROM
    $output .= '<td style="width: 30%;  vertical-align: top;">';
    $output .= '<div style="color: #666; font-size: 9pt; font-weight: bold; margin-bottom: 0px; text-transform: uppercase;">'.$lang['BILL FROM:'].'</div>';
    $output .= '<div style="font-size: 11pt; font-weight: bold; color: #333;">'.$company_namea.'<div style="font-size: 9pt !important; color: #666; line-height: 1.5; font-weight: normal;">'.nl2br(htmlspecialchars($adminAddress)).'</div>
	</div>';
    $output .= '</td>';
	
	
      // Column 2: BILL FROM
    $output .= '<td style="width: 50px; vertical-align: top;">';

    $output .= '</td>';
	
    // Column 3: BILL TO
    $output .= '<td style="width: 30%; vertical-align: top;">';
    $output .= '<div style="color: #666; font-size: 9pt; font-weight: bold; margin-bottom: 3px; text-transform: uppercase;">'.$lang['BILL TO:'].'</div>';
	
	
    $output .= $billToBlockHtml !== '' ? $billToBlockHtml : '<div style="font-size: 11pt; font-weight: bold; color: #333;">'.htmlspecialchars($firstName).'<div style="font-size: 9pt !important; color: #666; line-height: 1.5; font-weight: normal;">'.htmlspecialchars($address).'</div></div>';
    if ($mainClientBlockHtml !== '') {
        $output .= $mainClientBlockHtml;
    }
    $output .= '</td>';
    
    // Column 4: Date of Issue and other details - Right aligned
    $output .= '<td style="width: 33%; padding: 0 0 0 50px; vertical-align: top; text-align: right;">';
    if (!empty($latestMile1->issue_date)) {
        $output .= '<div style="font-size: 9pt; margin-bottom: 0px; line-height:20px;"><strong style="color: #666;">'.$lang['Date of Issue'].':</strong> <span style="color: #333;">'.$latestMile1->issue_date.'</span>
		<br>
		<strong style="color: #666;">'.$lang['Due Date'].':</strong> <span style="color: #333;">'.$deadline.'</span>
		<br>
<strong style="color: #666;">'.$lang['Invoice No'].':</strong> <span style="color: #333;">'.$InvoiceNo.'</span>
		<br>
<strong style="color: #666;">'.$lang['Status'].':</strong> '.$statusa.'</div>';
		
    }
    $output .= '</td>';
    $output .= '</tr>';
    $output .= '</table>';
    // Spacer after BILL FROM/TO table
    $output .= '<div style="height: 20px;"></div>';

    // Project and Memo Section
    // Show Project field only if invoice has a project (not for internal/external/direct client invoices)
    $hasProject = !empty($latestMile1->p_id) && $latestMile1->p_id != 0 && isset($latestProj1->project_title);
    if (($hasProject && !$isInternal) || !empty($latestMile1->memo)) {
        $output .= '<table cellpadding="0" cellspacing="0" style="width: 100%;">';
        if ($hasProject && !$isInternal) {
            $output .= '<tr><td style="padding: 8px 0;"><div style="font-size: 11pt; font-weight: bold; color: #333;">'.$lang['Project'].': <span style="font-weight: normal;">'.$project_title.'</span></div><br /></td></tr>';
        }
        if (!empty($latestMile1->memo)) {
            $output .= '<tr><td>';

            // Preserve line breaks in memo - split by newlines and join with <br />
            $memo_lines = preg_split('/\r\n|\r|\n/', $latestMile1->memo);
            $memo_text = '';
            foreach ($memo_lines as $index => $line) {
                if ($index > 0) {
                    $memo_text .= '<br />';
                }
                $memo_text .= htmlspecialchars($line);
            }
            $output .= '<div style="font-size: 10pt; color: #666; line-height: 1.6;">'.$memo_text.'</div><br><br>';
            $output .= '</td></tr>';
        }
        $output .= '</table>';
        // Spacer after Project/Memo table
        $output .= '<div style="height: 25px;"></div>';
    }
    
    // Invoice Items Table
    $output .= '<table class="invoice-table"  cellpadding="12" cellspacing="0"  style="margin-top: 30px;">';
    // Table Header
    $output .= '<tr>';
    $output .= '<th class="desc-col" style="text-align: left; padding: 18px 15px;">'.$lang['Items'].'</th>';
    $output .= '<th class="qty-col">'.$lang['Qty'].'</th>';
    $output .= '<th class="price-col">'.$lang['Unit Price'].'</th>';
    $output .= '<th class="amount-col">'.$lang['Amount'].'</th>';
    $output .= '</tr>';
    
    // Table Rows
    if ($invoiceItems && count($invoiceItems) > 0) {
        foreach ($invoiceItems as $item) {
            $itemTotal = floatval($item->rate) * floatval($item->quantity);
            $output .= '<tr>';
            $output .= '<td class="desc-col" style="padding: 20px 15px; vertical-align: top; ">';
            $output .= '<div style="font-weight: bold; color: #333; font-size: 10pt;">';
            $output .= htmlspecialchars($item->description);
            // Show item_description below the item name if it exists
            if (!empty($item->item_description)) {
                // Split by newlines and join with <br /> for proper line breaks
                $desc_lines = preg_split('/\r\n|\r|\n/', $item->item_description);
                $desc_text = '';
                foreach ($desc_lines as $index => $line) {
                    if ($index > 0) {
                        $desc_text .= '<br />';
                    }
                    $desc_text .= htmlspecialchars($line);
                }
                $output .= '<br /><span style="font-weight: normal; font-size: 9pt; color: #666;">'.$desc_text.'</span>';
            }
            $output .= '</div>';
            $output .= '</td>';
			
			
			
			
			
            $output .= '<td class="qty-col" style="padding: 20px 15px; vertical-align: top; text-align: right;">'.number_format($item->quantity, 0).'</td>';
            $output .= '<td class="price-col" style="padding: 20px 15px; vertical-align: top; text-align: right;">'.$currency_symbol.number_format($item->rate, 2).'</td>';
            $output .= '<td class="amount-col" style="padding: 20px 15px; vertical-align: top; text-align: right;">'.$currency_symbol.number_format($itemTotal, 2).'</td>';
            $output .= '</tr>';
        }
    } else {
        // Fallback to old single item display
         
        $output .= '<td class="desc-col" style="padding: 20px 15px; vertical-align: top;">';
        $output .= '<div style="font-weight: bold; color: #333; font-size: 10pt;">'.$miletitle.'</div>';
        $output .= '</td>';
        $output .= '<td class="qty-col" style="padding: 20px 15px; vertical-align: top; text-align: right;">1</td>';
        $output .= '<td class="price-col" style="padding: 20px 15px; vertical-align: top; text-align: right;">'.$currency_symbol.number_format($latestMile1->budget, 2).'</td>';
        $output .= '<td class="amount-col" style="padding: 20px 15px; vertical-align: top; text-align: right;">'.$currency_symbol.number_format($latestMile1->budget, 2).'</td>';
        $output .= '</tr>';
    }
    $output .= '</table>';
    // Spacer after Invoice Items table
    $output .= '<div style="height: 30px;"></div>';
	
    // Totals Section
    $output .= '<table cellpadding="2" cellspacing="10" style="width: 100%; margin-left: auto; margin-top: 30px;" align="right">';
    $output .= '<tr><td style="width: 60%;"></td><td style="width: 39%;">';
    
    $output .= '<table cellpadding="2" cellspacing="0" style="width: 100%;">';
    
    // Subtotal
    $output .= '<tr class="totals-row">';
    $output .= '<td style="text-align: right; font-size: 10pt; color: #666;">'.$lang['Sub Total:'].'</td>';
    $output .= '<td style="text-align: right; font-size: 10pt; color: #333;">'.$currency_symbol.number_format($subtotal, 2).'</td>';
    $output .= '</tr>';
    
    // Discount
    if ($discount_amount > 0) {
        $discountLabel = $lang['Discount'];
        if ($discount_type === 'percentage') {
            $discountLabel .= ' ('.number_format($discount, 2).'%)';
        }
        $output .= '<tr class="totals-row">';
        $output .= '<td style="text-align: right; font-size: 10pt; color: #666;">'.$discountLabel.':</td>';
        $output .= '<td style="text-align: right; font-size: 10pt; color: #333;">-'.$currency_symbol.number_format($discount_amount, 2).'</td>';
        $output .= '</tr>';
    }
    
    // Sales Tax
    if ($tax_amount > 0) {
        $taxLabel = $lang['Sales Tax'];if ($sales_tax_type === 'percentage') {$taxLabel .= ' ('.number_format($sales_tax, 2).'%)';
        }
        $output .= '<tr class="totals-row">';
        $output .= '<td style="text-align: right; font-size: 10pt; color: #666; white-space: nowrap;">'.$taxLabel.':</td>';
        $output .= '<td style="text-align: right; font-size: 10pt; color: #333;">'.$currency_symbol.number_format($tax_amount, 2).'</td>';
        $output .= '</tr>';
    }
    
    // Total
    $output .= '<tr class="totals-row" style="border-top: 2px solid #333; margin-top: 15px;">';
    $output .= '<td style="text-align: right; font-size: 10pt; font-weight: bold; color: #333;">'.$lang['Total:'].'</td>';
    $output .= '<td style="text-align: right; font-size: 10pt; font-weight: bold; color: #333;">'.$currency_symbol.number_format($total, 2).'</td>';
    $output .= '</tr>';
    
    $output .= '</table>';
    $output .= '</td></tr>';
    $output .= '</table>';

    return $output;
}

// Clear any output that might have been generated
ob_clean();

// ------------------------------------------------------------------
// Initialize and Output PDF
// ------------------------------------------------------------------
$obj_pdf = new TCPDF('P', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$obj_pdf->SetCreator(PDF_CREATOR);
$obj_pdf->SetTitle("Invoice");
$obj_pdf->SetHeaderData('', '', PDF_HEADER_TITLE, PDF_HEADER_STRING);
$obj_pdf->setHeaderFont(array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
$obj_pdf->setFooterFont(array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
$obj_pdf->SetDefaultMonospacedFont('helvetica');
$obj_pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
$obj_pdf->SetMargins(15, 15, 15);
$obj_pdf->setPrintHeader(false);
$obj_pdf->setPrintFooter(false);
$obj_pdf->SetAutoPageBreak(true, 15);
$obj_pdf->SetFont('helvetica', '', 11);

// Add page
$obj_pdf->AddPage();

$obj_pdf->setJPEGQuality(75);
$obj_pdf->setImageScale(1.53);

// Fetch milestone data to get footer (before writing content)
$latestMile1 = milestone::findByMilestoneId($edit_id1);

// Write main content
$content = fetch_data($edit_id1);
$obj_pdf->writeHTML($content);

// Position footer at bottom of page if it exists
if (!empty($latestMile1->footer)) {
    // Get current page number and Y position after content
    $current_page = $obj_pdf->getPage();
    $current_y = $obj_pdf->GetY();
    
    // Get page dimensions
    $page_height = $obj_pdf->getPageHeight();
    $bottom_margin = 15; // Bottom margin in points
    
    // Calculate footer height estimate (very minimal padding)
    $footer_lines = substr_count($latestMile1->footer, "\n") + 1;
    // Estimate: 9pt font size * 1.6 line height = ~14.4pt per line, plus minimal padding
    $footer_height = ($footer_lines * 14.4) + 15; // Very minimal padding
    
    // Calculate bottom position (page height minus bottom margin)
    $bottom_position = $page_height - $bottom_margin;
    
    // If we're on page 1, always try to keep footer on page 1
    if ($current_page == 1) {
        // Calculate where footer would be at bottom of page 1
        $footer_y_at_bottom = $bottom_position - $footer_height;
        
        // Always position at bottom of page 1 if there's any space
        // Only use minimum spacing if footer would overlap content
        $minimum_spacing = 10;
        if ($footer_y_at_bottom < $current_y + $minimum_spacing) {
            // Footer would overlap - position right after content
            $footer_y = $current_y + $minimum_spacing;
        } else {
            // Footer fits - position at bottom of page
            $footer_y = $footer_y_at_bottom;
        }
        
        // Make sure we stay on page 1
        $obj_pdf->setPage(1);
        $obj_pdf->SetY($footer_y);
    } else {
        // We're already on a later page - position at bottom of current page
        $obj_pdf->SetY($bottom_position - $footer_height);
    }
    
    // Temporarily disable auto page break to prevent TCPDF from creating new page
    $old_auto_page_break = $obj_pdf->getAutoPageBreak();
    $old_break_margin = $obj_pdf->getBreakMargin();
    $obj_pdf->SetAutoPageBreak(false);
    
    // Write footer content with reduced spacing
    $footer_content = '<table cellpadding="0" cellspacing="0" style="width: 100%; margin-top: 10px; padding-top: 10px;">';
    $footer_content .= '<tr><td style="text-align: left; padding: 10px 0;">';
    $footer_content .= '<div style="font-size: 10pt; color: #666; line-height: 1.6;">';
    $footer_content .= nl2br(htmlspecialchars($latestMile1->footer));
    $footer_content .= '</div>';
    $footer_content .= '</td></tr>';
    $footer_content .= '</table>';
    
    $obj_pdf->writeHTML($footer_content, false, false, false, false, '');
    
    // Restore auto page break settings
    $obj_pdf->SetAutoPageBreak($old_auto_page_break, $old_break_margin);
}

// Clear any remaining output and send PDF
ob_end_clean();
$obj_pdf->Output('invoice.pdf', 'I');
?>

