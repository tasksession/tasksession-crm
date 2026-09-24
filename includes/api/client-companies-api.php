<?php
ob_start();
require_once(__DIR__ . '/../lib-initialize.php');
require_once(__DIR__ . '/../permissions.php');
require_once(__DIR__ . '/../custom-fields/company_custom_field_values.php');
require_once(__DIR__ . '/../company_activity_logger.php');
require_once(__DIR__ . '/../company_members_helper.php');
header('Content-Type: application/json; charset=utf-8');

register_shutdown_function(static function () {
    $err = error_get_last();
    if (!$err || !in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return;
    }
    if (ob_get_level()) {
        ob_clean();
    }
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    $msg = 'Server error';
    $debug = [
        'fatal_message' => (string) ($err['message'] ?? ''),
        'fatal_file' => (string) ($err['file'] ?? '') . ':' . (int) ($err['line'] ?? 0),
        'fatal_type' => (int) ($err['type'] ?? 0),
    ];
    if (function_exists('cc_api_debug_enabled') && cc_api_debug_enabled()) {
        $msg = $debug['fatal_message'] !== '' ? $debug['fatal_message'] : $msg;
        if (function_exists('cc_build_debug')) {
            $debug = array_merge(cc_build_debug(), $debug);
        }
    }
    $payload = ['ok' => false, 'error' => $msg];
    if ($debug !== []) {
        $payload['debug'] = $debug;
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
});

$conn = $database->connection;
ensure_user_permissions($conn);

function cc_table_exists(mysqli $conn) {
    $r = mysqli_query($conn, "SHOW TABLES LIKE 'client_companies'");
    return $r && mysqli_num_rows($r) > 0;
}

function cc_pins_table_exists(mysqli $conn) {
    $r = mysqli_query($conn, "SHOW TABLES LIKE 'client_company_pins'");
    return $r && mysqli_num_rows($r) > 0;
}

const CC_MAX_PINNED_COMPANIES = 4;

function cc_api_debug_enabled(): bool
{
    $isAdmin = isset($_SESSION['accountStatus']) && (int) $_SESSION['accountStatus'] === 1;
    if (!$isAdmin) {
        return false;
    }
    if (!empty($_GET['debug']) || !empty($_POST['debug'])) {
        return true;
    }
    if (!empty($_SERVER['HTTP_X_CC_API_DEBUG'])) {
        return true;
    }

    return !empty($GLOBALS['cc_api_debug_force']);
}

/**
 * @param array<string,mixed> $extra
 * @return array<string,mixed>
 */
function cc_build_debug(?Throwable $e = null, array $extra = []): array
{
    global $conn;
    $last = error_get_last();
    $base = [
        'php_version' => PHP_VERSION,
        'mysqlnd' => function_exists('mysqli_stmt_get_result'),
        'mysqli_error' => ($conn instanceof mysqli) ? mysqli_error($conn) : '',
        'action' => (string) ($_GET['action'] ?? ($_POST['action'] ?? '')),
    ];
    if ($e) {
        $base['exception'] = $e->getMessage();
        $base['exception_file'] = $e->getFile() . ':' . $e->getLine();
    }
    if (is_array($last)) {
        $base['last_php_error'] = $last;
    }

    return array_merge($base, $extra);
}

function cc_err($code, $msg, array $debugExtra = []) {
    http_response_code($code);
    if (ob_get_level()) {
        ob_clean();
    }
    $payload = ['ok' => false, 'error' => $msg];
    if (cc_api_debug_enabled()) {
        $payload['debug'] = cc_build_debug(null, $debugExtra);
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** @return array<string,mixed>|null */
function cc_query_one(mysqli $conn, string $sql) {
    $res = mysqli_query($conn, $sql);
    if (!$res) {
        if (cc_api_debug_enabled()) {
            $GLOBALS['cc_last_query_error'] = mysqli_error($conn);
            $GLOBALS['cc_last_query_sql'] = $sql;
        }
        return null;
    }
    $row = mysqli_fetch_assoc($res);
    mysqli_free_result($res);
    return $row ?: null;
}

function cc_user_can($action) {
    $accountStatus = isset($_SESSION['accountStatus']) ? (int) $_SESSION['accountStatus'] : 0;
    if ($accountStatus === 1) {
        return true;
    }
    if ($accountStatus !== 3) {
        return false;
    }

    $readActions = ['list', 'get'];
    if (in_array($action, $readActions, true)) {
        return function_exists('has_permission') && has_permission('client_view');
    }
    if ($action === 'create') {
        return function_exists('has_permission') && has_permission('client_create');
    }
    if (in_array($action, ['update', 'clone', 'pin', 'unpin'], true)) {
        return function_exists('has_permission') && (
            has_permission('client_profile_update') || has_permission('client_create')
        );
    }
    if ($action === 'delete') {
        return function_exists('has_permission') && has_permission('client_delete');
    }
    return false;
}

function cc_require_csrf(array $body) {
    $token = '';
    if (!empty($_SERVER['HTTP_X_CSRF_TOKEN'])) {
        $token = (string) $_SERVER['HTTP_X_CSRF_TOKEN'];
    } elseif (isset($body['csrf_token'])) {
        $token = (string) $body['csrf_token'];
    }

    if ($token === '' || !function_exists('validate_csrf_token') || !validate_csrf_token($token)) {
        cc_err(403, 'Invalid CSRF token');
    }
}

function cc_secure_logo_upload(array $file, $target_dir) {
    if (empty($file['name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['success' => false, 'filename' => null, 'skip' => true];
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Upload error'];
    }
    $originalFilename = basename(str_replace("\0", '', (string) $file['name']));
    $originalFilename = str_replace(['../', '..\\', '/', '\\'], '', $originalFilename);
    $ext = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'ico'];
    if (!in_array($ext, $allowedExtensions, true)) {
        return ['success' => false, 'error' => 'Invalid file type'];
    }
    $maxSize = 10 * 1024 * 1024;
    if (($file['size'] ?? 0) > $maxSize) {
        return ['success' => false, 'error' => 'File too large'];
    }
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = $finfo ? finfo_file($finfo, $file['tmp_name']) : '';
        if ($finfo) {
            finfo_close($finfo);
        }
        $allowedMimeTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/x-icon', 'image/vnd.microsoft.icon'];
        if (!in_array($mimeType, $allowedMimeTypes, true)) {
            return ['success' => false, 'error' => 'Invalid MIME type'];
        }
    }
    if (!is_dir($target_dir)) {
        if (!@mkdir($target_dir, 0755, true)) {
            return ['success' => false, 'error' => 'Could not create upload directory'];
        }
    }
    $htaccess = rtrim($target_dir, '/\\') . DIRECTORY_SEPARATOR . '.htaccess';
    if (!is_file($htaccess)) {
        $htaccessBody = <<<'HTACCESS'
# Company logos — public read (overrides uploads/.htaccess deny)
<IfModule mod_authz_core.c>
    <FilesMatch "\.(?i:jpe?g|png|gif|webp|ico)$">
        Require all granted
    </FilesMatch>
    Require all denied
</IfModule>
<IfModule !mod_authz_core.c>
    <FilesMatch "\.(?i:jpe?g|png|gif|webp|ico)$">
        Order allow,deny
        Allow from all
    </FilesMatch>
    Order deny,allow
    Deny from all
</IfModule>
HTACCESS;
        @file_put_contents($htaccess, $htaccessBody);
    }
    $newFileName = time() . '_cc_' . uniqid('', true) . '.' . $ext;
    $dest = rtrim($target_dir, '/\\') . DIRECTORY_SEPARATOR . $newFileName;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return ['success' => false, 'error' => 'Failed to save file'];
    }
    return ['success' => true, 'filename' => 'uploads/client-companies/' . $newFileName];
}

function cc_validate_client_ids(mysqli $conn, array $ids) {
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    if (empty($ids)) {
        return [];
    }
    $out = [];
    foreach ($ids as $id) {
        if ($id <= 0) {
            continue;
        }
        $id = (int) $id;
        $row = cc_query_one($conn, 'SELECT id FROM users WHERE id = ' . $id . ' AND status = 0 AND accountStatus = 2 LIMIT 1');
        if ($row) {
            $out[] = $id;
        }
    }
    sort($out);
    return array_values(array_unique($out));
}

function cc_currency_allowed(mysqli $conn, $currencyKey) {
    $currencyKey = trim((string) $currencyKey);
    if ($currencyKey === '') {
        return false;
    }
    $settings = settings::findById(1);
    if (!$settings) {
        return false;
    }
    $enabled = $settings->getMultipleCurrencies();
    return is_array($enabled) && in_array($currencyKey, $enabled, true);
}

function cc_parse_client_ids_from_mixed($raw) {
    if (is_array($raw)) {
        return $raw;
    }
    if (is_string($raw) && $raw !== '') {
        $parts = preg_split('/[\s,]+/', $raw, -1, PREG_SPLIT_NO_EMPTY);
        return is_array($parts) ? $parts : [];
    }
    return [];
}

/**
 * @return int Primary user id (0 = none). With multiple members, uses explicit primary_client_id when valid, otherwise first linked client.
 */
function cc_links_table_exists(mysqli $conn) {
    $r = mysqli_query($conn, "SHOW TABLES LIKE 'client_company_links'");
    return $r && mysqli_num_rows($r) > 0;
}

function cc_validate_company_ids(mysqli $conn, array $ids, int $excludeCompanyId = 0) {
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    $out = [];
    foreach ($ids as $id) {
        if ($id <= 0 || ($excludeCompanyId > 0 && $id === $excludeCompanyId)) {
            continue;
        }
        $id = (int) $id;
        $row = cc_query_one($conn, 'SELECT id FROM client_companies WHERE id = ' . $id . ' AND deleted_at IS NULL LIMIT 1');
        if ($row) {
            $out[] = $id;
        }
    }
    sort($out);
    return array_values(array_unique($out));
}

function cc_parse_ids_from_mixed($raw) {
    return cc_parse_client_ids_from_mixed($raw);
}

function cc_fetch_linked_companies(mysqli $conn, int $companyId) {
    if ($companyId <= 0 || !cc_links_table_exists($conn)) {
        return [];
    }
    $out = [];
    $cid = (int) $companyId;
    $sql = 'SELECT DISTINCT c.id, c.name, c.logo_path, c.vat_number, c.email
        FROM client_company_links l
        INNER JOIN client_companies c ON (
            (l.company_id = ' . $cid . ' AND c.id = l.linked_company_id)
            OR (l.linked_company_id = ' . $cid . ' AND c.id = l.company_id)
        )
        WHERE c.deleted_at IS NULL AND c.id != ' . $cid . '
        ORDER BY c.name ASC';
    $res = mysqli_query($conn, $sql);
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $out[] = [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'logo_path' => $row['logo_path'] !== null ? (string) $row['logo_path'] : '',
                'vat_number' => (string) ($row['vat_number'] ?? ''),
                'email' => (string) ($row['email'] ?? ''),
            ];
        }
        mysqli_free_result($res);
    }
    return $out;
}

function cc_delete_company_link_pair(mysqli $conn, int $companyId, int $linkedId) {
    if ($companyId <= 0 || $linkedId <= 0 || $companyId === $linkedId) {
        return;
    }
    $del = mysqli_prepare(
        $conn,
        'DELETE FROM client_company_links
         WHERE (company_id = ? AND linked_company_id = ?)
            OR (company_id = ? AND linked_company_id = ?)'
    );
    if (!$del) {
        return;
    }
    mysqli_stmt_bind_param($del, 'iiii', $companyId, $linkedId, $linkedId, $companyId);
    mysqli_stmt_execute($del);
    mysqli_stmt_close($del);
}

function cc_insert_company_link_pair(mysqli $conn, int $companyId, int $linkedId, int $actorUserId) {
    if ($companyId <= 0 || $linkedId <= 0 || $companyId === $linkedId) {
        return;
    }
    $ins = mysqli_prepare($conn, 'INSERT IGNORE INTO client_company_links (company_id, linked_company_id, created_by) VALUES (?, ?, ?)');
    if (!$ins) {
        return;
    }
    mysqli_stmt_bind_param($ins, 'iii', $companyId, $linkedId, $actorUserId);
    mysqli_stmt_execute($ins);
    mysqli_stmt_bind_param($ins, 'iii', $linkedId, $companyId, $actorUserId);
    mysqli_stmt_execute($ins);
    mysqli_stmt_close($ins);
}

function cc_sync_linked_companies(mysqli $conn, int $companyId, array $linkedIds, int $actorUserId) {
    if ($companyId <= 0 || !cc_links_table_exists($conn)) {
        return;
    }
    $linkedIds = array_values(array_unique(array_filter(array_map('intval', $linkedIds), static function ($id) use ($companyId) {
        return $id > 0 && $id !== $companyId;
    })));
    sort($linkedIds);

    $oldPartners = cc_fetch_linked_companies($conn, $companyId);
    $oldIds = array_map(static function ($row) {
        return (int) $row['id'];
    }, $oldPartners);
    sort($oldIds);

    $added = array_values(array_diff($linkedIds, $oldIds));
    $removed = array_values(array_diff($oldIds, $linkedIds));

    foreach ($removed as $lid) {
        cc_delete_company_link_pair($conn, $companyId, (int) $lid);
    }
    foreach ($added as $lid) {
        cc_insert_company_link_pair($conn, $companyId, (int) $lid, $actorUserId);
    }
    foreach ($added as $lid) {
        $name = '';
        $nq = mysqli_query($conn, 'SELECT name FROM client_companies WHERE id = ' . (int) $lid . ' LIMIT 1');
        if ($nq && ($nr = mysqli_fetch_assoc($nq))) {
            $name = (string) ($nr['name'] ?? '');
        }
        if ($nq) {
            mysqli_free_result($nq);
        }
        company_activity_log($companyId, 'company_linked', 'Linked company: ' . ($name !== '' ? $name : '#' . $lid), ['linked_company_id' => $lid], $actorUserId);
    }
    foreach ($removed as $lid) {
        $name = '';
        $nq = mysqli_query($conn, 'SELECT name FROM client_companies WHERE id = ' . (int) $lid . ' LIMIT 1');
        if ($nq && ($nr = mysqli_fetch_assoc($nq))) {
            $name = (string) ($nr['name'] ?? '');
        }
        if ($nq) {
            mysqli_free_result($nq);
        }
        company_activity_log($companyId, 'company_unlinked', 'Unlinked company: ' . ($name !== '' ? $name : '#' . $lid), ['linked_company_id' => $lid], $actorUserId);
    }
}

function cc_log_member_changes(mysqli $conn, int $companyId, array $oldMemberIds, array $newMemberIds, int $primaryResolved, int $actorUserId) {
    $added = array_values(array_diff($newMemberIds, $oldMemberIds));
    $removed = array_values(array_diff($oldMemberIds, $newMemberIds));
    foreach ($added as $uid) {
        $disp = 'Client #' . $uid;
        $uid = (int) $uid;
        $row = cc_query_one($conn, 'SELECT firstName, email FROM users WHERE id = ' . $uid . ' LIMIT 1');
        if ($row) {
            $fn = trim((string) ($row['firstName'] ?? ''));
            $disp = $fn !== '' ? $fn : (string) ($row['email'] ?? $disp);
        }
        $isPrimary = ((int) $uid === $primaryResolved);
        company_activity_log($companyId, 'client_linked', 'Linked client: ' . $disp . ($isPrimary ? ' (main)' : ''), ['user_id' => $uid, 'is_primary' => $isPrimary], $actorUserId);
    }
    foreach ($removed as $uid) {
        company_activity_log($companyId, 'client_unlinked', 'Unlinked client #' . $uid, ['user_id' => $uid], $actorUserId);
    }
}

function cc_resolve_primary_client_id(array $body, array $validClients) {
    $n = count($validClients);
    if ($n === 0) {
        return 0;
    }
    if ($n === 1) {
        return (int) $validClients[0];
    }
    $pid = isset($body['primary_client_id']) ? (int) $body['primary_client_id'] : 0;
    if ($pid > 0 && in_array($pid, $validClients, true)) {
        return $pid;
    }

    return (int) $validClients[0];
}

if (!cc_table_exists($conn)) {
    cc_err(503, 'Client companies are not available on this installation.');
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$isMultipart = !empty($_SERVER['CONTENT_TYPE']) && stripos((string) $_SERVER['CONTENT_TYPE'], 'multipart/form-data') !== false;

$body = [];
if ($method === 'POST' && $isMultipart) {
    $body = $_POST;
} elseif ($method === 'POST') {
    $raw = file_get_contents('php://input');
    if ($raw !== '' && $raw !== false) {
        $decoded = json_decode($raw, true);
        $body = is_array($decoded) ? $decoded : [];
    }
}

$action = $method === 'GET' ? (string) ($_GET['action'] ?? '') : (string) ($body['action'] ?? '');
if (!$session->isLoggedIn() || !cc_user_can($action)) {
    cc_err(403, 'Forbidden');
}
if ($method === 'POST' && !in_array($action, ['list', 'get'], true)) {
    cc_require_csrf($body);
}

if ($action === 'list') {
    $rows = [];
    $pinJoin = cc_pins_table_exists($conn)
        ? ' LEFT JOIN client_company_pins p ON p.company_id = c.id '
        : '';
    $pinOrder = cc_pins_table_exists($conn)
        ? ' ORDER BY (p.company_id IS NOT NULL) DESC, p.pinned_at ASC, c.name ASC '
        : ' ORDER BY c.name ASC ';
    $pinSelect = cc_pins_table_exists($conn)
        ? ', IF(p.company_id IS NOT NULL, 1, 0) AS is_pinned '
        : ', 0 AS is_pinned ';
    $q = mysqli_query(
        $conn,
        "SELECT c.id, c.name, c.logo_path, c.currency, c.created_at,
        (SELECT COUNT(*) FROM client_company_members m WHERE m.company_id = c.id) AS member_count
        $pinSelect
        FROM client_companies c
        $pinJoin
        WHERE c.deleted_at IS NULL
        $pinOrder"
    );
    if ($q === false) {
        cc_err(500, 'Database error loading companies', [
            'sql_context' => 'list',
            'mysqli_error' => mysqli_error($conn),
        ]);
    }
    if ($q) {
        while ($row = mysqli_fetch_assoc($q)) {
            $rows[] = [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'logo_path' => $row['logo_path'] !== null ? (string) $row['logo_path'] : '',
                'currency' => (string) $row['currency'],
                'member_count' => (int) $row['member_count'],
                'is_pinned' => !empty($row['is_pinned']),
                'created_at' => isset($row['created_at']) ? (string) $row['created_at'] : '',
            ];
        }
    }
    echo json_encode(['ok' => true, 'companies' => $rows]);
    exit;
}

if ($action === 'get') {
    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    if ($id <= 0) {
        cc_err(400, 'Invalid id');
    }
    // Use mysqli_query (not stmt->get_result) so JSON stays valid on PHP builds without mysqlnd.
    $sqlCompany = 'SELECT c.id, c.name, c.logo_path, c.vat_number, c.phone, c.email, c.website, c.currency,
        c.address, c.city, c.state, c.zip, c.country,
        c.billing_address, c.billing_street, c.billing_city, c.billing_state, c.billing_zip, c.billing_country,
        c.created_at, c.created_by,
        u.firstName AS creator_first
        FROM client_companies c
        LEFT JOIN users u ON u.id = c.created_by
        WHERE c.id = ' . $id . ' AND c.deleted_at IS NULL LIMIT 1';
    $resCompany = mysqli_query($conn, $sqlCompany);
    if ($resCompany === false) {
        cc_err(500, 'Database error', [
            'sql_context' => 'get company',
            'mysqli_error' => mysqli_error($conn),
            'company_id' => $id,
        ]);
    }
    $c = mysqli_fetch_assoc($resCompany);
    mysqli_free_result($resCompany);
    if (!$c) {
        cc_err(404, 'Company not found');
    }
    $creatorName = trim((string) ($c['creator_first'] ?? ''));
    $membersOut = company_fetch_members($conn, $id, true);
    $uids = array_map(static function ($m) {
        return (int) ($m['id'] ?? 0);
    }, $membersOut);
    $primaryClientId = 0;
    foreach ($membersOut as $m) {
        if (!empty($m['is_primary'])) {
            $primaryClientId = (int) ($m['id'] ?? 0);
            break;
        }
    }
    $linkedCompanies = cc_fetch_linked_companies($conn, $id);
    $linkedCompanyIds = array_map(static function ($c) {
        return (int) $c['id'];
    }, $linkedCompanies);
    $customFieldValues = fetch_company_custom_field_values_for_hydrate($conn, $id);
    $customFieldViewRows = fetch_company_custom_field_view_rows($conn, $id);
    if (ob_get_level()) {
        ob_clean();
    }
    echo json_encode([
        'ok' => true,
        'custom_field_values' => $customFieldValues,
        'custom_field_view_rows' => $customFieldViewRows,
        'company' => [
            'id' => (int) $c['id'],
            'name' => (string) $c['name'],
            'logo_path' => $c['logo_path'] !== null ? (string) $c['logo_path'] : '',
            'vat_number' => (string) $c['vat_number'],
            'phone' => (string) $c['phone'],
            'email' => (string) $c['email'],
            'website' => (string) $c['website'],
            'currency' => (string) $c['currency'],
            'address' => (string) $c['address'],
            'city' => (string) $c['city'],
            'state' => (string) $c['state'],
            'zip' => (string) $c['zip'],
            'country' => (string) $c['country'],
            'billing_address' => (string) $c['billing_address'],
            'billing_street' => (string) $c['billing_street'],
            'billing_city' => (string) $c['billing_city'],
            'billing_state' => (string) $c['billing_state'],
            'billing_zip' => (string) $c['billing_zip'],
            'billing_country' => (string) $c['billing_country'],
            'created_at' => isset($c['created_at']) ? (string) $c['created_at'] : '',
            'creator_name' => $creatorName,
            'primary_client_id' => $primaryClientId,
            'client_ids' => $uids,
            'members' => $membersOut,
            'linked_company_ids' => $linkedCompanyIds,
            'linked_companies' => $linkedCompanies,
        ],
    ]);
    exit;
}

if ($method !== 'POST') {
    cc_err(405, 'Method not allowed');
}

if ($action === 'create' || $action === 'update') {
    $name = isset($body['name']) ? trim((string) $body['name']) : '';
    if ($name === '') {
        cc_err(400, 'Company name is required');
    }
    $vat = isset($body['vat_number']) ? trim((string) $body['vat_number']) : '';
    $phone = isset($body['phone']) ? trim((string) $body['phone']) : '';
    $email = isset($body['email']) ? trim((string) $body['email']) : '';
    $website = isset($body['website']) ? trim((string) $body['website']) : '';
    $currency = isset($body['currency']) ? trim((string) $body['currency']) : '';
    if ($currency === '' || !cc_currency_allowed($conn, $currency)) {
        cc_err(400, 'Invalid or missing currency');
    }
    $address = isset($body['address']) ? trim((string) $body['address']) : '';
    $city = isset($body['city']) ? trim((string) $body['city']) : '';
    $state = isset($body['state']) ? trim((string) $body['state']) : '';
    $zip = isset($body['zip']) ? trim((string) $body['zip']) : '';
    $country = isset($body['country']) ? trim((string) $body['country']) : '';
    $billAddr = isset($body['billing_address']) ? trim((string) $body['billing_address']) : '';
    $billStreet = isset($body['billing_street']) ? trim((string) $body['billing_street']) : '';
    $billCity = isset($body['billing_city']) ? trim((string) $body['billing_city']) : '';
    $billState = isset($body['billing_state']) ? trim((string) $body['billing_state']) : '';
    $billZip = isset($body['billing_zip']) ? trim((string) $body['billing_zip']) : '';
    $billCountry = isset($body['billing_country']) ? trim((string) $body['billing_country']) : '';

    $clientRaw = $body['client_ids'] ?? ($body['user_ids'] ?? []);
    $validClients = cc_validate_client_ids($conn, cc_parse_client_ids_from_mixed($clientRaw));
    $primaryResolved = cc_resolve_primary_client_id($body, $validClients);
    $linkedRaw = $body['linked_company_ids'] ?? [];
    $validLinked = cc_validate_company_ids($conn, cc_parse_ids_from_mixed($linkedRaw), $action === 'update' ? (int) ($body['id'] ?? 0) : 0);

    $uploadDir = SITE_ROOT . DS . 'uploads' . DS . 'client-companies' . DS;
    $logoFile = ($isMultipart && isset($_FILES['logo'])) ? $_FILES['logo'] : null;

    $cfPosted = (isset($body['custom_fields']) && is_array($body['custom_fields'])) ? $body['custom_fields'] : null;
    $cfErr = validate_company_custom_field_values_required($conn, $cfPosted);
    if ($cfErr !== null) {
        cc_err(400, $cfErr);
    }

    mysqli_begin_transaction($conn);
    try {
        if ($action === 'create') {
            $uid = (int) $session->userId;
            $stmt = mysqli_prepare(
                $conn,
                'INSERT INTO client_companies (name, logo_path, vat_number, phone, email, website, currency,
                address, city, state, zip, country,
                billing_address, billing_street, billing_city, billing_state, billing_zip, billing_country,
                created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            );
            $logoInit = '';
            mysqli_stmt_bind_param(
                $stmt,
                'ssssssssssssssssssi',
                $name,
                $logoInit,
                $vat,
                $phone,
                $email,
                $website,
                $currency,
                $address,
                $city,
                $state,
                $zip,
                $country,
                $billAddr,
                $billStreet,
                $billCity,
                $billState,
                $billZip,
                $billCountry,
                $uid
            );
            if (!mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                throw new RuntimeException('Could not create company');
            }
            $newId = (int) mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);

            $logoPath = null;
            if ($logoFile && ($logoFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $up = cc_secure_logo_upload($logoFile, $uploadDir);
                if (!empty($up['success']) && !empty($up['filename'])) {
                    $logoPath = $up['filename'];
                    $st2 = mysqli_prepare($conn, 'UPDATE client_companies SET logo_path = ? WHERE id = ? LIMIT 1');
                    mysqli_stmt_bind_param($st2, 'si', $logoPath, $newId);
                    mysqli_stmt_execute($st2);
                    mysqli_stmt_close($st2);
                } elseif (empty($up['skip'])) {
                    throw new RuntimeException($up['error'] ?? 'Logo upload failed');
                }
            }

            $ins = mysqli_prepare($conn, 'INSERT IGNORE INTO client_company_members (company_id, user_id, is_primary) VALUES (?, ?, ?)');
            foreach ($validClients as $cid) {
                $isP = ((int) $cid === $primaryResolved) ? 1 : 0;
                mysqli_stmt_bind_param($ins, 'iii', $newId, $cid, $isP);
                mysqli_stmt_execute($ins);
            }
            mysqli_stmt_close($ins);

            $cfPosted = (isset($body['custom_fields']) && is_array($body['custom_fields'])) ? $body['custom_fields'] : null;
            save_company_custom_field_values($conn, $newId, $cfPosted);

            cc_sync_linked_companies($conn, $newId, $validLinked, $uid);
            company_activity_log($newId, 'created', 'Company created: ' . $name, ['company_id' => $newId], $uid);
            if (!empty($validClients)) {
                cc_log_member_changes($conn, $newId, [], $validClients, $primaryResolved, $uid);
            }
            if ($logoPath) {
                company_activity_log($newId, 'logo_updated', 'Company logo uploaded', [], $uid);
            }

            mysqli_commit($conn);
            echo json_encode(['ok' => true, 'id' => $newId]);
            exit;
        }

        // update
        $id = isset($body['id']) ? (int) $body['id'] : 0;
        if ($id <= 0) {
            throw new RuntimeException('Invalid id');
        }
        $existing = cc_query_one($conn, 'SELECT id, logo_path FROM client_companies WHERE id = ' . $id . ' AND deleted_at IS NULL LIMIT 1');
        if (!$existing) {
            throw new RuntimeException('Company not found');
        }
        $oldLogo = $existing['logo_path'] ?? null;
        $oldMemberIds = [];
        $om = mysqli_query($conn, 'SELECT user_id FROM client_company_members WHERE company_id = ' . $id);
        if ($om) {
            while ($or = mysqli_fetch_assoc($om)) {
                $oldMemberIds[] = (int) $or['user_id'];
            }
            mysqli_free_result($om);
        }

        $stmt = mysqli_prepare(
            $conn,
            'UPDATE client_companies SET name=?, vat_number=?, phone=?, email=?, website=?, currency=?,
            address=?, city=?, state=?, zip=?, country=?,
            billing_address=?, billing_street=?, billing_city=?, billing_state=?, billing_zip=?, billing_country=?
            WHERE id=? AND deleted_at IS NULL LIMIT 1'
        );
        mysqli_stmt_bind_param(
            $stmt,
            'sssssssssssssssssi',
            $name,
            $vat,
            $phone,
            $email,
            $website,
            $currency,
            $address,
            $city,
            $state,
            $zip,
            $country,
            $billAddr,
            $billStreet,
            $billCity,
            $billState,
            $billZip,
            $billCountry,
            $id
        );
        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            throw new RuntimeException('Could not update company');
        }
        mysqli_stmt_close($stmt);

        if ($logoFile && ($logoFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $up = cc_secure_logo_upload($logoFile, $uploadDir);
            if (!empty($up['success']) && !empty($up['filename'])) {
                $newPath = $up['filename'];
                $st3 = mysqli_prepare($conn, 'UPDATE client_companies SET logo_path = ? WHERE id = ? LIMIT 1');
                mysqli_stmt_bind_param($st3, 'si', $newPath, $id);
                mysqli_stmt_execute($st3);
                mysqli_stmt_close($st3);
                if (!empty($oldLogo) && is_string($oldLogo)) {
                    $abs = SITE_ROOT . DS . str_replace(['/', '\\'], DS, $oldLogo);
                    if (is_file($abs) && strpos(realpath($abs), realpath(SITE_ROOT . DS . 'uploads')) === 0) {
                        @unlink($abs);
                    }
                }
                company_activity_log($id, 'logo_updated', 'Company logo updated', [], (int) $session->userId);
            } elseif (empty($up['skip'])) {
                throw new RuntimeException($up['error'] ?? 'Logo upload failed');
            }
        }

        $del = mysqli_prepare($conn, 'DELETE FROM client_company_members WHERE company_id = ?');
        mysqli_stmt_bind_param($del, 'i', $id);
        mysqli_stmt_execute($del);
        mysqli_stmt_close($del);

        $ins = mysqli_prepare($conn, 'INSERT IGNORE INTO client_company_members (company_id, user_id, is_primary) VALUES (?, ?, ?)');
        foreach ($validClients as $cid) {
            $isP = ((int) $cid === $primaryResolved) ? 1 : 0;
            mysqli_stmt_bind_param($ins, 'iii', $id, $cid, $isP);
            mysqli_stmt_execute($ins);
        }
        mysqli_stmt_close($ins);

        $cfPosted = (isset($body['custom_fields']) && is_array($body['custom_fields'])) ? $body['custom_fields'] : null;
        save_company_custom_field_values($conn, $id, $cfPosted);

        cc_sync_linked_companies($conn, $id, $validLinked, (int) $session->userId);
        cc_log_member_changes($conn, $id, $oldMemberIds, $validClients, $primaryResolved, (int) $session->userId);
        company_activity_log($id, 'updated', 'Company updated: ' . $name, ['company_id' => $id], (int) $session->userId);

        mysqli_commit($conn);
        echo json_encode(['ok' => true, 'id' => $id]);
        exit;
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        error_log('client-companies-api update error: ' . $e->getMessage());
        cc_err(400, 'Could not save company. Please check your input and try again.');
    }
}

if ($action === 'delete') {
    $id = isset($body['id']) ? (int) $body['id'] : 0;
    if ($id <= 0) {
        cc_err(400, 'Invalid id');
    }
    $stmt = mysqli_prepare($conn, 'UPDATE client_companies SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $aff = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    if ($aff < 1) {
        cc_err(404, 'Company not found or already in trash');
    }
    $nameRow = mysqli_query($conn, 'SELECT name FROM client_companies WHERE id = ' . $id . ' LIMIT 1');
    $delName = 'Company #' . $id;
    if ($nameRow && ($nr = mysqli_fetch_assoc($nameRow))) {
        $delName = (string) ($nr['name'] ?? $delName);
    }
    if ($nameRow) {
        mysqli_free_result($nameRow);
    }
    company_activity_log($id, 'deleted', 'Company moved to trash: ' . $delName, [], (int) $session->userId);
    if (cc_pins_table_exists($conn)) {
        $unpin = mysqli_prepare($conn, 'DELETE FROM client_company_pins WHERE company_id = ? LIMIT 1');
        if ($unpin) {
            mysqli_stmt_bind_param($unpin, 'i', $id);
            mysqli_stmt_execute($unpin);
            mysqli_stmt_close($unpin);
        }
    }
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'clone') {
    $id = isset($body['id']) ? (int) $body['id'] : 0;
    if ($id <= 0) {
        cc_err(400, 'Invalid id');
    }
    $src = cc_query_one($conn, 'SELECT * FROM client_companies WHERE id = ' . $id . ' AND deleted_at IS NULL LIMIT 1');
    if (!$src) {
        cc_err(404, 'Company not found');
    }
    $suffix = ' (copy)';
    $base = trim((string) ($src['name'] ?? ''));
    if ($base === '') {
        $base = 'Company #' . $id;
    }
    $maxLen = 191;
    $maxBase = $maxLen - strlen($suffix);
    $newName = (strlen($base) + strlen($suffix) <= $maxLen) ? $base . $suffix : substr($base, 0, max(1, $maxBase)) . $suffix;

    $uid = (int) $session->userId;
    $logoInit = '';
    $ins = mysqli_prepare(
        $conn,
        'INSERT INTO client_companies (name, logo_path, vat_number, phone, email, website, currency,
        address, city, state, zip, country,
        billing_address, billing_street, billing_city, billing_state, billing_zip, billing_country,
        created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
    );
    $vat = (string) $src['vat_number'];
    $phone = (string) $src['phone'];
    $email = (string) ($src['email'] ?? '');
    $website = (string) $src['website'];
    $currency = (string) $src['currency'];
    $address = (string) $src['address'];
    $city = (string) $src['city'];
    $state = (string) $src['state'];
    $zip = (string) $src['zip'];
    $country = (string) $src['country'];
    $ba = (string) $src['billing_address'];
    $bs = (string) $src['billing_street'];
    $bc = (string) $src['billing_city'];
    $bst = (string) $src['billing_state'];
    $bz = (string) $src['billing_zip'];
    $bco = (string) $src['billing_country'];
    mysqli_stmt_bind_param(
        $ins,
        'ssssssssssssssssssi',
        $newName,
        $logoInit,
        $vat,
        $phone,
        $email,
        $website,
        $currency,
        $address,
        $city,
        $state,
        $zip,
        $country,
        $ba,
        $bs,
        $bc,
        $bst,
        $bz,
        $bco,
        $uid
    );
    if (!mysqli_stmt_execute($ins)) {
        mysqli_stmt_close($ins);
        cc_err(500, 'Could not clone company');
    }
    $newId = (int) mysqli_insert_id($conn);
    mysqli_stmt_close($ins);

    $mres = mysqli_query($conn, 'SELECT user_id, is_primary FROM client_company_members WHERE company_id = ' . $id);
    $memIns = mysqli_prepare($conn, 'INSERT IGNORE INTO client_company_members (company_id, user_id, is_primary) VALUES (?, ?, ?)');
    if ($mres) {
        while ($row = mysqli_fetch_assoc($mres)) {
            $mid = (int) $row['user_id'];
            $isP = !empty($row['is_primary']) ? 1 : 0;
            mysqli_stmt_bind_param($memIns, 'iii', $newId, $mid, $isP);
            mysqli_stmt_execute($memIns);
        }
        mysqli_free_result($mres);
    }
    mysqli_stmt_close($memIns);

    if (company_custom_field_values_table_exists($conn)) {
        $srcId = (int) $id;
        $dstId = (int) $newId;
        $copySql = 'INSERT INTO company_custom_field_values (company_id, custom_field_id, field_value)
            SELECT ' . $dstId . ', v.custom_field_id, v.field_value
            FROM company_custom_field_values v
            INNER JOIN custom_fields f ON f.id = v.custom_field_id AND f.entity_type = \'company\'
            WHERE v.company_id = ' . $srcId;
        mysqli_query($conn, $copySql);
    }

    if (cc_links_table_exists($conn)) {
        $srcLinks = cc_fetch_linked_companies($conn, (int) $id);
        foreach ($srcLinks as $linkRow) {
            cc_insert_company_link_pair($conn, $newId, (int) $linkRow['id'], $uid);
        }
    }

    company_activity_log($newId, 'cloned', 'Company cloned from: ' . $base, ['source_company_id' => $id], $uid);

    echo json_encode(['ok' => true, 'id' => $newId]);
    exit;
}

if ($action === 'pin' || $action === 'unpin') {
    if (!cc_pins_table_exists($conn)) {
        cc_err(503, 'Company pins are not available on this installation.');
    }
    $id = isset($body['id']) ? (int) $body['id'] : 0;
    if ($id <= 0) {
        cc_err(400, 'Invalid id');
    }
    $crow = cc_query_one($conn, 'SELECT id, name FROM client_companies WHERE id = ' . $id . ' AND deleted_at IS NULL LIMIT 1');
    if (!$crow) {
        cc_err(404, 'Company not found');
    }
    $cname = (string) ($crow['name'] ?? '');
    $uid = (int) $session->userId;

    if ($action === 'pin') {
        $already = cc_query_one($conn, 'SELECT company_id FROM client_company_pins WHERE company_id = ' . $id . ' LIMIT 1');
        if ($already) {
            echo json_encode(['ok' => true, 'id' => $id, 'is_pinned' => true]);
            exit;
        }
        $cntRes = mysqli_query($conn, 'SELECT COUNT(*) AS c FROM client_company_pins');
        $cntRow = $cntRes ? mysqli_fetch_assoc($cntRes) : null;
        $pinCount = $cntRow ? (int) ($cntRow['c'] ?? 0) : 0;
        if ($pinCount >= CC_MAX_PINNED_COMPANIES) {
            cc_err(400, 'Maximum 4 companies can be pinned.');
        }
        $ins = mysqli_prepare($conn, 'INSERT INTO client_company_pins (company_id, pinned_by) VALUES (?, ?)');
        mysqli_stmt_bind_param($ins, 'ii', $id, $uid);
        if (!mysqli_stmt_execute($ins)) {
            mysqli_stmt_close($ins);
            cc_err(500, 'Could not pin company');
        }
        mysqli_stmt_close($ins);
        company_activity_log($id, 'pinned', 'Company pinned to top: ' . $cname, [], $uid);
        echo json_encode(['ok' => true, 'id' => $id, 'is_pinned' => true]);
        exit;
    }

    $del = mysqli_prepare($conn, 'DELETE FROM client_company_pins WHERE company_id = ? LIMIT 1');
    mysqli_stmt_bind_param($del, 'i', $id);
    mysqli_stmt_execute($del);
    $removed = mysqli_stmt_affected_rows($del) > 0;
    mysqli_stmt_close($del);
    if ($removed) {
        company_activity_log($id, 'unpinned', 'Company unpinned: ' . $cname, [], $uid);
    }
    echo json_encode(['ok' => true, 'id' => $id, 'is_pinned' => false]);
    exit;
}

cc_err(400, 'Unknown action');
