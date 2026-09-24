<?php
/** @var array $megaSearchRows @var array $megaSearchCtx */
global $connect, $url;

$ctx = $megaSearchCtx;
$rows = $megaSearchRows;
$base = (string) ($ctx['panel_base'] ?? '');

if (!function_exists('mega_search_render_company_row')) {
    function mega_search_render_company_row(array $row, array $ctx, string $base): void
    {
        global $connect, $url;
        $cid = (int) ($row['id'] ?? 0);
        $cname = (string) ($row['name'] ?? '');
        $phone = trim((string) ($row['phone'] ?? ''));
        $cur = (string) ($row['currency'] ?? '—');
        $createdRaw = isset($row['created_at']) ? (string) $row['created_at'] : '';
        $createdDisp = $createdRaw !== '' && strtotime($createdRaw) ? date('M j, Y', strtotime($createdRaw)) : '—';
        $memberCount = 0;
        $creatorName = '—';
        if ($connect instanceof mysqli && $cid > 0) {
            $mcRes = mysqli_query($connect, 'SELECT COUNT(*) AS cnt FROM client_company_members WHERE company_id = ' . $cid);
            $mcRow = $mcRes ? mysqli_fetch_assoc($mcRes) : null;
            $memberCount = $mcRow ? (int) ($mcRow['cnt'] ?? 0) : 0;
            $createdBy = (int) ($row['created_by'] ?? 0);
            if ($createdBy > 0) {
                $creator = User::findById($createdBy);
                if ($creator) {
                    $creatorName = trim((string) ($creator->firstName ?? ''));
                    if ($creatorName === '') {
                        $creatorName = (string) ($creator->email ?? '—');
                    }
                }
            }
        }
        ?>
<tr>
	<td>
		<div class="checkbox check-invoice">
			<input type="checkbox" id="company-<?php echo $cid; ?>" name="company-checkbox" class="company-bulk-checkbox" value="<?php echo $cid; ?>">
			<label for="company-<?php echo $cid; ?>"></label>
		</div>
	</td>
	<td style="text-align: left;">
		<div class="d-flex align-items-center col-gap-10">
			<?php
            $logoRelTbl = isset($row['logo_path']) ? trim((string) $row['logo_path']) : '';
            if ($logoRelTbl !== '') {
                $logoUrlTbl = rtrim((string) $url, '/') . '/' . ltrim($logoRelTbl, '/');
                echo '<img src="' . htmlspecialchars($logoUrlTbl, ENT_QUOTES, 'UTF-8') . '" alt="" class="rounded-circle" style="width:36px;height:36px;object-fit:cover;">';
            }
            ?>
			<span><?php echo htmlspecialchars($cname, ENT_QUOTES, 'UTF-8'); ?></span>
		</div>
	</td>
	<td class="clients-rpt" style="text-align:left;"><?php echo (int) $memberCount; ?></td>
	<td><?php echo $phone !== '' ? htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') : '—'; ?></td>
	<td><?php echo htmlspecialchars($cur !== '' ? $cur : '—', ENT_QUOTES, 'UTF-8'); ?></td>
	<td><?php echo htmlspecialchars($creatorName, ENT_QUOTES, 'UTF-8'); ?></td>
	<td><?php echo htmlspecialchars($createdDisp, ENT_QUOTES, 'UTF-8'); ?></td>
	<td>
		<a href="<?php echo htmlspecialchars($base . 'clients?company=' . $cid, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-grey"><?php echo htmlspecialchars(mega_search_lang($ctx, 'View', 'View'), ENT_QUOTES, 'UTF-8'); ?></a>
	</td>
</tr>
        <?php
    }
}

if (($megaSearchOutputMode ?? 'full') === 'rows') {
    foreach ($rows as $row) {
        mega_search_render_company_row($row, $ctx, $base);
    }
    return;
}
?>
<div class="clearfix"></div>
<div class="table-responsive scroll-x mega-search-companies-table-wrap">
<table class="table table-new projectspage client-companies-table" style="text-align: left;">
<thead>
	<tr>
		<th>
			<div class="checkbox check-invoice">
				<input type="checkbox" id="select-all-companies">
			</div>
		</th>
		<th width="26%"><?php echo htmlspecialchars(mega_search_lang($ctx, 'Company', 'Company'), ENT_QUOTES, 'UTF-8'); ?></th>
		<th><?php echo htmlspecialchars(mega_search_lang($ctx, 'Clients', 'Clients'), ENT_QUOTES, 'UTF-8'); ?></th>
		<th><?php echo htmlspecialchars(mega_search_lang($ctx, 'Phone', 'Phone'), ENT_QUOTES, 'UTF-8'); ?></th>
		<th><?php echo htmlspecialchars(mega_search_lang($ctx, 'Currency', 'Currency'), ENT_QUOTES, 'UTF-8'); ?></th>
		<th><?php echo htmlspecialchars(mega_search_lang($ctx, 'Created by', 'Created by'), ENT_QUOTES, 'UTF-8'); ?></th>
		<th><?php echo htmlspecialchars(mega_search_lang($ctx, 'Created', 'Created'), ENT_QUOTES, 'UTF-8'); ?></th>
		<th><?php echo htmlspecialchars(mega_search_lang($ctx, 'Options', 'Options'), ENT_QUOTES, 'UTF-8'); ?></th>
	</tr>
</thead>
<tbody id="projects-tbl">
<?php foreach ($rows as $row) {
    mega_search_render_company_row($row, $ctx, $base);
} ?>
</tbody>
</table>
</div>
