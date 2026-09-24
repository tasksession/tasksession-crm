<?php
/**
 * Setup guide wizard / celebration modal + fixed floating widget (from header).
 */
if (!function_exists('setup_guide_state')) {
    require_once dirname(__FILE__) . '/../includes/setup_guide.php';
}

$setupGuideCelebrate = function_exists('setup_guide_should_show_celebration') && setup_guide_should_show_celebration();
$setupGuideState = (!$setupGuideCelebrate && function_exists('setup_guide_state')) ? setup_guide_state() : array('show' => false);

if (!$setupGuideCelebrate && empty($setupGuideState['show'])) {
    return;
}

$base = isset($url) ? rtrim((string) $url, '/') . '/' : '/';
$ajaxUrl = $base . 'ajax/setup_guide.php';
$helpCenterUrl = 'https://www.tasksession.com/help-center/';

$doneCount = (int) ($setupGuideState['done_count'] ?? 0);
$total = (int) ($setupGuideState['total'] ?? count(setup_guide_steps()));
$pct = $total > 0 ? (int) round(($doneCount / $total) * 100) : 0;
$minimized = !empty($setupGuideState['minimized']);
$onStepPage = !empty($setupGuideState['on_step_page']);
$next = $setupGuideState['next'] ?? null;
$showModal = !$setupGuideCelebrate && !$minimized && !$onStepPage;
$showSticky = !$setupGuideCelebrate && !empty($setupGuideState['show']) && ($minimized || $onStepPage);
$smtpStepPending = false;
if (!empty($setupGuideState['show'])) {
    $smtpStepPending = !in_array('smtp', $setupGuideState['completed'] ?? array(), true);
}
$stickyCurrentLabel = '';
if ($showSticky && $onStepPage && !empty($setupGuideState['current_step'])) {
    foreach (($setupGuideState['steps'] ?? array()) as $__sgStepRow) {
        if (($__sgStepRow['key'] ?? '') === $setupGuideState['current_step']) {
            $stickyCurrentLabel = (string) ($__sgStepRow['label'] ?? '');
            break;
        }
    }
}
$onSmtpStepPage = $onStepPage && (($setupGuideState['current_step'] ?? '') === 'smtp') && $smtpStepPending;

if (!function_exists('ts_icon_inline')) {
    require_once dirname(__FILE__) . '/../includes/icon.php';
}
$setupGuideIconDone = function_exists('ts_icon_inline')
    ? ts_icon_inline('check-circle', 'setup-guide-step-icon setup-guide-step-icon-done')
    : '<svg class="setup-guide-step-icon setup-guide-step-icon-done" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>';
$setupGuideIconPending = '<svg class="setup-guide-step-icon setup-guide-step-icon-pending" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="12" cy="12" r="9"/></svg>';
$setupGuideIconCurrent = function_exists('ts_icon_inline')
    ? ts_icon_inline('arrow-right-circle', 'setup-guide-step-icon setup-guide-step-icon-current')
    : '<svg class="setup-guide-step-icon setup-guide-step-icon-current" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="m12.75 15 3-3m0 0-3-3m3 3h-7.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>';
$setupGuideIconCelebrate = function_exists('ts_icon_inline')
    ? ts_icon_inline('check-circle', 'setup-guide-celebrate-icon')
    : $setupGuideIconDone;
?>
<?php if ($setupGuideCelebrate): ?>
<div class="modal fade" id="setupGuideDoneModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="setupGuideDoneLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content setup-guide-modal-content">
            <div class="modal-body setup-guide-modal-body setup-guide-done-body text-center">
                <div class="setup-guide-celebrate-badge" aria-hidden="true">
                    <?php echo $setupGuideIconCelebrate; ?>
                </div>
                <h5 class="setup-guide-title" id="setupGuideDoneLabel">You're all set</h5>
                <p class="setup-guide-subtext setup-guide-done-copy">
                    Great work. Your Task Session workspace is ready to go.
                    Explore guides, tips, and answers anytime in our help center.
                </p>
                <div class="setup-guide-done-actions">
                    <a class="btn primary-btn" href="<?php echo htmlspecialchars($helpCenterUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
                        Visit help center
                    </a>
                    <button type="button" class="btn outline-btn" id="setupGuideAckCelebrationBtn">
                        Continue to dashboard
                    </button>
                </div>
                <p class="setup-guide-done-help">
                    Bookmark
                    <a href="<?php echo htmlspecialchars($helpCenterUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">tasksession.com/help-center</a>
                    for setup tips and troubleshooting.
                </p>
            </div>
        </div>
    </div>
</div>
<?php elseif ($showModal): ?>
<div class="modal fade" id="setupGuideModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="setupGuideModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content setup-guide-modal-content">
            <div class="modal-body setup-guide-modal-body">
                <div class="setup-guide-modal-top">
                    <h5 class="setup-guide-title" id="setupGuideModalLabel">Finish setting up Task Session</h5>
                    <button type="button" class="setup-guide-close" id="setupGuideMinimizeBtn" aria-label="Minimize">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <p class="setup-guide-subtext">Complete these steps so email, cron, branding, and permissions work correctly.</p>
                <div class="setup-guide-progress-meta">
                    <span><?php echo (int) $doneCount; ?> / <?php echo (int) $total; ?> complete</span>
                    <span><?php echo (int) $pct; ?>%</span>
                </div>
                <div class="setup-guide-progress-bar" role="progressbar" aria-valuenow="<?php echo (int) $pct; ?>" aria-valuemin="0" aria-valuemax="100">
                    <span style="width: <?php echo (int) $pct; ?>%;"></span>
                </div>
                <ul class="setup-guide-steps list-unstyled">
                    <?php foreach (($setupGuideState['steps'] ?? array()) as $step): ?>
                    <?php
                    $stepClass = 'setup-guide-step';
                    if (!empty($step['done'])) {
                        $stepClass .= ' is-done';
                    } elseif (!empty($step['current'])) {
                        $stepClass .= ' is-current';
                    }
                    ?>
                    <li class="<?php echo $stepClass; ?>">
                        <span class="setup-guide-step-check" aria-hidden="true">
                            <?php
                            if (!empty($step['done'])) {
                                echo $setupGuideIconDone;
                            } elseif (!empty($step['current'])) {
                                echo $setupGuideIconCurrent;
                            } else {
                                echo $setupGuideIconPending;
                            }
                            ?>
                        </span>
                        <span class="setup-guide-step-label"><?php echo htmlspecialchars($step['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php if (!empty($step['done'])): ?>
                        <span class="setup-guide-step-done-label">Done</span>
                        <?php elseif (!empty($step['current'])): ?>
                        <span class="setup-guide-step-current-label">You are here</span>
                        <?php else: ?>
                        <span class="setup-guide-step-actions">
                            <a class="setup-guide-step-link" href="<?php echo htmlspecialchars($base . ltrim($step['url'], '/'), ENT_QUOTES, 'UTF-8'); ?>">Open</a>
                            <?php if (($step['key'] ?? '') === 'smtp'): ?>
                            <button type="button" class="setup-guide-step-skip" data-setup-skip-smtp="1">Skip</button>
                            <?php endif; ?>
                        </span>
                        <?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php if ($next): ?>
                <div class="setup-guide-cta-wrap">
                    <a class="btn primary-btn setup-guide-cta" href="<?php echo htmlspecialchars($base . ltrim($next['url'], '/'), ENT_QUOTES, 'UTF-8'); ?>">
                        Go to <?php echo htmlspecialchars($next['label'], ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                    <?php if (($next['key'] ?? '') === 'smtp'): ?>
                    <button type="button" class="btn outline-btn setup-guide-cta-skip" data-setup-skip-smtp="1">Skip SMTP</button>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($showSticky)): ?>
<div class="setup-guide-sticky" id="setupGuideSticky" role="complementary" aria-label="Setup guide">
    <div class="setup-guide-sticky-accent" aria-hidden="true"></div>
    <div class="setup-guide-sticky-inner">
        <div class="setup-guide-sticky-top">
            <div>
                <p class="setup-guide-sticky-title">Setup guide</p>
                <p class="setup-guide-sticky-meta"><?php echo (int) $doneCount; ?> / <?php echo (int) $total; ?> complete</p>
            </div>
            <div class="setup-guide-sticky-menu" id="setupGuideStickyMenu">
                <button type="button" class="setup-guide-sticky-menu-btn" id="setupGuideStickyMenuBtn" aria-label="More options">⋮</button>
                <div class="setup-guide-sticky-menu-panel">
                    <button type="button" id="setupGuideRemindNextLoginBtn">Remind me next login</button>
                    <button type="button" id="setupGuideDismissForeverBtn">Don't show me again</button>
                </div>
            </div>
        </div>
        <div class="setup-guide-sticky-progress" aria-hidden="true">
            <span style="width: <?php echo (int) $pct; ?>%;"></span>
        </div>
        <?php if ($onSmtpStepPage): ?>
        <p class="setup-guide-sticky-next">Verify SMTP to continue, or skip for now.</p>
        <?php elseif ($onStepPage && $stickyCurrentLabel !== ''): ?>
        <p class="setup-guide-sticky-next">This page: <?php echo htmlspecialchars($stickyCurrentLabel, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php elseif ($next): ?>
        <p class="setup-guide-sticky-next">Next: <?php echo htmlspecialchars($next['label'], ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
        <div class="setup-guide-sticky-actions">
            <?php if ($onSmtpStepPage): ?>
            <button type="button" class="btn outline-btn" id="setupGuideSkipSmtpBtn">Skip SMTP</button>
            <?php elseif ($next): ?>
            <a class="btn primary-btn" href="<?php echo htmlspecialchars($base . ltrim($next['url'], '/'), ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo $onStepPage ? 'Next: ' . htmlspecialchars($next['label'], ENT_QUOTES, 'UTF-8') : 'Continue'; ?>
            </a>
            <?php else: ?>
            <button type="button" class="btn primary-btn" id="setupGuideExpandBtn">Continue</button>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
#setupGuideModal .setup-guide-modal-content,
#setupGuideDoneModal .setup-guide-modal-content {
    border: 1px solid var(--border-color);
    border-radius: 12px;
    overflow: hidden;
    background: var(--card-body-color);
    box-shadow: 0 18px 48px color-mix(in srgb, var(--title-color) 18%, transparent);
}
#setupGuideModal .setup-guide-modal-body,
#setupGuideDoneModal .setup-guide-modal-body {
    padding: 28px 24px 24px;
    background: var(--card-body-color);
    color: var(--title-color);
}
.setup-guide-modal-top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 8px;
}
.setup-guide-title {
    margin: 0;
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--title-color);
}
.setup-guide-close {
    border: 0;
    background: transparent;
    font-size: 28px;
    line-height: 1;
    color: color-mix(in srgb, var(--title-color) 55%, transparent);
    padding: 0 4px;
    cursor: pointer;
}
.setup-guide-subtext {
    margin: 0 0 16px;
    color: color-mix(in srgb, var(--title-color) 60%, transparent);
    font-size: 0.95rem;
    line-height: 1.45;
}
.setup-guide-done-body .setup-guide-title {
    margin-top: 12px;
    margin-bottom: 8px;
}
.setup-guide-done-copy {
    max-width: 360px;
    margin-left: auto;
    margin-right: auto;
}
.setup-guide-celebrate-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 72px;
    height: 72px;
    margin: 0 auto;
    border-radius: 50%;
    background: color-mix(in srgb, var(--primary-color) 14%, var(--card-body-color));
    color: var(--primary-color);
}
.setup-guide-celebrate-icon,
.setup-guide-celebrate-badge .setup-guide-step-icon,
.setup-guide-celebrate-badge svg {
    width: 36px !important;
    height: 36px !important;
    color: var(--primary-color);
}
.setup-guide-done-actions {
    display: flex;
    flex-direction: row;
    flex-wrap: wrap;
    justify-content: center;
    align-items: center;
    gap: 10px;
    margin: 8px 0 14px;
}
.setup-guide-done-actions .btn {
    width: auto !important;
    min-width: 0;
    white-space: nowrap;
}
.setup-guide-done-help {
    margin: 0;
    font-size: 0.82rem;
    color: color-mix(in srgb, var(--title-color) 55%, transparent);
    line-height: 1.45;
}
.setup-guide-done-help a {
    color: var(--primary-color);
    text-decoration: none;
    font-weight: 600;
}
.setup-guide-done-help a:hover {
    text-decoration: underline;
}
.setup-guide-progress-meta {
    display: flex;
    justify-content: space-between;
    font-size: 0.8rem;
    color: color-mix(in srgb, var(--title-color) 55%, transparent);
    margin-bottom: 6px;
}
.setup-guide-progress-bar {
    height: 8px;
    border-radius: 999px;
    background: color-mix(in srgb, var(--border-color) 85%, var(--card-body-color));
    overflow: hidden;
    margin-bottom: 16px;
}
.setup-guide-progress-bar > span {
    display: block;
    height: 100%;
    background: var(--primary-color);
    border-radius: 999px;
}
.setup-guide-steps {
    margin: 0 0 18px;
    padding: 0;
}
.setup-guide-step {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 0;
    border-bottom: 1px solid var(--border-color);
}
.setup-guide-step:last-child {
    border-bottom: 0;
}
.setup-guide-step-check {
    width: 22px;
    height: 22px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    color: color-mix(in srgb, var(--title-color) 40%, transparent);
}
.setup-guide-step-icon {
    width: 22px;
    height: 22px;
    display: block;
}
.setup-guide-step.is-done .setup-guide-step-check,
.setup-guide-step.is-done .setup-guide-step-icon {
    color: #059669;
}
.setup-guide-step.is-current .setup-guide-step-check,
.setup-guide-step.is-current .setup-guide-step-icon {
    color: var(--primary-color);
}
.setup-guide-step-label {
    flex: 1;
    font-size: 0.95rem;
    color: var(--title-color);
}
.setup-guide-step.is-done .setup-guide-step-label {
    color: color-mix(in srgb, var(--title-color) 55%, transparent);
}
.setup-guide-step.is-current .setup-guide-step-label {
    font-weight: 600;
    color: var(--primary-color);
}
.setup-guide-step-actions {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
}
.setup-guide-step-link,
.setup-guide-step-done-label,
.setup-guide-step-current-label,
.setup-guide-step-skip {
    font-size: 0.82rem;
    font-weight: 600;
}
.setup-guide-step-link {
    color: var(--primary-color);
    text-decoration: none;
}
.setup-guide-step-skip {
    border: 0;
    background: transparent;
    color: color-mix(in srgb, var(--title-color) 55%, transparent);
    padding: 0;
    cursor: pointer;
}
.setup-guide-step-skip:hover {
    color: var(--title-color);
}
.setup-guide-step-done-label {
    color: #059669;
}
.setup-guide-step-current-label {
    color: var(--primary-color);
}
.setup-guide-cta-wrap {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    align-items: center;
    gap: 10px;
}
.setup-guide-cta,
.setup-guide-cta-skip {
    width: auto !important;
    min-width: 0;
    padding: 10px 18px;
    border-radius: 8px;
    font-weight: 600;
}

/* Fixed floating setup widget (not in sidebar) */
.setup-guide-sticky {
    position: fixed;
    right: 24px;
    left: auto;
    bottom: 24px;
    z-index: 1080;
    width: min(280px, calc(100vw - 32px));
    margin: 0;
    padding: 0;
    border-radius: 8px;
    border: 1px solid var(--border-color);
    background: var(--card-body-color);
    box-shadow:
        0 10px 28px color-mix(in srgb, var(--title-color) 18%, transparent),
        0 0 0 1px color-mix(in srgb, var(--primary-color) 22%, transparent);
    overflow: visible;
    animation: setupGuideStickyIn 0.35s ease-out;
}
.setup-guide-sticky-accent {
    height: 3px;
    border-radius: 8px 8px 0 0;
    background: var(--primary-color);
    animation: setupGuideAccentPulse 2.2s ease-in-out infinite;
}
.setup-guide-sticky-inner {
    padding: 10px 12px 12px;
}
.setup-guide-sticky-top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 8px;
}
.setup-guide-sticky-title {
    margin: 0;
    font-size: 0.85rem;
    font-weight: 700;
    color: var(--title-color);
}
.setup-guide-sticky-meta {
    margin: 2px 0 0;
    font-size: 0.75rem;
    color: color-mix(in srgb, var(--title-color) 55%, transparent);
}
.setup-guide-sticky-progress {
    height: 4px;
    border-radius: 999px;
    background: color-mix(in srgb, var(--border-color) 80%, var(--card-body-color));
    overflow: hidden;
    margin-bottom: 8px;
}
.setup-guide-sticky-progress > span {
    display: block;
    height: 100%;
    border-radius: 999px;
    background: var(--primary-color);
}
.setup-guide-sticky-menu {
    position: relative;
}
.setup-guide-sticky-menu-btn {
    border: 0;
    background: transparent;
    color: color-mix(in srgb, var(--title-color) 55%, transparent);
    font-size: 18px;
    line-height: 1;
    padding: 0 2px;
    cursor: pointer;
}
.setup-guide-sticky-menu-panel {
    display: none;
    position: absolute;
    right: 0;
    bottom: calc(100% + 6px);
    z-index: 40;
    min-width: 170px;
    padding: 6px;
    border-radius: 6px;
    border: 1px solid var(--border-color);
    background: var(--card-body-color);
    box-shadow: 0 8px 20px color-mix(in srgb, var(--title-color) 14%, transparent);
}
.setup-guide-sticky-menu.open .setup-guide-sticky-menu-panel {
    display: block;
}
.setup-guide-sticky-menu-panel button {
    display: block;
    width: 100%;
    border: 0;
    background: transparent;
    text-align: left;
    padding: 8px 10px;
    border-radius: 4px;
    font-size: 0.8rem;
    color: var(--title-color);
    cursor: pointer;
}
.setup-guide-sticky-menu-panel button:hover {
    background: color-mix(in srgb, var(--primary-color) 10%, var(--card-body-color));
}
.setup-guide-sticky-next {
    margin: 0 0 8px;
    font-size: 0.8rem;
    color: color-mix(in srgb, var(--title-color) 70%, transparent);
}
.setup-guide-sticky-actions {
    display: flex;
    justify-content: flex-start;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
.setup-guide-sticky-actions .btn {
    width: auto !important;
    padding: 4px 10px !important;
    font-size: 0.72rem !important;
    font-weight: 600;
    border-radius: 6px !important;
    line-height: 1.25;
    min-height: 0 !important;
}
@keyframes setupGuideStickyIn {
    from {
        opacity: 0;
        transform: translateY(12px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
@keyframes setupGuideAccentPulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.55; }
}
@media (max-width: 767.98px) {
    .setup-guide-sticky {
        left: 12px;
        right: 12px;
        bottom: 12px;
        width: auto;
    }
}
</style>

<script>
(function () {
    var ajaxUrl = <?php echo json_encode($ajaxUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

    function postAction(action, extra) {
        var body = new URLSearchParams();
        body.set('action', action);
        if (extra) {
            Object.keys(extra).forEach(function (k) {
                body.set(k, extra[k]);
            });
        }
        return fetch(ajaxUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString(),
            credentials: 'same-origin'
        }).then(function (r) { return r.json(); });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var doneModalEl = document.getElementById('setupGuideDoneModal');
        if (doneModalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(doneModalEl).show();
        }

        var modalEl = document.getElementById('setupGuideModal');
        if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }

        var ackBtn = document.getElementById('setupGuideAckCelebrationBtn');
        if (ackBtn) {
            ackBtn.addEventListener('click', function () {
                postAction('ack_celebration').finally(function () {
                    if (doneModalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                        var inst = bootstrap.Modal.getInstance(doneModalEl);
                        if (inst) inst.hide();
                    }
                    window.location.reload();
                });
            });
        }

        var helpCta = document.querySelector('#setupGuideDoneModal a.primary-btn');
        if (helpCta) {
            helpCta.addEventListener('click', function () {
                // Open Help Center in a new tab; dismiss celebration so it won't keep popping up.
                postAction('ack_celebration').finally(function () {
                    if (doneModalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                        var inst = bootstrap.Modal.getInstance(doneModalEl);
                        if (inst) inst.hide();
                    }
                });
            });
        }

        var minimizeBtn = document.getElementById('setupGuideMinimizeBtn');
        if (minimizeBtn) {
            minimizeBtn.addEventListener('click', function () {
                postAction('minimize').finally(function () {
                    window.location.reload();
                });
            });
        }

        var expandBtn = document.getElementById('setupGuideExpandBtn');
        if (expandBtn) {
            expandBtn.addEventListener('click', function (e) {
                e.preventDefault();
                postAction('expand').finally(function () {
                    window.location.reload();
                });
            });
        }

        function skipSmtpStep() {
            postAction('mark_step', { step: 'smtp' }).finally(function () {
                window.location.reload();
            });
        }

        var skipSmtpBtn = document.getElementById('setupGuideSkipSmtpBtn');
        if (skipSmtpBtn) {
            skipSmtpBtn.addEventListener('click', skipSmtpStep);
        }
        document.querySelectorAll('[data-setup-skip-smtp]').forEach(function (el) {
            el.addEventListener('click', function (e) {
                e.preventDefault();
                skipSmtpStep();
            });
        });

        var menu = document.getElementById('setupGuideStickyMenu');
        var menuBtn = document.getElementById('setupGuideStickyMenuBtn');
        var dismissBtn = document.getElementById('setupGuideDismissForeverBtn');
        var remindBtn = document.getElementById('setupGuideRemindNextLoginBtn');
        if (menuBtn && menu) {
            menuBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                menu.classList.toggle('open');
            });
            document.addEventListener('click', function () {
                menu.classList.remove('open');
            });
        }
        if (remindBtn) {
            remindBtn.addEventListener('click', function () {
                postAction('remind_next_login').finally(function () {
                    var sticky = document.getElementById('setupGuideSticky');
                    if (sticky) sticky.remove();
                    var modalNode = document.getElementById('setupGuideModal');
                    if (modalNode) modalNode.remove();
                    window.location.reload();
                });
            });
        }
        if (dismissBtn) {
            dismissBtn.addEventListener('click', function () {
                postAction('dismiss_forever').finally(function () {
                    var sticky = document.getElementById('setupGuideSticky');
                    if (sticky) sticky.remove();
                    var modalNode = document.getElementById('setupGuideModal');
                    if (modalNode) modalNode.remove();
                    window.location.reload();
                });
            });
        }
    });
})();
</script>
