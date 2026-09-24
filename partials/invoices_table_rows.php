<?php
/**
 * Invoice table rows — shared by invoices.php and mega search.
 * Expects: $invoiceTableRows (array), $lang, global $db, $url
 */
global $db, $url;
if (!function_exists('getCurrencySymbol')) {
    require_once dirname(__DIR__) . '/includes/invoice_display_helpers.php';
}
if (!function_exists('invoice_action_menu_icon')) {
    require_once dirname(__DIR__) . '/includes/invoice_dropdown_icons.php';
}
if (!isset($invoicesTableLinkBase)) { $invoicesTableLinkBase = ''; }
if (!isset($invoicesTableFormAction)) { $invoicesTableFormAction = '#'; }
if (!isset($invoicesPdfUrl)) {
    $invoicesPdfUrl = function_exists('tasksession_download_pdf_href')
        ? tasksession_download_pdf_href()
        : (rtrim((string) ($url ?? ''), '/') . '/templates/download_pdf');
}
$invoicesTableLinkBase = (string) $invoicesTableLinkBase;
$invoicesTableFormAction = (string) $invoicesTableFormAction;
$invoicesPdfUrl = (string) $invoicesPdfUrl;
$invoicesLinkEsc = htmlspecialchars($invoicesTableLinkBase, ENT_QUOTES, 'UTF-8');
$invoicesPdfEsc = htmlspecialchars($invoicesPdfUrl, ENT_QUOTES, 'UTF-8');
$invoicesFormEsc = htmlspecialchars($invoicesTableFormAction, ENT_QUOTES, 'UTF-8');
if (!isset($invoicesTableCanEdit)) {
    $invoicesTableCanEdit = true;
}
if (!isset($invoicesTableCanDelete)) {
    $invoicesTableCanDelete = true;
}
if (!isset($invoicesTableCanDuplicate)) {
    $invoicesTableCanDuplicate = true;
}
if (!isset($invoicesTableCanCharge)) {
    $invoicesTableCanCharge = true;
}
if (!empty($invoiceTableRows)) {
    foreach ($invoiceTableRows as $row) {
        ?><tr>
														<td>
														  <div class="checkbox check-invoice">
															<input type="checkbox" id="invoice-<?php echo $row['id']; ?>" class="invoice-checkbox" value="<?php echo $row['id']; ?>" <?php echo (($row['status'] == 1) || !$invoicesTableCanDelete) ? 'disabled' : ''; ?>>
															<label for="invoice-<?php echo $row['id']; ?>"></label>
														  </div>
														</td>
														<td> <div class="min-width-200">
														  <?= htmlentities(invoice_list_display_title($row)) ?>
														  </div>
														</td>
														<td class="clients-rpt">
															<div class="avatar-wrapper mt-0 d-flex align-items-center">
																<?php
																// Use m.c_id (milestone client) first, fall back to p.c_id (project client)
																$client_id = !empty($row['c_id']) ? $row['c_id'] : (!empty($row['project_c_id']) ? $row['project_c_id'] : null);
																if (!empty($client_id)) {
																	$user1 = user::findById($client_id);
																	if ($user1) {
																		echo '<a href="' . $invoicesLinkEsc . 'profile?user_id=' . (int)$user1->id . '" class="d-flex align-items-center" style="gap: 10px;">';
																		echo '<div class="user-box">';
																		echo getUserAvatarHtml($user1->id, $user1->firstName, $user1->lastName ?? '', 35, 35, '', $user1->firstName);
																		echo '</div>';
																		echo '<span class="avatar-name">' . htmlentities($user1->firstName) . '</span>';
																		echo '</a>';
																	} else {
																		echo '<span class="text-muted">-</span>';
																	}
																} else {
																	echo '<span class="text-muted">-</span>';
																}
																?>
															</div>
														</td>
												<td>
													  <?php if ($row['status'] == 1): ?>
														<span class="badge success"><?php echo $lang['Paid']; ?></span>
													  <?php elseif ($row['status'] == 2): ?>
														<span class="badge" style="background-color: #6c757d; color: white; text-transform: uppercase;"><?php echo $lang['Cancel']; ?></span>
													  <?php else: ?>
														<span class="badge red-badge"><?php echo $lang['Unpaid']; ?></span>
													  <?php endif; ?>
																			</td>
													<td>
													  <?= $row['deadline'] ?>
																			</td>
													<td>
													  <?php 
													  $totalAmount = calculateInvoiceTotal($row);
													  echo getCurrencySymbol($row['currency']) . number_format($totalAmount, 2);
													  ?>
																			</td>
                                                    <td class="clients-rpt">
															<div class="avatar-wrapper mt-0 d-flex align-items-center">
                                                      <?php
                                                      $creator = isset($row['created_by']) ? user::findById((int)$row['created_by']) : null;
                                                      if ($creator) {
                                                          echo '<a href="' . $invoicesLinkEsc . 'profile?user_id=' . (int)$creator->id . '" class="d-flex align-items-center" style="gap: 10px;">';
                                                          echo '<div class="user-box">';
                                                          echo getUserAvatarHtml($creator->id, $creator->firstName, $creator->lastName ?? '', 35, 35, '', $creator->firstName);
                                                          echo '</div>';
                                                          echo '<span class="avatar-name">' . htmlentities($creator->firstName) . '</span>';
                                                          echo '</a>';
                                                      } else {
                                                          echo '<span class="text-muted">-</span>';
                                                      }
                                                      ?>
															</div>
                                                    </td>
													<td style="text-align: center;">
													  <?php if (isset($row['is_recurring']) && $row['is_recurring'] == 1): ?>
														<?php 
														  $frequency = isset($row['recurring_frequency']) ? $row['recurring_frequency'] : '';
														  $frequencyLabel = getRecurringFrequencyLabel($frequency, $lang);
														  $tooltipText = !empty($frequencyLabel) ? $lang['Recurring'] . ': ' . $frequencyLabel : $lang['Recurring Invoice'];
														?>
														<?php echo ts_icon('refresh'); ?>
													  <?php else: ?>
														<span class="text-muted">-</span>
													  <?php endif; ?>
													</td>
																			<td class="viewinvoicebt">
																				<button type="button"
																					class="btn outine client-invoice-view-trigger"
																					data-invoice-id="<?= (int) $row['id'] ?>">
																				  <?= $lang['View invoice'] ?>
																				</button>
													</td>
														<td>
																<div class="action-toggle" data-bs-toggle="collapse" data-bs-target="#actionDropdown<?php echo $row['id']; ?>">
																	<?php echo $lang['Action']; ?><?php echo ts_icon('chevron-down'); ?>
																</div>
																<div id="actionDropdown<?php echo $row['id']; ?>" class="toggle-action collapse shadow-dept">
																                                        <ul>
																	<?php 
																	// Check if this is a direct client invoice (no p_id but has c_id)
																	$isDirectClientInvoice = (empty($row['p_id']) || $row['p_id'] == 0) && !empty($row['c_id']);
																	$isPaidDirectInvoice = ($row['status'] == 1) && $isDirectClientInvoice;
																	?>
																	<?php if ($isPaidDirectInvoice): // Paid direct client invoice - show only Download PDF ?>
																	<li>
																		<form action="<?php echo $invoicesPdfEsc; ?>" method="post" target="_blank" enctype="multipart/form-data">
																			<input type="hidden" value="<?php echo $row['id']; ?>" name="milestone_id" />
																			<button type="submit" name="mile_submit" class="dropdown-item d-flex align-items-center">
																				<?php echo invoice_action_menu_icon('download_pdf'); ?>
																				<?php echo isset($lang['Download PDF']) ? $lang['Download PDF'] : 'Download PDF'; ?>
																			</button>
																		</form>
																	</li>
																	<?php if ($invoicesTableCanDuplicate): ?>
																	<li>
																		<form method="post" action="<?php echo $invoicesFormEsc; ?>" class="d-inline">
																			<input type="hidden" name="invoice_id" value="<?php echo (int) $row['id']; ?>">
																			<button type="submit" class="dropdown-item d-flex align-items-center" name="duplicate_invoice" onclick="return confirm('<?php echo htmlspecialchars($lang['Duplicate invoice confirm'] ?? 'Create a copy of this invoice as a new unpaid invoice?', ENT_QUOTES); ?>');">
																				<?php echo invoice_action_menu_icon('duplicate'); ?>
																				<?php echo htmlspecialchars($lang['Duplicate Invoice'] ?? 'Duplicate Invoice'); ?>
																			</button>
																		</form>
																	</li>
																	<?php endif; ?>
																	<?php else: // Show all menu items ?>
																	<?php if ($row['status'] != 1 && $invoicesTableCanEdit): // Only show edit for unpaid invoices ?>
																	<li>
																		<a href="<?php echo $invoicesLinkEsc; ?>edit-invoice?id=<?php echo $row['id']; ?>" class="d-flex align-items-center">
																			<?php echo invoice_action_menu_icon('edit'); ?>
																			<?php echo $lang['Edit Invoice']; ?>
																		</a>
																	</li>
																	<?php endif; ?>
																	<?php if ($invoicesTableCanDuplicate): ?>
																	<li>
																		<form method="post" action="<?php echo $invoicesFormEsc; ?>" class="d-inline">
																			<input type="hidden" name="invoice_id" value="<?php echo (int) $row['id']; ?>">
																			<button type="submit" class="dropdown-item d-flex align-items-center" name="duplicate_invoice" onclick="return confirm('<?php echo htmlspecialchars($lang['Duplicate invoice confirm'] ?? 'Create a copy of this invoice as a new unpaid invoice?', ENT_QUOTES); ?>');">
																				<?php echo invoice_action_menu_icon('duplicate'); ?>
																				<?php echo htmlspecialchars($lang['Duplicate Invoice'] ?? 'Duplicate Invoice'); ?>
																			</button>
																		</form>
																	</li>
																	<?php endif; ?>
																	<?php if ($row['status'] != 1 && $invoicesTableCanEdit): // Only show "Mark as Paid" for unpaid invoices ?>
																	<li>
																		<form method="post" action="<?php echo $invoicesFormEsc; ?>" class="d-inline">
																			<input type="hidden" name="invoice_id" value="<?php echo $row['id']; ?>">
																			<button type="submit" class="dropdown-item text-success d-flex align-items-center" name="mark_as_paid" onclick="return confirm('<?php echo isset($lang['Are you sure you want to mark this invoice as paid?']) ? $lang['Are you sure you want to mark this invoice as paid?'] : 'Are you sure you want to mark this invoice as paid?'; ?>');">
																				<?php echo invoice_action_menu_icon('mark_paid'); ?>
																				<?php echo isset($lang['Mark as Paid']) ? $lang['Mark as Paid'] : 'Mark as Paid'; ?>
																			</button>
																		</form>
																	</li>
																	<?php endif; ?>
																	<?php include dirname(__DIR__) . '/partials/invoice_auto_charge_menu_item.php'; ?>
																	<?php if ($row['status'] != 1 && $row['status'] != 2 && $invoicesTableCanEdit): // Only show "Cancel Invoice" for unpaid invoices ?>
																	<li>
																		<form method="post" action="<?php echo $invoicesFormEsc; ?>" class="d-inline">
																			<input type="hidden" name="invoice_id" value="<?php echo $row['id']; ?>">
																			<button type="submit" class="dropdown-item text-warning d-flex align-items-center" name="cancel_invoice" onclick="return confirm('<?php echo isset($lang['Are you sure you want to cancel this invoice?']) ? $lang['Are you sure you want to cancel this invoice?'] : 'Are you sure you want to cancel this invoice?'; ?>');">
																				<?php echo invoice_action_menu_icon('cancel'); ?>
																				<?php echo isset($lang['Cancel Invoice']) ? $lang['Cancel Invoice'] : 'Cancel Invoice'; ?>
																			</button>
																		</form>
																	</li>
																	<?php endif; ?>
																	<?php if ($row['status'] != 1 && $row['status'] != 2): // Only show "Copy payment link" for unpaid invoices ?>
																	<li>
																		<a href="#" class="d-flex align-items-center" onclick="copyPaymentLink(<?php echo $row['id']; ?>); return false;">
																			<?php echo invoice_action_menu_icon('copy_link'); ?>
																			<?php echo isset($lang['Copy payment link']) ? $lang['Copy payment link'] : 'Copy payment link'; ?>
																		</a>
																	</li>
																	<?php endif; ?>
																	<?php
																	// Add all internal project IDs here (comma separated)
																	$internalProjectIds = [17]; // <-- replace 17 with your actual internal project p_id(s)
																	$isInternal = (
																		empty($row['p_id']) ||
																		$row['p_id'] == 0 ||
																		in_array($row['p_id'], $internalProjectIds) ||
																		(isset($row['project_title']) && strtolower(trim($row['project_title'])) == 'internal invoice') ||
																		(isset($row['project_title']) && strtolower(trim($row['project_title'])) == 'internal invoices')
																	);
																	?>
																	<?php if (!$isInternal): ?>
																	<li>
																		<a href="<?php echo $invoicesLinkEsc; ?>overview?projectId=<?php echo $row['p_id']; ?>" class="d-flex align-items-center">
																			<?php echo invoice_action_menu_icon('view_project'); ?>
																			<?php echo $lang['View Project']; ?>
																		</a>
																	</li>
																	<?php endif; ?>
																	<li>
																		<form action="<?php echo $invoicesPdfEsc; ?>" method="post" target="_blank" enctype="multipart/form-data">
																			<input type="hidden" value="<?php echo $row['id']; ?>" name="milestone_id" />
																			<button type="submit" name="mile_submit" class="dropdown-item d-flex align-items-center">
																				<?php echo invoice_action_menu_icon('download_pdf'); ?>
																				<?php echo isset($lang['Download PDF']) ? $lang['Download PDF'] : 'Download PDF'; ?>
																			</button>
																		</form>
																	</li>
																	<?php if ($row['status'] != 1 && $invoicesTableCanDelete): // Only show delete for unpaid invoices ?>
																	<li>
																		<form method="post" action="<?php echo $invoicesFormEsc; ?>" class="d-inline">
																			<input type="hidden" name="delete_id" value="<?php echo $row['id']; ?>">
																			<button type="submit" class="dropdown-item text-danger d-flex align-items-center" name="delete-mile" onclick="return confirm('Are you sure you want to delete this invoice?');">
																				<?php echo invoice_action_menu_icon('delete'); ?>
																				<?php echo $lang['Delete']; ?>
																			</button>
																		</form>
																	</li>
																	<?php endif; ?>
																	<?php endif; ?>
																	</ul>
																	</div>
																</td>
															</tr><?php
    }
} else {
    ?><tr><td colspan="10" class="text-center"><?php echo isset($lang['Payment history not available!']) ? $lang['Payment history not available!'] : 'Payment history not available!'; ?></td></tr><?php
}