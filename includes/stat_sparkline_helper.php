<?php
/**
 * Dynamic sparkline for dashboard / profile stat cards.
 * Pass last-N-months numeric series; the SVG path is built from those values.
 */

function statSparklineMonthKeys($months = 12) {
    $keys = [];
    $cursor = new DateTime('first day of this month');
    $cursor->modify('-' . ((int)$months - 1) . ' months');
    for ($i = 0; $i < (int)$months; $i++) {
        $keys[] = $cursor->format('Y-m');
        $cursor->modify('+1 month');
    }
    return $keys;
}

/** Month span from first Y-m bucket through current month (for all-time sparklines). */
function statSparklineMonthsSince($firstYm, $maxMonths = 120) {
    $firstYm = trim((string) $firstYm);
    if ($firstYm === '' || $firstYm === '0000-00') {
        return 12;
    }
    try {
        $first = DateTime::createFromFormat('Y-m-d', $firstYm . '-01');
        $now = new DateTime('first day of this month');
        if (!$first || $first > $now) {
            return 12;
        }
        $months = ((int) $now->format('Y') - (int) $first->format('Y')) * 12
            + ((int) $now->format('m') - (int) $first->format('m')) + 1;
        return max(2, min((int) $maxMonths, $months));
    } catch (Exception $e) {
        return 12;
    }
}

/** MySQL 8+ safe: never compare to '0000-00-00' (NO_ZERO_DATE). Uses UNIX_TIMESTAMP. */
function statSparklineSqlUnixValid(string $columnExpr): string
{
    return "(UNIX_TIMESTAMP({$columnExpr}) IS NOT NULL AND UNIX_TIMESTAMP({$columnExpr}) > 0)";
}

/** Milestone chart date: issue_date if valid, else deadline. */
function statSparklineSqlMilestoneEffectiveDate(): string
{
    return 'CASE
        WHEN UNIX_TIMESTAMP(issue_date) > 0 THEN issue_date
        WHEN UNIX_TIMESTAMP(deadline) > 0 THEN deadline
        ELSE NULL
    END';
}

function statSparklineSeriesFromQuery($database, $sql, $months = 12) {
    $keys = statSparklineMonthKeys($months);
    $map = array_fill_keys($keys, 0);
    if (!$database || $sql === '') {
        return array_values($map);
    }
    try {
    $result = method_exists($database, 'querySoft')
        ? $database->querySoft($sql)
        : $database->query($sql);
    if ($result) {
        while ($row = $database->fetchArray($result)) {
            $ym = isset($row['ym']) ? (string)$row['ym'] : '';
            if (isset($map[$ym])) {
                $map[$ym] = (int)($row['c'] ?? 0);
            }
        }
        }
    } catch (Throwable $e) {
        error_log('[statSparklineSeriesFromQuery] ' . $e->getMessage());
    }
    return array_values($map);
}

function statSparklineCubicPath(array $coords, $tension = 6, $minY = null, $maxY = null) {
    $n = count($coords);
    if ($n < 2) {
        return 'M0 52 L320 52';
    }
    $tension = max(4, (float) $tension);
    $d = 'M' . round($coords[0][0], 2) . ' ' . round($coords[0][1], 2);
    for ($i = 0; $i < $n - 1; $i++) {
        $p0 = $coords[$i === 0 ? 0 : $i - 1];
        $p1 = $coords[$i];
        $p2 = $coords[$i + 1];
        $p3 = $coords[($i + 2 >= $n) ? $n - 1 : $i + 2];
        $c1x = $p1[0] + ($p2[0] - $p0[0]) / $tension;
        $c1y = $p1[1] + ($p2[1] - $p0[1]) / $tension;
        $c2x = $p2[0] - ($p3[0] - $p1[0]) / $tension;
        $c2y = $p2[1] - ($p3[1] - $p1[1]) / $tension;
        // Clamp only to chart floor/ceiling (not segment) so flat→peak rises smoothly
        // like Total clients / staff, without dipping below the baseline.
        if ($minY !== null) {
            $c1y = max((float) $minY, $c1y);
            $c2y = max((float) $minY, $c2y);
        }
        if ($maxY !== null) {
            $c1y = min((float) $maxY, $c1y);
            $c2y = min((float) $maxY, $c2y);
        }
        $d .= ' C' . round($c1x, 2) . ' ' . round($c1y, 2)
            . ' ' . round($c2x, 2) . ' ' . round($c2y, 2)
            . ' ' . round($p2[0], 2) . ' ' . round($p2[1], 2);
    }
    return $d;
}

/**
 * Soften sparse spikes into a smooth hill (clients/staff style) without changing totals.
 *
 * @param array<int,float> $norms
 * @return array<int,float>
 */
function statSparklineSmoothSparseNorms(array $norms)
{
    $n = count($norms);
    if ($n < 3) {
        return $norms;
    }
    $out = $norms;
    for ($i = 0; $i < $n; $i++) {
        $peak = (float) $norms[$i];
        if ($peak < 0.08) {
            continue;
        }
        if ($i > 0) {
            $out[$i - 1] = max((float) $out[$i - 1], $peak * 0.38);
        }
        if ($i > 1) {
            $out[$i - 2] = max((float) $out[$i - 2], $peak * 0.14);
        }
        if ($i + 1 < $n) {
            $out[$i + 1] = max((float) $out[$i + 1], $peak * 0.38);
        }
        if ($i + 2 < $n) {
            $out[$i + 2] = max((float) $out[$i + 2], $peak * 0.14);
        }
    }
    // Light 3-point blur so the rise/fall feels continuous.
    $blurred = $out;
    for ($i = 1; $i < $n - 1; $i++) {
        $blurred[$i] = ($out[$i - 1] * 0.25) + ($out[$i] * 0.5) + ($out[$i + 1] * 0.25);
    }
    for ($i = 0; $i < $n; $i++) {
        $blurred[$i] = max(0, min(1, (float) $blurred[$i]));
    }
    return $blurred;
}

/**
 * Edge-to-edge dual spark with AI-addon curve logic.
 * Same cubic tension as Total clients / AI spark; Gaussian bumps so up/down
 * ease smoothly (not a triangle) while the crest stays narrow.
 *
 * @param array<int,float> $norms
 * @return array<int,float>
 */
function statSparklineAiNarrowHillSeries(array $norms)
{
    $n = count($norms);
    if ($n < 2) {
        return $norms;
    }
    $cap = 0.92;
    $floor = 0.06;
    $peak = $floor;
    $peakI = $n - 1;
    for ($i = 0; $i < $n; $i++) {
        $v = (float) $norms[$i];
        if ($v >= $peak) {
            $peak = $v;
            $peakI = $i;
        }
    }
    $second = $floor;
    for ($i = 0; $i < $n; $i++) {
        if ($i === $peakI) {
            continue;
        }
        $second = max($second, (float) $norms[$i]);
    }
    // Dense samples + soft Gaussian = AI cubic path looks rounded, not polygonal.
    $outN = 72;
    $out = array_fill(0, $outN, $floor);
    if ($peak <= $floor + 0.02) {
        return $out;
    }
    $t = $n <= 1 ? 1.0 : ($peakI / ($n - 1));
    $crestAt = $outN * (0.60 + 0.22 * $t);
    $crestAt = max(10.0, min($outN - 14.0, $crestAt));
    // FWHM ≈ 2.35*sigma → ~8% of chart (AI hill width), many samples under the curve.
    $mainSigma = $outN * 0.034;
    $ripple = max($floor, min($peak * 0.32, $second * 0.55));
    statSparklineAiGaussianBump($out, $crestAt, $peak, $floor, $mainSigma, $cap);
    statSparklineAiGaussianBump($out, $crestAt + $outN * 0.10, $ripple, $floor, $mainSigma * 0.55, $cap);
    statSparklineAiGaussianBump($out, $crestAt + $outN * 0.17, max($floor, $ripple * 0.62), $floor, $mainSigma * 0.48, $cap);
    return $out;
}

/**
 * Soft Gaussian bump (AI spark rise/fall) into a norms buffer.
 *
 * @param array<int,float> $out
 */
function statSparklineAiGaussianBump(array &$out, $center, $amp, $floor, $sigma, $cap)
{
    $n = count($out);
    if ($n < 2 || $amp <= $floor) {
        return;
    }
    $sigma = max(0.45, (float) $sigma);
    $reach = (int) ceil($sigma * 3.8);
    $c = (float) $center;
    for ($i = max(0, (int) floor($c) - $reach); $i <= min($n - 1, (int) ceil($c) + $reach); $i++) {
        $g = exp(-0.5 * pow(($i - $c) / $sigma, 2));
        $v = $floor + (((float) $amp) - $floor) * $g;
        $out[$i] = max((float) $out[$i], min((float) $cap, $v));
    }
}

/** Dual sparkline layout — match single spark pad/tension for the same smooth UI. */
function statSparklineDualNormCap() {
    return 0.92;
}

function statSparklineDualPadY() {
    return 10;
}

function statSparklineDualViewBox() {
    return '0 0 320 70';
}

function statSparklineDualBaselineY() {
    return 70;
}

function statSparklineDualNormsFromPoints(array $paidPoints, array $unpaidPoints) {
    $n = max(count($paidPoints), count($unpaidPoints), 2);
    $paidPoints = array_pad(array_slice(array_values($paidPoints), 0, $n), $n, 0);
    $unpaidPoints = array_pad(array_slice(array_values($unpaidPoints), 0, $n), $n, 0);
    $all = array_merge($paidPoints, $unpaidPoints);
    $min = min($all);
    $max = max($all);
    $range = $max - $min;
    $cap = statSparklineDualNormCap();
    $floor = 0.06; // keep flat segments slightly above the chart floor (clients-style)
    $paidNorms = [];
    $unpaidNorms = [];
    for ($i = 0; $i < $n; $i++) {
        if ($range <= 0) {
            $paidNorms[] = min($cap, 0.22);
            $unpaidNorms[] = min($cap, 0.12);
        } else {
            $paidNorms[] = max($floor, min($cap, ($paidPoints[$i] - $min) / $range));
            $unpaidNorms[] = max($floor, min($cap, ($unpaidPoints[$i] - $min) / $range));
        }
    }
    return [
        statSparklineAiNarrowHillSeries($paidNorms),
        statSparklineAiNarrowHillSeries($unpaidNorms),
    ];
}

function statSparklineDualPathsFromPoints(array $paidPoints, array $unpaidPoints) {
    list($paidNorms, $unpaidNorms) = statSparklineDualNormsFromPoints($paidPoints, $unpaidPoints);
    $height = statSparklineDualBaselineY();
    $padY = statSparklineDualPadY();
    $minY = 4;
    // Same cubic tension as AI addon / Total clients spark (smooth rise & fall).
    $tension = 6;
    $paidLine = statSparklinePathFromNorms($paidNorms, 320, $height, $padY, $minY, $tension);
    $unpaidLine = statSparklinePathFromNorms($unpaidNorms, 320, $height, $padY, $minY, $tension);
    $base = statSparklineDualBaselineY();
    $paidArea = $paidLine . ' L320 ' . $base . ' L0 ' . $base . ' Z';
    return [$paidLine, $unpaidLine, $paidArea];
}

function statSparklinePathFromNorms(array $norms, $width = 320, $height = 70, $padY = 10, $minY = null, $tension = 6) {
    $n = count($norms);
    if ($n < 2) {
        $norms = [0.2, 0.2];
        $n = 2;
    }
    // Extra bottom inset so stroke/round caps never sit on (or past) the chart floor —
    // same floor behavior as Total clients / dual sparklines.
    $padTop = (float) $padY;
    $padBottom = (float) $padY + 4;
    $usable = max(1, $height - $padTop - $padBottom);
    $step = $width / ($n - 1);
    $topFloor = $minY !== null ? (float) $minY : max(2, $padTop * 0.35);
    $bottomCeil = $height - $padBottom;
    $coords = [];
    for ($i = 0; $i < $n; $i++) {
        $norm = max(0, min(1, (float) $norms[$i]));
        $y = ($height - $padBottom) - ($norm * $usable);
        $coords[] = [$i * $step, max($topFloor, min($bottomCeil, $y))];
    }
    return statSparklineCubicPath($coords, $tension, $topFloor, $bottomCeil);
}

function statSparklinePath(array $points, $width = 320, $height = 70, $padY = 10) {
    $n = count($points);
    if ($n < 2) {
        $points = array_pad($points, 2, 0);
        $n = 2;
    }
    $min = min($points);
    $max = max($points);
    $range = $max - $min;
    if ($range <= 0) {
        return statSparklinePathFromNorms(array_fill(0, $n, 0.16), $width, $height, $padY);
    }
    $norms = [];
    for ($i = 0; $i < $n; $i++) {
        $norms[] = ($points[$i] - $min) / $range;
    }
    return statSparklinePathFromNorms($norms, $width, $height, $padY);
}

function statSparklineBootNorms($variant) {
    $waves = [
        'clients' => [0.38, 0.52, 0.30, 0.58, 0.22, 0.50, 0.34, 0.18, 0.46, 0.28, 0.16, 0.40],
        'staff' => [0.32, 0.18, 0.48, 0.28, 0.60, 0.24, 0.42, 0.16, 0.54, 0.30, 0.20, 0.44],
        'unpaid' => [0.22, 0.46, 0.28, 0.56, 0.18, 0.50, 0.34, 0.20, 0.48, 0.26, 0.58, 0.32],
        'paid' => [0.44, 0.24, 0.56, 0.18, 0.48, 0.30, 0.60, 0.22, 0.40, 0.16, 0.52, 0.28],
        'session' => [0.36, 0.50, 0.22, 0.54, 0.28, 0.16, 0.46, 0.32, 0.58, 0.20, 0.42, 0.26],
    ];
    return $waves[$variant] ?? $waves['clients'];
}

function renderAdminStatSparkline($variant, array $points = []) {
    static $sparkSeq = 0;
    $sparkSeq++;

    $aliases = [
        'projects' => 'clients',
        'tasks' => 'staff',
    ];
    if (isset($aliases[$variant])) {
        $variant = $aliases[$variant];
    }
    $safeVariant = preg_replace('/[^a-z0-9_-]/i', '', (string)$variant);
    if ($safeVariant === '') {
        $safeVariant = 'spark';
    }
    $hasData = false;
    foreach ($points as $value) {
        if ((float)$value > 0) {
            $hasData = true;
            break;
        }
    }
    $bootLine = statSparklinePathFromNorms(statSparklineBootNorms($variant));
    $realLine = $hasData
        ? statSparklinePath($points)
        : statSparklinePathFromNorms(array_fill(0, 12, 0.16));
    $bootArea = $bootLine . ' L320 70 L0 70 Z';
    $realArea = $realLine . ' L320 70 L0 70 Z';
    $delay = round((($sparkSeq - 1) % 4) * 0.08, 2);
    $gradId = 'statSparkFill-' . $safeVariant . '-' . $sparkSeq;
    $gradIdEsc = htmlspecialchars($gradId, ENT_QUOTES, 'UTF-8');
    ?>
    <div class="stat-sparkline<?php echo $hasData ? ' stat-sparkline--live' : ' stat-sparkline--empty'; ?>" aria-hidden="true" style="--spark-delay: <?php echo $delay; ?>s;">
        <svg class="stat-sparkline__svg" viewBox="0 0 320 70" preserveAspectRatio="none" focusable="false">
            <defs>
                <linearGradient id="<?php echo $gradIdEsc; ?>" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" class="stat-sparkline__fill-start"></stop>
                    <stop offset="100%" class="stat-sparkline__fill-end"></stop>
                </linearGradient>
            </defs>
            <path class="stat-sparkline__area" fill="url(#<?php echo $gradIdEsc; ?>)" d="<?php echo htmlspecialchars($bootArea, ENT_QUOTES, 'UTF-8'); ?>" data-spark-from="<?php echo htmlspecialchars($bootArea, ENT_QUOTES, 'UTF-8'); ?>" data-spark-to="<?php echo htmlspecialchars($realArea, ENT_QUOTES, 'UTF-8'); ?>"></path>
            <path class="stat-sparkline__line" stroke-width="1.5" d="<?php echo htmlspecialchars($bootLine, ENT_QUOTES, 'UTF-8'); ?>" data-spark-from="<?php echo htmlspecialchars($bootLine, ENT_QUOTES, 'UTF-8'); ?>" data-spark-to="<?php echo htmlspecialchars($realLine, ENT_QUOTES, 'UTF-8'); ?>"></path>
        </svg>
    </div>
    <?php
}

/**
 * Dual paid (secondary) + unpaid (dashed red) sparkline for Invoice Overview card.
 */
function renderAdminInvoiceDualSparkline(array $paidPoints = [], array $unpaidPoints = [], $idPrefix = 'invoice-overview') {
    static $dualSeq = 0;
    $dualSeq++;

    list($paidLine, $unpaidLine, $paidArea) = statSparklineDualPathsFromPoints($paidPoints, $unpaidPoints);
    $gradId = 'statSparkInvFill-' . $dualSeq;
    $gradIdEsc = htmlspecialchars($gradId, ENT_QUOTES, 'UTF-8');
    $viewBox = statSparklineDualViewBox();
    $safePrefix = preg_replace('/[^a-z0-9_-]/i', '', (string) $idPrefix);
    if ($safePrefix === '') {
        $safePrefix = 'invoice-overview';
    }
    $areaId = htmlspecialchars($safePrefix . '-area-paid', ENT_QUOTES, 'UTF-8');
    $linePaidId = htmlspecialchars($safePrefix . '-line-paid', ENT_QUOTES, 'UTF-8');
    $lineUnpaidId = htmlspecialchars($safePrefix . '-line-unpaid', ENT_QUOTES, 'UTF-8');
    ?>
    <div class="stat-sparkline stat-sparkline--dual" aria-hidden="true">
        <svg class="stat-sparkline__svg" viewBox="<?php echo htmlspecialchars($viewBox, ENT_QUOTES, 'UTF-8'); ?>" preserveAspectRatio="none" focusable="false">
            <defs>
                <linearGradient id="<?php echo $gradIdEsc; ?>" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" class="stat-sparkline__fill-paid-start"></stop>
                    <stop offset="100%" class="stat-sparkline__fill-paid-end"></stop>
                </linearGradient>
            </defs>
            <path class="stat-sparkline__area stat-sparkline__area--paid" id="<?php echo $areaId; ?>" fill="url(#<?php echo $gradIdEsc; ?>)" d="<?php echo htmlspecialchars($paidArea, ENT_QUOTES, 'UTF-8'); ?>"></path>
            <path class="stat-sparkline__line stat-sparkline__line--paid" id="<?php echo $linePaidId; ?>" d="<?php echo htmlspecialchars($paidLine, ENT_QUOTES, 'UTF-8'); ?>"></path>
            <path class="stat-sparkline__line stat-sparkline__line--unpaid" id="<?php echo $lineUnpaidId; ?>" d="<?php echo htmlspecialchars($unpaidLine, ENT_QUOTES, 'UTF-8'); ?>"></path>
        </svg>
    </div>
    <?php
}

function staffDashTimeProgressPct(int $loggedSec, int $targetSec): float
{
    if ($targetSec <= 0) {
        return 0.0;
    }
    return min(100.0, ($loggedSec / $targetSec) * 100.0);
}

/** Zoomed target so the dashboard timer graph stays readable while time is still low. */
function staffDashGraphScaleTarget(int $loggedSec, int $targetSec): int
{
    if ($targetSec <= 0) {
        return 3600;
    }
    $loggedSec = max(0, $loggedSec);
    $floor = 3600;
    $zoom = max($floor, $loggedSec + 600);

    return min($targetSec, $zoom);
}

function staffDashTimeGraphColor(float $pct): string
{
    $t = max(0.0, min(1.0, $pct / 100.0));
    $r = (int) round(239 + (34 - 239) * $t);
    $g = (int) round(68 + (197 - 68) * $t);
    $b = (int) round(68 + (94 - 68) * $t);
    return sprintf('rgb(%d,%d,%d)', $r, $g, $b);
}

function staffDashTimeLateDurationLabel(int $seconds): string
{
    $seconds = max(0, $seconds);
    if ($seconds <= 0) {
        return '0 min';
    }
    $h = intdiv($seconds, 3600);
    $m = intdiv($seconds % 3600, 60);
    if ($h > 0) {
        return $m > 0 ? ($h . 'h ' . $m . 'm') : ($h . 'h');
    }
    return max(1, $m) . ' min';
}

function staffDashTimeLateNote(int $loggedSec, int $targetSec, array $lang): string
{
    $behind = $targetSec - $loggedSec;
    if ($behind <= 0) {
        return (string) ($lang['On target'] ?? 'On target');
    }
    $dur = staffDashTimeLateDurationLabel($behind);
    $tpl = (string) ($lang['time_logged_late_note'] ?? '%s late');
    return sprintf($tpl, $dur);
}

function staffDashTimeGraphNorms(int $loggedSec, int $targetSec): array
{
    $pctNorm = $targetSec > 0 ? min(1.0, $loggedSec / $targetSec) : 0.0;
    $boot = statSparklineBootNorms('session');
    if ($pctNorm <= 0) {
        return array_fill(0, count($boot), 0.16);
    }
    $pctNorm = max(0.22, $pctNorm);
    $norms = [];
    foreach ($boot as $v) {
        $norms[] = 0.16 + ((float) $v - 0.16) * $pctNorm;
    }
    return $norms;
}

function renderStaffDashProgressGraph(int $loggedSec, int $targetSec, string $idPrefix = 'staff-dash-time-logged'): void
{
    static $sparkSeq = 0;
    $sparkSeq++;

    $hasData = $loggedSec > 0;
    $graphTargetSec = staffDashGraphScaleTarget($loggedSec, $targetSec);
    $bootLine = statSparklinePathFromNorms(statSparklineBootNorms('session'));
    $realLine = statSparklinePathFromNorms(staffDashTimeGraphNorms($loggedSec, $graphTargetSec));
    $bootArea = $bootLine . ' L320 70 L0 70 Z';
    $realArea = $realLine . ' L320 70 L0 70 Z';
    $delay = round((($sparkSeq - 1) % 4) * 0.08, 2);
    $safePrefix = preg_replace('/[^a-z0-9_-]/i', '', $idPrefix);
    if ($safePrefix === '') {
        $safePrefix = 'staff-dash-progress';
    }
    $gradId = 'statSparkFill-' . $safePrefix . '-' . $sparkSeq;
    $gradIdEsc = htmlspecialchars($gradId, ENT_QUOTES, 'UTF-8');
    $areaId = htmlspecialchars($safePrefix . '-graph-area', ENT_QUOTES, 'UTF-8');
    $lineId = htmlspecialchars($safePrefix . '-graph-line', ENT_QUOTES, 'UTF-8');
    ?>
    <div class="stat-sparkline<?php echo $hasData ? ' stat-sparkline--live' : ' stat-sparkline--empty'; ?>" aria-hidden="true" style="--spark-delay: <?php echo $delay; ?>s;">
        <svg class="stat-sparkline__svg" viewBox="0 0 320 70" preserveAspectRatio="none" focusable="false">
            <defs>
                <linearGradient id="<?php echo $gradIdEsc; ?>" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" class="stat-sparkline__fill-start"></stop>
                    <stop offset="100%" class="stat-sparkline__fill-end"></stop>
                </linearGradient>
            </defs>
            <path class="stat-sparkline__area" id="<?php echo $areaId; ?>" fill="url(#<?php echo $gradIdEsc; ?>)" d="<?php echo htmlspecialchars($bootArea, ENT_QUOTES, 'UTF-8'); ?>" data-spark-from="<?php echo htmlspecialchars($bootArea, ENT_QUOTES, 'UTF-8'); ?>" data-spark-to="<?php echo htmlspecialchars($realArea, ENT_QUOTES, 'UTF-8'); ?>"></path>
            <path class="stat-sparkline__line" id="<?php echo $lineId; ?>" stroke-width="1.5" d="<?php echo htmlspecialchars($bootLine, ENT_QUOTES, 'UTF-8'); ?>" data-spark-from="<?php echo htmlspecialchars($bootLine, ENT_QUOTES, 'UTF-8'); ?>" data-spark-to="<?php echo htmlspecialchars($realLine, ENT_QUOTES, 'UTF-8'); ?>"></path>
        </svg>
    </div>
    <?php
}

function renderStaffDashTimeLoggedGraph(int $loggedSec, int $targetSec): void
{
    renderStaffDashProgressGraph($loggedSec, $targetSec, 'staff-dash-time-logged');
}

function renderDashWorkOverviewCard($url, $lang, array $work, array $sparkOnTime, array $sparkLate, $onTimePct = 0, $onTimePctChange = 0, array $options = [])
{
    $defaults = [
        'card_id' => 'task-information-card',
        'title' => $lang['Task Information'] ?? 'Task information',
        'badge_count' => (int) ($work['assigned'] ?? 0),
        'badge_label' => $lang['Total Task'] ?? 'Total Tasks',
        'link_url' => $url . 'client/kanban.php',
        'view_label' => $lang['View Task'] ?? 'View Tasks',
    ];
    $options = array_merge($defaults, $options);
    $completed = (int) ($work['completed'] ?? 0);
    $dueToday = (int) ($work['due_today'] ?? 0);
    $overdue = (int) ($work['overdue'] ?? 0);
    $completeLabel = $lang['Complete'] ?? 'Complete';
    $dueTodayLabel = $lang['Due Today'] ?? 'Due today';
    $overdueLabel = $lang['Overdue'] ?? 'Overdue';
    $onTimeLabel = $lang['On-Time Completion'] ?? $lang['Overall tasks completed on time'] ?? 'On-Time Completion';
    $vsLastMonth = $lang['task_reports_vs_last_month'] ?? 'vs last month';
    $onTimePct = max(0, min(100, (int) $onTimePct));
    if ($onTimePct >= 80) {
        $onTimeColor = 'green';
    } elseif ($onTimePct >= 50) {
        $onTimeColor = '#d97706';
    } else {
        $onTimeColor = 'red';
    }
    $cardId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $options['card_id']);
    ?>
    <div class="widget-card stat-spark-card stat-spark-card--invoice-overview stat-spark-card--attendance-overview widget-card--linked" id="<?php echo htmlspecialchars($cardId, ENT_QUOTES, 'UTF-8'); ?>">
        <div class="stat-invoice-head">
            <div class="grey"><span><?php echo htmlspecialchars((string) $options['title'], ENT_QUOTES, 'UTF-8'); ?></span></div>
            <span class="stat-invoice-badge"><?php echo (int) $options['badge_count']; ?> <?php echo htmlspecialchars((string) $options['badge_label'], ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <div class="stat-invoice-grid stat-invoice-grid--triple">
            <div class="stat-invoice-col">
                <div class="counts dash-rttb is-paid mt-0"><?php echo $completed; ?></div>
                <div class="grey persent-count"><?php echo htmlspecialchars($completeLabel, ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
            <div class="stat-invoice-col">
                <div class="counts dash-rttb is-due-today mt-0"><?php echo $dueToday; ?></div>
                <div class="grey persent-count"><?php echo htmlspecialchars($dueTodayLabel, ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
            <div class="stat-invoice-col">
                <div class="counts dash-rttb is-overdue mt-0"><?php echo $overdue; ?></div>
                <div class="grey persent-count"><?php echo htmlspecialchars($overdueLabel, ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
        </div>
        <?php renderAdminInvoiceDualSparkline($sparkOnTime, $sparkLate, 'task-info-spark'); ?>
        <div class="stat-invoice-foot stat-invoice-foot--work-overview">
            <div class="grey persent-count stat-work-performance">
                <strong style="color:<?php echo htmlspecialchars($onTimeColor, ENT_QUOTES, 'UTF-8'); ?>;"><?php echo $onTimePct; ?>%</strong>
                <?php echo htmlspecialchars($onTimeLabel, ENT_QUOTES, 'UTF-8'); ?>
                <?php
                if ($onTimePctChange > 0) {
                    echo ' · <span style="color:green;">+' . (int) $onTimePctChange . '%</span> ' . htmlspecialchars($vsLastMonth, ENT_QUOTES, 'UTF-8');
                } elseif ($onTimePctChange < 0) {
                    echo ' · <span style="color:red;">' . (int) $onTimePctChange . '%</span> ' . htmlspecialchars($vsLastMonth, ENT_QUOTES, 'UTF-8');
                } else {
                    echo ' · 0% ' . htmlspecialchars($vsLastMonth, ENT_QUOTES, 'UTF-8');
                }
                ?>
            </div>
        </div>
        <a href="<?php echo htmlspecialchars((string) $options['link_url'], ENT_QUOTES, 'UTF-8'); ?>" class="widget-card__stretch-link" aria-label="<?php echo htmlspecialchars((string) $options['view_label'], ENT_QUOTES, 'UTF-8'); ?>"></a>
    </div>
    <?php
}

function renderDashSessionCard($url, $lang, $dateLabel, $durationLabel, $isOnline, array $sparkSession, $profileUrl)
{
    $title = $lang['Last Session'] ?? 'Last session';
    $activeLabel = $lang['Active'] ?? 'Active';
    ?>
    <div class="widget-card stat-spark-card stat-spark-card--session widget-card--linked">
        <div class="grey"><span><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></span></div>
        <div class="counts dash-rttb"><?php echo htmlspecialchars((string) $dateLabel, ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="grey persent-count">
            <?php if (!empty($isOnline)): ?>
                <span class="stat-session-status is-online">
                    <?php echo ts_icon('dot', 'stat-session-dot-icon'); ?>
                    <span><?php echo htmlspecialchars($activeLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                </span>
            <?php else: ?>
                <?php echo htmlspecialchars((string) $durationLabel, ENT_QUOTES, 'UTF-8'); ?>
            <?php endif; ?>
        </div>
        <?php renderAdminStatSparkline('session', $sparkSession); ?>
        <a href="<?php echo htmlspecialchars((string) $profileUrl, ENT_QUOTES, 'UTF-8'); ?>" class="widget-card__stretch-link" aria-label="<?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>"></a>
    </div>
    <?php
}

function renderDashOpenProjectCard($url, $lang, $count, $pctChange, array $sparkProjects, $projectsPath = 'staff/projects.php')
{
    ?>
    <div class="widget-card dash-counter stat-spark-card stat-spark-card--projects widget-card--linked">
        <div class="grey d-flex align-items-center col-gap-5">
            <span><?php echo $lang['Open Project']; ?></span>
            <i style="color:#888;cursor:pointer;" data-bs-toggle="tooltip" data-bs-placement="top" title="<?php echo htmlspecialchars($lang['This count shows the number of open projects that were active at any point during this month.'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo ts_icon('info', 'w-6'); ?></i>
        </div>
        <div class="counts dash-rttb"><?php echo (int) $count; ?></div>
        <div class="grey persent-count">
            <?php
            if ($pctChange > 0) {
                echo '<span style="color:green;">' . ts_icon('arrow-up-right', 'w-4') . ' ' . (int) $pctChange . '%</span> vs last month';
            } elseif ($pctChange < 0) {
                echo '<span style="color:red;">' . ts_icon('arrow-down-right', 'w-4') . ' ' . abs((int) $pctChange) . '%</span> vs last month';
            } else {
                echo ts_icon('arrows-up-down', 'w-4') . ' 0% vs last month';
            }
            ?>
        </div>
        <?php renderAdminStatSparkline('projects', $sparkProjects); ?>
        <a href="<?php echo htmlspecialchars($url . $projectsPath, ENT_QUOTES, 'UTF-8'); ?>" class="widget-card__stretch-link" aria-label="<?php echo htmlspecialchars($lang['Open Project'], ENT_QUOTES, 'UTF-8'); ?>"></a>
    </div>
    <?php
}

function renderDashOpenTaskCard($url, $lang, $count, $pctChange, array $sparkTasks, $tasksPath = 'staff/kanban.php')
{
    ?>
    <div class="widget-card dash-counter stat-spark-card stat-spark-card--tasks widget-card--linked">
        <div class="grey d-flex align-items-center col-gap-5">
            <span><?php echo $lang['Open Task']; ?></span>
            <i style="color:#888;cursor:pointer;" data-bs-toggle="tooltip" data-bs-placement="top" title="<?php echo htmlspecialchars($lang['This count shows the number of open task that were active at any point during this month.'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo ts_icon('info', 'w-6'); ?></i>
        </div>
        <div class="counts dash-rttb"><?php echo (int) $count; ?></div>
        <div class="grey persent-count">
            <?php
            if ($pctChange > 0) {
                echo '<span style="color:green;">' . ts_icon('arrow-up-right', 'w-4') . ' ' . (int) $pctChange . '%</span> vs last month';
            } elseif ($pctChange < 0) {
                echo '<span style="color:red;">' . ts_icon('arrow-down-right', 'w-4') . ' ' . abs((int) $pctChange) . '%</span> vs last month';
            } else {
                echo ts_icon('arrows-up-down', 'w-4') . ' 0% vs last month';
            }
            ?>
        </div>
        <?php renderAdminStatSparkline('tasks', $sparkTasks); ?>
        <a href="<?php echo htmlspecialchars($url . $tasksPath, ENT_QUOTES, 'UTF-8'); ?>" class="widget-card__stretch-link" aria-label="<?php echo htmlspecialchars($lang['Open Task'], ENT_QUOTES, 'UTF-8'); ?>"></a>
    </div>
    <?php
}
