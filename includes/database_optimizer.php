<?php
/**
 * Database Optimizer
 * Handles automatic cleanup and optimization of security-related tables
 */

class DatabaseOptimizer {
    private $db;
    
    public function __construct() {
        global $connect;
        $this->db = $connect;
    }
    
    /**
     * Run all optimization tasks
     */
    public function runOptimization() {
        $this->cleanupLoginAttempts();
        $this->cleanupSecurityLogs();
        $this->cleanupRememberMeTokens();
        $this->optimizeTables();
        $this->updateTableStats();
    }
    
    /**
     * Clean up old login attempts
     * Keep only 7 days of data for security analysis
     */
    public function cleanupLoginAttempts() {
        // Delete failed attempts older than 7 days
        $sql = "DELETE FROM login_attempts 
                WHERE success = FALSE 
                AND attempt_time < DATE_SUB(NOW(), INTERVAL 7 DAY)";
        $this->db->query($sql);
        
        // Delete successful attempts older than 30 days
        $sql = "DELETE FROM login_attempts 
                WHERE success = TRUE 
                AND attempt_time < DATE_SUB(NOW(), INTERVAL 30 DAY)";
        $this->db->query($sql);
        
        // Keep only last 1000 records per user for successful logins
        $sql = "DELETE la1 FROM login_attempts la1
                INNER JOIN (
                    SELECT user_id, id
                    FROM login_attempts 
                    WHERE success = TRUE 
                    AND user_id IS NOT NULL
                    ORDER BY attempt_time DESC
                ) la2 ON la1.user_id = la2.user_id
                WHERE la1.id < la2.id
                AND la1.success = TRUE
                AND la1.user_id IS NOT NULL";
        $this->db->query($sql);
    }
    
    /**
     * Clean up old security logs
     * Keep only 30 days of data
     */
    public function cleanupSecurityLogs() {
        $sql = "DELETE FROM security_logs 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)";
        $this->db->query($sql);
    }
    
    /**
     * Clean up expired remember me tokens
     */
    public function cleanupRememberMeTokens() {
        $sql = "DELETE FROM remember_me_tokens 
                WHERE expires_at < NOW() 
                OR is_active = FALSE";
        $this->db->query($sql);
        
        // Keep only 5 active tokens per user
        $sql = "DELETE t1 FROM remember_me_tokens t1
                INNER JOIN (
                    SELECT user_id, id
                    FROM remember_me_tokens 
                    WHERE is_active = TRUE 
                    AND expires_at > NOW()
                    ORDER BY created_at DESC
                ) t2 ON t1.user_id = t2.user_id
                WHERE t1.id < t2.id
                AND t1.is_active = TRUE
                AND t1.expires_at > NOW()";
        $this->db->query($sql);
    }
    
    /**
     * Optimize tables for better performance
     */
    public function optimizeTables() {
        $tables = ['login_attempts', 'security_logs', 'remember_me_tokens'];
        
        foreach ($tables as $table) {
            $sql = "OPTIMIZE TABLE $table";
            $this->db->query($sql);
        }
    }
    
    /**
     * Update table statistics
     */
    public function updateTableStats() {
        $tables = ['login_attempts', 'security_logs', 'remember_me_tokens'];
        
        foreach ($tables as $table) {
            $sql = "ANALYZE TABLE $table";
            $this->db->query($sql);
        }
    }
    
    /**
     * Get database statistics
     */
    public function getDatabaseStats() {
        $stats = [];
        $tables = ['login_attempts', 'security_logs', 'remember_me_tokens'];
        
        foreach ($tables as $table) {
            // Check if table exists
            $check_sql = "SHOW TABLES LIKE '$table'";
            $check_result = $this->db->query($check_sql);
            
            if ($check_result && $check_result->num_rows > 0) {
                // Get row count
                $count_sql = "SELECT COUNT(*) as count FROM $table";
                $count_result = $this->db->query($count_sql);
                $count_row = $count_result->fetch_assoc();
                $rows = $count_row['count'];
                
                // Get table size
                $size_sql = "SELECT 
                    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
                    FROM information_schema.tables 
                    WHERE table_schema = DATABASE() 
                    AND table_name = '$table'";
                $size_result = $this->db->query($size_sql);
                $size_row = $size_result->fetch_assoc();
                $size_mb = $size_row['size_mb'] ?? 0;
                
                $stats[$table] = [
                    'rows' => $rows,
                    'size_mb' => $size_mb
                ];
            } else {
                $stats[$table] = [
                    'rows' => 0,
                    'size_mb' => 0
                ];
            }
        }
        
        return $stats;
    }
    
    /**
     * Get cleanup recommendations
     */
    public function getCleanupRecommendations() {
        $recommendations = [];
        $stats = $this->getDatabaseStats();
        
        foreach ($stats as $table => $data) {
            if ($data['rows'] > 1000) {
                $recommendations[] = "Consider cleaning up old records from $table table (currently {$data['rows']} records)";
            }
            
            if ($data['size_mb'] > 10) {
                $recommendations[] = "Table $table is large ({$data['size_mb']} MB). Consider optimization.";
            }
        }
        
        if (empty($recommendations)) {
            $recommendations[] = "Database is well-optimized. No immediate cleanup needed.";
        }
        
        return $recommendations;
    }
    
    /**
     * Create MySQL event for scheduled cleanup
     */
    public function createCleanupEvent() {
        // Enable event scheduler
        $this->enableEventScheduler();
        
        // Create the cleanup event
        $sql = "CREATE EVENT IF NOT EXISTS daily_security_cleanup
                ON SCHEDULE EVERY 1 DAY
                STARTS CURRENT_TIMESTAMP
                DO
                BEGIN
                    -- Clean up old login attempts
                    DELETE FROM login_attempts 
                    WHERE success = FALSE 
                    AND attempt_time < DATE_SUB(NOW(), INTERVAL 7 DAY);
                    
                    DELETE FROM login_attempts 
                    WHERE success = TRUE 
                    AND attempt_time < DATE_SUB(NOW(), INTERVAL 30 DAY);
                    
                    -- Clean up old security logs
                    DELETE FROM security_logs 
                    WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
                    
                    -- Clean up expired remember me tokens
                    DELETE FROM remember_me_tokens 
                    WHERE expires_at < NOW() 
                    OR is_active = FALSE;
                    
                    -- Optimize tables weekly
                    IF DAYOFWEEK(NOW()) = 1 THEN
                        OPTIMIZE TABLE login_attempts, security_logs, remember_me_tokens;
                    END IF;
                END";
        
        return $this->db->query($sql);
    }
    
    /**
     * Enable MySQL event scheduler
     */
    public function enableEventScheduler() {
        $sql = "SET GLOBAL event_scheduler = ON";
        return $this->db->query($sql);
    }
}
?> 