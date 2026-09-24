<?php
/** @var array $megaSearchRows @var array $megaSearchCtx */
$ctx = $megaSearchCtx;
$rows = $megaSearchRows;
$base = (string) ($ctx['panel_base'] ?? '');
$rowNo = (int) ($megaSearchRowNoStart ?? 1);

if (($megaSearchOutputMode ?? 'full') === 'rows') {
    foreach ($rows as $lead):
        $leadId = (int) ($lead['id'] ?? 0);
        $leadName = (string) ($lead['name'] ?? '');
        $leadEmail = (string) ($lead['email'] ?? '');
        $leadPhone = (string) ($lead['phone'] ?? '');
        $company = (string) ($lead['company'] ?? '');
        $isLost = !empty($lead['is_lost']);
        $isJunk = !empty($lead['is_junk']);
        $isWon = !empty($lead['is_won']);
        $lv = isset($lead['lead_value']) ? (float) $lead['lead_value'] : 0.0;
        $cur = (string) ($lead['currency'] ?? 'USD,$');
        ?>
<tr data-lead-id="<?php echo $leadId; ?>">
	<td><?php echo (int) $rowNo; ?></td>
	<td style="text-align:left;">
		<div><a href="<?php echo htmlspecialchars($base . 'leads?search=' . urlencode($leadName), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($leadName, ENT_QUOTES, 'UTF-8'); ?></a></div>
		<?php if ($leadEmail !== ''): ?><div class="grey font-size-12"><?php echo htmlspecialchars($leadEmail, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
		<?php if ($company !== ''): ?><div class="grey font-size-12"><?php echo htmlspecialchars($company, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
	</td>
	<td style="text-align:left;"><?php echo $leadPhone !== '' ? htmlspecialchars($leadPhone, ENT_QUOTES, 'UTF-8') : '—'; ?></td>
	<td style="text-align:left;">
		<div class="due-badge">
			<?php if ($isLost): ?><span class="badge todo-bg-op"><?php echo htmlspecialchars(mega_search_lang($ctx, 'Lost', 'Lost'), ENT_QUOTES, 'UTF-8'); ?></span>
			<?php elseif ($isJunk): ?><span class="badge junk-bg-op"><?php echo htmlspecialchars(mega_search_lang($ctx, 'Junk', 'Junk'), ENT_QUOTES, 'UTF-8'); ?></span>
			<?php elseif ($isWon): ?><span class="badge color-done review done-bg-op"><?php echo htmlspecialchars(mega_search_lang($ctx, 'Won', 'Won'), ENT_QUOTES, 'UTF-8'); ?></span>
			<?php else: ?>—<?php endif; ?>
		</div>
	</td>
	<td class="green-amount"><?php echo htmlspecialchars($cur) . number_format($lv, 2); ?></td>
	<td><a href="<?php echo htmlspecialchars($base . 'leads?search=' . urlencode($leadName), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-grey"><?php echo htmlspecialchars(mega_search_lang($ctx, 'View Lead', 'View Lead'), ENT_QUOTES, 'UTF-8'); ?></a></td>
</tr>
        <?php
        $rowNo++;
    endforeach;
    return;
}
?>
<div class="clearfix"></div>
<div class="table-responsive scroll-x vh-100">
<table class="table table-new table-invoice h-100">
<thead>
	<tr>
		<th><?php echo htmlspecialchars(mega_search_col_no_label($ctx), ENT_QUOTES, 'UTF-8'); ?></th>
		<th style="min-width: 270px; text-align: left;"><?php echo htmlspecialchars(mega_search_lang($ctx, 'Lead', 'Lead'), ENT_QUOTES, 'UTF-8'); ?></th>
		<th style="text-align: left;"><?php echo htmlspecialchars(mega_search_lang($ctx, 'Phone', 'Phone'), ENT_QUOTES, 'UTF-8'); ?></th>
		<th style="text-align: left;"><?php echo htmlspecialchars(mega_search_lang($ctx, 'Status', 'Status'), ENT_QUOTES, 'UTF-8'); ?></th>
		<th><?php echo htmlspecialchars(mega_search_lang($ctx, 'Lead Value', 'Lead Value'), ENT_QUOTES, 'UTF-8'); ?></th>
		<th class="min-width-200"><?php echo htmlspecialchars(mega_search_lang($ctx, 'Action', 'Action'), ENT_QUOTES, 'UTF-8'); ?></th>
	</tr>
</thead>
<tbody id="leads-tbl" class="table-x">
<?php foreach ($rows as $lead):
	$leadId = (int) ($lead['id'] ?? 0);
	$leadName = (string) ($lead['name'] ?? '');
	$leadEmail = (string) ($lead['email'] ?? '');
	$leadPhone = (string) ($lead['phone'] ?? '');
	$company = (string) ($lead['company'] ?? '');
	$isLost = !empty($lead['is_lost']);
	$isJunk = !empty($lead['is_junk']);
	$isWon = !empty($lead['is_won']);
	$lv = isset($lead['lead_value']) ? (float) $lead['lead_value'] : 0.0;
	$cur = (string) ($lead['currency'] ?? 'USD,$');
?>
<tr data-lead-id="<?php echo $leadId; ?>">
	<td><?php echo (int) $rowNo; ?></td>
	<td style="text-align:left;">
		<div><a href="<?php echo htmlspecialchars($base . 'leads?search=' . urlencode($leadName), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($leadName, ENT_QUOTES, 'UTF-8'); ?></a></div>
		<?php if ($leadEmail !== ''): ?><div class="grey font-size-12"><?php echo htmlspecialchars($leadEmail, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
		<?php if ($company !== ''): ?><div class="grey font-size-12"><?php echo htmlspecialchars($company, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
	</td>
	<td style="text-align:left;"><?php echo $leadPhone !== '' ? htmlspecialchars($leadPhone, ENT_QUOTES, 'UTF-8') : '—'; ?></td>
	<td style="text-align:left;">
		<div class="due-badge">
			<?php if ($isLost): ?><span class="badge todo-bg-op"><?php echo htmlspecialchars(mega_search_lang($ctx, 'Lost', 'Lost'), ENT_QUOTES, 'UTF-8'); ?></span>
			<?php elseif ($isJunk): ?><span class="badge junk-bg-op"><?php echo htmlspecialchars(mega_search_lang($ctx, 'Junk', 'Junk'), ENT_QUOTES, 'UTF-8'); ?></span>
			<?php elseif ($isWon): ?><span class="badge color-done review done-bg-op"><?php echo htmlspecialchars(mega_search_lang($ctx, 'Won', 'Won'), ENT_QUOTES, 'UTF-8'); ?></span>
			<?php else: ?>—<?php endif; ?>
		</div>
	</td>
	<td class="green-amount"><?php echo htmlspecialchars($cur) . number_format($lv, 2); ?></td>
	<td><a href="<?php echo htmlspecialchars($base . 'leads?search=' . urlencode($leadName), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-grey"><?php echo htmlspecialchars(mega_search_lang($ctx, 'View Lead', 'View Lead'), ENT_QUOTES, 'UTF-8'); ?></a></td>
</tr>
<?php
$rowNo++;
endforeach; ?>
</tbody>
</table>
</div>
