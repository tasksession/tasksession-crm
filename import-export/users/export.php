<?php
require_once __DIR__ . '/../../includes/lib-initialize.php';
require_once __DIR__ . '/../../includes/import-export/CsvUtilities.php';

if (!$session->isLoggedIn()) {
    header('HTTP/1.1 403 Forbidden');
    exit;
}
$isAdmin = isset($_SESSION['accountStatus']) && (int)$_SESSION['accountStatus'] === 1;
if (!$isAdmin) {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

require_once __DIR__ . '/../../includes/permissions.php';
ensure_user_permissions($connect);

$type = isset($_GET['type']) ? strtolower(trim((string)$_GET['type'])) : 'client';
if (!in_array($type, ['client', 'staff', 'admin'], true)) {
    header('HTTP/1.1 400 Bad Request');
    exit;
}
if ($type === 'admin' && !$isAdmin) {
    header('HTTP/1.1 403 Forbidden');
    exit;
}
$accountStatus = $type === 'client' ? 2 : ($type === 'staff' ? 3 : 1);
$entityType = $type;
$isSample = isset($_GET['sample']) && $_GET['sample'] === '1';

/** Same keys as client import wizard / ClientCompanyLinkHelper (export round-trip). */
$orgHeaders = [];
$orgStmt = null;
if ($type === 'client') {
    $ccTbl = mysqli_query($connect, "SHOW TABLES LIKE 'client_companies'");
    if ($ccTbl && mysqli_num_rows($ccTbl) > 0) {
        $orgHeaders = [
            'Org Company Name',
            'Org Currency',
            'Org VAT Number',
            'Org Phone',
            'Org Email',
            'Org Website',
            'Org Address',
            'Org City',
            'Org State',
            'Org Zip',
            'Org Country',
            'Org Billing address',
            'Org Billing Street',
            'Org Billing City',
            'Org Billing State',
            'Org Billing Zip',
            'Org Billing Country',
        ];
        if (!$isSample) {
            $memOk = false;
            $memTbl = mysqli_query($connect, "SHOW TABLES LIKE 'client_company_members'");
            if ($memTbl && mysqli_num_rows($memTbl) > 0) {
                $memOk = true;
            }
            if ($memTbl) {
                mysqli_free_result($memTbl);
            }
            if ($memOk) {
                $orgSql = 'SELECT c.id AS org_company_id, c.name, c.currency, c.vat_number, c.phone, c.email, c.website,
                c.address, c.city, c.state, c.zip, c.country,
                c.billing_address, c.billing_street, c.billing_city, c.billing_state, c.billing_zip, c.billing_country
                FROM client_company_members m
                INNER JOIN client_companies c ON c.id = m.company_id AND c.deleted_at IS NULL
                WHERE m.user_id = ?
                ORDER BY m.is_primary DESC, m.company_id ASC
                LIMIT 1';
                $orgStmt = $connect->prepare($orgSql);
            }
        }
        mysqli_free_result($ccTbl);
    } elseif ($ccTbl) {
        mysqli_free_result($ccTbl);
    }
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . ($isSample ? "users-{$type}-sample.csv" : "users-{$type}-export.csv"));
$out = fopen('php://output', 'w');
$headers = ['User ID', 'Email', 'First Name', 'Title', 'Phone', 'Website', 'Company', 'Address', 'City', 'State', 'Zip', 'Country', 'Currency', 'Assigned Team', 'Role ID'];
$headerNormUsed = [];
foreach ($headers as $h0) {
    $nh = strtolower(trim((string)$h0));
    $nh = preg_replace('/\s+/', ' ', $nh);
    $headerNormUsed[$nh] = true;
}
$cfRes = mysqli_query(
    $connect,
    "SELECT id, label FROM custom_fields WHERE entity_type = '" . mysqli_real_escape_string($connect, $entityType) . "' AND COALESCE(is_disabled, 0) = 0 ORDER BY sort_order ASC, id ASC"
);
$cfIds = [];
if ($cfRes) {
    while ($cf = mysqli_fetch_assoc($cfRes)) {
        $fid = (int)$cf['id'];
        $base = trim((string)($cf['label'] ?? ''));
        if ($base === '') {
            $base = 'Custom field ' . $fid;
        }
        $colName = $base;
        $norm = strtolower($base);
        $norm = preg_replace('/\s+/', ' ', $norm);
        if (isset($headerNormUsed[$norm])) {
            $colName = $base . ' [' . $fid . ']';
            $norm = strtolower($colName);
            $norm = preg_replace('/\s+/', ' ', $norm);
        }
        $headerNormUsed[$norm] = true;
        $headers[] = $colName;
        $cfIds[] = $fid;
    }
    mysqli_free_result($cfRes);
}

$orgCompanyCfIds = [];
$orgCompanyCfHeaders = [];
if ($type === 'client' && $orgHeaders !== []) {
    $ccvChk = @mysqli_query($connect, "SHOW TABLES LIKE 'company_custom_field_values'");
    $ccvExportOk = $ccvChk && mysqli_num_rows($ccvChk) > 0;
    if ($ccvChk) {
        mysqli_free_result($ccvChk);
    }
    if ($ccvExportOk) {
        $orgCfExportRes = mysqli_query(
            $connect,
            "SELECT id, label FROM custom_fields WHERE entity_type = 'company' AND COALESCE(is_disabled, 0) = 0 ORDER BY sort_order ASC, id ASC"
        );
        if ($orgCfExportRes) {
            while ($cf = mysqli_fetch_assoc($orgCfExportRes)) {
                $fid = (int)($cf['id'] ?? 0);
                if ($fid <= 0) {
                    continue;
                }
                $lab = trim((string)($cf['label'] ?? ''));
                if ($lab === '') {
                    $lab = 'Custom field ' . $fid;
                }
                $base = 'Org: ' . $lab;
                $colName = $base;
                $norm = strtolower($base);
                $norm = preg_replace('/\s+/', ' ', $norm);
                if (isset($headerNormUsed[$norm])) {
                    $colName = $base . ' [' . $fid . ']';
                    $norm = strtolower($colName);
                    $norm = preg_replace('/\s+/', ' ', $norm);
                }
                $headerNormUsed[$norm] = true;
                $orgCompanyCfHeaders[] = $colName;
                $orgCompanyCfIds[] = $fid;
            }
            mysqli_free_result($orgCfExportRes);
        }
    }
}
if ($orgHeaders !== []) {
    $headers = array_merge($headers, $orgHeaders, $orgCompanyCfHeaders);
}
fputcsv($out, $headers);

if ($isSample) {
    $sampleRow = ['', 'sample@example.com', 'Sample User', 'Director', '+1 555-0100', 'https://example.com', 'Acme Inc', '123 Main', 'NYC', 'NY', '10001', 'US', 'USD,$', '', $type === 'staff' ? '1' : ''];
    foreach ($cfIds as $_fid) {
        $sampleRow[] = '';
    }
    if ($type === 'client' && $orgHeaders !== []) {
        $orgVals = [
            'Demo Org Ltd',
            'USD,$',
            'GB123456789',
            '+44 20 7946 0000',
            'contact@demoorg.example',
            'https://demoorg.example',
            '1 Demo Street',
            'London',
            'England',
            'SW1A 1AA',
            'GB',
            '1 Demo Street',
            '1 Demo Street',
            'London',
            'England',
            'SW1A 1AA',
            'GB',
        ];
        $sampleRow = array_merge($sampleRow, $orgVals, array_fill(0, count($orgCompanyCfIds), ''));
    }
    fputcsv($out, Comon_IE_CsvUtilities::safeCsvRow($sampleRow));
    if ($type === 'client' && $orgHeaders !== []) {
        $sampleRow2 = ['', 'sample2@example.com', 'Sample User Two', 'Manager', '+1 555-0200', 'https://example.com', 'Acme Inc', '456 Oak', 'NYC', 'NY', '10002', 'US', 'USD,$', '', ''];
        foreach ($cfIds as $_fid) {
            $sampleRow2[] = '';
        }
        $sampleRow2 = array_merge($sampleRow2, $orgVals, array_fill(0, count($orgCompanyCfIds), ''));
        fputcsv($out, Comon_IE_CsvUtilities::safeCsvRow($sampleRow2));
    }
    fclose($out);
    exit;
}

$sql = 'SELECT * FROM users WHERE accountStatus = ' . (int)$accountStatus . ' AND status = 0 ORDER BY id DESC';
$res = mysqli_query($connect, $sql);
if ($res) {
    while ($u = mysqli_fetch_assoc($res)) {
        $uid = (int)$u['id'];
        $row = [
            (string)$uid,
            $u['email'] ?? '',
            $u['firstName'] ?? '',
            $u['title'] ?? '',
            $u['phone'] ?? '',
            $u['website'] ?? '',
            $u['company'] ?? '',
            $u['address'] ?? '',
            $u['city'] ?? '',
            $u['state'] ?? '',
            $u['zip'] ?? '',
            $u['country'] ?? '',
            $u['currency'] ?? '',
            $u['assigned_team'] ?? '',
            (string)($u['role_id'] ?? ''),
        ];
        foreach ($cfIds as $fid) {
            $val = '';
            $st = $connect->prepare('SELECT field_value FROM user_custom_field_values WHERE user_id = ? AND custom_field_id = ? LIMIT 1');
            if ($st) {
                $st->bind_param('ii', $uid, $fid);
                $st->execute();
                $r2 = $st->get_result();
                if ($r2 && ($x = $r2->fetch_assoc())) {
                    $val = (string)$x['field_value'];
                }
                $st->close();
            }
            $row[] = $val;
        }
        if ($orgHeaders !== []) {
            $orgRow = array_fill(0, count($orgHeaders), '');
            $orgCompanyId = 0;
            if ($orgStmt) {
                $orgStmt->bind_param('i', $uid);
                if ($orgStmt->execute()) {
                    $ogr = $orgStmt->get_result();
                    if ($ogr && ($oc = $ogr->fetch_assoc())) {
                        $orgCompanyId = (int)($oc['org_company_id'] ?? 0);
                        $orgRow = [
                            (string)($oc['name'] ?? ''),
                            (string)($oc['currency'] ?? ''),
                            (string)($oc['vat_number'] ?? ''),
                            (string)($oc['phone'] ?? ''),
                            (string)($oc['email'] ?? ''),
                            (string)($oc['website'] ?? ''),
                            (string)($oc['address'] ?? ''),
                            (string)($oc['city'] ?? ''),
                            (string)($oc['state'] ?? ''),
                            (string)($oc['zip'] ?? ''),
                            (string)($oc['country'] ?? ''),
                            (string)($oc['billing_address'] ?? ''),
                            (string)($oc['billing_street'] ?? ''),
                            (string)($oc['billing_city'] ?? ''),
                            (string)($oc['billing_state'] ?? ''),
                            (string)($oc['billing_zip'] ?? ''),
                            (string)($oc['billing_country'] ?? ''),
                        ];
                    }
                    if ($ogr) {
                        $ogr->free();
                    }
                }
            }
            if ($orgCompanyCfIds !== []) {
                $cfLocal = [];
                if ($orgCompanyId > 0) {
                    $in = implode(',', array_map('intval', $orgCompanyCfIds));
                    $qcf = mysqli_query(
                        $connect,
                        'SELECT custom_field_id, field_value FROM company_custom_field_values WHERE company_id = '
                        . $orgCompanyId . ' AND custom_field_id IN (' . $in . ')'
                    );
                    if ($qcf) {
                        while ($xr = mysqli_fetch_assoc($qcf)) {
                            $cfLocal[(int)($xr['custom_field_id'] ?? 0)] = (string)($xr['field_value'] ?? '');
                        }
                        mysqli_free_result($qcf);
                    }
                }
                foreach ($orgCompanyCfIds as $ofid) {
                    $orgRow[] = (string)($cfLocal[$ofid] ?? '');
                }
            }
            $row = array_merge($row, $orgRow);
        }
        fputcsv($out, Comon_IE_CsvUtilities::safeCsvRow($row));
    }
    mysqli_free_result($res);
}
if ($orgStmt instanceof mysqli_stmt) {
    $orgStmt->close();
}
fclose($out);
exit;
