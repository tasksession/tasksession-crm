<?php

require_once('database.php');

class PasswordResetToken extends DatabaseObject {
    
    protected static $tblName = "password_reset_tokens";
    protected static $tblFields = array('id', 'user_id', 'token', 'expires_at', 'used', 'created_at');
    
    public $id;
    public $user_id;
    public $token;
    public $expires_at;
    public $used;
    public $created_at;

    public static function hashToken($token) {
        return 'sha256:' . hash('sha256', (string) $token);
    }
    
    // Generate a secure random token
    public static function generateToken($length = 64) {
        return bin2hex(random_bytes($length / 2));
    }
    
    // Create a new reset token for a user
    public static function createToken($userId) {
        global $connect;

        if (function_exists('auth_security_ensure_schema')) {
            auth_security_ensure_schema($connect);
        }
        
        // First, invalidate any existing tokens for this user
        self::invalidateUserTokens($userId);
        
        // Generate a new secure token
        $token = self::generateToken();
        $stored = self::hashToken($token);
        
        // Set expiration time (1 hour from now)
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        $sql = "INSERT INTO password_reset_tokens (user_id, token, expires_at, used, created_at) 
                VALUES (?, ?, ?, 0, NOW())";
        
        $stmt = $connect->prepare($sql);
        $stmt->bind_param("iss", $userId, $stored, $expiresAt);
        
        if ($stmt->execute()) {
            return $token;
        }
        
        return false;
    }
    
    // Validate a token (hashed lookup only — no plaintext legacy)
    public static function validateToken($token) {
        global $connect;

        $token = trim((string) $token);
        if ($token === '') {
            return false;
        }
        $stored = self::hashToken($token);
        
        $sql = "SELECT * FROM password_reset_tokens 
                WHERE token = ? AND used = 0 AND expires_at > NOW() 
                ORDER BY created_at DESC LIMIT 1";
        
        $stmt = $connect->prepare($sql);
        $stmt->bind_param("s", $stored);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            return $result->fetch_assoc();
        }
        
        return false;
    }
    
    // Mark a token as used
    public static function markTokenAsUsed($token) {
        global $connect;
        
        $stored = self::hashToken($token);
        $sql = "UPDATE password_reset_tokens SET used = 1 WHERE token = ?";
        $stmt = $connect->prepare($sql);
        $stmt->bind_param("s", $stored);
        
        return $stmt->execute();
    }
    
    // Invalidate all tokens for a specific user
    public static function invalidateUserTokens($userId) {
        global $connect;
        
        $sql = "UPDATE password_reset_tokens SET used = 1 WHERE user_id = ?";
        $stmt = $connect->prepare($sql);
        $stmt->bind_param("i", $userId);
        
        return $stmt->execute();
    }
    
    // Clean up expired tokens (can be called by a cron job)
    public static function cleanupExpiredTokens() {
        global $connect;
        
        $sql = "DELETE FROM password_reset_tokens WHERE expires_at < NOW() OR used = 1";
        return $connect->query($sql);
    }
    
    // Get user by token
    public static function getUserByToken($token) {
        $tokenData = self::validateToken($token);
        
        if ($tokenData) {
            return User::findById($tokenData['user_id']);
        }
        
        return false;
    }
}
