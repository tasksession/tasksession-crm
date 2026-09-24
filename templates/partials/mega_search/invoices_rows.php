<?php
/** @var array $megaSearchRows @var array $megaSearchCtx */
global $database, $lang, $url, $db;

$ctx = $megaSearchCtx;
$rows = $megaSearchRows;
$lang = is_array($ctx['lang'] ?? null) && ($ctx['lang'] !== []) ? $ctx['lang'] : ($lang ?? []);
$invoiceTableRows = $rows;
$invoicesTableLinkBase = (string) ($ctx['panel_base'] ?? '');
$invoicesTableFormAction = $invoicesTableLinkBase . 'invoices';
$invoicesPdfUrl = function_exists('tasksession_download_pdf_href')
    ? tasksession_download_pdf_href()
    : (rtrim((string) ($ctx['url'] ?? ''), '/') . '/templates/download_pdf');

ob_start();
include dirname(__DIR__, 3) . '/partials/invoices_table_rows.php';
$invoiceRowsHtml = ob_get_clean();
$invoiceRowsHtml = preg_replace('/\xEF\xBB\xBF/u', '', $invoiceRowsHtml);
$invoiceRowsHtml = preg_replace('/\x{FEFF}/u', '', $invoiceRowsHtml);

if (($megaSearchOutputMode ?? 'full') === 'rows') {
    echo $invoiceRowsHtml;
    return;
}

echo '<div class="table-responsive scroll-x mega-search-invoices-table-wrap"><table class="table table-new table-invoice h-100"><thead><tr>';
echo '<th><div class="checkbox check-invoice"><input type="checkbox" id="select-all-invoices"></div></th>';
echo '<th width="26%">' . htmlspecialchars($lang['Invoice Title'] ?? 'Invoice title', ENT_QUOTES, 'UTF-8') . '</th>';
echo '<th width="20%">' . htmlspecialchars($lang['Client'] ?? 'Client', ENT_QUOTES, 'UTF-8') . '</th>';
echo '<th>' . htmlspecialchars($lang['Status'] ?? 'Status', ENT_QUOTES, 'UTF-8') . '</th>';
echo '<th class="table-due">' . htmlspecialchars($lang['Due date'] ?? 'Due date', ENT_QUOTES, 'UTF-8') . '</th>';
echo '<th class="table-amount">' . htmlspecialchars($lang['Amount'] ?? 'Amount', ENT_QUOTES, 'UTF-8') . '</th>';
echo '<th>' . htmlspecialchars($lang['Created By'] ?? 'Created by', ENT_QUOTES, 'UTF-8') . '</th>';
echo '<th>' . htmlspecialchars($lang['Recurring'] ?? 'Recurring', ENT_QUOTES, 'UTF-8') . '</th>';
echo '<th style="text-align:left;">' . htmlspecialchars($lang['Invoice'] ?? 'Invoice', ENT_QUOTES, 'UTF-8') . '</th>';
echo '<th class="min-width-200">' . htmlspecialchars($lang['Action'] ?? 'Action', ENT_QUOTES, 'UTF-8') . '</th>';
echo '</tr></thead><tbody id="projects-tbl">' . $invoiceRowsHtml . '</tbody></table></div>';
