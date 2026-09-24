<?php
/**
 * Log Lead Compose Operations
 * Receives log data from client-side and writes to log file
 */

header('Content-Type: application/json');

// Get JSON input
$input = file_get_contents('php://input');
$logData = json_decode($input, true);

if (!$logData) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}

// Production mode: Logging disabled
// Just return success response without writing to file

echo json_encode(['success' => true]);
exit;
?>

