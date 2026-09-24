<?php

require_once(__DIR__ . '/bootstrap_config.php'); 

class MySQLDatabase {
	
	public $connection;
	public $lastQuery;
	private $magicQuotesActive;
	private $realEscapeStringExists;
	
	function __construct() {
		$this->openConnection();
		// Modern defaults (magic quotes removed long ago)
		$this->magicQuotesActive = false;
		$this->realEscapeStringExists = function_exists('mysqli_real_escape_string');
	}

	public function openConnection() {
		global $connect;
		if (isset($connect) && $connect instanceof mysqli && (int) $connect->connect_errno === 0) {
			$this->connection = $connect;
			if (function_exists('mysqli_set_charset')) {
				mysqli_set_charset($this->connection, 'utf8mb4');
			}
			return;
		}
		require_once __DIR__ . '/mysqli_connect_safe.php';
		if (function_exists('crm_mysqli_open')) {
			$this->connection = crm_mysqli_open();
		} else {
			$this->connection = mysqli_connect(DB_SERVER, DB_USER, DB_PASS, DB_NAME);
		}
		if (!$this->connection || (int) $this->connection->connect_errno !== 0) {
			die("Database connection failed: " . (isset($this->connection->connect_error) ? $this->connection->connect_error : mysqli_connect_error()));
		}
		mysqli_set_charset($this->connection, "utf8mb4");
	}

	public function closeConnection() {
		if(isset($this->connection)) {
			mysql_close($this->connection);
			unset($this->connection);
		}
	}

	public function query($sql) {
		$this->lastQuery = $sql;
		$lightweight = (defined('CRM_LIGHTWEIGHT_INIT') && CRM_LIGHTWEIGHT_INIT)
			|| (function_exists('crm_is_lightweight_request') && crm_is_lightweight_request());
		try {
			$result = mysqli_query($this->connection, $sql);
			if ($lightweight) {
				if (!$result) {
					error_log('[MySQLDatabase::query] ' . mysqli_error($this->connection) . ' | SQL: ' . $sql);
				}
				return $result;
			}
			$this->confirmQuery($result);
			return $result;
		} catch (Throwable $e) {
			error_log('[MySQLDatabase::query] ' . $e->getMessage() . ' | SQL: ' . $sql);
			return false;
		}
	}

	/**
	 * Run SQL without dying on failure. Use for JSON APIs and paths that must return JSON/HTML-free errors.
	 *
	 * @return mysqli_result|bool
	 */
	public function querySoft($sql) {
		$this->lastQuery = $sql;
		try {
			$result = mysqli_query($this->connection, $sql);
			if (!$result) {
				error_log('[MySQLDatabase::querySoft] ' . mysqli_error($this->connection) . ' | SQL: ' . $sql);
			}
			return $result;
		} catch (Throwable $e) {
			error_log('[MySQLDatabase::querySoft] ' . $e->getMessage() . ' | SQL: ' . $sql);
			return false;
		}
	}

	public function escapeValue( $value ) {
		if( $this->realEscapeStringExists ) { // PHP v4.3.0 or higher
			// undo any magic quote effects so mysql_real_escape_string can do the work
			if( $this->magicQuotesActive ) { $value = stripslashes( $value ); }
			$value = mysqli_real_escape_string( $this->connection, $value );
		} else { // before PHP v4.3.0
			// if magic quotes aren't already on then add slashes manually
			if( !$this->magicQuotesActive ) { $value = addslashes( $value ); }
			// if magic quotes are active, then the slashes already exist
		}
		return $value;
	}
	
	// "database-neutral" methods
	public function fetchArray($resultSet) {
		return mysqli_fetch_array($resultSet);
	}
	
	public function numRows($resultSet) {
		return mysqli_num_rows($resultSet);
	}
	
	public function insertId() {
		// get the last id inserted over the current db connection
		return mysqli_insert_id($this->connection);
	}
	
	public function affectedRows() {
		return mysqli_affected_rows($this->connection);
	}

	private function confirmQuery($result) {
		if (!$result) {
	    $output = "Database query failed: " . mysqli_error($this->connection) . "<br /><br />";
	    // $output .= "Last SQL query: " . $this->lastQuery;
	    die( $output );
		}
	}
	
}

$database = new MySQLDatabase();
$db =& $database;

?>