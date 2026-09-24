<?php
// If it's going to need the database, then it's 
// probably smart to require it before we start.
require_once('database.php');

class Projects extends DatabaseObject {
	
	protected static $tblName="projects";
	protected static $tblFields = array('p_id', 'c_id', 'c_ids', 'main_client_id', 's_ids', 'project_title', 'project_desc', 'budget', 'status', 'archive', 'trash', 'start_time','end_time');
	
	public $p_id;
	public $c_id;
	public $c_ids;
	public $main_client_id;
	public $s_ids;
	public $project_title;
	public $project_desc;
	public $budget;
	public $status;
	public $archive;
	public $trash;
	public $start_time;
	public $end_time;
	
	public $message=NULL;

	// Helper method to get main client
	public function getMainClient() {
		global $database;
		if (!$this->main_client_id) return false;
		return User::findById($this->main_client_id);
	}

	// Helper method to get all clients
	public function getAllClients() {
		global $database;
		if (empty($this->c_ids)) return [];
		$clientIds = array_filter(explode(',', $this->c_ids));
		$clients = [];
		foreach ($clientIds as $id) {
			$client = User::findById((int)$id);
			if ($client) $clients[] = $client;
		}
		return $clients;
	}

	// Helper method to check if a user is a client for this project
	public function isClient($userId) {
		if (empty($this->c_ids)) return false;
		$clientIds = array_map('trim', explode(',', $this->c_ids));
		return in_array((string)$userId, $clientIds, true);
	}

	// Helper method to check if a user is the main client
	public function isMainClient($userId) {
		return $this->main_client_id == $userId;
	}
	
 	// This will return  record by username in users table
	// Find user by username
	public static function findByProjectId($proj_id="") {
		global $connect;
		$proj_id = (int)$proj_id;
		$stmt = $connect->prepare("SELECT * FROM projects WHERE p_id = ? LIMIT 1");
		$stmt->bind_param("i", $proj_id);
		$stmt->execute();
		$result = $stmt->get_result();
		$row = $result->fetch_assoc();
		$stmt->close();
		
		if ($row) {
			return static::instantiate($row);
		}
		return false;
	}
	// Find user by id
	public static function findByTitle($proj_title="") {
		global $connect;
		$stmt = $connect->prepare("SELECT * FROM projects WHERE project_title = ? LIMIT 1");
		$stmt->bind_param("s", $proj_title);
		$stmt->execute();
		$result = $stmt->get_result();
		$row = $result->fetch_assoc();
		$stmt->close();
		
		if ($row) {
			return static::instantiate($row);
		}
		return false;
	}
	
	public function create() {
		global $database;
		$attributes = $this->sanitizedAttributes();
		$tblNN = static::$tblName;
		$attrkeysss = join(", ", array_keys($attributes));
		$attrvalss = join("', '", array_values($attributes));
		$sql = "";
		$sql .= "INSERT INTO $tblNN ( $attrkeysss ) VALUES ('$attrvalss')";
		if($database->query($sql)) {
			$this->p_id = $database->insertId(); // Set p_id after insert
			return $this->p_id;
		} else {
			return mysqli_error($database);
		}
	}
}
?>