<?php

	class ConnectMe
	{
		public $db_server;
		public $db_username;
		public $db_password;
		public $db_name;
		public $mysqli;
		public $result;
		
		public function __construct($db_server, $db_username, $db_password, $db_name) 
		{
			$this->db_server = $db_server;	
			$this->db_username = $db_username;
			$this->db_password = $db_password;
			$this->db_name = $db_name;

			global $connect;
			if (isset($connect) && $connect instanceof mysqli && (int) $connect->connect_errno === 0) {
				$this->mysqli = $connect;
			} else {
				$helper = __DIR__ . '/mysqli_connect_safe.php';
				if (is_file($helper)) {
					require_once $helper;
				}
				if (function_exists('crm_mysqli_open')) {
					$this->mysqli = crm_mysqli_open($this->db_server, $this->db_username, $this->db_password, $this->db_name);
				} else {
					$this->mysqli = new mysqli($this->db_server, $this->db_username, $this->db_password, $this->db_name);
				}
			}

			if (!$this->mysqli instanceof mysqli || $this->mysqli->connect_error)
			{
				die('Connection Failed');
			}
			
			// utf8mb4 required for emoji in chat reactions / notifications
			$this->mysqli->set_charset('utf8mb4');
			$this->mysqli->query("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
			$this->mysqli->query('SET character_set_connection=utf8mb4');
			$this->mysqli->query('SET character_set_client=utf8mb4');
			$this->mysqli->query('SET character_set_results=utf8mb4');
		}
		
		public function results($result)
		{
			$result_array = array();
			
			for($i = 0; $row = $result->fetch_assoc(); $i++)
			{
			   $result_array[$i] = $row; 
			}
			
			return $result_array;
		}
		
		public function array_values_recursive($ary)  
		{
			$lst = array();
			foreach( array_keys($ary) as $k ) 
			{
				$v = $ary[$k];
				if(is_scalar($v)) 
				{
					$lst[] = $v;
				} elseif (is_array($v)) {
					$lst = array_merge($lst, $this->array_values_recursive($v));
				}
			}
		
			return $lst;
		}
		
		public function sanitize_integer($get_id)
		{
			// clean it
			$sanitize = strip_tags($get_id);
			$sanitize = str_replace("'","", $sanitize);
			$sanitize = str_replace('"', "", $sanitize);
			
			$sanitize = (int) $sanitize;
			
			if(is_int($sanitize))
			{
				return $sanitize;
			}
			
			// return all data before a space
			$sanitize = substr($sanitize, 0, strpos($sanitize, ' '));
			
			// get only the numbers
			preg_match("/^\d+$/", $sanitize, $matches);	
			
			if(!empty($matches['0']))
			{
				return $matches['0'];	
			}
		}
		
		public function escape($arg)
		{
			return $this->mysqli->real_escape_string($arg);
		}
		
		public function query($query)
		{
			try {
				if (!isset($this->mysqli) || !($this->mysqli instanceof mysqli)) {
					return false;
				}
				$this->result = @$this->mysqli->query($query);
				return $this->result;
			} catch (Throwable $e) {
				error_log('[ConnectMe::query] ' . $e->getMessage());
				return false;
			}
		}
		
		public function fetch_row($result)
		{
			if($result) {
				return $result->fetch_assoc();	
			} else {
				return false;	
			}
		}
		
		public function affected_rows()
		{
			return $this->mysqli->affected_rows;	
		}
		
		public function num_rows($result)
		{
			if($result) {
				return $result->num_rows;	
			} else {
				return 0;	
			}	
		}
		
		public function error()
		{
			return $this->mysqli->error;
		}	
		
		public function error_number()
		{
			return $this->mysqli->errno;	
		}
		
		public function insert_id()
		{
			return $this->mysqli->insert_id;	
		}

		public function __destruct() 
		{
			global $connect;
			if (isset($this->mysqli) && $this->mysqli instanceof mysqli) {
				$shared = isset($connect) && $connect instanceof mysqli && $this->mysqli === $connect;
				if (!$shared) {
					@$this->mysqli->close();
				}
			}
			unset($this->db_server);
			unset($this->db_username);
			unset($this->db_name);
			unset($this->mysqli);
			unset($this->query);
			unset($this->result);
		}
			
	}
	
?>