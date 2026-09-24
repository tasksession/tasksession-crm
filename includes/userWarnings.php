<?php
// If it's going to need the database, then it's 
// probably smart to require it before we start.
require_once('database.php');

class userWarnings extends DatabaseObject {
	
	protected static $tblName="user_warnings";
	protected static $tblFields = array('id', 'subject', 'detail','fkUserId','datetime');
	
	public $id;
	public $subject;
	public $detail;
	public $fkUserId;
	public $datetime;
		
	
	public function findAleadySentWarnings($check)
	{
		$sql="select * from user_warnings where  fkUserId=$this->fkUserId order by id desc ";
		
		$userWarnings=self::findBySql($sql);
		
		$countUserWarnings=count($userWarnings);
			
		if($check=="count")
		{
		 return $countUserWarnings;
		}
		if($check=="data")
		{
			return $userWarnings;
		}
	}
	
}

?>