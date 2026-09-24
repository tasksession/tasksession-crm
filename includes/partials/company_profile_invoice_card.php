<?php
/**
 * Invoice card for company profile (matches admin profile.php invoice tab).
 * Requires: $milestone, $companyProfileReturnUrl, $url, $lang, $companyProfileCanEdit
 */
if (empty($milestone) || !is_array($milestone)) {
    return;
}
$mid = (int) ($milestone['id'] ?? 0);
$mStatus = (int) ($milestone['status'] ?? 0);
$totalAmount = company_profile_calculate_invoice_total($milestone);
$currencySym = company_profile_currency_symbol((string) ($milestone['currency'] ?? ''));
$returnUrlEnc = urlencode((string) ($companyProfileReturnUrl ?? ''));
?>
<div class="col-md-6 col-lg-6 col-xl-4">
    <div class="card shadow-sm card-style">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="size-12 badge <?php
                    if ($mStatus === 1) {
                        echo 'success';
                    } elseif ($mStatus === 2) {
                        echo '';
                    } else {
                        echo 'red-badge';
                    }
                ?>"<?php if ($mStatus === 2): ?> style="background-color:#6c757d;color:white;text-transform:uppercase;"<?php endif; ?>>
                    <?php
                    if ($mStatus === 1) {
                        echo htmlspecialchars($lang['Paid'] ?? 'Paid', ENT_QUOTES, 'UTF-8');
                    } elseif ($mStatus === 2) {
                        echo htmlspecialchars($lang['Cancel'] ?? 'Cancel', ENT_QUOTES, 'UTF-8');
                    } else {
                        echo htmlspecialchars($lang['Unpaid'] ?? 'Unpaid', ENT_QUOTES, 'UTF-8');
                    }
                    ?>
                </span>
                <div class="dropdown">
                    <button class="btn-dots" type="button" id="dropdownMenu<?php echo $mid; ?>" data-bs-toggle="dropdown" aria-expanded="false">
                        <?php echo ts_icon('dots-vertical', 'w-6'); ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenu<?php echo $mid; ?>">
                        <?php if ($mStatus === 1): ?>
                        <li>
                            <form action="<?php echo htmlspecialchars(tasksession_download_pdf_href(), ENT_QUOTES, 'UTF-8'); ?>" method="post" target="_blank" enctype="multipart/form-data">
                                <input type="hidden" value="<?php echo $mid; ?>" name="milestone_id" />
                                <button type="submit" name="mile_submit" class="dropdown-item">
                                    <?php echo ts_icon('download', 'me-2 tasksession-timer-log-menu-ico'); ?>
                                    <?php echo htmlspecialchars($lang['Download PDF'] ?? 'Download PDF', ENT_QUOTES, 'UTF-8'); ?>
                                </button>
                            </form>
                        </li>
                        <?php else: ?>
                        <?php if (!empty($milestone['p_id']) && (int) $milestone['p_id'] > 0): ?>
                        <li>
                            <a class="dropdown-item" href="payments?projectId=<?php echo (int) $milestone['p_id']; ?>">
                                <?php echo ts_icon('archive', 'tasksession-timer-log-menu-ico me-2'); ?><?php echo htmlspecialchars($lang['View Project'] ?? 'View project', ENT_QUOTES, 'UTF-8'); ?>
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if (!empty($companyProfileCanEditInvoice)): ?>
                        <li>
                            <a href="edit-invoice?id=<?php echo $mid; ?>&return_url=<?php echo $returnUrlEnc; ?>" class="dropdown-item">
                                <?php echo ts_icon('edit', 'tasksession-timer-log-menu-ico me-2'); ?> <?php echo htmlspecialchars($lang['Edit Invoice'] ?? 'Edit Invoice', ENT_QUOTES, 'UTF-8'); ?>
                            </a>
                        </li>
                        <li>
                            <form method="post" action="" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="invoice_id" value="<?php echo $mid; ?>">
                                <button type="submit" class="dropdown-item text-success" name="company_mark_as_paid" onclick="return confirm('<?php echo htmlspecialchars($lang['Are you sure you want to mark this invoice as paid?'] ?? 'Are you sure you want to mark this invoice as paid?', ENT_QUOTES, 'UTF-8'); ?>');">
                                    <?php echo ts_icon('check-circle', 'me-2 tasksession-timer-log-menu-ico'); ?>
                                    <?php echo htmlspecialchars($lang['Mark as Paid'] ?? 'Mark as Paid', ENT_QUOTES, 'UTF-8'); ?>
                                </button>
                            </form>
                        </li>
                        <?php if ($mStatus !== 2): ?>
                        <li>
                            <form method="post" action="" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="invoice_id" value="<?php echo $mid; ?>">
                                <button type="submit" class="dropdown-item text-warning" name="company_cancel_invoice" onclick="return confirm('<?php echo htmlspecialchars($lang['Are you sure you want to cancel this invoice?'] ?? 'Are you sure you want to cancel this invoice?', ENT_QUOTES, 'UTF-8'); ?>');">
                                    <?php echo ts_icon('close', 'me-2 tasksession-timer-log-menu-ico'); ?>
                                    <?php echo htmlspecialchars($lang['Cancel Invoice'] ?? 'Cancel Invoice', ENT_QUOTES, 'UTF-8'); ?>
                                </button>
                            </form>
                        </li>
                        <?php endif; ?>
                        <?php endif; ?>
                        <?php if ($mStatus !== 2): ?>
                        <li>
                            <a href="#" class="dropdown-item" onclick="copyPaymentLink(<?php echo $mid; ?>); return false;">
                                <?php echo ts_icon('link', 'me-2 tasksession-timer-log-menu-ico'); ?>
                                <?php echo htmlspecialchars($lang['Copy payment link'] ?? 'Copy payment link', ENT_QUOTES, 'UTF-8'); ?>
                            </a>
                        </li>
                        <?php endif; ?>
                        <li>
                            <form action="<?php echo htmlspecialchars(tasksession_download_pdf_href(), ENT_QUOTES, 'UTF-8'); ?>" method="post" target="_blank" enctype="multipart/form-data">
                                <input type="hidden" value="<?php echo $mid; ?>" name="milestone_id" />
                                <button type="submit" name="mile_submit" class="dropdown-item">
                                    <?php echo ts_icon('download', 'me-2 tasksession-timer-log-menu-ico'); ?>
                                    <?php echo htmlspecialchars($lang['Download PDF'] ?? 'Download PDF', ENT_QUOTES, 'UTF-8'); ?>
                                </button>
                            </form>
                        </li>
                        <?php if (!empty($companyProfileCanDeleteInvoice)): ?>
                        <li>
                            <form method="post" action="">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" value="<?php echo $mid; ?>" name="delete_id" />
                                <button type="submit" class="dropdown-item text-danger" name="company_delete_invoice" onclick="return confirm('Are you sure you want to delete this milestone?');">
                                    <?php echo ts_icon('delete', 'tasksession-timer-log-menu-ico me-2'); ?> <?php echo htmlspecialchars($lang['Delete'] ?? 'Delete', ENT_QUOTES, 'UTF-8'); ?>
                                </button>
                            </form>
                        </li>
                        <?php endif; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
            <h5 class="card-title grey"><?php echo htmlspecialchars((string) ($milestone['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></h5>
            <h3 class="big-text"><?php echo htmlspecialchars($currencySym, ENT_QUOTES, 'UTF-8') . number_format($totalAmount, 2); ?></h3>
            <div class="task-date grey">
                <div><b class="dark"><?php echo htmlspecialchars($lang['Due date'] ?? 'Due date', ENT_QUOTES, 'UTF-8'); ?>:</b> <?php echo htmlspecialchars((string) ($milestone['deadline'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                <div><b class="dark"><?php echo htmlspecialchars($lang['Invoice'] ?? 'Invoice', ENT_QUOTES, 'UTF-8'); ?>: #</b> <?php echo (int) ($milestone['p_id'] ?? 0) . $mid; ?></div>
                <div><b class="dark"><?php echo htmlspecialchars($lang['Invoice type'] ?? 'Invoice type', ENT_QUOTES, 'UTF-8'); ?>:</b>
                    <?php if (!empty($milestone['is_recurring']) && (int) $milestone['is_recurring'] === 1): ?>
                        <?php echo htmlspecialchars($lang['Recurring Invoice'] ?? 'Recurring Invoice', ENT_QUOTES, 'UTF-8'); ?>
                    <?php else: ?>
                        <?php echo htmlspecialchars($lang['Single Invoice'] ?? 'Single Invoice', ENT_QUOTES, 'UTF-8'); ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php
            $creatorId = (int) ($milestone['created_by'] ?? 0);
            $creator = $creatorId > 0 ? User::findById($creatorId) : null;
            if ($creator):
                $creatorName = trim(($creator->firstName ?? '') . ' ' . ($creator->lastName ?? ''));
                $avatarData = getUserAvatarData($creatorId, $creator->firstName ?? '', $creator->lastName ?? '', 30, 30);
                $colorIndex = ($creatorId % 8) + 1;
            ?>
            <div class="invoice-created mt-2 mb-2">
                <div class="avatar-wrapper d-flex align-items-center">
                    <?php if ($avatarData['type'] === 'image' && !empty($avatarData['url'])): ?>
                        <img src="<?php echo htmlspecialchars($avatarData['url'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($creatorName, ENT_QUOTES, 'UTF-8'); ?>" title="<?php echo htmlspecialchars($creatorName, ENT_QUOTES, 'UTF-8'); ?>" class="avatar rounded-circle me-2" style="width:30px;height:30px;object-fit:cover;">
                    <?php else: ?>
                        <div class="avatar-initials color-<?php echo $colorIndex; ?> rounded-circle me-2 d-flex align-items-center justify-content-center" style="width:30px;height:30px;color:white;font-weight:bold;font-size:12px;" title="<?php echo htmlspecialchars($creatorName, ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars($avatarData['initials'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    <?php endif; ?>
                    <span class="avatar-name"><?php echo htmlspecialchars($creatorName, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            </div>
            <?php endif; ?>
            <div>
                <a href="edit-invoice?id=<?php echo $mid; ?>&return_url=<?php echo $returnUrlEnc; ?>" class="btn btn-outline-grey">
                    <?php echo htmlspecialchars($lang['View invoice'] ?? 'View invoice', ENT_QUOTES, 'UTF-8'); ?>
                </a>
            </div>
        </div>
    </div>
</div>
