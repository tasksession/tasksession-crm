<?php
require_once __DIR__ . '/session_bootstrap.php';
tasksession_session_start();
/*	
	error_reporting(1); */

	include('../includes/bootstrap_config.php');
	include('../includes/time_setup.php');

	require_once('../includes/user_presence.php');
	require_once('../includes/database.class.php');
	
	if (!isset($db1) || !($db1 instanceof ConnectMe)) {
		$db1 = new ConnectMe(DB_SERVER, DB_USER, DB_PASS, DB_NAME);
	}
	
	$__messagesClass = __DIR__ . '/messages.class.php';
	if (is_file($__messagesClass)) {
		include($__messagesClass);
	}

	if (class_exists('Messages')) {
		$msg = new Messages();
		$msg->logged_user_id = @$_SESSION['logged_user_id'];
		if ($msg->logged_user_id != '') {
			$msg->set_user_sessionStatus("online");
		}
	} else {
		$msg = null;
	}
	
	$__embed = __DIR__ . '/embed.php';
	if (is_file($__embed)) {
		include($__embed);
	}
	
	$__attach = __DIR__ . '/attachments.class.php';
	if (is_file($__attach)) {
		include($__attach);
	}
	
	$__maps = __DIR__ . '/maps.class.php';
	if (is_file($__maps)) {
		include($__maps);
	}
	
?>
