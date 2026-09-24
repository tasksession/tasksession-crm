<?php
/**
 * Project overview cards — admin/staff center column.
 * Expects vars from includes/project-overview-data.php
 */
if (!isset($project) || !isset($projectId)) {
    return;
}

$overviewShowEditActions = !isset($overviewShowEditActions) || $overviewShowEditActions;
$overviewShowClientActions = !isset($overviewShowClientActions) || $overviewShowClientActions;
$overviewShowCompanyActions = !isset($overviewShowCompanyActions) || $overviewShowCompanyActions;
$overviewShowContactCards = !isset($overviewShowContactCards) || $overviewShowContactCards;

$poBtnDotsSvg = '' . ts_icon('dots-vertical', 'w-6') . '';
$poEditIconSvg = '' . ts_icon('edit', 'tasksession-timer-log-menu-ico me-2') . '';
$poViewIconSvg = '' . ts_icon('eye', 'tasksession-timer-log-menu-ico me-2') . '';
$poEditForm = static function () use ($overviewEditUrl, $lang, $poBtnDotsSvg, $poEditIconSvg) {
    ?>
    <div class="dropdown">
        <button class="btn-dots" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="<?php echo htmlspecialchars($lang['Action'] ?? 'Action', ENT_QUOTES, 'UTF-8'); ?>">
            <?php echo $poBtnDotsSvg; ?>
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
            <li>
                <a href="<?php echo htmlspecialchars($overviewEditUrl, ENT_QUOTES, 'UTF-8'); ?>" class="dropdown-item d-flex align-items-center">
                    <?php echo $poEditIconSvg; ?>
                    <?php echo htmlspecialchars($lang['Edit Project'] ?? 'Edit project', ENT_QUOTES, 'UTF-8'); ?>
                </a>
            </li>
        </ul>
    </div>
    <?php
};
?>
<div class="project-overview-page">

    <div class="po-page-header mb-4">
        <nav class="po-breadcrumb" aria-label="<?php echo htmlspecialchars($lang['Breadcrumb'] ?? 'Breadcrumb', ENT_QUOTES, 'UTF-8'); ?>">
            <div class="po-breadcrumb-trail">
                <a href="<?php echo htmlspecialchars($overviewProjectsUrl, ENT_QUOTES, 'UTF-8'); ?>" class="po-breadcrumb-link grey"><?php echo htmlspecialchars($lang['Projects'] ?? 'Projects', ENT_QUOTES, 'UTF-8'); ?></a>
                <span class="po-breadcrumb-sep grey" aria-hidden="true">
                    <?php echo ts_icon('chevron-right', 'w-2'); ?>
                </span>
                <a href="<?php echo htmlspecialchars($overviewProjectUrl, ENT_QUOTES, 'UTF-8'); ?>" class="po-breadcrumb-link grey"><?php echo htmlspecialchars($overviewProjectBreadcrumbTitle, ENT_QUOTES, 'UTF-8'); ?></a>
                <span class="po-breadcrumb-sep grey" aria-hidden="true">
                    <?php echo ts_icon('chevron-right', 'w-2'); ?>
                </span>
                <span class="po-breadcrumb-current"><?php echo htmlspecialchars($lang['Overview'] ?? 'Overview', ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        </nav>
        <h1 class="po-page-title mb-0"><?php echo htmlspecialchars((string) ($project->project_title ?? ''), ENT_QUOTES, 'UTF-8'); ?></h1>
    </div>

    <div class="row g-3">
        <div class="col-12">
            <div class="widget-card po-overview-card">
                    <div class="po-card-header align-all mb-3">
                        <div class="title-head mb-0"><?php echo htmlspecialchars($lang['Project Description'] ?? 'Project Description', ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php if ($overviewShowEditActions) {
                            $poEditForm();
                        } ?>
                    </div>
                    <div class="po-description project-description" data-po-desc>
                        <?php if (trim(strip_tags((string) $overviewDescHtml)) !== '') : ?>
                            <div class="po-description-content po-description-content--clamp">
                                <div class="pre-formatted"><?php echo $overviewDescHtml; ?></div>
                            </div>
                            <button type="button" class="po-description-toggle" hidden aria-expanded="false" data-read-less="<?php echo htmlspecialchars($lang['Read less'] ?? 'Read less', ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars($lang['Read more'] ?? 'Read more', ENT_QUOTES, 'UTF-8'); ?>
                            </button>
                        <?php else : ?>
                            <p class="grey mb-0"><?php echo htmlspecialchars($lang['No description provided.'] ?? 'No description provided.', ENT_QUOTES, 'UTF-8'); ?></p>
                        <?php endif; ?>
                    </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <?php if (!empty($overviewShowBudget)) : ?>
        <div class="col-md-6">
            <div class="widget-card po-overview-card">
                    <div class="po-card-header align-all">
                        <div class="title-head mb-0"><?php echo htmlspecialchars($lang['Budget'] ?? 'Budget', ENT_QUOTES, 'UTF-8'); ?></div>
                        <span class="po-card-header-icon po-card-header-icon--green" aria-hidden="true">
                            <?php echo ts_icon('inbox-stack'); ?>
                        </span>
                    </div>
                    <div class="po-budget-total"><?php echo htmlspecialchars($overviewCurrencySymbol, ENT_QUOTES, 'UTF-8'); ?><?php echo number_format($overviewBudgetTotal, 0); ?></div>
                    <p class="grey mb-3"><?php echo htmlspecialchars($lang['Total allocated budget'] ?? 'Total allocated budget', ENT_QUOTES, 'UTF-8'); ?></p>
                    <div class="progress mb-2" style="height:8px;">
                        <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo (int) $overviewBudgetPercent; ?>%;" aria-valuenow="<?php echo (int) $overviewBudgetPercent; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <p class="grey mb-0 small">
                        <?php echo htmlspecialchars($overviewCurrencySymbol, ENT_QUOTES, 'UTF-8'); ?><?php echo number_format($overviewBudgetUsed, 0); ?>
                        <?php echo htmlspecialchars($lang['used'] ?? 'used', ENT_QUOTES, 'UTF-8'); ?>
                        (<?php echo (int) $overviewBudgetPercent; ?>%)
                    </p>
            </div>
        </div>
        <?php endif; ?>

        <div class="<?php echo !empty($overviewShowBudget) ? 'col-md-6' : 'col-12'; ?>">
            <div class="widget-card po-overview-card">
                    <div class="po-card-header align-all">
                        <div class="title-head mb-0"><?php echo htmlspecialchars($lang['Timeline'] ?? 'Timeline', ENT_QUOTES, 'UTF-8'); ?></div>
                        <span class="po-card-header-icon po-card-header-icon--purple" aria-hidden="true">
                            <?php echo ts_icon('calendar'); ?>
                        </span>
                    </div>
                    <div class="po-timeline">
                        <div class="po-timeline-item">
                            <span class="po-timeline-dot po-timeline-dot--active"></span>
                            <div>
                                <div class="po-timeline-date"><?php echo !empty($project->start_time) ? date('M j, Y', strtotime($project->start_time)) : '—'; ?></div>
                                <div class="grey"><?php echo htmlspecialchars($lang['Start Date'] ?? 'Start date', ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>
                        </div>
                        <div class="po-timeline-line" aria-hidden="true"></div>
                        <div class="po-timeline-item">
                            <span class="po-timeline-dot"></span>
                            <div>
                                <div class="po-timeline-date"><?php echo !empty($project->end_time) ? date('M j, Y', strtotime($project->end_time)) : '—'; ?></div>
                                <div class="grey"><?php echo htmlspecialchars($lang['Due Date'] ?? 'Due date', ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>
                        </div>
                    </div>
            </div>
        </div>
    </div>

    <?php if ($overviewShowContactCards && $overviewPrimaryClient) : ?>
    <div class="row g-3 po-contact-cards-row mb-4">
        <div class="col-lg-6 d-flex">
            <div class="widget-card po-overview-card contact-info h-100 w-100 d-flex flex-column">
                    <div class="po-card-header align-all mb-3">
                        <div class="title-head mb-0 text-uppercase"><?php echo htmlspecialchars($lang['Client'] ?? 'Client', ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php if ($overviewShowClientActions) : ?>
                        <div class="dropdown">
                            <button class="btn-dots" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="<?php echo htmlspecialchars($lang['Action'] ?? 'Action', ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo $poBtnDotsSvg; ?>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li>
                                    <a class="dropdown-item d-flex align-items-center" href="<?php echo htmlspecialchars($overviewClientProfileUrl, ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php echo $poViewIconSvg; ?>
                                        <?php echo htmlspecialchars($lang['View Profile'] ?? 'View profile', ENT_QUOTES, 'UTF-8'); ?>
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item d-flex align-items-center" href="<?php echo htmlspecialchars($overviewClientEditUrl, ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php echo $poEditIconSvg; ?>
                                        <?php echo htmlspecialchars($lang['Edit Profile'] ?? 'Edit profile', ENT_QUOTES, 'UTF-8'); ?>
                                    </a>
                                </li>
                            </ul>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="d-flex align-items-center mb-4 col-gap">
                        <div class="po-contact-avatar-wrap flex-shrink-0">
                            <?php echo getUserAvatarHtml(
                                (int) $overviewPrimaryClient->id,
                                (string) $overviewPrimaryClient->firstName,
                                (string) ($overviewPrimaryClient->lastName ?? ''),
                                48,
                                48,
                                'po-contact-avatar',
                                (string) $overviewPrimaryClient->firstName
                            ); ?>
                        </div>
                        <div>
                            <div class="po-client-name"><?php echo htmlspecialchars($overviewPrimaryClient->firstName . ' ' . ($overviewPrimaryClient->lastName ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="grey"><?php echo htmlspecialchars($lang['Primary Client'] ?? 'Primary Client', ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>
                    </div>
                    <div class="contact-infoo">
                        <div class="contact-item d-flex align-items-center mb-2">
                            <?php echo ts_icon('emails', 'text-muted'); ?>
                            <span><?php echo htmlspecialchars($overviewPrimaryClient->email ?? '—', ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <div class="contact-item d-flex align-items-center mb-2">
                            <?php echo ts_icon('phone', 'text-muted'); ?>
                            <span><?php echo !empty($overviewPrimaryClient->phone) ? htmlspecialchars($overviewPrimaryClient->phone, ENT_QUOTES, 'UTF-8') : '—'; ?></span>
                        </div>
                        <?php if (!empty($overviewPrimaryClient->website)) : ?>
                        <div class="contact-item d-flex align-items-center mb-2">
                            <?php echo ts_icon('globe', 'text-muted'); ?>
                            <span><?php echo htmlspecialchars($overviewPrimaryClient->website, ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="contact-item d-flex align-items-center mb-0">
                            <?php echo ts_icon('map-pin', 'text-muted'); ?>
                            <?php if ($overviewClientAddress !== '') : ?>
                                <span><?php echo htmlspecialchars($overviewClientAddress, ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php elseif ($overviewShowClientActions) : ?>
                                <a href="<?php echo htmlspecialchars($overviewClientEditUrl, ENT_QUOTES, 'UTF-8'); ?>" class="grey text-decoration-none"><?php echo htmlspecialchars($lang['Add address'] ?? 'Add address', ENT_QUOTES, 'UTF-8'); ?></a>
                            <?php else : ?>
                                <span class="grey"><?php echo htmlspecialchars($lang['Add address'] ?? 'Add address', ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
            </div>
        </div>

        <div class="col-lg-6 d-flex">
            <div class="widget-card po-overview-card contact-info h-100 w-100 d-flex flex-column">
                    <div class="po-card-header align-all mb-3">
                        <div class="title-head mb-0 text-uppercase"><?php echo htmlspecialchars($lang['Company'] ?? 'Company', ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php if ($overviewClientCompany && $overviewCompanyId > 0 && $overviewShowCompanyActions) : ?>
                        <div class="dropdown">
                            <button class="btn-dots" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="<?php echo htmlspecialchars($lang['Action'] ?? 'Action', ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo $poBtnDotsSvg; ?>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li>
                                    <button type="button" class="dropdown-item d-flex align-items-center js-view-client-company" data-company-id="<?php echo (int) $overviewCompanyId; ?>">
                                        <?php echo $poViewIconSvg; ?>
                                        <?php echo htmlspecialchars($lang['View company'] ?? 'View company', ENT_QUOTES, 'UTF-8'); ?>
                                    </button>
                                </li>
                                <li>
                                    <button type="button" class="dropdown-item d-flex align-items-center js-edit-client-company" data-company-id="<?php echo (int) $overviewCompanyId; ?>">
                                        <?php echo $poEditIconSvg; ?>
                                        <?php echo htmlspecialchars($lang['Edit company'] ?? 'Edit company', ENT_QUOTES, 'UTF-8'); ?>
                                    </button>
                                </li>
                            </ul>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php if ($overviewClientCompany) : ?>
                    <div class="d-flex align-items-center mb-4 col-gap">
                        <div class="po-contact-avatar-wrap flex-shrink-0">
                            <?php if ($overviewCompanyLogoUrl !== '') : ?>
                                <img src="<?php echo htmlspecialchars($overviewCompanyLogoUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="" class="po-contact-avatar po-company-logo" width="48" height="48">
                            <?php else : ?>
                                <div class="po-company-initial po-contact-avatar rounded-circle d-flex align-items-center justify-content-center">
                                    <?php
                                    $coName = (string) ($overviewClientCompany['name'] ?? 'C');
                                    echo htmlspecialchars(strtoupper(substr($coName, 0, 1)), ENT_QUOTES, 'UTF-8');
                                    ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div>
                            <div class="po-client-name"><?php echo htmlspecialchars($overviewClientCompany['name'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="grey">
                                <?php
                                $vatLabel = trim((string) ($overviewClientCompany['vat_number'] ?? ''));
                                echo $vatLabel !== ''
                                    ? htmlspecialchars(($lang['VAT'] ?? 'VAT') . ': ' . $vatLabel, ENT_QUOTES, 'UTF-8')
                                    : htmlspecialchars($lang['Linked company'] ?? 'Linked company', ENT_QUOTES, 'UTF-8');
                                ?>
                            </div>
                        </div>
                    </div>
                    <div class="contact-infoo">
                        <div class="contact-item d-flex align-items-center mb-2">
                            <?php echo ts_icon('emails', 'text-muted'); ?>
                            <span><?php echo !empty($overviewClientCompany['email']) ? htmlspecialchars($overviewClientCompany['email'], ENT_QUOTES, 'UTF-8') : '—'; ?></span>
                        </div>
                        <div class="contact-item d-flex align-items-center mb-2">
                            <?php echo ts_icon('phone', 'text-muted'); ?>
                            <span><?php echo !empty($overviewClientCompany['phone']) ? htmlspecialchars($overviewClientCompany['phone'], ENT_QUOTES, 'UTF-8') : '—'; ?></span>
                        </div>
                        <?php if (!empty($overviewClientCompany['website'])) : ?>
                        <div class="contact-item d-flex align-items-center mb-2">
                            <?php echo ts_icon('globe', 'text-muted'); ?>
                            <span><?php echo htmlspecialchars($overviewClientCompany['website'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="contact-item d-flex align-items-center mb-0">
                            <?php echo ts_icon('map-pin', 'text-muted'); ?>
                            <?php if ($overviewCompanyAddress !== '') : ?>
                                <span><?php echo htmlspecialchars($overviewCompanyAddress, ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php elseif ($overviewCompanyId > 0 && $overviewShowCompanyActions) : ?>
                                <button type="button" class="btn btn-link p-0 border-0 grey text-decoration-none js-edit-client-company" data-company-id="<?php echo (int) $overviewCompanyId; ?>"><?php echo htmlspecialchars($lang['Add address'] ?? 'Add address', ENT_QUOTES, 'UTF-8'); ?></button>
                            <?php else : ?>
                                <span class="grey"><?php echo htmlspecialchars($lang['Add address'] ?? 'Add address', ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php else : ?>
                    <div class="po-company-empty flex-grow d-flex flex-column align-items-start">
                        <img src="<?php echo htmlspecialchars(rtrim((string) $url, '/') . '/assets/images/create.png', ENT_QUOTES, 'UTF-8'); ?>" alt="" class="po-company-empty-icon mb-3">
                        <p class="grey mb-2"><?php echo htmlspecialchars($lang['No company created yet'] ?? 'No company created yet', ENT_QUOTES, 'UTF-8'); ?></p>
                        <?php if ($overviewShowCompanyActions && !empty($overviewCompaniesTableReady)) : ?>
                            <a href="#" class="js-open-client-company-create primary-btn" data-prefill-client-id="<?php echo (int) $overviewPrimaryClient->id; ?>"><?php echo htmlspecialchars($lang['Create now'] ?? 'Create now', ENT_QUOTES, 'UTF-8'); ?></a>
                        <?php else : ?>
                            <span class="grey"><?php echo htmlspecialchars($lang['Create now'] ?? 'Create now', ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($overviewHasCustomFields)) : ?>
    <div class="row g-3">
        <div class="col-12">
            <div class="widget-card po-overview-card">
                    <div class="po-card-header align-all mb-3">
                        <div class="d-flex align-items-center col-gap-10">
                            <div class="title-head mb-0"><?php echo htmlspecialchars($lang['Custom Fields'] ?? 'Custom fields', ENT_QUOTES, 'UTF-8'); ?></div>
                            <span class="badge bg-light text-dark rounded-pill"><?php echo count($overviewCustomFields); ?> <?php echo htmlspecialchars($lang['fields'] ?? 'fields', ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <div class="d-flex align-items-center col-gap-15">
                            <?php if ($overviewShowEditActions) {
                                $poEditForm();
                            } ?>
                        </div>
                    </div>
                    <div class="row g-3 po-cf-grid">
                        <?php foreach ($overviewCustomFields as $cfRow) :
                            $cfType = (string) ($cfRow['field_type'] ?? 'text');
                            $cfIconClass = 'po-cf-icon--' . preg_replace('/[^a-z0-9_-]/', '', $cfType);
                            ?>
                            <div class="col-md-6 mb-3">
                                <div class="po-cf-item">
                                    <div class="d-flex align-items-center justify-content-between col-gap w-100">
                                        <div class="po-cf-info min-w-0">
                                            <div class="po-cf-label grey"><?php echo htmlspecialchars($cfRow['label'], ENT_QUOTES, 'UTF-8'); ?></div>
                                            <div class="po-cf-value"><?php echo htmlspecialchars($cfRow['value_text'], ENT_QUOTES, 'UTF-8'); ?></div>
                                        </div>
                                        <span class="po-cf-icon <?php echo htmlspecialchars($cfIconClass, ENT_QUOTES, 'UTF-8'); ?> flex-shrink-0" aria-hidden="true"><?php echo project_cf_field_type_icon_svg($cfType); ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="row g-3 mb-3">
        <div class="col-12">
            <div class="widget-card po-overview-card">
                    <div class="title-head mb-3"><?php echo htmlspecialchars($lang['Recent Activity'] ?? 'Recent Activity', ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php if (empty($overviewActivities)) : ?>
                        <p class="grey mb-0"><?php echo htmlspecialchars($lang['No recent activity'] ?? 'No recent activity', ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php else : ?>
                        <div class="po-activity-list">
                            <?php foreach ($overviewActivities as $actRow) :
                                $act = project_activity_format_message($actRow, $lang);
                                $actIcon = preg_replace('/[^a-z0-9_-]/', '', (string) ($act['icon'] ?? 'edit'));
                                if ($actIcon === '') {
                                    $actIcon = 'edit';
                                }
                                ?>
                                <div class="po-activity-item po-activity-item--<?php echo htmlspecialchars($actIcon, ENT_QUOTES, 'UTF-8'); ?>">
                                    <span class="po-activity-icon po-activity-icon--<?php echo htmlspecialchars($actIcon, ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"><?php echo project_activity_icon_svg($actIcon); ?></span>
                                    <div class="po-activity-content flex-grow min-w-0">
                                        <div class="po-activity-top d-flex justify-content-between align-items-start gap-3">
                                            <strong class="po-activity-title"><?php echo htmlspecialchars($act['title'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                            <span class="po-activity-time grey"><?php echo htmlspecialchars($act['relative_time'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        </div>
                                        <p class="po-activity-desc grey mb-0"><?php echo $act['body']; ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
            </div>
        </div>
    </div>

</div>
