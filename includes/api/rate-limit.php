<?php
/**
 * Simple file-based API rate limiting (Phase 7).
 */

function api_rate_limit_dir()
{
    $dir = dirname(__DIR__, 2) . '/uploads/cache/api-rate';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir;
}

function api_rate_limit_check(array $ctx)
{
    $limit = max(10, (int) ($ctx['rate_limit_per_min'] ?? 60));
    $key = ($ctx['auth_type'] ?? 'anon') . '_' . (int) ($ctx['user_id'] ?? 0);
    if (!empty($ctx['token']['id'])) {
        $key .= '_t' . (int) $ctx['token']['id'];
    }
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $key .= '_' . substr(hash('sha256', $ip), 0, 12);
    $file = api_rate_limit_dir() . '/' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $key) . '.json';

    $now = time();
    $window = 60;
    $data = ['start' => $now, 'count' => 0];
    if (is_file($file)) {
        $raw = @file_get_contents($file);
        $parsed = $raw ? json_decode($raw, true) : null;
        if (is_array($parsed) && !empty($parsed['start'])) {
            $data = $parsed;
        }
    }
    if (($now - (int) $data['start']) >= $window) {
        $data = ['start' => $now, 'count' => 0];
    }
    $data['count'] = (int) $data['count'] + 1;
    @file_put_contents($file, json_encode($data), LOCK_EX);

    if ($data['count'] > $limit) {
        if (!headers_sent()) {
            header('Retry-After: ' . max(1, $window - ($now - (int) $data['start'])));
        }
        api_json_error('Rate limit exceeded', 429, 'rate_limited', [
            'limit_per_min' => $limit,
        ]);
    }
}
