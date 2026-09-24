<?php
/*
 * Generate Payment Link AJAX Handler
 * Generates a unique payment token and returns the payment URL
 */

require_once __DIR__ . '/../includes/lib-initialize.php';

while (ob_get_level() > 0) {
    ob_end_clean();
}
header('Content-Type: application/json; charset=utf-8');

function generate_payment_link_json_response(array $payload): void
{
    echo json_encode($payload);
    exit;
}

function generate_payment_link_ensure_table(mysqli $connect): bool
{
    $res = @$connect->query("SHOW TABLES LIKE 'invoice_payment_links'");
    if ($res && $res->num_rows > 0) {
        return true;
    }
    $sql = "CREATE TABLE IF NOT EXISTS `invoice_payment_links` (
      `id` INT(11) NOT NULL AUTO_INCREMENT,
      `invoice_id` INT(11) NOT NULL,
      `token` VARCHAR(64) NOT NULL,
      `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `expires_at` DATETIME NULL DEFAULT NULL,
      `used` TINYINT(1) NOT NULL DEFAULT 0,
      `used_at` DATETIME NULL DEFAULT NULL,
      PRIMARY KEY (`id`),
      UNIQUE KEY `token` (`token`),
      KEY `invoice_id` (`invoice_id`),
      KEY `expires_at` (`expires_at`),
      KEY `used` (`used`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
    return (bool) @$connect->query($sql);
}

try {
    if (!$session->isLoggedIn() || !in_array((int) ($_SESSION['accountStatus'] ?? 0), [1, 3], true)) {
        generate_payment_link_json_response(['success' => false, 'message' => 'Unauthorized']);
    }

    require_once __DIR__ . '/../includes/csrf-middleware.php';
    csrf_require_for_request();

    if ((int) $_SESSION['accountStatus'] === 3) {
        require_once __DIR__ . '/../includes/permissions.php';
        if (!has_permission('milestone_view') && !has_permission('milestone_update')) {
            generate_payment_link_json_response(['success' => false, 'message' => 'Unauthorized']);
        }
    }

    if (!isset($_POST['invoice_id']) || $_POST['invoice_id'] === '') {
        generate_payment_link_json_response(['success' => false, 'message' => 'Invoice ID is required']);
    }

    $invoice_id = (int) $_POST['invoice_id'];
    if ($invoice_id <= 0) {
        generate_payment_link_json_response(['success' => false, 'message' => 'Invoice ID is required']);
    }

    global $connect, $url;

    if (empty($connect) || !($connect instanceof mysqli)) {
        generate_payment_link_json_response(['success' => false, 'message' => 'Database connection unavailable']);
    }

    if (!generate_payment_link_ensure_table($connect)) {
        generate_payment_link_json_response([
            'success' => false,
            'message' => 'Payment links table is missing. Run system update or apply admin/migrate-payment-links.sql.',
        ]);
    }

    $invoice = milestone::findById($invoice_id);
    if (!$invoice) {
        generate_payment_link_json_response(['success' => false, 'message' => 'Invoice not found']);
    }

    $projectId = (int) ($invoice->p_id ?? 0);
    if ($projectId > 0) {
        $project = class_exists('projects') ? projects::findByProjectId($projectId) : null;
        if (!$project) {
            generate_payment_link_json_response(['success' => false, 'message' => 'Unauthorized']);
        }
    }

    if ((int) $invoice->status === 1) {
        generate_payment_link_json_response(['success' => false, 'message' => 'Invoice is already paid']);
    }
    if ((int) $invoice->status === 2) {
        generate_payment_link_json_response(['success' => false, 'message' => 'Invoice is cancelled']);
    }

    $token = null;

    $checkSql = 'SELECT token, expires_at FROM invoice_payment_links WHERE invoice_id = ? AND used = 0 ORDER BY created_at DESC LIMIT 1';
    $checkStmt = $connect->prepare($checkSql);
    if (!$checkStmt) {
        generate_payment_link_json_response(['success' => false, 'message' => 'Failed to prepare payment link query']);
    }
    $checkStmt->bind_param('i', $invoice_id);
    if (!$checkStmt->execute()) {
        generate_payment_link_json_response(['success' => false, 'message' => 'Failed to load payment link']);
    }

    if (method_exists($checkStmt, 'get_result')) {
        $result = $checkStmt->get_result();
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $token = $row['token'];
            if (!empty($row['expires_at']) && strtotime($row['expires_at']) < time()) {
                $expires_at = date('Y-m-d H:i:s', strtotime('+30 days'));
                $updateSql = 'UPDATE invoice_payment_links SET expires_at = ? WHERE token = ?';
                $updateStmt = $connect->prepare($updateSql);
                if ($updateStmt) {
                    $updateStmt->bind_param('ss', $expires_at, $token);
                    $updateStmt->execute();
                }
            }
        }
    } else {
        $linkToken = null;
        $linkExpires = null;
        $checkStmt->bind_result($linkToken, $linkExpires);
        if ($checkStmt->fetch()) {
            $token = $linkToken;
            if (!empty($linkExpires) && strtotime($linkExpires) < time()) {
                $expires_at = date('Y-m-d H:i:s', strtotime('+30 days'));
                $updateSql = 'UPDATE invoice_payment_links SET expires_at = ? WHERE token = ?';
                $updateStmt = $connect->prepare($updateSql);
                if ($updateStmt) {
                    $updateStmt->bind_param('ss', $expires_at, $token);
                    $updateStmt->execute();
                }
            }
        }
    }
    $checkStmt->close();

    if ($token === null || $token === '') {
        $maxAttempts = 10;
        $attempt = 0;
        $token = '';

        while ($attempt < $maxAttempts) {
            $token = bin2hex(random_bytes(16));
            $tokenCheckSql = 'SELECT id FROM invoice_payment_links WHERE token = ? LIMIT 1';
            $tokenCheckStmt = $connect->prepare($tokenCheckSql);
            if (!$tokenCheckStmt) {
                break;
            }
            $tokenCheckStmt->bind_param('s', $token);
            $tokenCheckStmt->execute();
            $unique = true;
            if (method_exists($tokenCheckStmt, 'get_result')) {
                $tokenResult = $tokenCheckStmt->get_result();
                $unique = !$tokenResult || $tokenResult->num_rows === 0;
            } else {
                $existingId = null;
                $tokenCheckStmt->bind_result($existingId);
                $unique = !$tokenCheckStmt->fetch();
            }
            $tokenCheckStmt->close();
            if ($unique) {
                break;
            }
            $attempt++;
        }

        if ($attempt >= $maxAttempts) {
            generate_payment_link_json_response(['success' => false, 'message' => 'Failed to generate unique token']);
        }

        $expires_at = date('Y-m-d H:i:s', strtotime('+30 days'));
        $insertSql = 'INSERT INTO invoice_payment_links (invoice_id, token, expires_at, created_at) VALUES (?, ?, ?, NOW())';
        $insertStmt = $connect->prepare($insertSql);
        if (!$insertStmt) {
            generate_payment_link_json_response(['success' => false, 'message' => 'Failed to prepare payment link insert']);
        }
        $insertStmt->bind_param('iss', $invoice_id, $token, $expires_at);
        if (!$insertStmt->execute()) {
            generate_payment_link_json_response(['success' => false, 'message' => 'Failed to create payment link']);
        }
        $insertStmt->close();
    }

    $baseUrl = isset($url) ? rtrim((string) $url, '/') . '/' : '';
    if ($baseUrl === '' || $baseUrl === '/') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $script_path = dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/ajax'));
        $root_path = preg_replace('#/ajax$#', '', $script_path);
        if ($root_path === '/' || $root_path === '') {
            $root_path = '';
        }
        $baseUrl = $scheme . '://' . $host . $root_path . '/';
    }

    $payment_url = function_exists('tasksession_payment_url')
        ? tasksession_payment_url($token)
        : ($baseUrl . 'pay/' . $token);

    generate_payment_link_json_response([
        'success' => true,
        'payment_url' => $payment_url,
        'token' => $token,
    ]);
} catch (Throwable $e) {
    error_log('generate-payment-link.php: ' . $e->getMessage());
    generate_payment_link_json_response([
        'success' => false,
        'message' => 'Error generating payment link',
    ]);
}
