<?php
ob_start();

register_shutdown_function(static function (): void {
    $err = error_get_last();
    if (!$err || !in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return;
    }
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=UTF-8');
    }
    echo json_encode(['error' => 'server_error', 'message' => 'Fatal: ' . ($err['message'] ?? 'unknown')], JSON_UNESCAPED_UNICODE);
});

require_once __DIR__ . '/../includes/lib-initialize.php';
require_once __DIR__ . '/../includes/reports_common_helper.php';

if (!isset($session) || !$session->isLoggedIn()) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(401);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

session_write_close();

if (!function_exists('monthly_range_resolve_currency_filter')) {
    function monthly_range_resolve_currency_filter(): string
    {
        if (!empty($_POST['currency_code'])) {
            $code = trim((string) $_POST['currency_code']);
            $symbol = isset($_POST['currency_symbol']) ? trim((string) $_POST['currency_symbol']) : '$';
            return $code !== '' ? $code . ',' . $symbol : 'all';
        }
        if (isset($_POST['currency'])) {
            return (string) $_POST['currency'];
        }
        return 'all';
    }
}

function monthly_range_get_currency_symbol($currencyString) {
    if (empty($currencyString)) {
        return '$';
    }
    if (strlen($currencyString) <= 3) {
        return $currencyString;
    }
    $parts = explode(',', $currencyString);
    if (count($parts) > 1) {
        $symbol = trim($parts[1]);
        $symbol = str_replace([':', ' '], '', $symbol);
        return $symbol;
    }
    return '$';
}

function monthly_range_invoice_total($milestone) {
    $parts = monthly_range_invoice_parts($milestone);
    return $parts['total'];
}

function monthly_range_invoice_parts($milestone, $preloadedItems = null) {
    require_once __DIR__ . '/../includes/invoice_item.php';
    $subtotal = 0;
    if ($preloadedItems !== null) {
        $invoiceItems = (is_array($preloadedItems) && count($preloadedItems) > 0) ? $preloadedItems : false;
    } else {
        $invoiceItems = InvoiceItem::findByMilestoneId($milestone->id);
    }
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

function monthly_range_sum_bucket(array $milestones, ?array $itemsByMilestone = null) {
    $total = 0.0;
    $tax = 0.0;
    foreach ($milestones as $milestone) {
        $mid = (int) ($milestone->id ?? 0);
        $items = null;
        if (is_array($itemsByMilestone) && $mid > 0) {
            $items = $itemsByMilestone[$mid] ?? [];
        }
        $parts = monthly_range_invoice_parts($milestone, $items);
        $total += $parts['total'];
        $tax += $parts['tax'];
    }
    return ['total' => $total, 'tax' => $tax, 'count' => count($milestones)];
}

/**
 * @return array<int, list<object>>
 */
function monthly_range_batch_invoice_items(array $milestoneLists): array
{
    require_once __DIR__ . '/../includes/invoice_item.php';
    $ids = [];
    foreach ($milestoneLists as $list) {
        if (!is_array($list)) {
            continue;
        }
        foreach ($list as $m) {
            $mid = (int) ($m->id ?? 0);
            if ($mid > 0) {
                $ids[] = $mid;
            }
        }
    }
    return InvoiceItem::findByMilestoneIds($ids);
}

/**
 * Fetch all milestones for a status once, then bucket by day or month in PHP.
 *
 * @return array{0: list<object>, 1: list<object>, 2: list<object>}
 */
function monthly_range_fetch_status_lists($database, $start_date, $end_date, $currency_condition)
{
    $ms_esc = $database->escapeValue($start_date);
    $me_esc = $database->escapeValue($end_date);
    $cols = 'm.id, m.budget, m.sales_tax, m.sales_tax_type, m.discount, m.discount_type, m.status, m.releaseDate, m.deadline, m.issue_date';
    $paid = milestone::findBySql(
        "SELECT {$cols} FROM milestones m
         LEFT JOIN projects p ON m.p_id = p.p_id
         WHERE m.status = 1
         AND m.releaseDate IS NOT NULL
         AND m.releaseDate >= '{$ms_esc}'
         AND m.releaseDate <= '{$me_esc}'
         {$currency_condition}"
    );
    $unpaid = milestone::findBySql(
        "SELECT {$cols} FROM milestones m
         LEFT JOIN projects p ON m.p_id = p.p_id
         WHERE m.status = 0
         AND m.deadline IS NOT NULL
         AND m.deadline >= '{$ms_esc}'
         AND m.deadline <= '{$me_esc}'
         {$currency_condition}"
    );
    $cancelledEffDate = reports_sql_milestone_effective_date('m.');
    $cancelled = milestone::findBySql(
        "SELECT {$cols} FROM milestones m
         LEFT JOIN projects p ON m.p_id = p.p_id
         WHERE m.status = 2
         AND ({$cancelledEffDate}) >= '{$ms_esc}'
         AND ({$cancelledEffDate}) <= '{$me_esc}'
         {$currency_condition}"
    );
    return [
        is_array($paid) ? $paid : [],
        is_array($unpaid) ? $unpaid : [],
        is_array($cancelled) ? $cancelled : [],
    ];
}

function monthly_range_bucket_key($milestone, $status, $use_daily)
{
    if ((int) $status === 1) {
        $d = (string) ($milestone->releaseDate ?? '');
    } elseif ((int) $status === 0) {
        $d = (string) ($milestone->deadline ?? '');
    } else {
        $issue = (string) ($milestone->issue_date ?? '');
        if ($issue !== '' && $issue !== '0000-00-00') {
            $d = $issue;
        } else {
            $d = (string) ($milestone->deadline ?? '');
        }
    }
    if ($d === '' || $d === '0000-00-00') {
        return '';
    }
    $ts = strtotime($d);
    if ($ts === false) {
        return '';
    }
    return $use_daily ? date('Y-m-d', $ts) : date('Y-m', $ts);
}

function monthly_range_paid_total_for_range($database, $start_date, $end_date, $currency_condition) {
    $ms_esc = $database->escapeValue($start_date);
    $me_esc = $database->escapeValue($end_date);
    $milestones_paid = milestone::findBySql(
        "SELECT m.id, m.budget, m.sales_tax, m.sales_tax_type, m.discount, m.discount_type FROM milestones m
         LEFT JOIN projects p ON m.p_id = p.p_id
         WHERE m.status = 1
         AND m.releaseDate IS NOT NULL
         AND m.releaseDate >= '{$ms_esc}'
         AND m.releaseDate <= '{$me_esc}'
         {$currency_condition}"
    );
    if (!is_array($milestones_paid)) {
        $milestones_paid = [];
    }
    $items = monthly_range_batch_invoice_items([$milestones_paid]);
    $bucket = monthly_range_sum_bucket($milestones_paid, $items);
    return $bucket['total'];
}

function monthly_range_pct_change($current, $previous) {
    $current = (float) $current;
    $previous = (float) $previous;
    if ($previous <= 0) {
        return $current > 0 ? 100 : 0;
    }
    $pct = (int) round((($current - $previous) / $previous) * 100);
    if ($pct > 999) {
        return 999;
    }
    if ($pct < -999) {
        return -999;
    }
    return $pct;
}

while (ob_get_level() > 0) {
    ob_end_clean();
}
header('Content-Type: application/json; charset=UTF-8');

try {
    global $database;

    $start_date = isset($_POST['start_date']) ? $_POST['start_date'] : date('Y-m-01');
    $end_date = isset($_POST['end_date']) ? $_POST['end_date'] : date('Y-m-d');
    $currency_filter = monthly_range_resolve_currency_filter();

    $start_ts = strtotime($start_date);
    $end_ts = strtotime($end_date);
    if ($start_ts === false) {
        $start_ts = strtotime(date('Y-m-01'));
    }
    if ($end_ts === false) {
        $end_ts = time();
    }
    if ($end_ts < $start_ts) {
        $tmp = $start_ts;
        $start_ts = $end_ts;
        $end_ts = $tmp;
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
    $cancelled_count = 0;

    $currency_condition = '';
    if ($currency_filter !== 'all') {
        $currency_parts = explode(',', $currency_filter);
        $currency_code = trim($currency_parts[0]);
        $currency_code = $database->escapeValue($currency_code);
        $currency_condition = " AND (m.currency LIKE '{$currency_code},%' OR m.currency = '{$currency_code}' OR m.currency LIKE '%,{$currency_code}')";
    }

    // Short ranges (Last 7/30/90 days): daily points; longer ranges: monthly.
    // Fetch each status once for the full range (3 queries), batch invoice_items, bucket in PHP.
    $days_span = (int) floor(($end_ts - $start_ts) / 86400) + 1;
    $use_daily = ($days_span <= 92);

    [$allPaid, $allUnpaid, $allCancelled] = monthly_range_fetch_status_lists(
        $database,
        $start_date,
        $end_date,
        $currency_condition
    );
    $itemsByMilestone = monthly_range_batch_invoice_items([$allPaid, $allUnpaid, $allCancelled]);

    $paidByKey = [];
    $unpaidByKey = [];
    $cancelledByKey = [];
    foreach ($allPaid as $m) {
        $k = monthly_range_bucket_key($m, 1, $use_daily);
        if ($k === '') {
            continue;
        }
        if (!isset($paidByKey[$k])) {
            $paidByKey[$k] = [];
        }
        $paidByKey[$k][] = $m;
    }
    foreach ($allUnpaid as $m) {
        $k = monthly_range_bucket_key($m, 0, $use_daily);
        if ($k === '') {
            continue;
        }
        if (!isset($unpaidByKey[$k])) {
            $unpaidByKey[$k] = [];
        }
        $unpaidByKey[$k][] = $m;
    }
    foreach ($allCancelled as $m) {
        $k = monthly_range_bucket_key($m, 2, $use_daily);
        if ($k === '') {
            continue;
        }
        if (!isset($cancelledByKey[$k])) {
            $cancelledByKey[$k] = [];
        }
        $cancelledByKey[$k][] = $m;
    }

    if ($use_daily) {
        $cur = $start_ts;
        while ($cur <= $end_ts) {
            $key = date('Y-m-d', $cur);
            $labels[] = date('M j', $cur);
            $paidBucket = monthly_range_sum_bucket($paidByKey[$key] ?? [], $itemsByMilestone);
            $unpaidBucket = monthly_range_sum_bucket($unpaidByKey[$key] ?? [], $itemsByMilestone);
            $cancelledBucket = monthly_range_sum_bucket($cancelledByKey[$key] ?? [], $itemsByMilestone);
            $paid_count += $paidBucket['count'];
            $unpaid_count += $unpaidBucket['count'];
            $cancelled_count += $cancelledBucket['count'];
            $paid[] = $paidBucket['total'];
            $unpaid[] = $unpaidBucket['total'];
            $cancelled[] = $cancelledBucket['total'];
            $paid_total += $paidBucket['total'];
            $unpaid_total += $unpaidBucket['total'];
            $cancelled_total += $cancelledBucket['total'];
            $sales_tax_total += $paidBucket['tax'] + $unpaidBucket['tax'] + $cancelledBucket['tax'];
            $cur = strtotime('+1 day', $cur);
        }
    } else {
        $cur = strtotime(date('Y-m-01', $start_ts));
        $end_month = strtotime(date('Y-m-01', $end_ts));
        while ($cur <= $end_month) {
            $key = date('Y-m', $cur);
            $labels[] = date('Y M', $cur);
            $paidBucket = monthly_range_sum_bucket($paidByKey[$key] ?? [], $itemsByMilestone);
            $unpaidBucket = monthly_range_sum_bucket($unpaidByKey[$key] ?? [], $itemsByMilestone);
            $cancelledBucket = monthly_range_sum_bucket($cancelledByKey[$key] ?? [], $itemsByMilestone);
            $paid_count += $paidBucket['count'];
            $unpaid_count += $unpaidBucket['count'];
            $cancelled_count += $cancelledBucket['count'];
            $paid[] = $paidBucket['total'];
            $unpaid[] = $unpaidBucket['total'];
            $cancelled[] = $cancelledBucket['total'];
            $paid_total += $paidBucket['total'];
            $unpaid_total += $unpaidBucket['total'];
            $cancelled_total += $cancelledBucket['total'];
            $sales_tax_total += $paidBucket['tax'] + $unpaidBucket['tax'] + $cancelledBucket['tax'];
            $cur = strtotime('+1 month', $cur);
        }
    }

    // Chart.js needs 2+ points to draw a line
    if (count($labels) === 1) {
        $labels[] = $labels[0];
        $paid[] = $paid[0];
        $unpaid[] = $unpaid[0];
        $cancelled[] = $cancelled[0];
    }

    $currency_symbol = '$';
    if ($currency_filter !== 'all') {
        $currency_symbol = monthly_range_get_currency_symbol($currency_filter);
    }

    // Previous equal-length period for "vs last …" comparison (paid totals).
    $span_days = max(1, (int) floor(($end_ts - $start_ts) / 86400) + 1);
    $prev_end_ts = strtotime('-1 day', $start_ts);
    $prev_start_ts = strtotime('-' . ($span_days - 1) . ' days', $prev_end_ts);
    $prev_start = date('Y-m-d', $prev_start_ts);
    $prev_end = date('Y-m-d', $prev_end_ts);
    $prev_paid_total = monthly_range_paid_total_for_range($database, $prev_start, $prev_end, $currency_condition);
    $paid_pct_change = monthly_range_pct_change($paid_total, $prev_paid_total);
    if ($span_days <= 10) {
        $vs_label = 'vs last period';
    } elseif ($span_days <= 45) {
        $vs_label = 'vs last month';
    } elseif ($span_days >= 360) {
        $vs_label = 'vs last year';
    } else {
        $vs_label = 'vs last period';
    }

    echo json_encode([
        'labels' => $labels,
        'paid' => $paid,
        'unpaid' => $unpaid,
        'cancelled' => $cancelled,
        'paid_total' => $paid_total,
        'unpaid_total' => $unpaid_total,
        'cancelled_total' => $cancelled_total,
        'sales_tax_total' => round($sales_tax_total, 2),
        'paid_count' => $paid_count,
        'unpaid_count' => $unpaid_count,
        'cancelled_count' => $cancelled_count,
        'currency_symbol' => $currency_symbol,
        'granularity' => $use_daily ? 'day' : 'month',
        'prev_paid_total' => round($prev_paid_total, 2),
        'paid_pct_change' => $paid_pct_change,
        'vs_label' => $vs_label,
    ]);
} catch (Throwable $e) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    error_log('[monthly_range.php] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['error' => 'server_error']);
}
