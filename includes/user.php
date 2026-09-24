<?php


require_once('database.php');

class User extends DatabaseObject {
    
    protected static $tblName="users";
    protected static $tblFields = array('id', 'Projects_ids', 'password', 'email', 'username', 'accountStatus', 'firstName', 'last_name', 'title', 'company', 'address', 'phone', 'website', 'teams_id', 'fb', 'regDate', 'type_status', 'last_seen', 'session_status', 'status', 'note', 'city', 'state', 'zip', 'country', 'user_language', 'currency', 'role_id', 'assigned_team', 'base_salary', 'attendance_deduction_mode', 'attendance_deduction_value', 'login_ip_restriction_enabled', 'allowed_login_ips', 'attendance_disabled', 'google_sub', 'auth_provider', 'avatar_url', 'email_verified', 'created_at', 'updated_at', 'push_notifications_enabled', 'push_active_chat_type', 'push_active_chat_id', 'push_active_chat_at', 'totp_secret', 'totp_enabled', 'totp_confirmed_at', 'totp_backup_codes', 'totp_last_timestep', 'session_epoch');
    
    public $id;
    public $Projects_ids;
    public $password; 
    public $email;
    public $username;
    public $accountStatus;
    public $firstName;
    public $last_name;
    public $title;
    public $company;
    public $address;
    public $phone;
    public $website;
    public $teams_id;
    public $fb;
    public $regDate;
    public $type_status;
    public $last_seen;
    public $session_status;
    public $status;
    public $note;
    public $city;
    public $state;
    public $zip;
    public $country;
    public $user_language;
    public $currency;
    public $role_id;
    public $assigned_team;
    public $base_salary;
    public $attendance_deduction_mode;
    public $attendance_deduction_value;
    public $login_ip_restriction_enabled;
    public $allowed_login_ips;
    public $attendance_disabled;
    
    // Google OAuth properties
    public $google_sub;
    public $auth_provider;
    public $avatar_url;
    public $email_verified;
    
    // Timestamp properties
    public $created_at;
    public $updated_at;

    // Browser push preference / active-chat mute context
    public $push_notifications_enabled;
    public $push_active_chat_type;
    public $push_active_chat_id;
    public $push_active_chat_at;

    public $totp_secret;
    public $totp_enabled;
    public $totp_confirmed_at;
    public $totp_backup_codes;
    public $totp_last_timestep;
    public $session_epoch;
    
    public $message=NULL;

    /** @var array<string, true>|null */
    protected static $usersTableColumns = null;

    /**
     * @return array<string, true>
     */
    protected static function usersTableColumnMap()
    {
        if (self::$usersTableColumns !== null) {
            return self::$usersTableColumns;
        }
        self::$usersTableColumns = [];
        global $connect;
        if (!isset($connect) || !($connect instanceof mysqli)) {
            return self::$usersTableColumns;
        }
        $res = @mysqli_query($connect, 'SHOW COLUMNS FROM users');
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $field = (string) ($row['Field'] ?? '');
                if ($field !== '') {
                    self::$usersTableColumns[$field] = true;
                }
            }
        }
        return self::$usersTableColumns;
    }

    protected function attributes()
    {
        $attributes = parent::attributes();
        $columns = self::usersTableColumnMap();
        if ($columns === []) {
            return $attributes;
        }
        return array_intersect_key($attributes, $columns);
    }

    /** Active user (status = 0). Trashed / disabled users use status = 1. */
    public static function isTrashed($userOrId)
    {
        if ($userOrId instanceof User || (is_object($userOrId) && isset($userOrId->status))) {
            return (int) ($userOrId->status ?? 0) === 1;
        }
        if (is_numeric($userOrId)) {
            $u = self::findById((int) $userOrId);
            return $u ? (int) ($u->status ?? 0) === 1 : false;
        }
        return false;
    }

    public static function trashedLoginMessage()
    {
        global $lang;
        if (isset($lang['Account has been disabled.'])) {
            return (string) $lang['Account has been disabled.'];
        }
        return 'This account has been disabled. Please contact your administrator.';
    }

    /** Clear remember-me tokens when a user is trashed. */
    public static function revokeRememberMeTokens($userId)
    {
        $userId = (int) $userId;
        if ($userId <= 0) {
            return false;
        }
        require_once __DIR__ . '/remember_me.php';
        $rememberMe = new RememberMe();
        $ok = $rememberMe->deleteAllUserTokens($userId);
        if (function_exists('auth_trusted_device_revoke_user')) {
            auth_trusted_device_revoke_user($userId);
        }
        return $ok;
    }

    // Updated authenticate method with prepared statements
    public static function authenticate($username="", $password="") {
        global $connect;
        
        // Use prepared statement for better security
        $sql = "SELECT * FROM users WHERE email = ? LIMIT 1";
        $stmt = $connect->prepare($sql);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $foundUser = $result->fetch_assoc();
            // Convert to object for consistency
            $user = new User();
            foreach($foundUser as $key => $value) {
                $user->$key = $value;
            }
            
            // Use password_verify to check the entered password against the hash
            if (password_verify($password, $user->password)) {
                return $user;
            }
        }
        return false;
    }

    public static function findallUser() {
        global $database;
        $sql  = "SELECT * FROM users";
        $result_array = self::findBySql($sql);  // $result_array is an object
        return !empty($result_array) ? array_shift($result_array) : false;
    }
    
    // This will return  record by username in users table
    public static function findByUsername($username="") {
        global $database;
        $sql  = "SELECT * FROM users ";
        $sql .= "WHERE username = '{$username}' ";
        $sql .= "LIMIT 1";
        $result_array = self::findBySql($sql);
        return !empty($result_array) ? array_shift($result_array) : false;
    }
    
    // Find user by id with prepared statement
    public static function findById($useId="") {
        global $connect;
        $sql = "SELECT * FROM users WHERE id = ? LIMIT 1";
        $stmt = $connect->prepare($sql);
        $stmt->bind_param("i", $useId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $foundUser = $result->fetch_assoc();
            // Convert to object for consistency
            $user = new User();
            foreach ($foundUser as $key => $value) {
                // Only set declared properties (avoids PHP 8.2+ dynamic property deprecations).
                if (property_exists($user, (string) $key)) {
                    $user->$key = $value;
                }
            }
            if (function_exists('auth_enforce_session_epoch_on_user')) {
                auth_enforce_session_epoch_on_user($user);
            }
            return $user;
        }
        return false;
    }
    
    public static function findByEmail($email="") {
        global $connect;
        $sql = "SELECT * FROM users WHERE email = ? LIMIT 1";
        $stmt = $connect->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $foundUser = $result->fetch_assoc();
            // Convert to object for consistency
            $user = new User();
            foreach ($foundUser as $key => $value) {
                if (property_exists($user, (string) $key)) {
                    $user->$key = $value;
                }
            }
            return $user;
        }
        return false;
    }
}
?>