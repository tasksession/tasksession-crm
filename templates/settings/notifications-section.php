<?php
/**
 * Settings > Notifications section
 */
$__pushBrandName = '';
if (isset($company_name) && trim((string) $company_name) !== '') {
    $__pushBrandName = trim((string) $company_name);
} elseif (isset($settings) && is_object($settings) && trim((string) ($settings->company_name ?? '')) !== '') {
    $__pushBrandName = trim((string) $settings->company_name);
} elseif (isset($syatem_title) && trim((string) $syatem_title) !== '') {
    $__pushBrandName = trim((string) $syatem_title);
} else {
    $__pushBrandName = 'Your workspace';
}
$__pushBrandNameEsc = htmlspecialchars($__pushBrandName, ENT_QUOTES, 'UTF-8');
$__pushDesc = sprintf(
    $lang['Get instant notifications from %s for messages, tasks, projects, and important updates.'] ?? 'Get instant notifications from %s for messages, tasks, projects, and important updates even when you\'re not online.',
    $__pushBrandNameEsc
);
?>
<div class="settings-card w-100 push-notifications-card" id="push-notifications-settings" data-push-brand="<?php echo $__pushBrandNameEsc; ?>">
    <div class="card-header push-notifications-card__header">
        <div class="push-notifications-card__header-icon mb-4" aria-hidden="true">
            <?php echo ts_icon('bell', 'push-notifications-card__header-icon-svg'); ?>
        </div>
        <div class="push-notifications-card__header-text">
            <h4><?php echo htmlspecialchars($lang['Browser Push Notifications'] ?? 'Browser push notifications', ENT_QUOTES, 'UTF-8'); ?></h4>
            <p><?php echo $__pushDesc; ?></p>
        </div>
    </div>

    <div class="card-body">
        <div id="push-not-supported" class="push-guidance-alert d-none">
            <?php echo htmlspecialchars($lang['Browser push not supported on this device'] ?? 'Browser push notifications are not supported on this browser or device.', ENT_QUOTES, 'UTF-8'); ?>
        </div>

        <div id="push-ios-in-app" class="push-ios-guidance d-none">
            <div class="push-ios-guidance__card">
                <p class="mb-2"><?php
                echo sprintf(
                    $lang['Open %s in Safari first, then add it to your Home Screen to enable notifications.'] ?? 'Open %s in Safari first, then add it to your Home Screen to enable notifications.',
                    $__pushBrandNameEsc
                );
                ?></p>
                <p class="text-muted small mb-0"><?php echo htmlspecialchars($lang['Use the browser menu and choose Open in Safari.'] ?? 'Use the browser menu and choose "Open in Safari".', ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
        </div>

        <div id="push-ios-unsupported" class="push-ios-guidance d-none">
            <div class="push-guidance-alert mb-0">
                <?php echo htmlspecialchars($lang['Browser notifications not available on this iPhone iPad'] ?? 'Browser notifications are not available on this iPhone/iPad version.', ENT_QUOTES, 'UTF-8'); ?>
            </div>
        </div>

        <div id="push-ios-home-screen" class="push-ios-guidance d-none">
            <div class="push-ios-guidance__card">
                <h5 class="push-ios-guidance__title"><?php echo htmlspecialchars($lang['Enable notifications on iPhone'] ?? 'Enable notifications on iPhone', ENT_QUOTES, 'UTF-8'); ?></h5>
                <p class="push-ios-guidance__lead"><?php
                echo sprintf(
                    $lang['Add %s to Home Screen for notifications'] ?? 'Add %s to your Home Screen to receive browser notifications.',
                    $__pushBrandNameEsc
                );
                ?></p>
                <ol class="push-ios-guidance__steps">
                    <li><?php echo htmlspecialchars($lang['Open this page in Safari'] ?? 'Open this page in Safari', ENT_QUOTES, 'UTF-8'); ?></li>
                    <li><?php echo htmlspecialchars($lang['Tap the Share button'] ?? 'Tap the Share button', ENT_QUOTES, 'UTF-8'); ?></li>
                    <li><?php echo htmlspecialchars($lang['Select Add to Home Screen'] ?? 'Select "Add to Home Screen"', ENT_QUOTES, 'UTF-8'); ?></li>
                    <li><?php echo htmlspecialchars($lang['Tap Add'] ?? 'Tap "Add"', ENT_QUOTES, 'UTF-8'); ?></li>
                    <li><?php
                    echo sprintf(
                        $lang['Open %s from the Home Screen icon'] ?? 'Open %s from the Home Screen icon',
                        $__pushBrandNameEsc
                    );
                    ?></li>
                    <li><?php echo htmlspecialchars($lang['Return to Settings Notifications'] ?? 'Return to Settings > Notifications', ENT_QUOTES, 'UTF-8'); ?></li>
                    <li><?php echo htmlspecialchars($lang['Tap Enable notifications'] ?? 'Tap "Enable notifications"', ENT_QUOTES, 'UTF-8'); ?></li>
                </ol>
                <p class="text-muted small mb-0"><?php echo htmlspecialchars($lang['Notifications from Home Screen app note'] ?? 'Notifications must be enabled from the Home Screen app on supported iPhone/iPad versions.', ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
        </div>

        <div id="push-settings-content" class="d-none">
            <div class="push-settings-row">
                <div class="push-settings-row__icon push-settings-row__icon--blue" aria-hidden="true">
                    <?php echo ts_icon('globe', 'push-settings-row__icon-svg'); ?>
                </div>
                <div class="push-settings-row__main">
                    <strong class="push-settings-row__title"><?php echo htmlspecialchars($lang['Browser Push'] ?? 'Browser Push', ENT_QUOTES, 'UTF-8'); ?></strong>
                    <span class="push-settings-row__sub"><?php echo htmlspecialchars($lang['Global preference for all your devices'] ?? 'Global preference for all your devices', ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="push-settings-row__action">
                    <div class="checkbox-wrapper-6 flex-shrink-0">
                        <input class="tgl tgl-light" id="push-global-toggle" type="checkbox" checked>
                        <label class="tgl-btn" for="push-global-toggle"></label>
                    </div>
                </div>
            </div>

            <div class="push-settings-row push-settings-row--status">
                <div class="push-settings-row__icon push-settings-row__icon--blue" aria-hidden="true">
                    <?php echo ts_icon('chart-bar', 'push-settings-row__icon-svg'); ?>
                </div>
                <div class="push-settings-row__main">
                    <strong class="push-settings-row__title"><?php echo htmlspecialchars($lang['Status'] ?? 'Status', ENT_QUOTES, 'UTF-8'); ?></strong>
                    <div id="push-current-device-status" class="push-status-badge push-status-badge--muted">
                        <span class="push-status-badge__dot" aria-hidden="true"></span>
                        <span id="push-current-device-status-text"><?php echo htmlspecialchars($lang['Checking'] ?? 'Checking…', ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <p id="push-status-hint" class="push-status-hint d-none"></p>
                </div>
                <div class="push-settings-row__action push-settings-row__action--buttons">
                    <button type="button" class="btn primary-btn btn-sm m-0 push-enable-btn" id="push-enable-btn">
                        <?php echo ts_icon('bell', 'push-btn-icon'); ?>
                        <span><?php echo htmlspecialchars($lang['Enable notifications'] ?? 'Enable notifications', ENT_QUOTES, 'UTF-8'); ?></span>
                    </button>
                    <button type="button" class="btn outline-btn btn-sm d-none m-0" id="push-unsubscribe-btn"><?php echo htmlspecialchars($lang['Unsubscribe this device'] ?? 'Unsubscribe this device', ENT_QUOTES, 'UTF-8'); ?></button>
                </div>
            </div>

            <div id="push-permission-blocked" class="push-guidance-alert push-guidance-alert--warning d-none">
                <?php echo htmlspecialchars($lang['Browser notification permission blocked'] ?? 'Browser notification permission is blocked. Allow notifications for this site in your browser settings.', ENT_QUOTES, 'UTF-8'); ?>
            </div>

            <div class="push-settings-row push-settings-row--devices">
                <div class="push-settings-row__icon push-settings-row__icon--purple" aria-hidden="true">
                    <?php echo ts_icon('desktop', 'push-settings-row__icon-svg'); ?>
                </div>
                <div class="push-settings-row__main push-settings-row__main--full">
                    <strong class="push-settings-row__title"><?php echo htmlspecialchars($lang['Notification devices'] ?? 'Notification devices', ENT_QUOTES, 'UTF-8'); ?></strong>

                    <div id="push-devices-empty" class="push-devices-empty">
                        <div class="push-devices-empty__inner">
                            <div class="push-devices-empty__phone" aria-hidden="true">
                                <?php echo ts_icon('mobile', 'push-devices-empty__phone-icon'); ?>
                            </div>
                            <div class="push-devices-empty__text">
                                <strong><?php echo htmlspecialchars($lang['No registered devices'] ?? 'No registered devices', ENT_QUOTES, 'UTF-8'); ?></strong>
                                <p><?php echo htmlspecialchars($lang['Once you enable notifications devices appear here'] ?? 'Once you enable notifications, your devices will appear here.', ENT_QUOTES, 'UTF-8'); ?></p>
                            </div>
                            <div class="push-devices-empty__art" aria-hidden="true">
                                <?php echo ts_icon('bell', 'push-devices-empty__bell-icon'); ?>
                            </div>
                        </div>
                    </div>

                    <div id="push-devices-list" class="push-devices-list d-none"></div>
                </div>
            </div>

            <div class="push-settings-test-row">
                <button type="button" class="btn outline-btn m-0 push-test-btn" id="push-test-btn">
                    <?php echo ts_icon('paper-airplane', 'push-btn-icon'); ?>
                    <span><?php echo htmlspecialchars($lang['Send test notification'] ?? 'Send test notification', ENT_QUOTES, 'UTF-8'); ?></span>
                </button>
            </div>

            <div class="push-privacy-note">
                <?php echo ts_icon('shield', 'push-privacy-note__icon'); ?>
                <span><?php echo htmlspecialchars($lang['Push privacy note'] ?? 'We respect your privacy. You can change these settings or unsubscribe at any time.', ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        </div>
    </div>
</div>
