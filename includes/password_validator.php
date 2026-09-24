<?php
/**
 * Password Strength Validator
 * Ensures users create strong, secure passwords
 */

class PasswordValidator {
    private $minLength = 8;
    private $requireUppercase = true;
    private $requireLowercase = true;
    private $requireNumbers = true;
    private $requireSpecialChars = true;
    private $commonPasswords = [
        'password', '123456', '123456789', 'qwerty', 'abc123', 'password123',
        'admin', 'letmein', 'welcome', 'monkey', 'dragon', 'master', 'hello',
        'freedom', 'whatever', 'qazwsx', 'trustno1', 'jordan', 'harley',
        'ranger', 'iwantu', 'jennifer', 'hunter', 'buster', 'soccer',
        'baseball', 'tiger', 'charlie', 'andrew', 'michelle', 'love',
        'sunshine', 'jessica', 'asshole', '696969', 'amanda', 'access',
        'yankees', '987654321', 'dallas', 'austin', 'thunder', 'taylor',
        'matrix', 'mobilemail', 'mom', 'monitor', 'monitoring', 'montana',
        'moon', 'moscow'
    ];
    
    public function __construct($options = []) {
        if (isset($options['minLength'])) {
            $this->minLength = $options['minLength'];
        }
        if (isset($options['requireUppercase'])) {
            $this->requireUppercase = $options['requireUppercase'];
        }
        if (isset($options['requireLowercase'])) {
            $this->requireLowercase = $options['requireLowercase'];
        }
        if (isset($options['requireNumbers'])) {
            $this->requireNumbers = $options['requireNumbers'];
        }
        if (isset($options['requireSpecialChars'])) {
            $this->requireSpecialChars = $options['requireSpecialChars'];
        }
    }
    
    /**
     * Validate password strength
     */
    public function validate($password) {
        $errors = [];
        $score = 0;
        
        // Check minimum length
        if (strlen($password) < $this->minLength) {
            $errors[] = "Password must be at least {$this->minLength} characters long.";
        } else {
            $score += 1;
        }
        
        // Check for uppercase letters
        if ($this->requireUppercase && !preg_match('/[A-Z]/', $password)) {
            $errors[] = "Password must contain at least one uppercase letter.";
        } else {
            $score += 1;
        }
        
        // Check for lowercase letters
        if ($this->requireLowercase && !preg_match('/[a-z]/', $password)) {
            $errors[] = "Password must contain at least one lowercase letter.";
        } else {
            $score += 1;
        }
        
        // Check for numbers
        if ($this->requireNumbers && !preg_match('/[0-9]/', $password)) {
            $errors[] = "Password must contain at least one number.";
        } else {
            $score += 1;
        }
        
        // Check for special characters
        if ($this->requireSpecialChars && !preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = "Password must contain at least one special character.";
        } else {
            $score += 1;
        }
        
        // Check for common passwords
        if (in_array(strtolower($password), $this->commonPasswords)) {
            $errors[] = "Password is too common. Please choose a more unique password.";
        } else {
            $score += 1;
        }
        
        // Check for sequential characters
        if ($this->hasSequentialChars($password)) {
            $errors[] = "Password contains sequential characters (e.g., 123, abc).";
        } else {
            $score += 1;
        }
        
        // Check for repeated characters
        if ($this->hasRepeatedChars($password)) {
            $errors[] = "Password contains too many repeated characters.";
        } else {
            $score += 1;
        }
        
        // Calculate strength level
        $strength = $this->calculateStrength($score, strlen($password));
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'score' => $score,
            'strength' => $strength,
            'suggestions' => $this->getSuggestions($password)
        ];
    }
    
    /**
     * Check for sequential characters
     */
    private function hasSequentialChars($password) {
        $length = strlen($password);
        for ($i = 0; $i < $length - 2; $i++) {
            $char1 = ord($password[$i]);
            $char2 = ord($password[$i + 1]);
            $char3 = ord($password[$i + 2]);
            
            if (($char2 == $char1 + 1 && $char3 == $char2 + 1) ||
                ($char2 == $char1 - 1 && $char3 == $char2 - 1)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Check for repeated characters
     */
    private function hasRepeatedChars($password) {
        $length = strlen($password);
        for ($i = 0; $i < $length - 2; $i++) {
            if ($password[$i] == $password[$i + 1] && $password[$i] == $password[$i + 2]) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Calculate password strength
     */
    private function calculateStrength($score, $length) {
        $maxScore = 7; // Maximum possible score
        $percentage = ($score / $maxScore) * 100;
        
        if ($percentage >= 85) {
            return 'very_strong';
        } elseif ($percentage >= 70) {
            return 'strong';
        } elseif ($percentage >= 50) {
            return 'moderate';
        } elseif ($percentage >= 30) {
            return 'weak';
        } else {
            return 'very_weak';
        }
    }
    
    /**
     * Get password suggestions
     */
    private function getSuggestions($password) {
        $suggestions = [];
        
        if (strlen($password) < $this->minLength) {
            $suggestions[] = "Make your password longer (at least {$this->minLength} characters).";
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            $suggestions[] = "Add uppercase letters to make your password stronger.";
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            $suggestions[] = "Add lowercase letters to make your password stronger.";
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            $suggestions[] = "Add numbers to make your password stronger.";
        }
        
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $suggestions[] = "Add special characters (!@#$%^&*) to make your password stronger.";
        }
        
        if (in_array(strtolower($password), $this->commonPasswords)) {
            $suggestions[] = "Avoid common words and phrases. Use a unique combination.";
        }
        
        if ($this->hasSequentialChars($password)) {
            $suggestions[] = "Avoid sequential characters like '123' or 'abc'.";
        }
        
        if ($this->hasRepeatedChars($password)) {
            $suggestions[] = "Avoid repeated characters like 'aaa' or '111'.";
        }
        
        return $suggestions;
    }
    
    /**
     * Generate a strong password
     */
    public function generateStrongPassword($length = 12) {
        $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $lowercase = 'abcdefghijklmnopqrstuvwxyz';
        $numbers = '0123456789';
        $special = '!@#$%^&*()_+-=[]{}|;:,.<>?';
        
        $password = '';
        
        // Ensure at least one character from each required set
        $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
        $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
        $password .= $numbers[random_int(0, strlen($numbers) - 1)];
        $password .= $special[random_int(0, strlen($special) - 1)];
        
        // Fill the rest with random characters
        $allChars = $uppercase . $lowercase . $numbers . $special;
        for ($i = 4; $i < $length; $i++) {
            $password .= $allChars[random_int(0, strlen($allChars) - 1)];
        }
        
        // Shuffle the password to make it more random
        return str_shuffle($password);
    }
    
    /**
     * Check if password has been compromised (basic check)
     */
    public function isCompromised($password) {
        // This is a basic implementation
        // In a real application, you might want to check against a database of compromised passwords
        $commonPatterns = [
            '/password/i',
            '/123456/',
            '/qwerty/i',
            '/admin/i',
            '/letmein/i',
            '/welcome/i'
        ];
        
        foreach ($commonPatterns as $pattern) {
            if (preg_match($pattern, $password)) {
                return true;
            }
        }
        
        return false;
    }
}
?> 