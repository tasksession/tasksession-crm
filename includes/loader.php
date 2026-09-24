<?php

require_once __DIR__ . '/session_bootstrap.php';
tasksession_session_start(); 
	
	error_reporting(1);
	
	include('bootstrap_config.php');

	require_once('user_presence.php');
	require_once('database.class.php');
	
	if (!isset($db1) || !($db1 instanceof ConnectMe)) {
		$db1 = new ConnectMe(DB_SERVER, DB_USER, DB_PASS, DB_NAME);
	}
	
	$__messagesClass = __DIR__ . '/messages.class.php';
	if (is_file($__messagesClass)) {
		include($__messagesClass);
	}

	if (class_exists('Messages')) {
		$msg = new Messages();

		// Session::login() sets both userId and logged_user_id. Older / partial sessions
		// may only have userId — sync so message queries never become "receiver =".
		$loggedUserId = isset($_SESSION['logged_user_id']) ? (int) $_SESSION['logged_user_id'] : 0;
		if ($loggedUserId <= 0 && !empty($_SESSION['userId'])) {
			$loggedUserId = (int) $_SESSION['userId'];
			$_SESSION['logged_user_id'] = $loggedUserId;
		}
		$msg->logged_user_id = $loggedUserId > 0 ? $loggedUserId : '';
		if ($msg->logged_user_id != '') {
			$msg->set_user_sessionStatus("online");
		}
	} else {
		$msg = null;
	}
			
?>
