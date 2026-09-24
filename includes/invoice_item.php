<?php
// If it's going to need the database, then it's 
// probably smart to require it before we start.
require_once('database.php');

class InvoiceItem extends DatabaseObject {
	
	protected static $tblName="invoice_items";
    protected static $tblFields = array('id', 'milestone_id', 'description', 'item_description', 'rate', 'quantity', 'sort_order');
	
	public $id;
	public $milestone_id;
	public $description;
	public $item_description;
	public $rate;
	public $quantity;
	public $sort_order;
	
	public $message = NULL;
	
	// Find items by milestone ID
	public static function findByMilestoneId($milestone_id="") {
		global $database;
		$sql  = "SELECT * FROM invoice_items ";
		$sql .= "WHERE milestone_id = '{$milestone_id}' ";
		$sql .= "ORDER BY sort_order ASC, id ASC";
		$result_array = self::findBySql($sql);
		
		return !empty($result_array) ? $result_array : false;
	}

	/**
	 * Batch-load invoice line items for many milestones (avoids N+1 on dashboard charts).
	 * @param list<int> $milestoneIds
	 * @return array<int, list<object>>
	 */
	public static function findByMilestoneIds(array $milestoneIds): array
	{
		$milestoneIds = array_values(array_unique(array_filter(array_map('intval', $milestoneIds))));
		$out = [];
		foreach ($milestoneIds as $mid) {
			$out[$mid] = [];
		}
		if ($milestoneIds === []) {
			return $out;
		}
		$idList = implode(',', $milestoneIds);
		$rows = self::findBySql(
			"SELECT * FROM invoice_items WHERE milestone_id IN ({$idList}) ORDER BY sort_order ASC, id ASC"
		);
		if (!is_array($rows)) {
			return $out;
		}
		foreach ($rows as $row) {
			$mid = (int) ($row->milestone_id ?? 0);
			if ($mid > 0) {
				if (!isset($out[$mid])) {
					$out[$mid] = [];
				}
				$out[$mid][] = $row;
			}
		}
		return $out;
	}
	
	// Delete all items for a milestone
	public static function deleteByMilestoneId($milestone_id="") {
		global $database;
		$sql = "DELETE FROM invoice_items WHERE milestone_id = '{$milestone_id}'";
		$database->query($sql);
		return true;
	}
}

?>

