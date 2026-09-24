<?php
if (!isset($user_id)) {
	return;
}
$uid = (int) $user_id;
$colorF = isset($_GET['color_filter']) && in_array((string) $_GET['color_filter'], ['blue', 'green', 'yellow'], true) ? (string) $_GET['color_filter'] : '';
$scopeF = isset($_GET['scope_filter']) && (string) $_GET['scope_filter'] === 'recent' ? 'recent' : '';
$isArchive = isset($_GET['profile_notes_list']) && (string) $_GET['profile_notes_list'] === 'archive';
$searchF = isset($_GET['search']) ? trim((string) $_GET['search']) : '';
$profileNotesFilterActive = $colorF !== '' || $scopeF !== '' || $isArchive || $searchF !== '';
$pnFilterBuild = static function (array $over) use ($uid, $colorF, $scopeF, $isArchive, $searchF) {
	$p = [
		'user_id' => $uid,
		'tab' => 'notes',
	];
	if ($searchF !== '') {
		$p['search'] = $searchF;
	}
	if ($isArchive && !array_key_exists('profile_notes_list', $over)) {
		$p['profile_notes_list'] = 'archive';
	}
	if ($colorF !== '' && !array_key_exists('color_filter', $over)) {
		$p['color_filter'] = $colorF;
	}
	if ($scopeF !== '' && !array_key_exists('scope_filter', $over)) {
		$p['scope_filter'] = $scopeF;
	}
	$p = array_merge($p, $over);
	foreach ($over as $k => $v) {
		if ($v === null) {
			unset($p[$k]);
		}
	}
	return 'profile?' . http_build_query($p, '', '&', PHP_QUERY_RFC3986);
};
