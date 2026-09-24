<?php
/**
 * Safe return URL for edit-project: capture current page, validate on POST, build redirect Location.
 */

function project_edit_return_allowed_basenames()
{
    return [
        'overview.php',
        'discussion.php',
        'task.php',
        'media.php',
        'payments.php',
        'notes.php',
        'projects.php',
        'archive.php',
        'profile.php',
        'task-area.php',
        'index.php',
        'add_task.php',
        'add_bulk_task.php',
        'invoices.php',
        'check-invoice-database.php',
        'attempts-ip.php',
        'media-management.php',
        'leads.php',
        'kanban.php',
        'all-tasks.php',
        'private-notes.php',
        'documents.php',
        'clients.php',
        'members.php',
    ];
}

function project_edit_return_install_path()
{
    global $url;
    $path = parse_url(rtrim((string)$url, '/'), PHP_URL_PATH);
    if ($path === false || $path === null || $path === '') {
        return '';
    }
    return rtrim($path, '/');
}

function project_edit_return_script_key($script)
{
    $script = basename((string) $script);
    if ($script === '') {
        return '';
    }
    if ($script === 'dashboard') {
        return 'index.php';
    }
    if (substr($script, -4) !== '.php') {
        $script .= '.php';
    }
    return $script;
}

function project_edit_return_script_allowed($script)
{
    $key = project_edit_return_script_key($script);
    return $key !== '' && in_array($key, project_edit_return_allowed_basenames(), true);
}

function project_edit_return_capture_current_uri()
{
    $requestUri = isset($_SERVER['REQUEST_URI']) ? (string)$_SERVER['REQUEST_URI'] : '';
    if ($requestUri === '') {
        return null;
    }
    $parts = parse_url($requestUri);
    if (!$parts || empty($parts['path'])) {
        return null;
    }
    $path = $parts['path'];
    if (strpos($path, '..') !== false) {
        return null;
    }
    if (!project_edit_return_script_allowed(basename($path))) {
        return null;
    }
    $install = project_edit_return_install_path();
    if ($install !== '' && strpos($path, $install) !== 0) {
        return null;
    }
    $query = isset($parts['query']) ? ('?' . $parts['query']) : '';
    return $path . $query;
}

function project_edit_return_query_project_id($queryString)
{
    $queryString = ltrim((string)$queryString, '?');
    if ($queryString === '') {
        return null;
    }
    $query = [];
    parse_str($queryString, $query);
    if (!empty($query['projectId'])) {
        return (int)$query['projectId'];
    }
    if (!empty($query['project_id'])) {
        return (int)$query['project_id'];
    }
    return null;
}

function project_edit_return_validate($raw, $editedProjectId)
{
    $raw = trim((string)$raw);
    if ($raw === '') {
        return null;
    }
    if (strpos($raw, '://') !== false) {
        return null;
    }

    $path = $raw;
    $query = '';
    if (strpos($raw, '?') !== false) {
        $bits = explode('?', $raw, 2);
        $path = $bits[0];
        $query = '?' . $bits[1];
    }

    if ($path === '' || $path[0] !== '/' || strpos($path, '..') !== false) {
        return null;
    }
    if (!project_edit_return_script_allowed(basename($path))) {
        return null;
    }

    $install = project_edit_return_install_path();
    if ($install !== '' && strpos($path, $install) !== 0) {
        return null;
    }

    $pidInUrl = project_edit_return_query_project_id($query);
    if ($pidInUrl !== null && $pidInUrl > 0 && (int)$editedProjectId !== $pidInUrl) {
        return null;
    }
    return $path . $query;
}

function project_edit_return_absolute_location($pathWithQuery)
{
    global $url;
    if ($pathWithQuery === '' || $pathWithQuery[0] !== '/') {
        return null;
    }

    $base = rtrim((string)$url, '/');
    $parsedUrl = parse_url($base);
    if (!$parsedUrl || empty($parsedUrl['scheme']) || empty($parsedUrl['host'])) {
        return null;
    }

    $origin = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];
    if (!empty($parsedUrl['port'])) {
        $origin .= ':' . $parsedUrl['port'];
    }
    $absolute = $origin . $pathWithQuery;
    if (function_exists('tasksession_pretty_redirect_location')) {
        $pretty = tasksession_pretty_redirect_location($absolute);
        if (is_string($pretty) && $pretty !== '') {
            return $pretty;
        }
    }
    return $absolute;
}

/**
 * Relative edit-project href (pretty) for the current role folder.
 */
function project_edit_page_href($projectId, $includeReturn = true)
{
    $href = 'edit-project?id=' . (int) $projectId;
    if ($includeReturn) {
        $uri = project_edit_return_capture_current_uri();
        if ($uri !== null && $uri !== '') {
            $href .= '&project_edit_return=' . rawurlencode($uri);
        }
    }
    return $href;
}

function project_edit_return_hidden_markup()
{
    $uri = project_edit_return_capture_current_uri();
    if ($uri === null || $uri === '') {
        return '';
    }
    return '<input type="hidden" name="project_edit_return" value="' . htmlspecialchars($uri, ENT_QUOTES, 'UTF-8') . '" />';
}
