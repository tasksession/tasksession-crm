<?php
/**
 * Loads data for project overview cards (admin/staff center column).
 *
 * Requires: $projectId, $project, $connect, sidebar vars from project-sidebar-data.php
 * Optional: $overviewPortal = 'admin'|'staff'|'client' (default admin)
 */

global $connect, $lang, $url;

require_once __DIR__ . '/custom-fields/project_custom_field_values.php';
require_once __DIR__ . '/project_activity.php';

if (!isset($overviewPortal) || !in_array($overviewPortal, ['admin', 'staff', 'client'], true)) {
    $overviewPortal = 'admin';
}
$portalBase = rtrim((string) $url, '/') . '/' . $overviewPortal . '/';

$overviewShowEditActions = true;
$overviewShowClientActions = true;
$overviewShowCompanyActions = true;
$overviewShowContactCards = true;

if ($overviewPortal === 'staff') {
    if (!function_exists('has_permission')) {
        require_once __DIR__ . '/permissions.php';
    }
    if (function_exists('ensure_user_permissions')) {
        ensure_user_permissions($connect);
    }
    $overviewShowEditActions = function_exists('has_permission') && has_permission('project_edit');
    $overviewShowClientActions = function_exists('has_permission') && has_permission('client_view');
    $overviewShowCompanyActions = $overviewShowClientActions;
    $overviewShowContactCards = $overviewShowClientActions;
}

$overviewCustomFields = fetch_project_custom_field_view_rows($connect, (int) $projectId);
$overviewHasCustomFields = count($overviewCustomFields) > 0;
$overviewActivities = project_activity_fetch_recent($connect, (int) $projectId, 10);
$overviewPrimaryClient = (isset($allClients) && is_array($allClients) && count($allClients) > 0)
    ? $allClients[0]
    : null;

if ($overviewPortal === 'client') {
    $overviewLoggedInUserId = isset($id) ? (int) $id : 0;
    if ($overviewLoggedInUserId <= 0 && isset($session) && is_object($session)) {
        $overviewLoggedInUserId = (int) $session->userId;
    }
    $overviewShowEditActions = false;
    $overviewShowCompanyActions = false;
    $overviewShowClientActions = $overviewPrimaryClient
        && $overviewLoggedInUserId === (int) $overviewPrimaryClient->id;
    $overviewShowContactCards = (bool) $overviewPrimaryClient;
}

$overviewCurrencySymbol = '$';
if ($overviewPrimaryClient && function_exists('getClientCurrencySymbol')) {
    $overviewCurrencySymbol = getClientCurrencySymbol($overviewPrimaryClient);
} elseif ($overviewPrimaryClient && !empty($overviewPrimaryClient->currency)) {
    $parts = explode(',', (string) $overviewPrimaryClient->currency);
    $overviewCurrencySymbol = count($parts) > 1 ? trim($parts[1]) : trim($parts[0]);
}

$overviewBudgetTotal = isset($totalBudget) ? (float) $totalBudget : 0.0;
if ($overviewBudgetTotal <= 0 && isset($project->budget) && is_numeric($project->budget)) {
    $overviewBudgetTotal = (float) $project->budget;
}
$overviewBudgetUsed = isset($paidAmount) ? (float) $paidAmount : 0.0;
$overviewBudgetPercent = ($overviewBudgetTotal > 0)
    ? (int) round(($overviewBudgetUsed / $overviewBudgetTotal) * 100)
    : 0;
$overviewShowBudget = $overviewBudgetTotal > 0;
if ($overviewPortal === 'staff' && function_exists('has_permission') && !has_permission('milestone_view')) {
    $overviewShowBudget = false;
}
if ($overviewPortal === 'client') {
    require_once __DIR__ . '/task_permission.php';
    $budgetUserId = isset($id) ? (int) $id : 0;
    if ($budgetUserId <= 0 && isset($session) && is_object($session)) {
        $budgetUserId = (int) $session->userId;
    }
    $clientTaskPermissions = $budgetUserId > 0 ? TaskPermission::getOrCreate($budgetUserId) : null;
    if (!$clientTaskPermissions || !$clientTaskPermissions->can_view_milestones) {
        $overviewShowBudget = false;
    }
}

$overviewEditUrl = $portalBase . 'edit-project?id=' . (int) $projectId;
$installPath = function_exists('project_edit_return_install_path') ? project_edit_return_install_path() : '';
$overviewEditReturn = ($installPath !== '' ? $installPath : '') . '/' . $overviewPortal . '/overview?projectId=' . (int) $projectId;
$overviewEditUrl .= '&project_edit_return=' . rawurlencode($overviewEditReturn);
$overviewProjectsUrl = $portalBase . 'projects';
$overviewProjectUrl = $portalBase . 'overview?projectId=' . (int) $projectId;
$overviewClientProfileUrl = '';
$overviewClientEditUrl = '';
$overviewCompanyId = 0;
$overviewCompaniesTableReady = false;

if ($connect) {
    $ccChk = @mysqli_query($connect, "SHOW TABLES LIKE 'client_companies'");
    if ($ccChk && mysqli_num_rows($ccChk) > 0) {
        $overviewCompaniesTableReady = true;
    }
    if ($ccChk) {
        mysqli_free_result($ccChk);
    }
}

if ($overviewPrimaryClient) {
    $overviewClientProfileUrl = $portalBase . 'profile?user_id=' . (int) $overviewPrimaryClient->id;
    $overviewClientEditUrl = $portalBase . 'edit?editprofile=' . (int) $overviewPrimaryClient->id;
}

if (!function_exists('project_overview_breadcrumb_title')) {
    function project_overview_breadcrumb_title(string $title): string
    {
        $title = trim(preg_replace('/\s+/u', ' ', $title));
        if ($title === '') {
            return '';
        }
        $words = preg_split('/\s+/u', $title);

        return implode(' ', array_slice($words, 0, 2));
    }
}

$overviewProjectBreadcrumbTitle = project_overview_breadcrumb_title((string) ($project->project_title ?? ''));

$overviewClientAddress = '';
if ($overviewPrimaryClient) {
    $clientAddressParts = array_filter([
        trim((string) ($overviewPrimaryClient->address ?? '')),
        trim((string) ($overviewPrimaryClient->city ?? '')),
        trim((string) ($overviewPrimaryClient->state ?? '')),
        trim((string) ($overviewPrimaryClient->country ?? '')),
        trim((string) ($overviewPrimaryClient->zip ?? '')),
    ], static function ($part) {
        return $part !== '';
    });
    $overviewClientAddress = !empty($clientAddressParts) ? implode(', ', $clientAddressParts) : '';
}

$overviewDescHtml = '';
if (!empty($project->project_desc)) {
    $overviewDescHtml = $project->project_desc;
}

$__invoiceCompanyHelper = __DIR__ . '/invoice_company_helper.php';
if (is_file($__invoiceCompanyHelper)) {
    require_once $__invoiceCompanyHelper;
}

$overviewClientCompany = null;
$overviewCompanyLogoUrl = '';

if ($overviewPrimaryClient && function_exists('invoice_get_companies_for_client') && function_exists('invoice_load_company_snapshot')) {
    $linkedCompanies = invoice_get_companies_for_client($connect, (int) $overviewPrimaryClient->id);
    if (!empty($linkedCompanies)) {
        $linkedCo = $linkedCompanies[0];
        $companyId = (int) ($linkedCo['id'] ?? 0);
        if ($companyId > 0) {
            $overviewCompanyId = $companyId;
            $snapshot = invoice_load_company_snapshot($connect, $companyId);
            if ($snapshot) {
                $overviewClientCompany = $snapshot;
                $logoRes = @mysqli_query(
                    $connect,
                    'SELECT logo_path FROM client_companies WHERE id = ' . $companyId . ' AND deleted_at IS NULL LIMIT 1'
                );
                if ($logoRes && ($logoRow = mysqli_fetch_assoc($logoRes))) {
                    $logoRel = trim((string) ($logoRow['logo_path'] ?? ''));
                    if ($logoRel !== '') {
                        $overviewCompanyLogoUrl = rtrim((string) $url, '/') . '/' . ltrim($logoRel, '/');
                    }
                    mysqli_free_result($logoRes);
                }
            }
        }
    }
}

$overviewCompanyAddress = '';
if ($overviewClientCompany && function_exists('invoice_company_display_address_from_snapshot')) {
    $overviewCompanyAddress = trim(invoice_company_display_address_from_snapshot($overviewClientCompany));
}

$overviewLoadCompanyModals = !empty($overviewCompaniesTableReady)
    && $overviewPrimaryClient
    && ($overviewPortal === 'admin' || ($overviewPortal === 'staff' && $overviewShowCompanyActions));

$companyPickableClients = [];
if (!empty($allClients) && is_array($allClients)) {
    $seenOverviewClientIds = [];
    foreach ($allClients as $pickClient) {
        if (!is_object($pickClient)) {
            continue;
        }
        $pickClientId = (int) ($pickClient->id ?? 0);
        if ($pickClientId <= 0 || isset($seenOverviewClientIds[$pickClientId])) {
            continue;
        }
        $seenOverviewClientIds[$pickClientId] = true;
        $pickName = trim((string) ($pickClient->firstName ?? '') . ' ' . (string) ($pickClient->lastName ?? ''));
        $pickEmail = (string) ($pickClient->email ?? '');
        $companyPickableClients[] = [
            'id' => $pickClientId,
            'name' => htmlspecialchars($pickName !== '' ? $pickName : ('Client #' . $pickClientId), ENT_QUOTES, 'UTF-8'),
            'email' => htmlspecialchars($pickEmail, ENT_QUOTES, 'UTF-8'),
            'image' => getUserAvatarHtml(
                $pickClientId,
                (string) ($pickClient->firstName ?? ''),
                (string) ($pickClient->lastName ?? ''),
                32,
                32,
                'rounded-circle',
                (string) ($pickClient->firstName ?? '')
            ),
            'search' => strtolower($pickName . ' ' . $pickEmail),
        ];
    }
}
