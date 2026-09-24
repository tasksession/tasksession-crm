<?php
// A class to help work with Sessions
// In our case, primarily to manage logging users in and out

// Keep in mind when working with sessions that it is generally 
// inadvisable to store DB-related objects in sessions

require_once("database-object.php");
require_once __DIR__ . '/session_bootstrap.php';

class Session extends DatabaseObject {
	
	private $loggedIn = false;
	public $userId;
	public $username;
	public $accountStatus;
	
	public $message;
	public $views;
	function __construct() {
    tasksession_session_start(); 
		$this->checkMessage();
		$this->checkLogin();
		if($this->loggedIn) {
		  // actions to take right away if user is logged in
		} else {
		  // actions to take right away if user is not logged in
		}
	}
	
	public function isLoggedIn() {
		return $this->loggedIn;
	}
	
	public function fbUserLoggedIn() {
		$this->loggedIn = true;
		return $this->loggedIn;
	}
	
	public function login($user) {
		if($user){
			if (session_status() === PHP_SESSION_ACTIVE) {
				session_regenerate_id(true);
			}
			$this->userId = $_SESSION['userId'] = $user->id;
			$this->username =$_SESSION['username']= $user->firstName;
			$this->accountStatus =$_SESSION['accountStatus']= $user->accountStatus;
			$_SESSION['logged_user_id'] = $user->id;
			$_SESSION['accountStatus'] = $user->accountStatus;
			$_SESSION['last_activity'] = time();
			$_SESSION['session_epoch'] = (int) ($user->session_epoch ?? 1);
			// Setup guide "Remind me next login" — clear so guide can show again
			unset($_SESSION['setup_guide_remind_next_login']);
			if (!function_exists('setup_guide_clear_remind_snooze')) {
				$sgFile = dirname(__FILE__) . '/setup_guide.php';
				if (is_file($sgFile)) {
					require_once $sgFile;
				}
			}
			if (function_exists('setup_guide_clear_remind_snooze')) {
				setup_guide_clear_remind_snooze();
			}
			return $this->loggedIn = true;
		}
	}
  
	public function logout() {
		$userId = isset($this->userId) ? (int)$this->userId : 0;
		if ($userId <= 0 && isset($_SESSION['userId'])) {
			$userId = (int)$_SESSION['userId'];
		}
		if ($userId > 0) {
			if (!function_exists('tasksession_pause_running_timers_for_user')) {
				$ttHelper = dirname(__FILE__) . DS . 'time_tracking_helper.php';
				if (is_file($ttHelper)) {
					require_once $ttHelper;
				}
			}
			if (function_exists('tasksession_pause_running_timers_for_user')) {
				global $connect;
				if (isset($connect) && $connect instanceof mysqli) {
					tasksession_pause_running_timers_for_user($connect, $userId);
				}
			}
			global $connect, $db1, $database;
			$offlineSql = "UPDATE users SET session_status = 'offline' WHERE id = " . (int) $userId;
			if (isset($connect) && $connect instanceof mysqli) {
				@$connect->query($offlineSql);
			} elseif (isset($db1) && is_object($db1) && method_exists($db1, 'query')) {
				@$db1->query($offlineSql);
			} elseif (isset($database) && is_object($database) && method_exists($database, 'query')) {
				@$database->query($offlineSql);
			}
		}

		// Clear all session variables
		$_SESSION = array();
		
		// If it's desired to kill the session, also delete the session cookie.
		if (ini_get("session.use_cookies")) {
			$params = session_get_cookie_params();
			setcookie(session_name(), '', time() - 42000,
				$params["path"], $params["domain"],
				$params["secure"], $params["httponly"]
			);
		}
		
		// Finally, destroy the session.
		session_destroy();
		
		unset($this->userId);
		unset($this->username);
		unset($this->accountStatus);
		$this->loggedIn = false;
	}
	
	private function checkLogin() {
		if(isset($_SESSION['userId'])) {
			if (defined('AUTH_IDLE_TIMEOUT_SECONDS')) {
				$idleFor = AUTH_IDLE_TIMEOUT_SECONDS;
			} else {
				$idleFor = 2700;
			}
			$last = (int) ($_SESSION['last_activity'] ?? 0);
			if ($last > 0 && (time() - $last) > $idleFor) {
				unset($_SESSION['userId'], $_SESSION['logged_user_id'], $_SESSION['accountStatus'], $_SESSION['username'], $_SESSION['session_epoch'], $_SESSION['last_activity']);
				unset($this->userId);
				unset($this->username);
				unset($this->accountStatus);
				$this->loggedIn = false;
				return;
			}
			$_SESSION['last_activity'] = time();
			$this->userId = $_SESSION['userId'];
			$this->loggedIn = true;
			
			// Also set username and accountStatus from session
			if(isset($_SESSION['username'])) {
				$this->username = $_SESSION['username'];
			}
			if(isset($_SESSION['accountStatus'])) {
				$this->accountStatus = $_SESSION['accountStatus'];
			}
			// Keep chat/messages key in sync with auth key (reliability, not a new auth path)
			if (empty($_SESSION['logged_user_id']) && (int) $_SESSION['userId'] > 0) {
				$_SESSION['logged_user_id'] = (int) $_SESSION['userId'];
			}
		} else {
			unset($this->userId);
			unset($this->username);
			unset($this->accountStatus);
			$this->loggedIn = false;
		}
	}
	
	// Check if current user is an admin
	public function isAdmin() {
		return $this->accountStatus == 1;
	}
	
	// Check if current user is a staff member
	public function isStaff() {
		return $this->accountStatus == 3;
	}
	
	// Check if current user has a specific task permission
	public function hasTaskPermission($permission) {
		// Admin has all permissions
		if ($this->isAdmin()) {
			return true;
		}
		
		// Only staff can have task permissions
		if (!$this->isStaff()) {
			return false;
		}
		
		// Include task permission class
		require_once('task_permission.php');
		
		// Check the specific permission
		return TaskPermission::hasPermission($this->userId, $permission);
	}
	
	// Convenience methods for specific permissions
	public function canCreateTask() {
		return $this->hasTaskPermission('can_create_task');
	}
	
	public function canDeleteTask() {
		return $this->hasTaskPermission('can_delete_task');
	}
	
	public function canChangeTaskStatus() {
		return $this->hasTaskPermission('can_change_status');
	}
	
	public function canUpdateTask() {
		return $this->hasTaskPermission('can_update_task');
	}
	
	public function canAssignTaskMembers() {
		return $this->hasTaskPermission('can_assign_members');
	}
	
	public function pageView() {
		if(isset($_SESSION['views'])) {
			return $_SESSION['views'];
		}
	}
	
	public function message($msg="") {
		if(!empty($msg)) {
			// then this is "set message"
			// make sure you understand why $this->message=$msg wouldn't work
			$_SESSION['message'] = $msg;
		} else {
			// then this is "get message"
				return $this->message;
		}
	}
	
	private function checkMessage() {
		// Is there a message stored in the session?
		if(isset($_SESSION['message'])) {
			// Add it as an attribute and erase the stored version
			$this->message = $_SESSION['message'];
			unset($_SESSION['message']);
		} else {
			$this->message = "";
		}
	}
	
  
}

$session = new Session();
$message = $session->message();
?>