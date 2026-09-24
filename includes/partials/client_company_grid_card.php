<?php
/**
 * Media-vault style company grid card.
 *
 * @var int    $cid
 * @var string $cname
 * @var int    $mc
 * @var string $logoRel
 * @var array  $showAv
 * @var int    $extra
 * @var string $ccCreatedDisp
 * @var string $ccCreatorName
 * @var string $url
 * @var array  $lang
 * @var bool   $clientCompanyCardShowActions
 * @var string $clientCompanyCardHref
 */
if (!isset($clientCompanyCardShowActions)) {
    $clientCompanyCardShowActions = true;
}
if (!isset($companyCardCanMutate)) {
    $companyCardCanMutate = true;
}
if (!isset($companyCardCanDelete)) {
    $companyCardCanDelete = true;
}
if (!isset($clientCompanyCardColClass) || $clientCompanyCardColClass === '') {
    $clientCompanyCardColClass = 'col-md-4 col-lg-4 col-xl-3 mb-4';
}
$clientCompanyCardAsLink = !empty($clientCompanyCardHref);
$cardTag = $clientCompanyCardAsLink ? 'a' : 'div';
$cardClass = 'file-item file-item-file client-card team-group-card client-company-card h-100';
if ($clientCompanyCardAsLink) {
    $cardClass .= ' text-decoration-none';
}
$logoRel = isset($logoRel) ? trim((string) $logoRel) : '';
$showAv = isset($showAv) && is_array($showAv) ? $showAv : [];
$extra = isset($extra) ? (int) $extra : 0;
$mc = isset($mc) ? (int) $mc : 0;
$ccCreatorName = isset($ccCreatorName) ? trim((string) $ccCreatorName) : '';
$ccIsPinned = !empty($ccIsPinned);
$companyPinsEnabled = !empty($companyPinsEnabled);
if ($ccIsPinned) {
    $cardClass .= ' client-company-card--pinned';
}
$cardAttrs = 'class="' . htmlspecialchars($cardClass, ENT_QUOTES, 'UTF-8') . '" data-company-id="' . (int) $cid . '"';
if ($clientCompanyCardAsLink) {
    $cardAttrs .= ' href="' . htmlspecialchars((string) $clientCompanyCardHref, ENT_QUOTES, 'UTF-8') . '"';
} else {
    $cardAttrs .= ' style="cursor:pointer;" role="button" tabindex="0" aria-label="' . htmlspecialchars(($lang['View company'] ?? 'View company') . ': ' . $cname, ENT_QUOTES, 'UTF-8') . '"';
}
?>
<div class="<?php echo htmlspecialchars($clientCompanyCardColClass, ENT_QUOTES, 'UTF-8'); ?>">
    <<?php echo $cardTag; ?> <?php echo $cardAttrs; ?>>
        <?php if ($clientCompanyCardShowActions && !$clientCompanyCardAsLink): ?>
        <div class="file-actions-dropdown">
            <div class="dropdown dropstart">
                <button class="btn-dots dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="<?php echo htmlspecialchars($lang['Actions'] ?? 'Actions'); ?>">
                    <?php echo ts_icon('dots-vertical', 'w-6'); ?>
                </button>
                <ul class="dropdown-menu shadow">
                    <li>
                        <button type="button" class="dropdown-item d-flex align-items-center js-view-client-company" data-company-id="<?php echo (int) $cid; ?>">
                            <?php echo ts_icon('eye', 'tasksession-timer-log-menu-ico me-2'); ?>
                            <?php echo htmlspecialchars($lang['View company'] ?? 'View company'); ?>
                        </button>
                    </li>
                    <?php if ($companyCardCanMutate): ?>
                    <li>
                        <button type="button" class="dropdown-item d-flex align-items-center js-edit-client-company" data-company-id="<?php echo (int) $cid; ?>">
                            <?php echo ts_icon('edit', 'tasksession-timer-log-menu-ico me-2'); ?>
                            <?php echo htmlspecialchars($lang['Edit company'] ?? 'Edit company'); ?>
                        </button>
                    </li>
                    <?php include __DIR__ . '/client_company_pin_dropdown_item.php'; ?>
                    <?php endif; ?>
                    <?php if ($companyCardCanMutate): ?>
                    <li>
                        <button type="button" class="dropdown-item d-flex align-items-center js-clone-client-company" data-company-id="<?php echo (int) $cid; ?>">
                            <?php echo ts_icon('duplicate', 'tasksession-timer-log-menu-ico me-2'); ?>
                            <?php echo htmlspecialchars($lang['Clone company'] ?? 'Clone company'); ?>
                        </button>
                    </li>
                    <?php endif; ?>
                    <?php if ($companyCardCanDelete): ?>
                    <li>
                        <button type="button" class="dropdown-item d-flex align-items-center text-danger js-delete-client-company" data-company-id="<?php echo (int) $cid; ?>" data-company-name="<?php echo htmlspecialchars($cname, ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo ts_icon('delete', 'tasksession-timer-log-menu-ico me-2'); ?>
                            <?php echo htmlspecialchars($lang['Delete company'] ?? 'Delete company'); ?>
                        </button>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
        <?php endif; ?>
        <?php if ($ccIsPinned): ?>
        <span class="client-company-card__pin-badge" title="<?php echo htmlspecialchars($lang['Pinned company'] ?? 'Pinned company'); ?>" aria-hidden="true">
            <?php echo ts_icon('pin', 'w-4'); ?>
        </span>
        <?php endif; ?>
        <div class="file-icon client-company-card__hero">
            <?php if ($logoRel !== ''):
                $logoUrl = rtrim($url, '/') . '/' . ltrim($logoRel, '/');
            ?>
            <div class="file-thumbnail-container">
                <img src="<?php echo htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="" class="file-thumbnail client-company-card__thumb-img">
            </div>
            <?php else: ?>
            <div class="client-company-card__avatar-fallback">
                <?php if (!empty($showAv)): ?>
                <div class="client-company-card__members">
                    <?php
                    foreach ($showAv as $uidAv) {
                        $uobj = User::findById((int) $uidAv);
                        if (!$uobj) {
                            continue;
                        }
                        $tip = trim((string) (($uobj->firstName ?? '') . ' ' . ($uobj->lastName ?? '')));
                        echo '<div class="client-company-card__member-avatar" data-bs-toggle="tooltip" title="' . htmlspecialchars($tip, ENT_QUOTES, 'UTF-8') . '">';
                        echo getUserAvatarHtml((int) $uidAv, $uobj->firstName ?? '', $uobj->lastName ?? '', 50, 50, '', $uobj->firstName ?? '');
                        echo '</div>';
                    }
                    if ($extra > 0) {
                        echo '<div class="client-company-card__members-more d-flex align-items-center justify-content-center">+' . (int) $extra . '</div>';
                    }
                    ?>
                </div>
                <?php else:
                    $initial = strtoupper(substr(trim($cname), 0, 1));
                    if ($initial === '') {
                        $initial = '?';
                    }
                    $companyColorIndex = ((int) $cid > 0 ? ((int) $cid % 8) : (crc32(trim($cname)) % 8)) + 1;
                ?>
                <div class="avatar-initials color-<?php echo (int) $companyColorIndex; ?> avatar-initials-large rounded-circle" aria-hidden="true"><?php echo htmlspecialchars($initial, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <div class="file-info client-company-card__info">
            <h6 class="file-name client-company-card__title"><?php echo htmlspecialchars($cname); ?></h6>
            <div class="client-company-card__meta-row">
                <p class="file-meta client-company-card__meta">
                    <?php echo htmlspecialchars($lang['Total Clients'] ?? 'Total clients'); ?>:
                    <?php echo (int) $mc; ?>
                </p>
                <?php if (!empty($ccCreatedDisp)): ?>
                <p class="client-company-card__created"><?php echo htmlspecialchars(($lang['Created date'] ?? 'Created date') . ': ' . $ccCreatedDisp); ?></p>
                <?php endif; ?>
            </div>
            <small class="text-muted client-company-card__creator">
                <?php echo htmlspecialchars($lang['Created by'] ?? 'Created by'); ?>
                <?php echo htmlspecialchars($ccCreatorName !== '' ? $ccCreatorName : '—'); ?>
            </small>
            <?php if (!empty($clientCompanyCardSubtitle)): ?>
            <small class="text-muted client-company-card__subtitle"><?php echo htmlspecialchars((string) $clientCompanyCardSubtitle, ENT_QUOTES, 'UTF-8'); ?></small>
            <?php endif; ?>
        </div>
    </<?php echo $cardTag; ?>>
</div>
