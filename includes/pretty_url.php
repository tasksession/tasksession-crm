<?php
/**
 * TaskSession pretty URL helpers — browser page paths only.
 * Do not strip ajax/api/real-chat/includes/assets or service endpoints.
 */

if (!function_exists('tasksession_pretty_excluded_path')) {
    /**
     * Paths that must keep .php (or are never rewritten).
     */
    function tasksession_pretty_excluded_path($relativePath)
    {
        $path = strtolower(str_replace('\\', '/', ltrim((string) $relativePath, '/')));
        $pathOnly = $path;
        $qPos = strpos($pathOnly, '?');
        if ($qPos !== false) {
            $pathOnly = substr($pathOnly, 0, $qPos);
        }
        $hashPos = strpos($pathOnly, '#');
        if ($hashPos !== false) {
            $pathOnly = substr($pathOnly, 0, $hashPos);
        }

        $prefixes = array(
            'ajax/',
            'api/',
            'real-chat/',
            'includes/',
            'system-api/',
            'install/',
            'assets/',
            'vendor/',
            'uploads/',
            'payment-api/',
            'cron.php',
            'cron-old.php',
            'menu-alias.php',
        );
        foreach ($prefixes as $prefix) {
            if ($pathOnly === rtrim($prefix, '/') || strpos($pathOnly, $prefix) === 0) {
                return true;
            }
        }

        $basenames = array(
            'serve-product-image.php',
            'api-webhooks.php',
            'cron-scheduler.php',
            'update-woo.php',
            'webhook.php',
            'export_private_note.php',
        );
        $base = basename($pathOnly);
        if (in_array($base, $basenames, true)) {
            return true;
        }

        // Module CSV POST/download endpoints under import-export/{module}/import|export.php
        // (migration/import.php and migration/export.php are UI pages — not excluded here).
        if (preg_match('#^import-export/(?!migration(?:/|$))[^/]+/(import|export)\.php$#', $pathOnly)) {
            return true;
        }

        return false;
    }
}

if (!function_exists('tasksession_pretty_is_page_path')) {
    /**
     * Whitelist-ish: role folders, mail, marketing, ecommerce, ai, known root UI pages.
     */
    function tasksession_pretty_is_page_path($relativePath)
    {
        $path = strtolower(str_replace('\\', '/', ltrim((string) $relativePath, '/')));
        $pathOnly = $path;
        $qPos = strpos($pathOnly, '?');
        if ($qPos !== false) {
            $pathOnly = substr($pathOnly, 0, $qPos);
        }
        $hashPos = strpos($pathOnly, '#');
        if ($hashPos !== false) {
            $pathOnly = substr($pathOnly, 0, $hashPos);
        }

        if (tasksession_pretty_excluded_path($pathOnly)) {
            return false;
        }

        // Root login page → app base URL (not /dashboard)
        if ($pathOnly === 'index.php' || $pathOnly === 'index' || $pathOnly === '') {
            return true;
        }

        // Public payment page pay.php/{token} or pay/{token}
        if (preg_match('#^pay(?:\.php)?(?:/|$)#', $pathOnly)) {
            return true;
        }

        $rootPages = array(
            'chatting.php', 'discussion.php', 'settings.php', 'search.php', 'events.php',
            'media.php', 'activity.php', 'forgot-password.php', 'reset_password.php', 'unauthorized.php',
            'authenticator.php', 'verify-2fa.php', 'setup-2fa.php',
            'chatting', 'discussion', 'settings', 'search', 'events',
            'media', 'activity', 'forgot-password', 'reset_password', 'unauthorized',
            'authenticator', 'verify-2fa', 'setup-2fa',
            'templates/download_pdf.php', 'templates/download_pdf',
        );
        if (in_array($pathOnly, $rootPages, true)) {
            return true;
        }

        if (preg_match('#^(admin|staff|client|mail|marketing|ecommerce|ai|import-export)/#', $pathOnly)) {
            return true;
        }

        return false;
    }
}

if (!function_exists('tasksession_pretty_path')) {
    /**
     * Convert a relative app path to pretty form. Preserves query and hash.
     * admin/index.php → admin/dashboard; admin/clients.php → admin/clients.
     *
     * @param string $path Relative path (may include query/hash)
     * @return string
     */
    function tasksession_pretty_path($path)
    {
        $raw = str_replace('\\', '/', trim((string) $path));
        if ($raw === '' || preg_match('#^(https?:)?//#i', $raw) || strpos($raw, 'mailto:') === 0) {
            return $raw;
        }

        $hash = '';
        $hashPos = strpos($raw, '#');
        if ($hashPos !== false) {
            $hash = substr($raw, $hashPos);
            $raw = substr($raw, 0, $hashPos);
        }
        $query = '';
        $qPos = strpos($raw, '?');
        if ($qPos !== false) {
            $query = substr($raw, $qPos);
            $raw = substr($raw, 0, $qPos);
        }

        $rel = ltrim($raw, '/');
        if ($rel === '') {
            return $query . $hash;
        }
        if (!tasksession_pretty_is_page_path($rel . $query)) {
            return $rel . $query . $hash;
        }
        if (tasksession_pretty_excluded_path($rel)) {
            return $rel . $query . $hash;
        }

        // Root login → empty path (DirectoryIndex), never /dashboard
        if (preg_match('#^index\.php$#i', $rel) || strcasecmp($rel, 'index') === 0) {
            return $query . $hash;
        }

        // Public payment link: pay.php/{token} → pay/{token}
        if (preg_match('#^pay\.php/([a-zA-Z0-9_-]+)$#', $rel, $m)) {
            return 'pay/' . $m[1] . $query . $hash;
        }
        if (preg_match('#^pay/([a-zA-Z0-9_-]+)$#', $rel)) {
            return $rel . $query . $hash;
        }

        // Role dashboard
        if (preg_match('#^(admin|staff|client)/index\.php$#i', $rel, $m)) {
            return strtolower($m[1]) . '/dashboard' . $query . $hash;
        }
        if (preg_match('#^(admin|staff|client)/index$#i', $rel, $m)) {
            return strtolower($m[1]) . '/dashboard' . $query . $hash;
        }

        // AI agents-alerts special pretty path
        if (preg_match('#^ai/agents-alerts\.php$#i', $rel) || preg_match('#^ai/agents-alerts$#i', $rel)) {
            return 'ai/agents/alerts' . $query . $hash;
        }

        // import-export migration hub
        if (preg_match('#^import-export/migration/index\.php$#i', $rel) || preg_match('#^import-export/migration/index$#i', $rel)) {
            return 'import-export/migration/' . $query . $hash;
        }
        if (preg_match('#^import-export/migration/(import|export)\.php$#i', $rel, $m)) {
            return 'import-export/migration/' . strtolower($m[1]) . '/' . $query . $hash;
        }
        if (preg_match('#^import-export/migration/(import|export)/?$#i', $rel, $m)) {
            return 'import-export/migration/' . strtolower($m[1]) . '/' . $query . $hash;
        }
        if (preg_match('#^import-export/([^/]+)/index\.php$#i', $rel, $m)) {
            return 'import-export/' . $m[1] . '/' . $query . $hash;
        }
        if (preg_match('#^import-export/history\.php$#i', $rel) || preg_match('#^import-export/history$#i', $rel)) {
            return 'import-export/history' . $query . $hash;
        }

        // Strip trailing .php for whitelisted pages
        if (preg_match('#^(.+)\.php$#i', $rel, $m)) {
            $rel = $m[1];
        }

        return $rel . $query . $hash;
    }
}

if (!function_exists('tasksession_role_dashboard_path')) {
    function tasksession_role_dashboard_path($role)
    {
        $role = strtolower(trim((string) $role));
        if (!in_array($role, array('admin', 'staff', 'client'), true)) {
            $role = 'admin';
        }
        return $role . '/dashboard';
    }
}

if (!function_exists('tasksession_app_href')) {
    /**
     * Absolute app URL with pretty path.
     *
     * @param string $path Relative path
     * @return string
     */
    function tasksession_app_href($path)
    {
        global $url;
        $base = isset($url) ? rtrim((string) $url, '/') . '/' : '/';
        $pretty = tasksession_pretty_path($path);
        if (preg_match('#^(https?:)?//#i', $pretty)) {
            return $pretty;
        }
        return $base . ltrim($pretty, '/');
    }
}

if (!function_exists('tasksession_download_pdf_href')) {
    /**
     * Invoice / document PDF download endpoint (pretty: templates/download_pdf).
     */
    function tasksession_download_pdf_href()
    {
        return tasksession_app_href('templates/download_pdf.php');
    }
}

if (!function_exists('tasksession_should_pretty_redirect_target')) {
    /**
     * Whether redirectTo() should rewrite this Location to a pretty page URL.
     *
     * @param string $location
     * @return bool
     */
    function tasksession_should_pretty_redirect_target($location)
    {
        $location = trim((string) $location);
        if ($location === '' || preg_match('#^(javascript:|mailto:)#i', $location)) {
            return false;
        }

        $path = $location;
        if (preg_match('#^https?://#i', $location)) {
            $parsed = parse_url($location);
            $path = isset($parsed['path']) ? (string) $parsed['path'] : '';
        }

        global $url;
        $basePath = '';
        if (!empty($url)) {
            $bu = parse_url((string) $url);
            if (!empty($bu['path'])) {
                $basePath = rtrim((string) $bu['path'], '/');
            }
        }
        if ($basePath !== '' && strpos($path, $basePath) === 0) {
            $path = substr($path, strlen($basePath));
        }
        $path = ltrim(str_replace('\\', '/', $path), '/');
        if ($path === '') {
            return false;
        }

        // Rebuild relative with query for classification
        $rel = $path;
        if (preg_match('#^https?://#i', $location)) {
            $parsed = parse_url($location);
            if (!empty($parsed['query'])) {
                $rel .= '?' . $parsed['query'];
            }
        } elseif (strpos($location, '?') !== false) {
            $q = parse_url($location, PHP_URL_QUERY);
            if ($q) {
                $rel = preg_replace('#\?.*$#', '', $path) . '?' . $q;
            }
        }

        return tasksession_pretty_is_page_path($rel) && !tasksession_pretty_excluded_path($rel);
    }
}

if (!function_exists('tasksession_pretty_redirect_location')) {
    /**
     * Rewrite a full or relative redirect Location to pretty form when safe.
     *
     * @param string $location
     * @return string
     */
    function tasksession_pretty_redirect_location($location)
    {
        $location = (string) $location;
        if (!tasksession_should_pretty_redirect_target($location)) {
            return $location;
        }

        global $url;
        $base = isset($url) ? rtrim((string) $url, '/') . '/' : '';

        if (preg_match('#^https?://#i', $location)) {
            $parsed = parse_url($location);
            $path = isset($parsed['path']) ? (string) $parsed['path'] : '';
            $basePath = '';
            if ($base !== '') {
                $bu = parse_url($base);
                if (!empty($bu['path'])) {
                    $basePath = rtrim((string) $bu['path'], '/');
                }
            }
            $rel = $path;
            if ($basePath !== '' && strpos($path, $basePath) === 0) {
                $rel = substr($path, strlen($basePath));
            }
            $rel = ltrim($rel, '/');
            if (!empty($parsed['query'])) {
                $rel .= '?' . $parsed['query'];
            }
            if (!empty($parsed['fragment'])) {
                $rel .= '#' . $parsed['fragment'];
            }
            $pretty = tasksession_pretty_path($rel);
            $scheme = isset($parsed['scheme']) ? $parsed['scheme'] . '://' : '';
            $host = isset($parsed['host']) ? $parsed['host'] : '';
            $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';
            $prefix = $scheme . $host . $port . ($basePath !== '' ? $basePath . '/' : '/');
            return rtrim($prefix, '/') . '/' . ltrim($pretty, '/');
        }

        // Relative or base-prefixed path built as $url . 'admin/...'
        if ($base !== '' && strpos($location, $base) === 0) {
            $rel = substr($location, strlen($base));
            return $base . ltrim(tasksession_pretty_path($rel), '/');
        }

        return tasksession_pretty_path($location);
    }
}

if (!function_exists('tasksession_normalize_landing_storage_path')) {
    /**
     * Map legacy index.php landings and .php pages to pretty option keys.
     */
    function tasksession_normalize_landing_storage_path($path, $role = 'admin')
    {
        $path = str_replace('\\', '/', trim((string) $path));
        $path = ltrim($path, '/');
        if ($path === 'admin/menu-label-overrides.php') {
            $path = 'admin/menu-settings.php';
        }
        if (preg_match('#^(admin|staff|client)/index\.php$#i', $path, $m)) {
            $path = strtolower($m[1]) . '/dashboard';
        }
        if ($path !== '' && substr($path, -4) === '.php' && function_exists('tasksession_pretty_path')) {
            $path = tasksession_pretty_path($path);
        }
        return $path;
    }
}

if (!function_exists('tasksession_payment_url')) {
    /**
     * Public invoice payment URL (pretty): {base}/pay/{token}
     */
    function tasksession_payment_url($token)
    {
        global $url;
        $token = trim((string) $token);
        $base = isset($url) ? rtrim((string) $url, '/') . '/' : '/';
        return $base . 'pay/' . rawurlencode($token);
    }
}
