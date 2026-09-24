<?php
/**
 * API token helpers — hashed Bearer tokens (Phase 7).
 */

function api_token_default_scopes()
{
    return [
        'tasks.read',
        'clients.read',
        'projects.read',
        'invoices.read',
        'leads.read',
        'tools.read',
        'tools.execute.read',
    ];
}

function api_token_all_scopes()
{
    return array_merge(api_token_default_scopes(), [
        'tasks.write',
        'tools.execute.write',
        'emails.read',
        'files.read',
    ]);
}

function api_token_hash($rawToken)
{
    return hash('sha256', (string) $rawToken);
}

function api_token_table_exists($connect = null)
{
    if ($connect === null) {
        global $connect;
    }
    if (!$connect instanceof mysqli) {
        return false;
    }
    $res = $connect->query("SHOW TABLES LIKE 'api_tokens'");
    $ok = $res && $res->num_rows > 0;
    if ($res) {
        $res->free();
    }
    return $ok;
}

function api_token_normalize_scopes($scopes)
{
    if (is_string($scopes)) {
        $decoded = json_decode($scopes, true);
        if (is_array($decoded)) {
            $scopes = $decoded;
        } else {
            $scopes = preg_split('/[\s,]+/', $scopes) ?: [];
        }
    }
    if (!is_array($scopes)) {
        return [];
    }
    $allowed = array_flip(api_token_all_scopes());
    $out = [];
    foreach ($scopes as $s) {
        $s = trim((string) $s);
        if ($s !== '' && isset($allowed[$s])) {
            $out[] = $s;
        }
    }
    return array_values(array_unique($out));
}

function api_token_create($userId, $name, array $scopes = [], $rateLimit = 60, $expiresAt = 0, $connect = null)
{
    if ($connect === null) {
        global $connect;
    }
    if (!api_token_table_exists($connect)) {
        return ['success' => false, 'message' => 'api_tokens table missing — run migration 5.5.sql'];
    }
    $userId = (int) $userId;
    if ($userId <= 0) {
        return ['success' => false, 'message' => 'Invalid user'];
    }
    $scopes = api_token_normalize_scopes($scopes ?: api_token_default_scopes());
    if (empty($scopes)) {
        $scopes = api_token_default_scopes();
    }
    $raw = 'ts_' . bin2hex(random_bytes(32));
    $hash = api_token_hash($raw);
    $prefix = substr($raw, 0, 10);
    $now = time();
    $rateLimit = max(10, min(1000, (int) $rateLimit));
    $expiresAt = (int) $expiresAt;
    $escName = $connect->real_escape_string(mb_substr(trim((string) $name), 0, 120) ?: 'API token');
    $escHash = $connect->real_escape_string($hash);
    $escPrefix = $connect->real_escape_string($prefix);
    $escScopes = $connect->real_escape_string(json_encode($scopes));

    $ok = $connect->query(
        "INSERT INTO api_tokens
         (user_id, name, token_hash, token_prefix, scopes, rate_limit_per_min, expires_at, created_at, updated_at)
         VALUES ({$userId}, '{$escName}', '{$escHash}', '{$escPrefix}', '{$escScopes}', {$rateLimit}, {$expiresAt}, {$now}, {$now})"
    );
    if (!$ok) {
        return ['success' => false, 'message' => 'Could not create token'];
    }

    return [
        'success' => true,
        'data' => [
            'id' => (int) $connect->insert_id,
            'name' => $escName,
            'token' => $raw,
            'token_prefix' => $prefix,
            'scopes' => $scopes,
            'rate_limit_per_min' => $rateLimit,
            'expires_at' => $expiresAt,
            'created_at' => $now,
        ],
    ];
}

function api_token_list_for_user($userId, $connect = null)
{
    if ($connect === null) {
        global $connect;
    }
    if (!api_token_table_exists($connect)) {
        return [];
    }
    $userId = (int) $userId;
    $admin = function_exists('ai_is_admin_user') && ai_is_admin_user();
    $where = $admin ? '1=1' : "user_id = {$userId}";
    $res = $connect->query(
        "SELECT id, user_id, name, token_prefix, scopes, rate_limit_per_min, last_used_at, expires_at, revoked_at, created_at
         FROM api_tokens WHERE {$where} ORDER BY id DESC LIMIT 100"
    );
    $rows = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $row['scopes'] = api_token_normalize_scopes($row['scopes'] ?? []);
            $rows[] = $row;
        }
        $res->free();
    }
    return $rows;
}

function api_token_revoke($tokenId, $userId = null, $connect = null)
{
    if ($connect === null) {
        global $connect;
    }
    if (!api_token_table_exists($connect)) {
        return ['success' => false, 'message' => 'api_tokens missing'];
    }
    $tokenId = (int) $tokenId;
    $now = time();
    $where = "id = {$tokenId}";
    if ($userId !== null && !(function_exists('ai_is_admin_user') && ai_is_admin_user())) {
        $where .= ' AND user_id = ' . (int) $userId;
    }
    $connect->query("UPDATE api_tokens SET revoked_at = {$now}, updated_at = {$now} WHERE {$where} AND revoked_at = 0");
    return ['success' => $connect->affected_rows > 0];
}

function api_token_find_by_raw($rawToken, $connect = null)
{
    if ($connect === null) {
        global $connect;
    }
    if (!api_token_table_exists($connect) || $rawToken === '') {
        return null;
    }
    $hash = $connect->real_escape_string(api_token_hash($rawToken));
    $now = time();
    $res = $connect->query(
        "SELECT * FROM api_tokens
         WHERE token_hash = '{$hash}' AND revoked_at = 0
           AND (expires_at = 0 OR expires_at > {$now})
         LIMIT 1"
    );
    if (!$res || !($row = $res->fetch_assoc())) {
        return null;
    }
    $res->free();
    $row['scopes'] = api_token_normalize_scopes($row['scopes'] ?? []);
    return $row;
}

function api_token_touch($tokenId, $connect = null)
{
    if ($connect === null) {
        global $connect;
    }
    $tokenId = (int) $tokenId;
    $now = time();
    $connect->query("UPDATE api_tokens SET last_used_at = {$now}, updated_at = {$now} WHERE id = {$tokenId}");
}
