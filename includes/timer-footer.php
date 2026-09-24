<?php
/**
 * Task session time tracking — footer script bootstrap (__tasksessionTimeTracking + JS tags).
 * Included from templates/main-footer.php when logged in; expects $url from footer scope.
 */
if (isset($session) && is_object($session) && method_exists($session, 'isLoggedIn') && $session->isLoggedIn()
    && function_exists('tasksession_time_tracking_enabled') && tasksession_time_tracking_enabled()) {
    global $lang;
    if (!isset($lang) || !is_array($lang)) {
        $lang = [];
    }
    $tasksessionTtAcct = isset($_SESSION['accountStatus']) ? (int) $_SESSION['accountStatus'] : 0;
    $tasksessionTtUserInitials = 'ME';
    if (class_exists('User')) {
        $tasksessionTtU = User::findById((int) $session->userId);
        if ($tasksessionTtU) {
            $tasksessionTtFn = trim((string) ($tasksessionTtU->firstName ?? ''));
            if ($tasksessionTtFn !== '') {
                $tasksessionTtParts = preg_split('/\s+/u', $tasksessionTtFn, -1, PREG_SPLIT_NO_EMPTY);
                $tasksessionTtIni = '';
                foreach ($tasksessionTtParts as $tasksessionTtPart) {
                    if (strlen($tasksessionTtIni) >= 3) {
                        break;
                    }
                    $tasksessionTtCh = function_exists('mb_substr')
                        ? mb_substr($tasksessionTtPart, 0, 1, 'UTF-8')
                        : substr($tasksessionTtPart, 0, 1);
                    $tasksessionTtIni .= function_exists('mb_strtoupper')
                        ? mb_strtoupper($tasksessionTtCh, 'UTF-8')
                        : strtoupper($tasksessionTtCh);
                }
                if ($tasksessionTtIni !== '') {
                    $tasksessionTtUserInitials = $tasksessionTtIni;
                }
            }
        }
    }
    $tasksessionTtShowManualTab = false;
    if ($tasksessionTtAcct === 1) {
        $tasksessionTtShowManualTab = true;
    } elseif ($tasksessionTtAcct === 3) {
        global $connect;
        $tasksessionPermPath = dirname(__DIR__) . '/includes/permissions.php';
        if (!empty($connect) && is_file($tasksessionPermPath)) {
            require_once $tasksessionPermPath;
            ensure_user_permissions($connect);
            $tasksessionTtShowManualTab = function_exists('has_permission') && has_permission('timer_log_show_manual');
        } else {
            $tasksessionTtShowManualTab = true;
        }
    }
    $tasksessionTtCfg = [
        'enabled' => true,
        'apiUrl' => rtrim($url, '/') . '/ajax/task_timer.php',
        'canMutateTimer' => ($tasksessionTtAcct === 1 || $tasksessionTtAcct === 3),
        'canEditEstimateInSidebar' => ($tasksessionTtAcct === 1 || $tasksessionTtAcct === 3),
        'accountStatus' => $tasksessionTtAcct,
        'isClient' => ($tasksessionTtAcct === 2),
        'clientTimerReadOnly' => ($tasksessionTtAcct === 2),
        'userId' => (int) $session->userId,
        'userInitials' => $tasksessionTtUserInitials,
        'timerLogShowManual' => $tasksessionTtShowManualTab,
        'strings' => [
            'noTimeLogged' => $lang['no_time_logged'] ?? '',
            'billableYes' => $lang['billable_yes'] ?? '',
            'billableNo' => $lang['billable_no'] ?? '',
            'pauseOrComplete' => $lang['pause_or_complete_current_timer'] ?? '',
            'remainingTime' => $lang['remaining_time'] ?? '',
            'overTime' => $lang['over_time'] ?? '',
            'timerOutOf' => $lang['timer_out_of'] ?? 'Out of %s',
            'timerLoadError' => $lang['timer_load_error'] ?? '',
            'percentDone' => $lang['percent_done'] ?? '% done',
            'plannedLabel' => $lang['planned_time_col'] ?? 'Planned time',
            'startTimer' => $lang['start_timer'] ?? '',
            'pauseTimer' => $lang['pause_timer'] ?? '',
            'resumeTimer' => $lang['resume_timer'] ?? '',
            'timerLogVerifiedByTimer' => $lang['timer_log_verified_by_timer'] ?? '',
            'timerLogManualEntry' => $lang['timer_log_manual_entry'] ?? '',
            'timerLogTooltipBillable' => $lang['timer_log_tooltip_billable'] ?? '',
            'timerLogTooltipNonBillable' => $lang['timer_log_tooltip_non_billable'] ?? '',
            'timerLogMarkIncomplete' => $lang['timer_log_mark_incomplete'] ?? '',
            'timerLogEditEntry' => $lang['timer_log_edit_entry'] ?? '',
            'timerLogDeleteEntry' => $lang['timer_log_delete_entry'] ?? '',
            'timerLogConfirmDelete' => $lang['timer_log_confirm_delete'] ?? '',
            'timerLogActionsAria' => $lang['timer_log_actions'] ?? '',
            'timerLogCopied' => $lang['timer_log_copied'] ?? '',
            'manualEntryDenied' => $lang['timer_manual_entry_denied'] ?? 'Manual time entry is not allowed for your role.',
            'kanbanTimerActive' => $lang['kanban_timer_active'] ?? 'Timer Active',
            'kanbanTimerPaused' => $lang['kanban_timer_paused'] ?? 'Timer Paused',
            'taskTimeEntryToday' => $lang['task_time_entry_today'] ?? '1 entry',
            'taskTimeEntriesToday' => $lang['task_time_entries_today'] ?? '%d entries',
            'today' => $lang['today'] ?? 'today',
            'notCheckedIn' => $lang['Not checked in'] ?? 'Not checked in',
            'shiftEnded' => $lang['Shift ended'] ?? 'Shift ended',
            'shiftComplete' => $lang['Shift complete'] ?? 'Shift complete',
            'shiftTimeLeftNote' => $lang['shift_time_left_note'] ?? '%s left',
        ],
    ];
    echo '<script>window.__tasksessionTimeTracking = ' . json_encode($tasksessionTtCfg, JSON_UNESCAPED_SLASHES) . ';</script>' . "\n";
    $tasksessionCsrfFetchM = @filemtime(SITE_ROOT . DS . 'assets' . DS . 'js' . DS . 'csrf-fetch.js');
    $tasksessionTaskTimerM = @filemtime(SITE_ROOT . DS . 'assets' . DS . 'js' . DS . 'task-timer.js');
    echo '<script src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . 'assets/js/csrf-fetch.js?v=' . ($tasksessionCsrfFetchM ?: '1') . '"></script>' . "\n";
    echo '<script src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . 'assets/js/task-timer.js?v=' . ($tasksessionTaskTimerM ?: '1') . '"></script>' . "\n";
} else {
    echo '<script>window.__tasksessionTimeTracking = { enabled: false };</script>' . "\n";
}
