<?php
/**
 * Sales Statistics AJAX (clean endpoint) — replaces monthly_range.php for dashboard charts.
 */
ob_start();

require_once __DIR__ . '/../includes/lib-initialize.php';
require_once __DIR__ . '/../includes/reports_common_helper.php';

function month_report_json_exit(array $payload, int $code = 200): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($code);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload);
    exit;
}

if (!isset($session) || !$session->isLoggedIn()) {
    month_report_json_exit(['error' => 'Unauthorized'], 401);
}

try {
    $start_date = isset($_POST['start_date']) ? (string)$_POST['start_date'] : date('Y-m-01');
    $end_date = isset($_POST['end_date']) ? (string)$_POST['end_date'] : date('Y-m-d');
    $currency_filter = isset($_POST['currency']) ? (string)$_POST['currency'] : 'all';

    $start_ts = strtotime($start_date);
    $end_ts = strtotime($end_date);
    if ($start_ts === false) {
        $start_ts = strtotime(date('Y-m-01'));
    }
    if ($end_ts === false) {
        $end_ts = strtotime(date('Y-m-d'));
    }
    if ($start_ts > $end_ts) {
        $tmp = $start_ts;
        $start_ts = $end_ts;
        $end_ts = $tmp;
    }
    $start_date = date('Y-m-d', $start_ts);
    $end_date = date('Y-m-d', $end_ts);

    $labels = [];
    $paid = [];
    $unpaid = [];
    $paid_total = 0.0;
    $unpaid_total = 0.0;

    $currency_condition = '';
    if ($currency_filter !== 'all' && $currency_filter !== '') {
        $currency_parts = explode(',', $currency_filter);
        $currency_code = trim($currency_parts[0]);
        if ($currency_code !== '') {
            global $database;
            $esc = $database->escapeValue($currency_code);
            $currency_condition = " AND (m.currency LIKE '{$esc},%' OR m.currency = '{$esc}' OR m.currency LIKE '%,{$esc}')";
        }
    }

    $months = [];
    $cur = strtotime(date('Y-m-01', $start_ts));
    $end_month = strtotime(date('Y-m-01', $end_ts));
    while ($cur <= $end_month) {
        $months[] = [
            'year' => (int)date('Y', $cur),
            'month' => (int)date('n', $cur),
            'label' => date('Y M', $cur),
        ];
        $cur = strtotime('+1 month', $cur);
    }

    foreach ($months as $m) {
        $labels[] = $m['label'];
        $year = (int)$m['year'];
        $month = (int)$m['month'];

        $milestones_paid = milestone::findBySql(
            "SELECT m.*, m.budget, m.sales_tax, m.sales_tax_type, m.discount, m.discount_type FROM milestones m
             LEFT JOIN projects p ON m.p_id = p.p_id
             WHERE m.status = 1
             AND m.releaseDate IS NOT NULL
             AND YEAR(m.releaseDate) = {$year}
             AND MONTH(m.releaseDate) = {$month}
             AND m.releaseDate >= '{$start_date}'
             AND m.releaseDate <= '{$end_date}'
             {$currency_condition}"
        );

        $milestones_unpaid = milestone::findBySql(
            "SELECT m.*, m.budget, m.sales_tax, m.sales_tax_type, m.discount, m.discount_type FROM milestones m
             LEFT JOIN projects p ON m.p_id = p.p_id
             WHERE m.status = 0
             AND m.deadline IS NOT NULL
             AND YEAR(m.deadline) = {$year}
             AND MONTH(m.deadline) = {$month}
             AND m.deadline >= '{$start_date}'
             AND m.deadline <= '{$end_date}'
             {$currency_condition}"
        );

        if (!is_array($milestones_paid)) {
            $milestones_paid = [];
        }
        if (!is_array($milestones_unpaid)) {
            $milestones_unpaid = [];
        }

        $sum_paid = 0.0;
        $sum_unpaid = 0.0;
        foreach ($milestones_paid as $milestone) {
            $sum_paid += reports_invoice_total($milestone);
        }
        foreach ($milestones_unpaid as $milestone) {
            $sum_unpaid += reports_invoice_total($milestone);
        }

        $paid[] = round($sum_paid, 2);
        $unpaid[] = round($sum_unpaid, 2);
        $paid_total += $sum_paid;
        $unpaid_total += $sum_unpaid;
    }

    $currency_symbol = reports_currency_symbol($currency_filter !== 'all' ? $currency_filter : '');

    month_report_json_exit([
        'labels' => $labels,
        'paid' => $paid,
        'unpaid' => $unpaid,
        'paid_total' => round($paid_total, 2),
        'unpaid_total' => round($unpaid_total, 2),
        'currency_symbol' => $currency_symbol,
    ]);
} catch (Throwable $e) {
    error_log('[month-report.php] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    month_report_json_exit(['error' => 'server_error', 'message' => $e->getMessage()], 500);
}
