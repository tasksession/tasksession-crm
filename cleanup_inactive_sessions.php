<?php
/**
 * Cleanup Inactive Sessions Script
 * 
 * This script should be run periodically (e.g., every hour via cron job)
 * to automatically log out users who closed their browser without logging out
 * 
 * Usage: php cleanup_inactive_sessions.php
 * Cron: 0 * * * * /usr/bin/php /path/to/cleanup_inactive_sessions.php
 */

// Set time limit for long-running script
set_time_limit(300); // 5 minutes

// Include necessary files
require_once('includes/lib-initialize.php');
require_once('includes/activity_logger.php');

echo "Starting inactive session cleanup...\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n";

// Check for inactive sessions
ActivityLogger::checkInactiveSessions();

echo "Inactive session cleanup completed.\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n";
?>
