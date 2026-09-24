<?php
/**
 * Estimated time dropdown (Task Time Tracking module). Requires $lang, optional $estimatedSecondsSelected (int|null).
 * Optional: $estimatedTimeReadOnly (bool), $estimatedTimeColumnClass (string, default col-12).
 */
if (!function_exists('tasksession_time_tracking_enabled') || !tasksession_time_tracking_enabled()) {
    return;
}
$estSel = isset($estimatedSecondsSelected) ? $estimatedSecondsSelected : null;
if ($estSel !== null) {
    $estSel = (int)$estSel;
}
$estimatedTimeReadOnly = !empty($estimatedTimeReadOnly);
$estColClass = isset($estimatedTimeColumnClass) ? (string)$estimatedTimeColumnClass : 'col-12';

if ($estimatedTimeReadOnly) {
    $plain = function_exists('tasksession_format_estimated_seconds_display')
        ? tasksession_format_estimated_seconds_display($estSel, $lang)
        : '—';
    ?>
<div class="<?php echo htmlspecialchars($estColClass, ENT_QUOTES, 'UTF-8'); ?>">
    <div class="form-group">
        <label><?php echo htmlspecialchars(isset($lang['estimated_time']) ? $lang['estimated_time'] : 'Estimated Time', ENT_QUOTES, 'UTF-8'); ?></label>
        <p class="form-control-plaintext mb-0"><?php echo htmlspecialchars($plain, ENT_QUOTES, 'UTF-8'); ?></p>
    </div>
</div>
    <?php
    return;
}

$presets = [
    900 => ['time_estimate_15m', '15 min'],
    1800 => ['time_estimate_30m', '30 min'],
    2700 => ['time_estimate_45m', '45 min'],
    3600 => ['time_estimate_1h', '1 hour'],
    5400 => ['time_estimate_1h30', '1:30 hours'],
    7200 => ['time_estimate_2h', '2:00 hours'],
    10800 => ['time_estimate_3h', '3:00 hours'],
    14400 => ['time_estimate_4h', '4:00 hours'],
    18000 => ['time_estimate_5h', '5:00 hours'],
    21600 => ['time_estimate_6h', '6:00 hours'],
    25200 => ['time_estimate_7h', '7:00 hours'],
    28800 => ['time_estimate_8h', '8:00 hours'],
    32400 => ['time_estimate_9h', '9:00 hours'],
    36000 => ['time_estimate_10h', '10:00 hours'],
];
$isCustomEst = ($estSel !== null && $estSel > 0 && !isset($presets[$estSel]));
$noEstLabel = isset($lang['no_estimate']) ? $lang['no_estimate'] : '—';
$customizeLabel = isset($lang['customize_time']) ? $lang['customize_time'] : 'Customize time';
$initialEstBtnLabel = $noEstLabel;
if ($estSel !== null && $estSel > 0) {
    if (isset($presets[$estSel])) {
        $meta = $presets[$estSel];
        $initialEstBtnLabel = isset($lang[$meta[0]]) ? $lang[$meta[0]] : $meta[1];
    } else {
        $initialEstBtnLabel = $customizeLabel;
    }
}
?>
<div class="<?php echo htmlspecialchars($estColClass, ENT_QUOTES, 'UTF-8'); ?>">
    <div class="form-group">
        <label for="estimatedTimeDropdownBtn"><?php echo htmlspecialchars(isset($lang['estimated_time']) ? $lang['estimated_time'] : 'Estimated Time', ENT_QUOTES, 'UTF-8'); ?></label>
        <div class="tasksession-estimated-dropdown">
            <div class="dropdown">
                <button
                    class="field-btn dropdown-toggle w-100 text-start tasksession-estimated-dropdown-btn"
                    type="button"
                    id="estimatedTimeDropdownBtn"
                    data-bs-toggle="dropdown"
                    aria-expanded="false"
                >
                    <span class="tasksession-estimated-dropdown-label"><?php echo htmlspecialchars($initialEstBtnLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                </button>
                <ul class="dropdown-menu w-100 dropdown-scroll" aria-labelledby="estimatedTimeDropdownBtn">
                    <li>
                        <button type="button" class="dropdown-item tasksession-est-est-item" data-est-value=""><?php echo htmlspecialchars($noEstLabel, ENT_QUOTES, 'UTF-8'); ?></button>
                    </li>
                    <?php foreach ($presets as $sec => $meta) :
                        $lk = $meta[0];
                        $lb = isset($lang[$lk]) ? $lang[$lk] : $meta[1];
                        ?>
                    <li>
                        <button type="button" class="dropdown-item tasksession-est-est-item" data-est-value="<?php echo (int)$sec; ?>"><?php echo htmlspecialchars($lb, ENT_QUOTES, 'UTF-8'); ?></button>
                    </li>
                    <?php endforeach; ?>
                    <li>
                        <button type="button" class="dropdown-item tasksession-est-est-item" data-est-value="custom"><?php echo htmlspecialchars($customizeLabel, ENT_QUOTES, 'UTF-8'); ?></button>
                    </li>
                </ul>
            </div>
            <input type="hidden" name="estimated_time_seconds" class="tasksession-estimated-seconds" value="<?php echo ($estSel !== null && $estSel > 0) ? (int)$estSel : ''; ?>">
            <div class="tasksession-estimated-custom-wrap mt-2<?php echo $isCustomEst ? ' tasksession-estimated-custom-wrap--open' : ''; ?>" style="display: <?php echo $isCustomEst ? 'block' : 'none'; ?>;">
                <div class="row g-2">
                    <div class="col-6">
                        <label class="small text-muted" for="estimatedTimeCustomHours"><?php echo htmlspecialchars(isset($lang['hours']) ? $lang['hours'] : 'Hours', ENT_QUOTES, 'UTF-8'); ?></label>
                        <input type="number" min="0" max="999" step="1" class="form-control tasksession-custom-est-hours" id="estimatedTimeCustomHours" value="<?php
                        $ch = $isCustomEst ? intdiv($estSel, 3600) : 0;
                        echo (int)$ch;
                        ?>">
                    </div>
                    <div class="col-6">
                        <label class="small text-muted" for="estimatedTimeCustomMinutes"><?php echo htmlspecialchars(isset($lang['minutes']) ? $lang['minutes'] : 'Minutes', ENT_QUOTES, 'UTF-8'); ?></label>
                        <input type="number" min="0" max="59" step="1" class="form-control tasksession-custom-est-minutes" id="estimatedTimeCustomMinutes" value="<?php
                        $cm = $isCustomEst ? intdiv($estSel % 3600, 60) : 0;
                        echo (int)$cm;
                        ?>">
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
