<?php
	include('../includes/loader_ajax.php');
	
	// Security: Authentication check
	if (!isset($session) || !$session->isLoggedIn()) {
		http_response_code(401);
		echo json_encode(['error' => 'Unauthorized']);
		exit;
	}
	
	if (!function_exists('user_presence_status')) {
		require_once dirname(__DIR__) . '/includes/user_presence.php';
	}

	function ajax_last_seen_payload($user): array
	{
		if (!$user) {
			return ['last_seen' => null, 'session_status' => 'offline'];
		}
		$lastSeen = !empty($user->last_seen)
			? date('d/m/Y, h:i a', is_numeric($user->last_seen) ? $user->last_seen : strtotime($user->last_seen))
			: null;
		user_presence_maybe_persist_offline((int) $user->id, $user->session_status ?? '', $user->last_seen ?? 0);
		return [
			'user_id' => (int) $user->id,
			'last_seen' => $lastSeen,
			'session_status' => user_presence_status($user->session_status ?? 'offline', $user->last_seen ?? 0),
		];
	}

	if (isset($_GET['user_ids'])) {
		$ids = array_values(array_unique(array_filter(array_map('intval', explode(',', (string) $_GET['user_ids'])))));
		$ids = array_slice($ids, 0, 40);
		$users = [];
		foreach ($ids as $batchId) {
			if ($batchId <= 0) {
				continue;
			}
			$row = ajax_last_seen_payload(User::findById($batchId));
			$row['user_id'] = $batchId;
			$users[] = $row;
		}
		echo json_encode(['users' => $users]);
		exit;
	}

	// Return last seen and session status for a user if GET request
	if (isset($_GET['user_id'])) {
		$userId = (int)$_GET['user_id'];
		$payload = ajax_last_seen_payload(User::findById($userId));
		echo json_encode($payload);
		exit;
	}

	// Existing POST logic for online/offline status
	if (isset($_POST['offline']) && $_POST['offline'] == 'true') {
		$msg->set_user_sessionStatus('offline');
	} elseif (isset($_POST['offline']) && $_POST['offline'] == 'false') {
		$msg->set_user_sessionStatus('online');
	}
?>