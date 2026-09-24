<?php
/**
 * REST API scope checks (Phase 7) — wraps CRM has_permission.
 */

require_once __DIR__ . '/auth.php';

function api_scope_has($scope, array $ctx = null)
{
    if ($ctx === null) {
        $ctx = api_auth_context();
    }
    if (!$ctx) {
        return false;
    }
    $scopes = $ctx['scopes'] ?? [];
    return in_array($scope, $scopes, true);
}

function api_scope_require($scope, array $ctx = null)
{
    if (!api_scope_has($scope, $ctx)) {
        api_json_error('Missing scope: ' . $scope, 403, 'forbidden_scope');
    }
}

/**
 * Map workspace tool → required API scope.
 */
function api_scope_for_tool($toolName, array $tool = [])
{
    $class = (string) ($tool['class'] ?? 'read');
    if ($class === 'write') {
        return 'tools.execute.write';
    }
    $module = (string) ($tool['module'] ?? '');
    $map = [
        'tasks' => 'tasks.read',
        'clients' => 'clients.read',
        'projects' => 'projects.read',
        'invoices' => 'invoices.read',
        'leads' => 'leads.read',
        'emails' => 'emails.read',
        'files' => 'files.read',
        'documents' => 'files.read',
    ];
    if (isset($map[$module]) && api_scope_has($map[$module])) {
        return $map[$module];
    }
    return 'tools.execute.read';
}

function api_require_tool_scope($toolName, array $tool)
{
    $need = api_scope_for_tool($toolName, $tool);
    // Accept either module scope or generic tools.execute.*
    $ctx = api_auth_context();
    $scopes = $ctx['scopes'] ?? [];
    $class = (string) ($tool['class'] ?? 'read');
    $ok = in_array($need, $scopes, true)
        || ($class === 'write' && in_array('tools.execute.write', $scopes, true))
        || ($class !== 'write' && (in_array('tools.execute.read', $scopes, true) || in_array('tools.read', $scopes, true)));
    if (!$ok) {
        api_json_error('Missing scope for tool `' . $toolName . '` (need ' . $need . ')', 403, 'forbidden_scope');
    }
}
