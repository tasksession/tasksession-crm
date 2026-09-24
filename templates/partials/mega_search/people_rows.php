<?php
/** @var array $megaSearchRows @var array $megaSearchCtx */
global $connect, $database, $lang;

$ctx = $megaSearchCtx;
$rows = $megaSearchRows;
$lang = is_array($ctx['lang'] ?? null) && ($ctx['lang'] !== []) ? $ctx['lang'] : ($lang ?? []);
$invoiceModuleEnabled = mega_search_invoice_module_enabled($ctx);
$clientsTableLinkBase = (string) ($ctx['panel_base'] ?? '');
$clientsTableCtx = $ctx;
$clientsTableShowSerial = false;
$recentlyRegisteredUsers = [];
foreach ($rows as $row) {
    $recentlyRegisteredUsers[] = (object) $row;
}
if (($megaSearchOutputMode ?? 'full') === 'rows') {
    include dirname(__DIR__, 3) . '/partials/clients_table_rows.php';
    return;
}
?>
<div class="clearfix"></div>
<div class="table-responsive scroll-x mega-search-people-table-wrap">
    <table class="table table-new table-invoice h-100">
        <thead>
            <tr>
                <th>
                    <div class="checkbox check-invoice">
                        <input type="checkbox" id="select-all-clients">
                    </div>
                </th>
                <th style="min-width: 270px; text-align: left;"><?php echo htmlspecialchars($lang['Client'] ?? 'Client', ENT_QUOTES, 'UTF-8'); ?></th>
                <th style="text-align: left;"><?php echo htmlspecialchars($lang['Email'] ?? 'Email', ENT_QUOTES, 'UTF-8'); ?></th>
                <th><?php echo htmlspecialchars($lang['Projects'] ?? 'Projects', ENT_QUOTES, 'UTF-8'); ?></th>
                <th><?php echo htmlspecialchars($lang['Task'] ?? 'Task', ENT_QUOTES, 'UTF-8'); ?></th>
                <?php if ($invoiceModuleEnabled): ?>
                <th><?php echo htmlspecialchars($lang['Invoice Activity'] ?? ($lang['Invoice'] ?? 'Invoice activity'), ENT_QUOTES, 'UTF-8'); ?></th>
                <?php endif; ?>
                <th><?php echo htmlspecialchars($lang['Last login'] ?? 'Last login', ENT_QUOTES, 'UTF-8'); ?></th>
                <th class="min-width-200"><?php echo htmlspecialchars($lang['Action'] ?? 'Action', ENT_QUOTES, 'UTF-8'); ?></th>
            </tr>
        </thead>
        <tbody id="projects-tbl">
            <?php include dirname(__DIR__, 3) . '/partials/clients_table_rows.php'; ?>
        </tbody>
    </table>
</div>
