<?php
/**
 * Activity Logger
 * Handles logging of user activities like password changes, login attempts, etc.
 */

class ActivityLogger {
    
    /**
     * Log password change activity
     */
    public static function logPasswordChange($userId, $changedBy = null) {
        global $connect;
        
        // Ensure security_logs table exists
        self::createSecurityLogsTable();
        
        $sql = "INSERT INTO security_logs (user_id, event, details, ip_address, user_agent) 
                VALUES (?, 'password.change', ?, ?, ?)";
        $stmt = $connect->prepare($sql);
        if (!$stmt) {
            error_log('[ActivityLogger::logPasswordChange] prepare failed: ' . $connect->error);
            return;
        }
        
        $details = "Password changed";
        if ($changedBy && $changedBy != $userId) {
            // Attempt to fetch the editor's display name; avoid exposing numeric IDs in logs
            if (class_exists('User')) {
                $editor = User::findById($changedBy);
                if ($editor && !empty($editor->firstName)) {
                    $details .= " by " . $editor->firstName;
                } else {
                    $details .= " by user";
                }
            } else {
                $details .= " by user";
            }
        } else if ($changedBy && $changedBy == $userId) {
            $details .= " by account owner";
        }
        
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $stmt->bind_param("isss", $userId, $details, $ipAddress, $userAgent);
        $stmt->execute();
    }
    
    /**
     * Log profile update activity
     */
    public static function logProfileUpdate($userId, $fields = []) {
        global $connect;
        
        // Ensure security_logs table exists
        self::createSecurityLogsTable();
        
        $sql = "INSERT INTO security_logs (user_id, event, details, ip_address, user_agent) 
                VALUES (?, 'profile.update', ?, ?, ?)";
        $stmt = $connect->prepare($sql);
        if (!$stmt) {
            error_log('[ActivityLogger::logProfileUpdate] prepare failed: ' . $connect->error);
            return;
        }
        
        $details = "Profile updated: " . implode(', ', $fields);
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $stmt->bind_param("isss", $userId, $details, $ipAddress, $userAgent);
        $stmt->execute();
    }
    
    
    /**
     * Check for inactive sessions and log them out
     * This should be called periodically to clean up sessions where users closed browser
     */
    public static function checkInactiveSessions() {
        global $connect;
        
        // Ensure security_logs table exists
        self::createSecurityLogsTable();
        
        // Find login events without corresponding logout events older than 24 hours
        $timeoutHours = 24; // Sessions timeout after 24 hours of inactivity
        $timeoutTime = date('Y-m-d H:i:s', strtotime("-{$timeoutHours} hours"));
        
        $sql = "SELECT la1.id, la1.user_id, la1.attempt_time 
                FROM login_attempts la1 
                WHERE la1.success = 1 
                AND la1.type = 'login' 
                AND la1.attempt_time < ?
                AND NOT EXISTS (
                    SELECT 1 FROM login_attempts la2 
                    WHERE la2.user_id = la1.user_id 
                    AND la2.type = 'logout' 
                    AND la2.attempt_time > la1.attempt_time
                )";
        
        $stmt = $connect->prepare($sql);
        $stmt->bind_param("s", $timeoutTime);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $uid = (int)$row['user_id'];
            $timeoutEmail = '';
            $emailStmt = $connect->prepare("SELECT email FROM users WHERE id = ? LIMIT 1");
            if ($emailStmt) {
                $emailStmt->bind_param("i", $uid);
                $emailStmt->execute();
                $emailRes = $emailStmt->get_result();
                if ($emailRes && ($emailRow = $emailRes->fetch_assoc())) {
                    $timeoutEmail = trim((string)($emailRow['email'] ?? ''));
                }
            }
            $logoutSql = "INSERT INTO login_attempts (user_id, email, success, type, ip_address, user_agent, attempt_time) 
                         VALUES (?, ?, 1, 'logout', 'System', 'Session Timeout', NOW())";
            $logoutStmt = $connect->prepare($logoutSql);
            $logoutStmt->bind_param("is", $uid, $timeoutEmail);
            $logoutStmt->execute();
        }
    }
    
    /**
     * Create security_logs table if it doesn't exist
     */
    private static function createSecurityLogsTable() {
        global $connect;
        
        $sql = "CREATE TABLE IF NOT EXISTS security_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            event VARCHAR(255) NOT NULL,
            details TEXT,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_event (event),
            INDEX idx_created_at (created_at)
        )";
        
        $connect->query($sql);
    }
    
}
?>
