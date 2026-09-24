<?php
/**
 * Secure Remember Me Token System
 */

require_once('database.php');

class RememberMe {
    private $db;
    private $tokenLength = 64;
    private $tokenExpiry = 30; // days
    
    public function __construct() {
        global $connect;
        $this->db = $connect;
    }
    
    /**
     * Create the remember_me_tokens table if it doesn't exist
     */
    private function createRememberMeTable() {
        static $ready = false;
        if ($ready || !$this->db) {
            return;
        }
        $exists = @$this->db->query("SHOW TABLES LIKE 'remember_me_tokens'");
        if ($exists && $exists->num_rows > 0) {
            $ready = true;
            return;
        }
        $sql = "CREATE TABLE IF NOT EXISTS remember_me_tokens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token VARCHAR(255) NOT NULL UNIQUE,
            expires_at TIMESTAMP NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            user_agent TEXT,
            ip_address VARCHAR(45),
            is_active BOOLEAN DEFAULT TRUE,
            INDEX idx_token (token),
            INDEX idx_user_id (user_id),
            INDEX idx_expires_at (expires_at)
        )";
        
        $this->db->query($sql);
        $ready = true;
    }
    
    /**
     * Generate a secure random token
     */
    private function generateToken() {
        return bin2hex(random_bytes($this->tokenLength / 2));
    }
    
    /**
     * Create a remember me token for a user
     */
    public function createToken($userId) {
        $this->createRememberMeTable();
        $token = $this->generateToken();
        $tokenHash = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s', strtotime("+{$this->tokenExpiry} days"));
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
        
        $this->cleanupOldTokens($userId);
        
        $sql = "INSERT INTO remember_me_tokens (user_id, token, expires_at, user_agent, ip_address) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("issss", $userId, $tokenHash, $expiresAt, $userAgent, $ipAddress);
        
        if ($stmt->execute()) {
            return $token;
        }
        
        return false;
    }
    
    /**
     * Validate a remember me token
     */
    public function validateToken($token) {
        if (empty($token)) {
            return false;
        }
        $this->createRememberMeTable();
        $token = (string) $token;
        $tokenHash = hash('sha256', $token);

        $userId = $this->lookupActiveTokenUser($tokenHash);
        if ($userId) {
            return $userId;
        }

        $legacyId = $this->lookupActiveTokenUser($token);
        if ($legacyId) {
            $upgrade = $this->db->prepare("UPDATE remember_me_tokens SET token = ? WHERE token = ? AND is_active = TRUE");
            if ($upgrade) {
                $upgrade->bind_param("ss", $tokenHash, $token);
                $upgrade->execute();
            }
            return $legacyId;
        }

        return false;
    }

    private function lookupActiveTokenUser($storedToken) {
        $sql = "SELECT user_id FROM remember_me_tokens 
                WHERE token = ? AND is_active = TRUE AND expires_at > NOW() LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("s", $storedToken);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return $row['user_id'];
        }
        return false;
    }
    
    /**
     * Delete a specific token
     */
    public function deleteToken($token) {
        $this->createRememberMeTable();
        $token = (string) $token;
        $tokenHash = hash('sha256', $token);
        $sql = "UPDATE remember_me_tokens SET is_active = FALSE WHERE token = ? OR token = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("ss", $tokenHash, $token);
        return $stmt->execute();
    }
    
    /**
     * Delete all tokens for a user
     */
    public function deleteAllUserTokens($userId) {
        $this->createRememberMeTable();
        $sql = "UPDATE remember_me_tokens SET is_active = FALSE WHERE user_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $userId);
        return $stmt->execute();
    }
    
    /**
     * Clean up expired tokens
     */
    public function cleanupExpiredTokens() {
        $sql = "UPDATE remember_me_tokens SET is_active = FALSE WHERE expires_at < NOW()";
        return $this->db->query($sql);
    }
    
    /**
     * Clean up old tokens for a specific user (keep only the most recent 3)
     */
    private function cleanupOldTokens($userId) {
        $sql = "UPDATE remember_me_tokens SET is_active = FALSE 
                WHERE user_id = ? AND id NOT IN (
                    SELECT id FROM (
                        SELECT id FROM remember_me_tokens 
                        WHERE user_id = ? AND is_active = TRUE 
                        ORDER BY created_at DESC 
                        LIMIT 3
                    ) as recent_tokens
                )";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("ii", $userId, $userId);
        return $stmt->execute();
    }
    
    /**
     * Get active token count for a user
     */
    public function getActiveTokenCount($userId) {
        $this->createRememberMeTable();
        $sql = "SELECT COUNT(*) as count FROM remember_me_tokens 
                WHERE user_id = ? AND is_active = TRUE AND expires_at > NOW()";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return $row['count'];
    }
    
    /**
     * Get all active sessions for a user
     */
    public function getActiveSessions($userId) {
        $sql = "SELECT token, user_agent, ip_address, created_at, expires_at 
                FROM remember_me_tokens 
                WHERE user_id = ? AND is_active = TRUE AND expires_at > NOW() 
                ORDER BY created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $sessions = [];
        while ($row = $result->fetch_assoc()) {
            $sessions[] = $row;
        }
        
        return $sessions;
    }
}
?> 