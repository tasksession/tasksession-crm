<?php
/*
 ================================================================================
   Task Session – Project Management System
   File    : includes/save_column_name.php
   Purpose : Centralized handler for saving and updating custom column names for project task boards
 ================================================================================
 */
require_once("lib-initialize.php");

// Auth check
if (!$session->isLoggedIn()) {
    echo json_encode(['status' => 'error', 'error' => 'Not logged in']);
    exit;
}

require_once __DIR__ . '/permissions.php';
global $connect;
ensure_user_permissions($connect);

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

$columnKey = isset($input['column_key']) ? $input['column_key'] : '';
$columnName = isset($input['column_name']) ? $input['column_name'] : '';
$projectId = isset($input['project_id']) ? (int)$input['project_id'] : -1; // Use -1 as default to distinguish from valid 0

// Check parameters - allow projectId=0 for "All Tasks" view
if (empty($columnKey) || empty($columnName) || $projectId < 0) {
    echo json_encode(['status' => 'error', 'error' => 'Missing required parameters']);
    exit;
}

// Valid column keys
$validKeys = ['todo', 'inprogress', 'review', 'done'];
if (!in_array($columnKey, $validKeys)) {
    echo json_encode(['status' => 'error', 'error' => 'Invalid column key']);
    exit;
}

// Check user type and permissions (2 = client, 3 = staff — match session accountStatus)
$userType = isset($_SESSION['accountStatus']) ? (int) $_SESSION['accountStatus'] : 0;
$userId = isset($_SESSION['userId']) ? (int) $_SESSION['userId'] : 0;

require_once __DIR__ . '/project_permission.php';

$canEditColumns = false;

switch ($userType) {
    case 1:
        $canEditColumns = true;
        break;
    case 2:
        $canEditColumns = ProjectPermission::isProjectClient($userId, $projectId);
        break;
    case 3:
        $canEditColumns = ProjectPermission::canManageProject($userId, $projectId);
        break;
    default:
        $canEditColumns = false;
}

if (!$canEditColumns) {
    echo json_encode(['status' => 'error', 'error' => 'Not authorized to edit column names']);
    exit;
}

// Check if project exists
require_once("projects.php");
$projectExists = true;

// Only check for project existence if projectId > 0
if ($projectId > 0) {
    $project = projects::findByProjectId($projectId);
    if (!$project) {
        $projectExists = false;
        echo json_encode(['status' => 'error', 'error' => 'Project not found']);
        exit;
    }
}

// We'll use a simple approach - store in a project_columns table
global $database;

// Check if the table exists, create it if not
$checkTable = "SHOW TABLES LIKE 'project_columns'";
$result = $database->query($checkTable);
if ($database->numRows($result) == 0) {
    // Create the table
    $createTable = "CREATE TABLE `project_columns` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `project_id` int(11) NOT NULL,
        `column_key` varchar(20) NOT NULL,
        `custom_name` varchar(50) NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `project_column` (`project_id`, `column_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    $database->query($createTable);
}

// Check if record exists using prepared statement
global $connect;
$checkStmt = $connect->prepare("SELECT id FROM project_columns WHERE project_id = ? AND column_key = ?");
$checkStmt->bind_param("is", $projectId, $columnKey);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();

// Begin transaction to ensure consistency
$connect->query("START TRANSACTION");

try {
    $row = $checkResult->fetch_assoc();
    if ($row) {
        // Update existing record
        $updateStmt = $connect->prepare("UPDATE project_columns SET custom_name = ? WHERE id = ?");
        $updateStmt->bind_param("si", $columnName, $row['id']);
        $updateStmt->execute();
        $updateStmt->close();
    } else {
        // Insert new record
        $insertStmt = $connect->prepare("INSERT INTO project_columns (project_id, column_key, custom_name) VALUES (?, ?, ?)");
        $insertStmt->bind_param("iss", $projectId, $columnKey, $columnName);
        $insertStmt->execute();
        $insertStmt->close();
    }
    $checkStmt->close();
    
    // Special handling for the All Tasks view (project_id = 0)
    // If updating a project column (project_id > 0), update the global setting too
    // If updating the All Tasks view directly, update the global setting only
    
    if ($projectId > 0) {
        // When updating a project-specific column, check if we should update the global setting too
        // Only update global if it matches the default name or is empty
        
        // Get the current global column name if it exists
        $globalCheckStmt = $connect->prepare("SELECT custom_name FROM project_columns WHERE project_id = 0 AND column_key = ?");
        $globalCheckStmt->bind_param("s", $columnKey);
        $globalCheckStmt->execute();
        $globalResult = $globalCheckStmt->get_result();
        
        if ($globalResult->num_rows > 0) {
            // Update the global column name if it exists
            $updateGlobalStmt = $connect->prepare("UPDATE project_columns SET custom_name = ? WHERE project_id = 0 AND column_key = ?");
            $updateGlobalStmt->bind_param("ss", $columnName, $columnKey);
            $updateGlobalStmt->execute();
            $updateGlobalStmt->close();
        } else {
            // Create global setting if it doesn't exist
            $insertGlobalStmt = $connect->prepare("INSERT INTO project_columns (project_id, column_key, custom_name) VALUES (0, ?, ?)");
            $insertGlobalStmt->bind_param("ss", $columnKey, $columnName);
            $insertGlobalStmt->execute();
            $insertGlobalStmt->close();
        }
        $globalCheckStmt->close();
    }
    
    // Commit the transaction
    $connect->query("COMMIT");
    
    // Return success
    echo json_encode(['status' => 'ok']);
} catch (Exception $e) {
    // Rollback on error
    $connect->query("ROLLBACK");
    echo json_encode(['status' => 'error', 'error' => 'Database error occurred']);
} 