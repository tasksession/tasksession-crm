<?php
/**
 * AJAX endpoint to get filtered financial data for a specific user
 * Used by the profile page financials chart
 * Based on monthly_range.php logic but for user-specific data
 */

ob_start();
require_once __DIR__ . '/../includes/lib-initialize.php';
require_once __DIR__ . '/../includes/reports_common_helper.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in
if (!$session->isLoggedIn()) {
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

// Get parameters
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$currency_filter = isset($_GET['currency']) ? $_GET['currency'] : 'all';

if ($user_id <= 0) {
    echo json_encode(['error' => 'Invalid user ID']);
    exit;
}

require_once __DIR__ . '/../includes/permissions.php';
if (function_exists('ensure_user_permissions')) {
    ensure_user_permissions($connect);
}

$profileUser = class_exists('user') ? user::findById($user_id) : null;
if (!$profileUser) {
    echo json_encode(['error' => 'User not found']);
    exit;
}

$sessionUid = (int) $session->userId;
$isSelf = ($user_id === $sessionUid);
$targetStatus = (int) ($profileUser->accountStatus ?? 0);
$actorStatus = (int) ($_SESSION['accountStatus'] ?? 0);

if ($actorStatus === 2 && !$isSelf) {
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

// Staff cannot pull other users' profile financials without the matching view permission.
if ($actorStatus === 3 && !$isSelf && function_exists('has_permission')) {
    if ($targetStatus === 2 && !has_permission('client_view')) {
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }
    if (($targetStatus === 1 || $targetStatus === 3) && !has_permission('staff_view')) {
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }
}

// Helper function for currency symbols
function getCurrencySymbol($currencyString) {
    if (empty($currencyString)) {
        return '$';
    }
    
    // If it's already just a symbol, return it
    if (strlen($currencyString) <= 3) {
        return $currencyString;
    }
    
    // Extract symbol from "CODE,SYMBOL" format
    $parts = explode(',', $currencyString);
    if (count($parts) > 1) {
        $symbol = trim($parts[1]);
        // Remove any colon or space from the symbol
        $symbol = str_replace([':', ' '], '', $symbol);
        return $symbol;
    }
    
    return '$';
}

/**
 * Invoice total + tax (same rules as before; tax exposed for Sales Tax UI).
 */
function userDataInvoiceParts($milestone) {
    require_once(__DIR__ . '/../includes/invoice_item.php');
    $subtotal = 0;
    $invoiceItems = InvoiceItem::findByMilestoneId($milestone->id);
    if ($invoiceItems && count($invoiceItems) > 0) {
        foreach ($invoiceItems as $item) {
            $subtotal += floatval($item->rate) * floatval($item->quantity);
        }
    } else {
        $subtotal = floatval($milestone->budget ?? 0);
    }
    
    $discount = isset($milestone->discount) ? floatval($milestone->discount) : 0;
    $discount_type = isset($milestone->discount_type) ? $milestone->discount_type : 'percentage';
    
    $discount_amount = 0;
    if ($discount > 0 && $subtotal > 0) {
        if ($discount_type === 'percentage') {
            $discount_amount = ($subtotal * $discount) / 100;
        } else {
            $discount_amount = $discount;
        }
    }
    
    $amount_after_discount = $subtotal - $discount_amount;
    
    $sales_tax = isset($milestone->sales_tax) ? floatval($milestone->sales_tax) : 0;
    $sales_tax_type = isset($milestone->sales_tax_type) ? $milestone->sales_tax_type : 'percentage';
    
    $tax_amount = 0;
    if ($sales_tax > 0 && $amount_after_discount > 0) {
        if ($sales_tax_type === 'percentage') {
            $tax_amount = ($amount_after_discount * $sales_tax) / 100;
        } else {
            $tax_amount = $sales_tax;
        }
    }
    
    return [
        'total' => $amount_after_discount + $tax_amount,
        'tax' => $tax_amount,
    ];
}

function calculateInvoiceTotal($milestone) {
    $parts = userDataInvoiceParts($milestone);
    return $parts['total'];
}

function userDataSumBucket($milestones) {
    $total = 0.0;
    $tax = 0.0;
    if (!is_array($milestones)) {
        $milestones = [];
    }
    foreach ($milestones as $milestone) {
        $parts = userDataInvoiceParts($milestone);
        $total += $parts['total'];
        $tax += $parts['tax'];
    }
    return ['total' => $total, 'tax' => $tax, 'count' => count($milestones)];
}

function userDataPctChange($current, $previous) {
    $current = (float) $current;
    $previous = (float) $previous;
    if ($previous == 0.0) {
        return $current > 0 ? 100 : 0;
    }
    return (int) round((($current - $previous) / $previous) * 100);
}

// Convert to timestamps
$start_ts = strtotime($start_date);
$end_ts = strtotime($end_date);
if ($start_ts === false || $end_ts === false) {
    echo json_encode(['error' => 'Invalid date range']);
    exit;
}
$start_date = date('Y-m-d', $start_ts);
$end_date = date('Y-m-d', $end_ts);
$start_esc = $database->escapeValue($start_date);
$end_esc = $database->escapeValue($end_date);

$labels = [];
$paid = [];
$unpaid = [];
$cancelled = [];
$paid_total = 0;
$unpaid_total = 0;
$cancelled_total = 0;
$sales_tax_total = 0;
$paid_count = 0;
$unpaid_count = 0;

// Build currency filter condition
$currency_condition = '';
if ($currency_filter !== 'all') {
    $currency_parts = explode(',', $currency_filter);
    $currency_code = trim($currency_parts[0]);
    $currency_code = $database->escapeValue($currency_code);
    $currency_condition = " AND (m.currency LIKE '{$currency_code},%' OR m.currency = '{$currency_code}' OR m.currency LIKE '%,{$currency_code}')";
}

// Determine user role to tailor filters (client vs staff)
$is_staff_profile = ((int)$profileUser->accountStatus === 3 || (int)$profileUser->accountStatus === 1);

if ($is_staff_profile) {
    $user_condition = " AND m.created_by = $user_id";
} else {
    $user_condition = " AND ((m.p_id IS NOT NULL AND m.p_id > 0 AND p.p_id IS NOT NULL AND (p.c_id = $user_id OR p.main_client_id = $user_id OR FIND_IN_SET($user_id, p.c_ids) > 0)) OR ((m.p_id IS NULL OR m.p_id = 0) AND m.c_id = $user_id))";
}
$join_clause = "LEFT JOIN projects p ON m.p_id = p.p_id";
$cancelledEffDate = reports_sql_milestone_effective_date('m.');

$days_span = (int) floor(($end_ts - $start_ts) / 86400) + 1;
$use_daily = ($days_span <= 92);

if ($use_daily) {
    $cur = $start_ts;
    while ($cur <= $end_ts) {
        $day = date('Y-m-d', $cur);
        $day_esc = $database->escapeValue($day);
        $labels[] = date('M j', $cur);

        $milestones_paid = milestone::findBySql(
            "SELECT m.*, m.budget, m.sales_tax, m.sales_tax_type, m.discount, m.discount_type FROM milestones m
             {$join_clause}
             WHERE m.status = 1
             AND m.releaseDate IS NOT NULL
             AND m.releaseDate = '{$day_esc}'
             {$currency_condition}
             {$user_condition}"
        );
        $milestones_unpaid = milestone::findBySql(
            "SELECT m.*, m.budget, m.sales_tax, m.sales_tax_type, m.discount, m.discount_type FROM milestones m
             {$join_clause}
             WHERE m.status = 0
             AND m.deadline IS NOT NULL
             AND m.deadline = '{$day_esc}'
             {$currency_condition}
             {$user_condition}"
        );
        $milestones_cancelled = milestone::findBySql(
            "SELECT m.*, m.budget, m.sales_tax, m.sales_tax_type, m.discount, m.discount_type FROM milestones m
             {$join_clause}
             WHERE m.status = 2
             AND ({$cancelledEffDate}) = '{$day_esc}'
             {$currency_condition}
             {$user_condition}"
        );

        $paidBucket = userDataSumBucket($milestones_paid);
        $unpaidBucket = userDataSumBucket($milestones_unpaid);
        $cancelledBucket = userDataSumBucket($milestones_cancelled);

        $paid[] = $paidBucket['total'];
        $unpaid[] = $unpaidBucket['total'];
        $cancelled[] = $cancelledBucket['total'];
        $paid_total += $paidBucket['total'];
        $unpaid_total += $unpaidBucket['total'];
        $cancelled_total += $cancelledBucket['total'];
        $sales_tax_total += $paidBucket['tax'] + $unpaidBucket['tax'] + $cancelledBucket['tax'];
        $paid_count += $paidBucket['count'];
        $unpaid_count += $unpaidBucket['count'];

        $cur = strtotime('+1 day', $cur);
    }
} else {
    $cur = strtotime(date('Y-m-01', $start_ts));
    $end_month = strtotime(date('Y-m-01', $end_ts));
    while ($cur <= $end_month) {
        $labels[] = date('Y M', $cur);

        $month_start = date('Y-m-01', $cur);
        $month_end = date('Y-m-t', $cur);
        if ($month_start < $start_date) {
            $month_start = $start_date;
        }
        if ($month_end > $end_date) {
            $month_end = $end_date;
        }
        $ms_esc = $database->escapeValue($month_start);
        $me_esc = $database->escapeValue($month_end);

        $milestones_paid = milestone::findBySql(
            "SELECT m.*, m.budget, m.sales_tax, m.sales_tax_type, m.discount, m.discount_type FROM milestones m
             {$join_clause}
             WHERE m.status = 1
             AND m.releaseDate IS NOT NULL
             AND m.releaseDate >= '{$ms_esc}'
             AND m.releaseDate <= '{$me_esc}'
             {$currency_condition}
             {$user_condition}"
        );
        $milestones_unpaid = milestone::findBySql(
            "SELECT m.*, m.budget, m.sales_tax, m.sales_tax_type, m.discount, m.discount_type FROM milestones m
             {$join_clause}
             WHERE m.status = 0
             AND m.deadline IS NOT NULL
             AND m.deadline >= '{$ms_esc}'
             AND m.deadline <= '{$me_esc}'
             {$currency_condition}
             {$user_condition}"
        );
        $milestones_cancelled = milestone::findBySql(
            "SELECT m.*, m.budget, m.sales_tax, m.sales_tax_type, m.discount, m.discount_type FROM milestones m
             {$join_clause}
             WHERE m.status = 2
             AND ({$cancelledEffDate}) >= '{$ms_esc}'
             AND ({$cancelledEffDate}) <= '{$me_esc}'
             {$currency_condition}
             {$user_condition}"
        );

        $paidBucket = userDataSumBucket($milestones_paid);
        $unpaidBucket = userDataSumBucket($milestones_unpaid);
        $cancelledBucket = userDataSumBucket($milestones_cancelled);

        $paid[] = $paidBucket['total'];
        $unpaid[] = $unpaidBucket['total'];
        $cancelled[] = $cancelledBucket['total'];
        $paid_total += $paidBucket['total'];
        $unpaid_total += $unpaidBucket['total'];
        $cancelled_total += $cancelledBucket['total'];
        $sales_tax_total += $paidBucket['tax'] + $unpaidBucket['tax'] + $cancelledBucket['tax'];
        $paid_count += $paidBucket['count'];
        $unpaid_count += $unpaidBucket['count'];

        $cur = strtotime('+1 month', $cur);
    }
}

// Previous period paid total for % meta (same span immediately before start)
$range_days = max(1, (int) floor(($end_ts - $start_ts) / 86400) + 1);
$prev_end_ts = strtotime('-1 day', $start_ts);
$prev_start_ts = strtotime('-' . ($range_days - 1) . ' days', $prev_end_ts);
$prev_start_esc = $database->escapeValue(date('Y-m-d', $prev_start_ts));
$prev_end_esc = $database->escapeValue(date('Y-m-d', $prev_end_ts));
$prev_paid_milestones = milestone::findBySql(
    "SELECT m.*, m.budget, m.sales_tax, m.sales_tax_type, m.discount, m.discount_type FROM milestones m
     {$join_clause}
     WHERE m.status = 1
     AND m.releaseDate IS NOT NULL
     AND m.releaseDate >= '{$prev_start_esc}'
     AND m.releaseDate <= '{$prev_end_esc}'
     {$currency_condition}
     {$user_condition}"
);
$prev_paid_total = userDataSumBucket($prev_paid_milestones)['total'];
$paid_pct_change = userDataPctChange($paid_total, $prev_paid_total);

if ($range_days <= 31) {
    $vs_label = 'vs last period';
} elseif ($range_days <= 92) {
    $vs_label = 'vs last period';
} elseif ($range_days >= 360) {
    $vs_label = 'vs last year';
} else {
    $vs_label = 'vs last period';
}

// Get currency symbol for display
$currency_symbol = '$';
if ($currency_filter !== 'all') {
    $currency_symbol = getCurrencySymbol($currency_filter);
}

echo json_encode([
    'success' => true,
    'monthLabels' => $labels,
    'labels' => $labels,
    'monthlyEarnings' => $paid,
    'monthlyUnpaid' => $unpaid,
    'monthlyCancelled' => $cancelled,
    'paid' => $paid,
    'unpaid' => $unpaid,
    'cancelled' => $cancelled,
    'totalPaid' => $paid_total,
    'totalUnpaid' => $unpaid_total,
    'paid_total' => $paid_total,
    'unpaid_total' => $unpaid_total,
    'cancelled_total' => $cancelled_total,
    'sales_tax_total' => round($sales_tax_total, 2),
    'paid_count' => $paid_count,
    'unpaid_count' => $unpaid_count,
    'total_count' => $paid_count + $unpaid_count,
    'paid_pct_change' => $paid_pct_change,
    'vs_label' => $vs_label,
    'currency_symbol' => $currency_symbol
]);
?>
