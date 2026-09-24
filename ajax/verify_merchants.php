<?php
// ajax/verify_merchants.php

require_once('../includes/loader_ajax.php');

// Security: Authentication check (only admins should verify payment merchants)
if (!isset($session) || !$session->isLoggedIn()) {
    http_response_code(401);
    header('Content-Type: text/html; charset=utf-8');
    echo "<span style='color:red;'>Unauthorized</span>";
    exit;
}

// Only allow admins
if (isset($_SESSION['accountStatus']) && $_SESSION['accountStatus'] != 1) {
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    echo "<span style='color:red;'>Forbidden: Admin access required</span>";
    exit;
}

header('Content-Type: text/html; charset=utf-8');

// Get merchant type from POST
$merchant = $_POST['merchant'] ?? '';
$response = '';

switch ($merchant) {
    case 'stripe':
        require_once('../payment-api/stripe-php/init.php'); // adjust path if needed
        $sk = $_POST['sk'] ?? '';
        $pk = $_POST['pk'] ?? '';
        if (!$sk || !$pk) {
            $response = "<span style='color:red;'>Please enter both Stripe keys.</span>";
            break;
        }
        try {
            \Stripe\Stripe::setApiKey($sk);
            $account = \Stripe\Account::retrieve();
            if ($account && !empty($account->id)) {
                $response = "<span style='color:green;'>Stripe connection successful! Account: " . htmlspecialchars($account->id) . "</span>";
            } else {
                $response = "<span style='color:red;'>Could not verify Stripe account. Please check your keys.</span>";
            }
        } catch (Exception $e) {
            $response = "<span style='color:red;'>Stripe Error: " . htmlspecialchars($e->getMessage()) . "</span>";
        }
        break;

    // Future: Add more merchants here (e.g., PayPal, 2Checkout)
    case 'paypal':
        $email = $_POST['email'] ?? '';
        if (!$email) {
            $response = "<span style='color:red;'>Please enter your PayPal business email.</span>";
            break;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $response = "<span style='color:red;'>Invalid email format.</span>";
            break;
        }
        // Real API verification would go here
        $response = "<span style='color:green;'>Email format looks good. (Note: This does not check if your PayPal account is active.)</span>";
        break;

    case '2checkout':
        $sid = $_POST['sid'] ?? '';
        $pk = $_POST['pk'] ?? '';
        if (!$sid || !$pk) {
            $response = "<span style='color:red;'>Please enter both Seller ID and Private Key.</span>";
            break;
        }
        // Real API verification would go here
        $response = "<span style='color:green;'>Seller ID and Private Key format look good. (Note: This does not check if your 2Checkout account is active.)</span>";
        break;

    default:
        $response = "<span style='color:red;'>Invalid or unsupported merchant.</span>";
}

echo $response; 