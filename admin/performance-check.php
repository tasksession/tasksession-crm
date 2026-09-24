<?php
/**
 * CRM Performance Diagnostic
 *
 * Times license cache vs remote API, DB, server load, and optional full bootstrap.
 * Open as admin, or append ?key=perfcheck
 * Optional: ?simulate=1 to time full lib-initialize.php (real page boot path)
 *
 * Remove or restrict on production when finished diagnosing.
 */
declare(strict_types=1);

$basePath = dirname(__DIR__);
$debugKey = 'perfcheck';
$pageStart = microtime(true);

header('Content-Type: text/html; charset=UTF-8');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

$authorized = false;
if (!empty($_SESSION['userId']) && isset($_SESSION['accountStatus']) && (int) $_SESSION['accountStatus'] === 1) {
    $authorized = true;
}
if (!$authorized && isset($_GET['key']) && hash_equals($debugKey, (string) $_GET['key'])) {
    $authorized = true;
}
if (!$authorized) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Forbidden. Log in as admin or use ?key={$debugKey}\n";
    exit;
}

$doSimulate = isset($_GET['simulate']) && (string) $_GET['simulate'] === '1';
$runFullStatus = !isset($_GET['skip_license_api']) || (string) $_GET['skip_license_api'] !== '1';
$runPageProbes = isset($_GET['probe_pages']) && (string) $_GET['probe_pages'] === '1';

/** @return string */
function perf_h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** @return string */
function perf_mask(string $s): string
{
    $len = strlen($s);
    if ($len <= 4) {
        return str_repeat('*', $len);
    }
    return substr($s, 0, 2) . str_repeat('*', max(0, $len - 4)) . substr($s, -2);
}

/** @return string */
function perf_ms(float $seconds): string
{
    return number_format($seconds * 1000, 1) . ' ms';
}

/**
 * @return 'ok'|'warn'|'bad'
 */
function perf_level(float $ms): string
{
    if ($ms >= 2000) {
        return 'bad';
    }
    if ($ms >= 500) {
        return 'warn';
    }
    return 'ok';
}

/**
 * @param array{label:string,ms:float,note?:string,level?:string} $row
 * @return string
 */
function perf_timing_row(array $row): string
{
    $ms = (float) $row['ms'];
    $level = $row['level'] ?? perf_level($ms);
    $note = isset($row['note']) && $row['note'] !== '' ? ' <span class="muted">— ' . perf_h($row['note']) . '</span>' : '';
    return '<tr><th>' . perf_h($row['label']) . '</th><td class="' . perf_h($level) . '"><strong>'
        . number_format($ms, 1) . ' ms</strong>' . $note . '</td></tr>';
}

/**
 * Resolve DB_* without executing config.php mysqli/die.
 * Supports getenv ?: "x", plain define("DB_X","y"), single/double quotes.
 *
 * @return array{DB_SERVER:string,DB_NAME:string,DB_USER:string,DB_PASS:string}
 */
function perf_parse_db_from_config(string $configPath): array
{
    $out = ['DB_SERVER' => '', 'DB_NAME' => '', 'DB_USER' => '', 'DB_PASS' => ''];

    foreach (array_keys($out) as $const) {
        $env = getenv($const);
        if ($env !== false && $env !== '') {
            $out[$const] = (string) $env;
        }
    }

    if (!is_readable($configPath)) {
        return $out;
    }
    $php = (string) file_get_contents($configPath);

    foreach (array_keys($out) as $const) {
        if ($out[$const] !== '') {
            continue;
        }
        $q = preg_quote($const, '/');
        $patterns = [
            // defined("X") ? null : define("X", getenv('X') ?: "value");
            '/define\s*\(\s*[\'"]' . $q . '[\'"]\s*,\s*getenv\s*\(\s*[\'"][^\'"]+[\'"]\s*\)\s*\?:\s*[\'"]((?:\\\\.|[^\'"\\\\])*)[\'"]\s*\)/s',
            // define("X", "value") or define('X', 'value')
            '/define\s*\(\s*[\'"]' . $q . '[\'"]\s*,\s*[\'"]((?:\\\\.|[^\'"\\\\])*)[\'"]\s*\)/s',
            // define("X", getenv("X") ?: 'value')
            '/define\s*\(\s*[\'"]' . $q . '[\'"]\s*,\s*getenv\s*\([^)]+\)\s*\?:\s*[\'"]((?:\\\\.|[^\'"\\\\])*)[\'"]\s*\)/s',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $php, $m)) {
                $out[$const] = stripcslashes($m[1]);
                break;
            }
        }
    }

    return $out;
}

/**
 * Load includes/config.php defines + $connect when regex/env failed.
 * Strips the die-on-error block so a bad connect returns null instead of killing the page.
 *
 * @return mysqli|null
 */
function perf_require_config_connect(string $configPath): ?mysqli
{
    if (isset($GLOBALS['connect']) && $GLOBALS['connect'] instanceof mysqli) {
        return $GLOBALS['connect'];
    }
    if (!is_readable($configPath)) {
        return null;
    }

    // Already defined (e.g. from lib-initialize) — connect manually
    if (defined('DB_SERVER') && defined('DB_NAME') && defined('DB_USER') && defined('DB_PASS')) {
        $c = @new mysqli((string) DB_SERVER, (string) DB_USER, (string) DB_PASS, (string) DB_NAME);
        if ($c->connect_error) {
            return null;
        }
        $c->set_charset('utf8mb4');
        $GLOBALS['connect'] = $c;
        return $c;
    }

    $code = (string) file_get_contents($configPath);
    // Soft-fail instead of die() so the diagnostic page still renders
    $code = preg_replace(
        '/if\s*\(\s*\$connect\s*->\s*connect_error\s*\)\s*\{[^}]*\}/s',
        'if ($connect->connect_error) { $connect = null; }',
        $code,
        1
    );
    if (!is_string($code) || $code === '') {
        return null;
    }

    try {
        // phpcs:ignore
        eval('?>' . $code);
    } catch (Throwable $e) {
        return null;
    }

    if (isset($connect) && $connect instanceof mysqli) {
        $GLOBALS['connect'] = $connect;
        return $connect;
    }
    if (isset($GLOBALS['connect']) && $GLOBALS['connect'] instanceof mysqli) {
        return $GLOBALS['connect'];
    }
    return null;
}

/**
 * @return array{ms:float,ok:bool,http_code:int,error:string,url:string}
 */
function perf_curl_probe(string $url, int $connectTimeout = 5, int $timeout = 10, bool $nobody = true, array $headers = []): array
{
    $result = ['ms' => 0.0, 'ok' => false, 'http_code' => 0, 'error' => '', 'url' => $url];
    if (!function_exists('curl_init')) {
        $result['error'] = 'curl extension missing';
        return $result;
    }
    $t0 = microtime(true);
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => $connectTimeout,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ];
    if ($nobody) {
        $opts[CURLOPT_NOBODY] = true;
    }
    if ($headers !== []) {
        $opts[CURLOPT_HTTPHEADER] = $headers;
    }
    curl_setopt_array($ch, $opts);
    curl_exec($ch);
    $result['ms'] = (microtime(true) - $t0) * 1000;
    $errno = curl_errno($ch);
    $result['http_code'] = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $result['error'] = $errno ? (curl_error($ch) ?: ('errno ' . $errno)) : '';
    $result['ok'] = ($errno === 0);
    curl_close($ch);
    return $result;
}

$timings = [];
$verdictCandidates = [];
$errors = [];
$licenseInfo = [
    'purchase_code_masked' => '',
    'cache_present' => false,
    'cache_expired' => false,
    'expires_at' => '',
    'ttl_remaining' => '',
    'cache_status' => '',
    'cache_timestamp' => '',
    'local_activation' => false,
    'path_used' => 'unknown',
    'full_status' => '',
    'full_message' => '',
    'api_url' => 'https://www.tasksession.com',
    'note' => 'Invalid/error API results are not cached — next page load can hit the remote API again.',
];
$serverInfo = [];
$slowRequests = [];
$slowRequestsError = '';
$bootstrapSimulateMs = null;
$pageProbeResults = [];
$tableCounts = [];
$knownHeavyPages = [
    ['path' => 'admin/index', 'why' => 'Dashboard: many aggregate queries + AJAX monthly_range + invoice_overview on load'],
    ['path' => 'admin/members', 'why' => 'Loads all staff then PHP-paginates; was N+1 FIND_IN_SET per row (now batched)'],
    ['path' => 'ajax/monthly_range.php', 'why' => 'Dashboard chart — was 36+ milestone queries/year (now 3 + batch invoice_items)'],
    ['path' => 'ajax/invoice_overview.php', 'why' => 'Dashboard invoice cards — was N+1 invoice_items (now batched)'],
    ['path' => 'staff/attendance', 'why' => 'Date-range attendance queries; probe as admin may 500/redirect'],
    ['path' => 'admin/all-tasks', 'why' => 'Large task lists / filters'],
    ['path' => 'admin/projects', 'why' => 'Project list with related counts'],
];

$configPath = $basePath . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'config.php';
$dbParsed = perf_parse_db_from_config($configPath);

mysqli_report(MYSQLI_REPORT_OFF);

// --- Optional: time full CRM bootstrap (real page path) ---
if ($doSimulate) {
    $t0 = microtime(true);
    try {
        require_once $basePath . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'lib-initialize.php';
        $bootstrapSimulateMs = (microtime(true) - $t0) * 1000;
        $timings[] = [
            'label' => 'Full lib-initialize.php (simulate)',
            'ms' => $bootstrapSimulateMs,
            'note' => 'Includes license middleware + settings + lang — same path as admin/staff pages',
        ];
        $verdictCandidates[] = [
            'ms' => $bootstrapSimulateMs,
            'label' => 'Full page bootstrap (lib-initialize)',
            'detail' => number_format($bootstrapSimulateMs, 1) . ' ms',
        ];
    } catch (Throwable $e) {
        $bootstrapSimulateMs = (microtime(true) - $t0) * 1000;
        $errors[] = 'Bootstrap simulate failed after ' . number_format($bootstrapSimulateMs, 1) . ' ms: ' . $e->getMessage();
    }
}

// --- Minimal / shared DB + license probes ---
/** @var mysqli|null $connect */
if (!isset($connect) || !($connect instanceof mysqli)) {
    $connect = (isset($GLOBALS['connect']) && $GLOBALS['connect'] instanceof mysqli)
        ? $GLOBALS['connect']
        : null;
}
if (!($connect instanceof mysqli)) {
    $t0 = microtime(true);
    $connectMethod = 'parsed';
    if ($dbParsed['DB_SERVER'] !== '') {
        $connect = @new mysqli($dbParsed['DB_SERVER'], $dbParsed['DB_USER'], $dbParsed['DB_PASS'], $dbParsed['DB_NAME']);
        if ($connect->connect_error) {
            $errors[] = 'DB connect (parsed creds) failed: ' . $connect->connect_error;
            $connect = null;
        } else {
            $connect->set_charset('utf8mb4');
        }
    }
    // Live configs often use different define styles — fall back to loading config.php
    if (!($connect instanceof mysqli)) {
        $connectMethod = 'config';
        $connect = perf_require_config_connect($configPath);
        if (!($connect instanceof mysqli)) {
            $errors[] = 'Could not connect via parsed credentials or includes/config.php. Check DB_* defines / env on this server.';
        }
    }
    $dbConnectMs = (microtime(true) - $t0) * 1000;
    $timings[] = [
        'label' => 'DB connect (mysqli)',
        'ms' => $dbConnectMs,
        'note' => $connect ? ('OK via ' . $connectMethod) : 'FAILED',
        'level' => $connect ? perf_level($dbConnectMs) : 'bad',
    ];
    if ($connect) {
        $verdictCandidates[] = ['ms' => $dbConnectMs, 'label' => 'Database connect', 'detail' => number_format($dbConnectMs, 1) . ' ms'];
    } else {
        $verdictCandidates[] = [
            'ms' => max($dbConnectMs, 99999.0),
            'label' => 'Database connect FAILED',
            'detail' => 'DB probes / license cache skipped until connect works',
        ];
    }
} else {
    $timings[] = [
        'label' => 'DB connect (mysqli)',
        'ms' => 0.0,
        'note' => 'Already open from lib-initialize',
        'level' => 'ok',
    ];
}

if ($connect instanceof mysqli) {
    $t0 = microtime(true);
    $okPing = (bool) @$connect->query('SELECT 1');
    $pingMs = (microtime(true) - $t0) * 1000;
    $timings[] = [
        'label' => 'DB query SELECT 1',
        'ms' => $pingMs,
        'note' => $okPing ? 'OK' : 'FAILED',
        'level' => $okPing ? perf_level($pingMs) : 'bad',
    ];

    $t0 = microtime(true);
    $settingsRow = null;
    $res = @$connect->query('SELECT id, purchase_code, url, company_name FROM settings WHERE id = 1 LIMIT 1');
    if ($res && ($row = $res->fetch_assoc())) {
        $settingsRow = $row;
    }
    $settingsMs = (microtime(true) - $t0) * 1000;
    $timings[] = [
        'label' => 'Settings row read',
        'ms' => $settingsMs,
        'note' => $settingsRow ? 'OK' : 'missing row',
        'level' => $settingsRow ? perf_level($settingsMs) : 'warn',
    ];

    $purchaseCode = is_array($settingsRow) ? trim((string) ($settingsRow['purchase_code'] ?? '')) : '';
    $licenseInfo['purchase_code_masked'] = '(n/a — Free edition)';
    $licenseInfo['local_activation'] = false;
    $licenseInfo['api_url'] = '';
    $licenseInfo['path_used'] = 'free_edition';
    $licenseInfo['note'] = 'Free edition: license verifier / wc_am_* tables are not shipped.';
    $licenseInfo['skipped'] = 'Free edition — license diagnostics disabled.';
    $runFullStatus = false;
    $timings[] = [
        'label' => 'License check',
        'ms' => 0.0,
        'note' => 'Skipped (Free edition)',
        'level' => 'ok',
    ];

    // Recent slow requests (reuse helper when possible)
    try {
        require_once $basePath . '/includes/server_load_helper.php';
        $tableCheck = @$connect->query("SHOW TABLES LIKE 'tblserver_request_logs'");
        if ($tableCheck && $tableCheck->num_rows > 0) {
            $slowRequests = get_slow_requests('24h');
        } else {
            $slowRequestsError = 'tblserver_request_logs not found — open admin/server-load once to initialize logging.';
        }
    } catch (Throwable $e) {
        $slowRequestsError = 'Could not load slow requests: ' . $e->getMessage();
    }

    // Table size + weight probes (helps rank heavy pages without self-HTTP curl)
    $tableCounts = [];
    foreach (['users', 'projects', 'tasks', 'milestones', 'invoice_items', 'leads', 'login_attempts'] as $tbl) {
        $t0 = microtime(true);
        $safe = preg_replace('/[^a-z0-9_]/i', '', $tbl);
        $cres = @$connect->query('SELECT COUNT(*) AS c FROM `' . $safe . '`');
        $ms = (microtime(true) - $t0) * 1000;
        $cnt = 0;
        if ($cres && ($crow = $cres->fetch_assoc())) {
            $cnt = (int) ($crow['c'] ?? 0);
        }
        $tableCounts[] = ['table' => $safe, 'rows' => $cnt, 'ms' => $ms];
        $timings[] = [
            'label' => 'COUNT ' . $safe,
            'ms' => $ms,
            'note' => number_format($cnt) . ' rows',
            'level' => perf_level($ms),
        ];
    }
}

// Server / heat metrics
$memCurrent = memory_get_usage(true);
$memPeak = memory_get_peak_usage(true);
$memLimitRaw = (string) ini_get('memory_limit');
$loadAvg = function_exists('sys_getloadavg') ? @sys_getloadavg() : false;
$diskFree = @disk_free_space($basePath);
$diskTotal = @disk_total_space($basePath);
$opcacheEnabled = false;
$opcacheMsg = 'opcache_get_status unavailable';
if (function_exists('opcache_get_status')) {
    $opc = @opcache_get_status(false);
    if (is_array($opc)) {
        $opcacheEnabled = !empty($opc['opcache_enabled']);
        $opcacheMsg = $opcacheEnabled
            ? ('enabled; cached_scripts=' . (int) ($opc['opcache_statistics']['num_cached_scripts'] ?? 0)
                . '; hit_rate=' . number_format((float) ($opc['opcache_statistics']['opcache_hit_rate'] ?? 0), 1) . '%')
            : 'extension present but disabled';
    } else {
        $opcacheMsg = 'disabled or restricted';
    }
} elseif (function_exists('extension_loaded') && extension_loaded('Zend OPcache')) {
    $opcacheMsg = 'Zend OPcache loaded but opcache_get_status blocked';
}

$serverInfo = [
    'php_version' => PHP_VERSION,
    'sapi' => PHP_SAPI,
    'memory_current' => $memCurrent,
    'memory_peak' => $memPeak,
    'memory_limit' => $memLimitRaw,
    'max_execution_time' => (string) ini_get('max_execution_time'),
    'load_avg' => is_array($loadAvg) ? $loadAvg : null,
    'disk_free' => $diskFree,
    'disk_total' => $diskTotal,
    'opcache' => $opcacheMsg,
    'server_software' => (string) ($_SERVER['SERVER_SOFTWARE'] ?? ''),
];

$loadWarning = '';
if (is_array($loadAvg) && isset($loadAvg[0]) && (float) $loadAvg[0] >= 4.0) {
    // Do NOT inject load*1000 into ms ranking — that falsely beats real timings (e.g. load 19 → "19840 ms").
    $loadWarning = 'Server load1=' . number_format((float) $loadAvg[0], 2)
        . ' is high (possible shared-host overload). Compare against DB/license timings below — load alone does not prove CRM page delay.';
}

// Optional same-origin page TTFB probes (uses viewer cookies when present)
// Under high load, self-curl often queues behind busy PHP workers and always times out — skip automatically.
$loadTooHighForProbes = is_array($loadAvg) && isset($loadAvg[0]) && (float) $loadAvg[0] >= 8.0;
if ($runPageProbes && $loadTooHighForProbes) {
    $errors[] = 'Page probes skipped: load1=' . number_format((float) $loadAvg[0], 2)
        . ' is too high for reliable self-curl (would false-timeout). Use slow-request log instead.';
    $runPageProbes = false;
}
if ($runPageProbes && function_exists('curl_init')) {
    $httpsOff = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $fwdProto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    $isHttps = $httpsOff || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443 || $fwdProto === 'https';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $scheme = $isHttps ? 'https' : 'http';
    $cookieHeader = [];
    if (!empty($_SERVER['HTTP_COOKIE'])) {
        $cookieHeader[] = 'Cookie: ' . (string) $_SERVER['HTTP_COOKIE'];
    }
    $paths = [
        '/admin/index',
        '/admin/members',
        '/staff/attendance',
    ];
    // Derive app base from script path (/comon/admin/performance-check → /comon)
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/admin/performance-check'));
    $appBase = preg_replace('#/admin/performance-check\.php$#', '', $scriptName);
    if (!is_string($appBase)) {
        $appBase = '';
    }
    foreach ($paths as $rel) {
        $url = $scheme . '://' . $host . $appBase . $rel;
        $probe = perf_curl_probe($url, 3, 8, false, $cookieHeader);
        $pageProbeResults[] = [
            'path' => $rel,
            'url' => $url,
            'ms' => $probe['ms'],
            'http_code' => $probe['http_code'],
            'ok' => $probe['ok'],
            'error' => $probe['error'],
        ];
        $timings[] = [
            'label' => 'Page TTFB ' . $rel,
            'ms' => $probe['ms'],
            'note' => 'HTTP ' . $probe['http_code'] . ($probe['error'] !== '' ? ' ' . $probe['error'] : ''),
            'level' => perf_level($probe['ms']),
        ];
        $verdictCandidates[] = [
            'ms' => $probe['ms'],
            'label' => 'Page probe ' . $rel,
            'detail' => number_format($probe['ms'], 1) . ' ms HTTP ' . $probe['http_code'],
        ];
    }
}

// Build verdict: highest ms among candidates (and special license messaging)
usort($verdictCandidates, static function ($a, $b) {
    return ($b['ms'] <=> $a['ms']);
});
$top = $verdictCandidates[0] ?? null;
$verdictLevel = 'ok';
$verdictText = 'No major delay detected in this run. Refresh 2–3 times; intermittent stalls often appear only on license cache miss.';
if ($top !== null) {
    if (stripos($top['label'], 'FAILED') !== false) {
        $verdictLevel = 'bad';
        $verdictText = 'Primary issue: ' . $top['label'] . ' (' . $top['detail'] . '). Fix DB connect so license/cache probes can run.';
    } elseif ($top['ms'] >= 2000) {
        $verdictLevel = 'bad';
        $verdictText = 'Primary suspect: ' . $top['label'] . ' (' . $top['detail'] . '). ';
        if (stripos($top['label'], 'License') !== false || stripos($top['label'], 'lib-initialize') !== false) {
            $verdictText .= 'This matches intermittent ~30–60s CRM stalls when the license API is slow and invalid results are not cached. '
                . 'license_modal.php can double the work when license_valid is false.';
        } else {
            $verdictText .= 'Investigate this component first.';
        }
    } elseif ($top['ms'] >= 500) {
        $verdictLevel = 'warn';
        $verdictText = 'Elevated: ' . $top['label'] . ' (' . $top['detail'] . '). Worth watching; not yet at typical 1-minute stall levels.';
    } else {
        $verdictText = 'Fastest path this run. Top component: ' . $top['label'] . ' (' . $top['detail'] . '). '
            . 'If pages still stall sometimes, re-run when slow or clear license cache and compare.';
    }
}

if ($licenseInfo['cache_present'] && $licenseInfo['cache_expired']) {
    $verdictText .= ' License cache row is EXPIRED — next full page loads may call the remote API.';
} elseif (!$licenseInfo['cache_present'] && $licenseInfo['local_activation']) {
    $verdictText .= ' No valid wc_am_cache row — getLicenseStatus may rebuild via local activation or remote API.';
}

if ($loadWarning !== '') {
    if ($verdictLevel === 'ok') {
        $verdictLevel = 'warn';
    }
    $verdictText .= ' ' . $loadWarning;
}

$pageTotalMs = (microtime(true) - $pageStart) * 1000;

$selfQs = $_GET;
unset($selfQs['simulate'], $selfQs['probe_pages'], $selfQs['skip_license_api']);
$baseQuery = http_build_query($selfQs);
$qJoin = $baseQuery !== '' ? '&' : '';
$linkSimulate = '?' . $baseQuery . $qJoin . 'simulate=1';
$linkProbe = '?' . $baseQuery . $qJoin . 'probe_pages=1';
$linkSkipApi = '?' . $baseQuery . $qJoin . 'skip_license_api=1';
$linkNormal = $baseQuery !== '' ? ('?' . $baseQuery) : '?';

function perf_fmt_bytes($bytes): string
{
    if ($bytes === false || $bytes === null) {
        return 'n/a';
    }
    $bytes = (float) $bytes;
    if ($bytes < 1024) {
        return round($bytes) . ' B';
    }
    $units = ['KB', 'MB', 'GB', 'TB'];
    $pow = (int) floor(log($bytes, 1024));
    $pow = max(1, min($pow, count($units)));
    return round($bytes / pow(1024, $pow), 2) . ' ' . $units[$pow - 1];
}

// ---- Paste-ready plain report ----
$plainReport = str_repeat('=', 72) . "\nCRM PERFORMANCE REPORT\n" . str_repeat('=', 72) . "\n";
$plainReport .= 'Generated: ' . date('c') . "\n";
$plainReport .= 'Page total: ' . number_format($pageTotalMs, 1) . " ms\n";
$plainReport .= 'Verdict: ' . $verdictText . "\n\n";
if ($errors !== []) {
    $plainReport .= "ERRORS\n";
    foreach ($errors as $err) {
        $plainReport .= '- ' . $err . "\n";
    }
    $plainReport .= "\n";
}
$plainReport .= "TIMINGS\n";
foreach ($timings as $row) {
    $plainReport .= '- ' . $row['label'] . ': ' . number_format((float) $row['ms'], 1) . ' ms';
    if (!empty($row['note'])) {
        $plainReport .= ' (' . $row['note'] . ')';
    }
    $plainReport .= "\n";
}
$plainReport .= "\nLICENSE\n";
$plainReport .= '- path=' . $licenseInfo['path_used'] . ' status=' . $licenseInfo['full_status'] . "\n";
$plainReport .= '- cache=' . ($licenseInfo['cache_present'] ? 'yes' : 'no')
    . ' expires=' . $licenseInfo['expires_at']
    . ' ttl=' . $licenseInfo['ttl_remaining'] . "\n";
$plainReport .= "\nSERVER\n";
$plainReport .= '- PHP ' . $serverInfo['php_version'] . ' ' . $serverInfo['sapi'] . ' / ' . $serverInfo['server_software'] . "\n";
if (is_array($serverInfo['load_avg'])) {
    $plainReport .= '- load ' . number_format((float) $serverInfo['load_avg'][0], 2)
        . ' / ' . number_format((float) $serverInfo['load_avg'][1], 2)
        . ' / ' . number_format((float) $serverInfo['load_avg'][2], 2) . "\n";
}
$plainReport .= '- memory ' . perf_fmt_bytes($serverInfo['memory_current']) . ' / peak '
    . perf_fmt_bytes($serverInfo['memory_peak']) . ' limit ' . $serverInfo['memory_limit'] . "\n";
$plainReport .= '- opcache ' . $serverInfo['opcache'] . "\n";
if ($tableCounts !== []) {
    $plainReport .= "\nTABLE COUNTS\n";
    foreach ($tableCounts as $tc) {
        $plainReport .= '- ' . $tc['table'] . ': ' . number_format((int) $tc['rows'])
            . ' rows in ' . number_format((float) $tc['ms'], 1) . " ms\n";
    }
}
$plainReport .= "\nKNOWN HEAVY PAGES (code review)\n";
foreach ($knownHeavyPages as $hp) {
    $plainReport .= '- ' . $hp['path'] . ' — ' . $hp['why'] . "\n";
}
if ($pageProbeResults !== []) {
    $plainReport .= "\nPAGE PROBES (self-curl; under high load often times out — use 8s cap)\n";
    foreach ($pageProbeResults as $pr) {
        $plainReport .= '- ' . $pr['path'] . ': ' . number_format((float) $pr['ms'], 1)
            . ' ms HTTP ' . (int) $pr['http_code']
            . ($pr['error'] !== '' ? ' ' . $pr['error'] : '') . "\n";
    }
}
if ($slowRequests !== []) {
    $plainReport .= "\nSLOW REQUESTS (24h sample)\n";
    foreach (array_slice($slowRequests, 0, 15) as $sr) {
        $plainReport .= '- ' . ($sr['time'] ?? '') . ' ' . ($sr['endpoint'] ?? '')
            . ' ' . ($sr['response_time_ms'] ?? '') . "\n";
    }
} elseif ($slowRequestsError !== '') {
    $plainReport .= "\nSLOW REQUESTS: " . $slowRequestsError . "\n";
}
$plainReport .= "\nFIXES SHIPPED THIS ROUND\n";
$plainReport .= "- admin/members: batch project/task counts + slim group picker\n";
$plainReport .= "- ajax/monthly_range.php: 3 range queries + batch invoice_items (was per-day/month × N+1)\n";
$plainReport .= "- ajax/invoice_overview.php: batch invoice_items for totals\n";
$plainReport .= "- admin/menu-settings: 14× SHOW COLUMNS → 1× SHOW COLUMNS (crm_ensure_settings_columns)\n";
$plainReport .= "- admin/index: cache SHOW TABLES/COLUMNS via crm_db_* helpers\n";
$plainReport .= str_repeat('=', 72) . "\n";

if (isset($_GET['format']) && (string) $_GET['format'] === 'text') {
    header('Content-Type: text/plain; charset=UTF-8');
    echo $plainReport;
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CRM Performance Check</title>
    <style>
        :root { --bg:#0f1419; --card:#1a2332; --text:#e7ecf3; --muted:#8b9bb4; --ok:#3dbe7a; --warn:#e0a106; --bad:#e85d5d; --line:#2a3548; }
        * { box-sizing: border-box; }
        body { margin:0; font-family: ui-sans-serif, system-ui, Segoe UI, sans-serif; background: var(--bg); color: var(--text); line-height:1.45; }
        .wrap { max-width: 980px; margin: 0 auto; padding: 24px 16px 48px; }
        h1 { font-size: 1.45rem; margin: 0 0 8px; }
        h2 { font-size: 1.05rem; margin: 28px 0 10px; color: #c5d0e0; }
        p, li { color: var(--muted); }
        a { color: #7eb6ff; }
        .verdict { padding: 14px 16px; border-radius: 8px; border: 1px solid var(--line); margin: 16px 0; font-size: 0.95rem; color: var(--text); }
        .verdict.ok { border-color: #2a6b4a; background: #13261c; }
        .verdict.warn { border-color: #7a5c10; background: #2a220c; }
        .verdict.bad { border-color: #7a3030; background: #2a1414; }
        table { width: 100%; border-collapse: collapse; background: var(--card); border-radius: 8px; overflow: hidden; margin-bottom: 8px; }
        th, td { text-align: left; padding: 10px 12px; border-bottom: 1px solid var(--line); vertical-align: top; font-size: 0.9rem; }
        th { width: 42%; color: #b7c4d8; font-weight: 600; }
        tr:last-child th, tr:last-child td { border-bottom: 0; }
        .ok { color: var(--ok); }
        .warn { color: var(--warn); }
        .bad { color: var(--bad); }
        .muted { color: var(--muted); font-weight: 400; }
        .actions { display: flex; flex-wrap: wrap; gap: 8px; margin: 12px 0 4px; }
        .actions a { display: inline-block; padding: 8px 12px; background: #243044; border-radius: 6px; text-decoration: none; font-size: 0.85rem; }
        .actions a:hover { background: #2e3d56; }
        .note { font-size: 0.85rem; }
        code { background: #243044; padding: 1px 5px; border-radius: 4px; font-size: 0.85em; }
        .err { color: var(--bad); margin: 8px 0; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>CRM Performance Check</h1>
    <p class="note">Generated <?php echo perf_h(date('Y-m-d H:i:s')); ?> · this page took <?php echo number_format($pageTotalMs, 1); ?> ms ·
        <a href="server-load">Server Health / slow requests</a></p>

    <div class="actions">
        <a href="<?php echo perf_h($linkNormal); ?>">Refresh (minimal probes)</a>
        <a href="<?php echo perf_h($linkSimulate); ?>">Simulate full bootstrap</a>
        <a href="<?php echo perf_h($linkProbe); ?>">Probe CRM pages (TTFB)</a>
        <a href="<?php echo perf_h($linkSkipApi); ?>">Skip license API call</a>
        <a href="<?php echo perf_h(($baseQuery !== '' ? '?' . $baseQuery . '&' : '?') . 'format=text'); ?>">Plain text (?format=text)</a>
    </div>

    <div class="verdict <?php echo perf_h($verdictLevel); ?>">
        <strong>Verdict:</strong> <?php echo perf_h($verdictText); ?>
    </div>

    <h2>Paste this report</h2>
    <p class="note">Select all → copy → paste in chat. Or open <code>?format=text</code>.</p>
    <textarea id="perf-paste" readonly rows="18" style="width:100%;font-family:ui-monospace,Consolas,monospace;font-size:12px;background:#0c1018;color:#d7e0ee;border:1px solid var(--line);border-radius:8px;padding:12px;"><?php echo perf_h($plainReport); ?></textarea>
    <div class="actions">
        <a href="#" onclick="var t=document.getElementById('perf-paste');t.select();navigator.clipboard&&navigator.clipboard.writeText(t.value);return false;">Copy report</a>
    </div>

    <?php foreach ($errors as $err): ?>
        <p class="err"><?php echo perf_h($err); ?></p>
    <?php endforeach; ?>

    <h2>0. Known heavy pages (fix targets)</h2>
    <table>
        <?php foreach ($knownHeavyPages as $hp): ?>
            <tr><th><?php echo perf_h($hp['path']); ?></th><td><?php echo perf_h($hp['why']); ?></td></tr>
        <?php endforeach; ?>
    </table>
    <h2>1. Component timings</h2>
    <table>
        <?php foreach ($timings as $row): ?>
            <?php echo perf_timing_row($row); ?>
        <?php endforeach; ?>
        <?php if ($timings === []): ?>
            <tr><th>No timings</th><td class="warn">DB/bootstrap probes did not run</td></tr>
        <?php endif; ?>
    </table>
    <p class="note">Thresholds: &lt;500ms green · 500–2000ms amber · ≥2000ms red. Full CRM pages run license check on every non-AJAX load via <code>lib-initialize.php</code>.</p>

    <h2>2. License &amp; cache</h2>
    <table>
        <tr><th>Purchase code</th><td><?php echo perf_h($licenseInfo['purchase_code_masked']); ?></td></tr>
        <tr><th>Local activation (key in settings)</th><td class="<?php echo $licenseInfo['local_activation'] ? 'ok' : 'warn'; ?>"><?php echo $licenseInfo['local_activation'] ? 'Yes' : 'No'; ?></td></tr>
        <tr><th>wc_am_cache row</th><td class="<?php echo $licenseInfo['cache_present'] ? 'ok' : 'warn'; ?>"><?php echo $licenseInfo['cache_present'] ? 'Present' : 'Missing'; ?></td></tr>
        <tr><th>Cache expires_at</th><td><?php echo perf_h($licenseInfo['expires_at'] !== '' ? $licenseInfo['expires_at'] : '—'); ?></td></tr>
        <tr><th>TTL remaining</th><td class="<?php echo $licenseInfo['cache_expired'] ? 'bad' : 'ok'; ?>"><?php echo perf_h($licenseInfo['ttl_remaining'] !== '' ? $licenseInfo['ttl_remaining'] : '—'); ?></td></tr>
        <tr><th>Cached status</th><td><?php echo perf_h($licenseInfo['cache_status'] !== '' ? $licenseInfo['cache_status'] : '—'); ?></td></tr>
        <tr><th>Cached timestamp</th><td><?php echo perf_h($licenseInfo['cache_timestamp'] !== '' ? $licenseInfo['cache_timestamp'] : '—'); ?></td></tr>
        <tr><th>Path used this run</th><td><strong><?php echo perf_h($licenseInfo['path_used']); ?></strong></td></tr>
        <tr><th>getLicenseStatus result</th><td><?php echo perf_h($licenseInfo['full_status'] !== '' ? ($licenseInfo['full_status'] . ' — ' . $licenseInfo['full_message']) : '—'); ?></td></tr>
        <tr><th>License API host</th><td><?php echo perf_h($licenseInfo['api_url']); ?></td></tr>
        <tr><th>Important</th><td class="warn"><?php echo perf_h($licenseInfo['note']); ?></td></tr>
    </table>

    <h2>3. Server load / PHP</h2>
    <table>
        <tr><th>PHP</th><td><?php echo perf_h($serverInfo['php_version'] . ' (' . $serverInfo['sapi'] . ')'); ?></td></tr>
        <tr><th>Server software</th><td><?php echo perf_h($serverInfo['server_software'] !== '' ? $serverInfo['server_software'] : '—'); ?></td></tr>
        <tr><th>Load average (1/5/15)</th><td><?php
            if (is_array($serverInfo['load_avg'])) {
                $l0 = (float) $serverInfo['load_avg'][0];
                $cls = $l0 >= 4 ? 'bad' : ($l0 >= 2 ? 'warn' : 'ok');
                echo '<span class="' . $cls . '">' . perf_h(number_format((float) $serverInfo['load_avg'][0], 2)
                    . ' / ' . number_format((float) $serverInfo['load_avg'][1], 2)
                    . ' / ' . number_format((float) $serverInfo['load_avg'][2], 2)) . '</span>';
            } else {
                echo '<span class="muted">n/a (Windows or restricted)</span>';
            }
        ?></td></tr>
        <tr><th>Memory current / peak</th><td><?php echo perf_h(perf_fmt_bytes($serverInfo['memory_current']) . ' / ' . perf_fmt_bytes($serverInfo['memory_peak'])); ?></td></tr>
        <tr><th>memory_limit</th><td><?php echo perf_h($serverInfo['memory_limit']); ?></td></tr>
        <tr><th>max_execution_time</th><td><?php echo perf_h($serverInfo['max_execution_time']); ?> <span class="muted">(license set_time_limit(15) does not abort blocking cURL)</span></td></tr>
        <tr><th>Disk free / total</th><td><?php
            if ($serverInfo['disk_free'] !== false && $serverInfo['disk_total'] !== false && (float) $serverInfo['disk_total'] > 0) {
                $pct = round((1 - (float) $serverInfo['disk_free'] / (float) $serverInfo['disk_total']) * 100, 1);
                echo perf_h(perf_fmt_bytes($serverInfo['disk_free']) . ' free / ' . perf_fmt_bytes($serverInfo['disk_total']) . ' (' . $pct . '% used)');
            } else {
                echo 'n/a';
            }
        ?></td></tr>
        <tr><th>OPcache</th><td><?php echo perf_h($serverInfo['opcache']); ?></td></tr>
        <tr><th>DB connection note</th><td class="muted">A normal CRM page typically opens multiple mysqli connections (config.php + database.php + lib-initialize). Extra overhead is usually small vs a slow license API.</td></tr>
    </table>

    <h2>3b. Table row counts</h2>
    <?php if ($tableCounts === []): ?>
        <p class="muted">No table counts (DB not connected).</p>
    <?php else: ?>
        <table>
            <?php foreach ($tableCounts as $tc): ?>
                <tr>
                    <th><?php echo perf_h($tc['table']); ?></th>
                    <td class="<?php echo perf_h(perf_level((float) $tc['ms'])); ?>">
                        <?php echo number_format((int) $tc['rows']); ?> rows · <?php echo number_format((float) $tc['ms'], 1); ?> ms
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>

    <h2>4. Recent slow CRM requests (24h, ≥500ms)</h2>
    <?php if ($slowRequestsError !== ''): ?>
        <p class="warn"><?php echo perf_h($slowRequestsError); ?></p>
    <?php elseif ($slowRequests === []): ?>
        <p class="muted">No slow requests logged in the last 24 hours (or logging not active). See <a href="server-load">server-load.php</a>.</p>
    <?php else: ?>
        <table>
            <tr><th>Time</th><td><strong>Endpoint</strong> · method · duration · status</td></tr>
            <?php foreach (array_slice($slowRequests, 0, 20) as $sr): ?>
                <tr>
                    <th><?php echo perf_h((string) ($sr['time'] ?? '')); ?></th>
                    <td class="<?php echo (($sr['level'] ?? '') === 'danger') ? 'bad' : ((($sr['level'] ?? '') === 'warning') ? 'warn' : 'ok'); ?>">
                        <?php echo perf_h((string) ($sr['endpoint'] ?? '')); ?>
                        · <?php echo perf_h((string) ($sr['method'] ?? '')); ?>
                        · <?php echo perf_h((string) ($sr['response_time_ms'] ?? '')); ?>
                        · HTTP <?php echo (int) ($sr['status_code'] ?? 0); ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
        <p class="note"><a href="server-load?section=slow">Open full slow-request list</a></p>
    <?php endif; ?>

    <?php if ($pageProbeResults !== []): ?>
        <h2>5. Page TTFB probes</h2>
        <table>
            <?php foreach ($pageProbeResults as $pr): ?>
                <tr>
                    <th><?php echo perf_h($pr['path']); ?></th>
                    <td class="<?php echo perf_h(perf_level((float) $pr['ms'])); ?>">
                        <strong><?php echo number_format((float) $pr['ms'], 1); ?> ms</strong>
                        · HTTP <?php echo (int) $pr['http_code']; ?>
                        <?php if ($pr['error'] !== ''): ?> · <?php echo perf_h($pr['error']); ?><?php endif; ?>
                        <div class="muted"><?php echo perf_h($pr['url']); ?></div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
        <p class="note">Uses your browser cookies when available. Redirects to login will still return quickly — focus on high ms with HTTP 200.</p>
    <?php endif; ?>

    <h2>How to interpret</h2>
    <ul>
        <li>If <strong>getLicenseStatus</strong> or <strong>license host</strong> is multi-second only sometimes → remote license API / expired cache (not browser cache).</li>
        <li>If <strong>Simulate full bootstrap</strong> is ~1 minute but DB SELECT 1 is fast → bootstrap/license, not MySQL heat alone.</li>
        <li>If load average is high and all timings are elevated → server overload may also be involved.</li>
        <li>AJAX under <code>/ajax/</code> skips license checks (<code>crm_is_lightweight_request</code>); full HTML pages do not.</li>
    </ul>
</div>
</body>
</html>
