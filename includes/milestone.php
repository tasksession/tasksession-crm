<?php
// If it's going to need the database, then it's 
// probably smart to require it before we start.
require_once('database.php');
require_once('reports_common_helper.php');

class milestone extends DatabaseObject {
	
	protected static $tblName="milestones";
	protected static $tblFields = array('id', 'p_id', 'c_id', 'company_id', 'company_billing_snapshot', 'title' , 'deadline', 'releaseDate', 'budget', 'paid_total', 'status', 'bill_from', 'bill_from_address', 'currency', 'created_by', 'sales_tax', 'sales_tax_type', 'discount', 'discount_type', 'memo', 'footer', 'issue_date', 'is_recurring', 'recurring_frequency', 'recurring_parent_id', 'recurring_next_date', 'recurring_stopped', 'recurring_end_date', 'recurring_next_renewal_date', 'recurring_billing_cycle_days', 'recurring_paused', 'recurring_auto_charge');
	
	public $id;
	public $p_id;
	public $c_id;
	public $company_id;
	public $company_billing_snapshot;
	public $title;
	public $deadline;
	public $releaseDate;
	public $budget;
	public $paid_total;
	public $status;
	public $bill_from;
	public $bill_from_address;
	public $currency;
    public $created_by;
	public $sales_tax;
	public $sales_tax_type;
	public $discount;
	public $discount_type;
	public $memo;
	public $footer;
	public $issue_date;
	public $is_recurring;
	public $recurring_frequency;
	public $recurring_parent_id;
	public $recurring_next_date;
	public $recurring_stopped;
	public $recurring_end_date;
	public $recurring_next_renewal_date;
	public $recurring_billing_cycle_days;
	public $recurring_paused;
	public $recurring_auto_charge;
	
	 public $message=NULL;

	
	
 	// This will return  record by username in users table
	// Find user by username
	public static function findByMilestoneId($proj_id="") {
    global $database;
		$sql  = "SELECT * FROM milestones ";
		$sql .= "WHERE id = '{$proj_id}' ";
		$sql .= "LIMIT 1";
		$result_array = self::findBySql($sql);  // $result_array is an object
		
		return !empty($result_array) ? array_shift($result_array) : false;
			
	}
	
	// Find user by username
	public static function findByProjectId($proj_id="") {
    global $database;
		$sql  = "SELECT * FROM milestones ";
		$sql .= "WHERE p_id = '{$proj_id}' ";
		$sql .= "LIMIT 1";
		$result_array = self::findBySql($sql);  // $result_array is an object
		
		return !empty($result_array) ? array_shift($result_array) : false;
			
	}
	
	// Find user by id
	public static function findByTitle($proj_title="") {
    global $database;
		$sql  = "SELECT * FROM milestones ";
		$sql .= "WHERE title = '{$proj_title}' ";
		$sql .= "LIMIT 1";
		$result_array = self::findBySql($sql);  // $result_array is an object
		
		return !empty($result_array) ? array_shift($result_array) : false;
			
	}
	
	// Find all invoices in a recurring series by parent ID
	public static function findByRecurringParentId($parent_id="") {
		global $database;
		$sql  = "SELECT * FROM milestones ";
		$sql .= "WHERE recurring_parent_id = '{$parent_id}' ";
		$sql .= "ORDER BY issue_date ASC";
		$result_array = self::findBySql($sql);
		
		return !empty($result_array) ? $result_array : false;
	}
	
	// Get all active recurring invoices that need next invoice created
	public static function getActiveRecurringInvoices() {
		global $database;
		$today = date('Y-m-d');
		$sql  = "SELECT * FROM milestones ";
		$sql .= "WHERE is_recurring = 1 ";
		$sql .= "AND recurring_stopped = 0 ";
		$sql .= "AND (recurring_paused IS NULL OR recurring_paused = 0) ";
		$sql .= "AND recurring_next_date <= '{$today}' ";
		// More robust check for parent invoices - handle NULL, 0, '0', and empty string
		$sql .= "AND (recurring_parent_id IS NULL OR recurring_parent_id = 0 OR recurring_parent_id = '' OR CAST(recurring_parent_id AS CHAR) = '0') ";
		// Handle end date - unset or future dates only (MySQL 8+ safe)
		$sql .= "AND (recurring_end_date IS NULL OR NOT " . reports_sql_valid_date('recurring_end_date') . " OR recurring_end_date >= CURDATE()) ";
		$sql .= "ORDER BY recurring_next_date ASC";
		$result_array = self::findBySql($sql);
		
		return !empty($result_array) ? $result_array : false;
	}
	
	/** Recurring series parent (subscription row), not a generated child invoice. */
	public function isRecurringSubscriptionParent() {
		if ((int) ($this->is_recurring ?? 0) !== 1) {
			return false;
		}
		$pid = $this->recurring_parent_id ?? null;
		return ($pid === null || $pid === '' || (int) $pid === 0);
	}
	
	/** Paid child invoices stay locked; paid subscription parents may be edited. */
	public function canBeEditedDespitePaidStatus() {
		return $this->isRecurringSubscriptionParent();
	}
	
	
}
?>