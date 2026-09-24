<?php
/**
 * Database Cleanup Cron Job
 * 
 * This script should be run daily via cron job:
 * 0 2 * * * /usr/bin/php /path/to/your/project/cron_cleanup.php
 * 
 * Or on Windows Task Scheduler:
 * php.exe "D:\XAMPP\htdocs\comon\cron_cleanup.php"
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/cron_errors.log');

// Start timing
$startTime = microtime(true);

// Include necessary files
require_once('initialize.php');
require_once('database_optimizer.php');

// Create logs directory if it doesn't exist
if (!is_dir(__DIR__ . '/../logs')) {
    mkdir(__DIR__ . '/../logs', 0755, true);
}

// Log function
function logMessage($message) {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message" . PHP_EOL;
    
    // Write to log file
    file_put_contents(__DIR__ . '/../logs/cleanup.log', $logMessage, FILE_APPEND | LOCK_EX);
    
    // Also output to console if running from command line
    if (php_sapi_name() === 'cli') {
        echo $logMessage;
    }
}

try {
    logMessage("Starting database cleanup process...");
    
    // Initialize optimizer
    $optimizer = new DatabaseOptimizer();
    
    // Get initial stats
    $initialStats = $optimizer->getDatabaseStats();
    $initialTotal = 0;
    foreach ($initialStats as $table) {
        $initialTotal += $table['rows'] ?? 0;
    }
    
    logMessage("Initial total records: $initialTotal");
    
    // Run optimization
    $optimizer->runOptimization();
    
    // Get final stats
    $finalStats = $optimizer->getDatabaseStats();
    $finalTotal = 0;
    foreach ($finalStats as $table) {
        $finalTotal += $table['rows'] ?? 0;
    }
    
    $recordsRemoved = $initialTotal - $finalTotal;
    
    logMessage("Cleanup completed successfully!");
    logMessage("Records removed: $recordsRemoved");
    logMessage("Final total records: $finalTotal");
    
    // Calculate execution time
    $executionTime = round(microtime(true) - $startTime, 2);
    logMessage("Execution time: {$executionTime} seconds");
    
    // Log table-specific details
    foreach ($finalStats as $tableName => $stats) {
        $size = number_format($stats['size_mb'] ?? 0, 2);
        $rows = number_format($stats['rows'] ?? 0);
        logMessage("Table '$tableName': $rows records, {$size} MB");
    }
    
    logMessage("Database cleanup process completed successfully.");
    
} catch (Exception $e) {
    logMessage("ERROR: " . $e->getMessage());
    logMessage("Stack trace: " . $e->getTraceAsString());
    exit(1);
}

logMessage("=== Cleanup Process End ===" . PHP_EOL);
?> 