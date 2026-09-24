<?php
/**
 * includes/load-project-sidebar-data.php
 *
 * Loads all data required to render the project sidebar:
 * - Project info
 * - Task counts and days left
 * - Milestone summaries
 * - Client and staff lists
 *
 * Requires:
 *   - $projectId to be set (int)
 *   - $url and $database globals
 *   - projects, tasks, milestone, User classes
 */

// Ensure projectId is defined and valid
if (!isset($projectId) || !is_numeric($projectId)) {
    // No valid project ID, bail out or redirect
    return;
}

// Make sure we have access to the database object
global $database, $url;

// 1. Load project (skip if caller already loaded it)
if (!isset($project) || !is_object($project) || empty($project->p_id)) {
    $project = projects::findByProjectId((int)$projectId);
}
if (!$project) {
    redirectTo($url . "admin/projects.php");
}

// Project title (used in the page header or tabs)
$projTitle = $project->project_title;

// 2. Task counts
// Total tasks
$taskSql = "SELECT COUNT(*) AS task_count FROM tasks WHERE project_id = {$projectId}";
$taskRes = $database->query($taskSql);
$taskCount = 0;
if ($taskRes && $row = $database->fetchArray($taskRes)) {
    $taskCount = (int)$row['task_count'];
}

// Completed tasks
$completedSql = 
    "SELECT COUNT(*) AS completed_count
     FROM tasks
     WHERE project_id = {$projectId}
       AND status = 'done'";
$compRes = $database->query($completedSql);
$completedTaskCount = 0;
if ($compRes && $row = $database->fetchArray($compRes)) {
    $completedTaskCount = (int)$row['completed_count'];
}

// 3. Days left across open tasks (unique dates)
$today = date('Y-m-d');
$daysSql = 
    "SELECT DISTINCT due_date
     FROM tasks
     WHERE project_id = {$projectId}
       AND status != 'done'
       AND due_date IS NOT NULL
       AND due_date >= '$today'";
$daysRes = $database->query($daysSql);
$totalDaysLeft = 0;
if ($daysRes && $database->numRows($daysRes) > 0) {
    while ($d = $database->fetchArray($daysRes)) {
        $due = $d['due_date'];
        $diff = (strtotime($due) - strtotime($today)) / (60 * 60 * 24);
        $totalDaysLeft += ceil($diff);
    }
}

// 4. Milestone summary (net totals after discount & tax — same as invoice cards/PDF)
require_once __DIR__ . '/milestone_invoice_total.php';

$activeMilestonesSql =
    "SELECT * FROM milestones
     WHERE p_id = {$projectId}
       AND status IN (0, 1)
     ORDER BY id ASC";
$activeMilestones = milestone::findBySql($activeMilestonesSql);

$totalMilestones = is_array($activeMilestones) ? count($activeMilestones) : 0;
$totalBudget = 0;
$paidMilestones = 0;
$unpaidMilestones = 0;
$paidAmount = 0;
$unpaidAmount = 0;

if (!empty($activeMilestones)) {
    foreach ($activeMilestones as $m) {
        $net = milestone_calculate_invoice_total($m);
        $totalBudget += $net;
        if ((int) $m->status === 1) {
            $paidMilestones++;
            $paidAmount += $net;
        } else {
            $unpaidMilestones++;
            $unpaidAmount += $net;
        }
    }
}

// Invoice count for this project (all milestones)
$invoiceCountSql = "SELECT COUNT(*) AS invoice_count FROM milestones WHERE p_id = {$projectId}";
$invoiceCountRes = $database->query($invoiceCountSql);
$invoiceCount = 0;
if ($invoiceCountRes && $row = $database->fetchArray($invoiceCountRes)) {
    $invoiceCount = (int)$row['invoice_count'];
}

// 5. Client and staff members — batch-load users (avoid N+1 findById)
$allClients = [];
$mainClient = null;
$staffMembers = [];
$userIdsToLoad = [];

if ($project->main_client_id) {
    $userIdsToLoad[] = (int) $project->main_client_id;
} elseif ($project->c_id) {
    $userIdsToLoad[] = (int) $project->c_id;
}

if (!empty($project->c_ids)) {
    foreach (array_filter(explode(',', $project->c_ids)) as $clientId) {
        $userIdsToLoad[] = (int) $clientId;
    }
}

if (!empty($project->s_ids)) {
    foreach (explode(',', $project->s_ids) as $sid) {
        $sid = (int) $sid;
        if ($sid > 0 && $sid !== (int) $project->c_id) {
            $userIdsToLoad[] = $sid;
        }
    }
}

$userIdsToLoad = array_values(array_unique(array_filter($userIdsToLoad)));
$userMap = [];
if (!empty($userIdsToLoad)) {
    $idsSql = implode(',', $userIdsToLoad);
    $loadedUsers = User::findBySql("SELECT * FROM users WHERE id IN ($idsSql)");
    if (is_array($loadedUsers)) {
        foreach ($loadedUsers as $loadedUser) {
            $userMap[(int) $loadedUser->id] = $loadedUser;
        }
    }
}

if ($project->main_client_id && isset($userMap[(int) $project->main_client_id])) {
    $mainClient = $userMap[(int) $project->main_client_id];
    $allClients[] = $mainClient;
} elseif ($project->c_id && isset($userMap[(int) $project->c_id])) {
    $mainClient = $userMap[(int) $project->c_id];
    $allClients[] = $mainClient;
}

if (!empty($project->c_ids)) {
    foreach (array_filter(explode(',', $project->c_ids)) as $clientId) {
        $clientId = (int) $clientId;
        if ($mainClient && $clientId === (int) $mainClient->id) {
            continue;
        }
        if (isset($userMap[$clientId])) {
            $allClients[] = $userMap[$clientId];
        }
    }
}

$client = $mainClient;

if (!empty($project->s_ids)) {
    foreach (explode(',', $project->s_ids) as $sid) {
        $sid = (int) $sid;
        if ($sid > 0 && $sid !== (int) $project->c_id && isset($userMap[$sid])) {
            $staffMembers[] = $userMap[$sid];
        }
    }
}

// All variables now available for project-sidebar.php:
// $projTitle, $taskCount, $completedTaskCount, $totalDaysLeft,
// $totalMilestones, $totalBudget, $paidMilestones, $paidAmount,
// $unpaidMilestones, $unpaidAmount, $client, $allClients, $staffMembers
?>
