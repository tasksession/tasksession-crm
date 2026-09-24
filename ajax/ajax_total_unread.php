<?php 
	include('../includes/loader_ajax.php');
	
	// Security: Authentication check
	if (!isset($session) || !$session->isLoggedIn()) {
		http_response_code(401);
		echo '';
		exit;
	}
	
	if(isset($_POST['total_unread']) && $_POST['total_unread'] == 'true')
	{
		$t_r = $msg->total_unread_messages();
		if($t_r !== false)
		{ 
			echo $t_r; 
		} else {
			echo '';	
		}
	}

?>