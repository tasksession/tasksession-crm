<?php
// If it's going to need the database, then it's 
// probably smart to require it before we start.
require_once('database.php');

class Message extends DatabaseObject {
	
	protected static $tblName="messages";
	protected static $tblFields = array('id', 'message', 'time',  'user_id', 'receiver','storage_a','storage_b','status', 'Project_id');
	
	public $id;
	public $message1;
	public $time;
	public $user_id;
	public $receiver;
	public $storage_a;
	public $storage_b;
	public $status;
	public $Project_id;
	
	public $message=NULL;

	
 	// This will return  record by username in users table
	// Find user by username
	
	// Find user by id
	public static function findById($useId="") {
    global $database;
		$sql  = "SELECT * FROM messages ";
		$sql .= "WHERE id = '{$useId}' ";
		$sql .= "LIMIT 1";
		$result_array = self::findBySql($sql);  // $result_array is an object
		
		return !empty($result_array) ? array_shift($result_array) : false;
			
	}
	
}
?>