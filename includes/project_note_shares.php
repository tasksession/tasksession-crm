<?php
/**
 * Project notes shares (view/edit) for project_tab_notes.
 */

if (!function_exists('project_note_shares_ensure_table')) {
	function project_note_shares_ensure_table(mysqli $connect): void {
		$connect->query("CREATE TABLE IF NOT EXISTS `project_tab_note_shares` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`project_note_id` INT NOT NULL,
			`project_id` INT NOT NULL,
			`creator_user_id` INT NOT NULL,
			`shared_with_user_id` INT NOT NULL,
			`permission` ENUM('view','edit') NOT NULL DEFAULT 'view',
			`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			`updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (`id`),
			UNIQUE KEY `uq_project_note_share` (`project_note_id`,`shared_with_user_id`),
			KEY `idx_proj_note_shares_project` (`project_id`),
			KEY `idx_proj_note_shares_creator` (`creator_user_id`),
			KEY `idx_proj_note_shares_target` (`shared_with_user_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

		// Backfill safety for older installations:
		// 1) dedupe rows by note+user (keep latest id)
		// 2) enforce unique key required by ON DUPLICATE KEY UPDATE in share_add.php
		$connect->query(
			"DELETE s1 FROM project_tab_note_shares s1
			INNER JOIN project_tab_note_shares s2
				ON s1.project_note_id = s2.project_note_id
				AND s1.shared_with_user_id = s2.shared_with_user_id
				AND s1.id < s2.id"
		);
		$idxRes = $connect->query("SHOW INDEX FROM project_tab_note_shares WHERE Key_name = 'uq_project_note_share'");
		$hasUnique = false;
		if ($idxRes instanceof mysqli_result) {
			$hasUnique = $idxRes->num_rows > 0;
			$idxRes->free();
		}
		if (!$hasUnique) {
			$connect->query('ALTER TABLE project_tab_note_shares ADD UNIQUE KEY uq_project_note_share (project_note_id, shared_with_user_id)');
		}
	}
}

if (!function_exists('project_note_share_get_note_for_creator')) {
	function project_note_share_get_note_for_creator(
		mysqli $connect,
		int $noteId,
		int $projectId,
		int $creatorId,
		string $creatorType
	): ?array {
		$ct = in_array($creatorType, ['admin', 'staff', 'client'], true) ? $creatorType : 'admin';
		$sql = "SELECT id, project_id, creator_id, creator_type
			FROM project_tab_notes
			WHERE id=" . (int) $noteId . " AND project_id=" . (int) $projectId . "
			  AND creator_id=" . (int) $creatorId . " AND creator_type='" . $connect->real_escape_string($ct) . "'
			LIMIT 1";
		$res = $connect->query($sql);
		if (!$res) {
			return null;
		}
		$row = $res->fetch_assoc();
		$res->free();
		return $row ?: null;
	}
}

if (!function_exists('project_note_share_recipient_access')) {
	function project_note_share_recipient_access(mysqli $connect, int $projectNoteId, int $recipientUserId): array {
		if ($projectNoteId <= 0 || $recipientUserId <= 0) {
			return ['can_view' => false, 'can_edit' => false, 'permission' => 'none'];
		}
		$sql = "SELECT s.permission
			FROM project_tab_note_shares s
			INNER JOIN project_tab_notes n ON n.id = s.project_note_id
			WHERE s.project_note_id = " . (int) $projectNoteId . "
			  AND s.shared_with_user_id = " . (int) $recipientUserId . "
			ORDER BY s.updated_at DESC, s.id DESC
			LIMIT 1";
		$res = $connect->query($sql);
		$row = $res ? $res->fetch_assoc() : null;
		if ($res instanceof mysqli_result) {
			$res->free();
		}
		$perm = ($row && isset($row['permission'])) ? (string) $row['permission'] : 'none';
		if (!in_array($perm, ['view', 'edit'], true)) {
			return ['can_view' => false, 'can_edit' => false, 'permission' => 'none'];
		}
		return [
			'can_view' => true,
			'can_edit' => $perm === 'edit',
			'permission' => $perm,
		];
	}
}

if (!function_exists('project_note_shares_team_rows_by_note_ids')) {
	function project_note_shares_team_rows_by_note_ids(mysqli $connect, array $projectNoteIds): array {
		$ids = array_values(array_unique(array_filter(array_map('intval', $projectNoteIds), static function ($v) {
			return $v > 0;
		})));
		$out = [];
		if ($ids === []) {
			return $out;
		}
		$in = implode(',', $ids);
		$sql = "SELECT s.project_note_id, s.shared_with_user_id, s.permission, u.firstName
			FROM project_tab_note_shares s
			LEFT JOIN users u ON u.id = s.shared_with_user_id
			WHERE s.project_note_id IN ({$in})
			ORDER BY s.created_at ASC";
		$res = $connect->query($sql);
		if (!$res) {
			return $out;
		}
		while ($row = $res->fetch_assoc()) {
			$nid = (int) ($row['project_note_id'] ?? 0);
			if ($nid <= 0) {
				continue;
			}
			if (!isset($out[$nid])) {
				$out[$nid] = [];
			}
			$perm = (string) ($row['permission'] ?? 'view');
			if (!in_array($perm, ['view', 'edit'], true)) {
				$perm = 'view';
			}
			$out[$nid][] = [
				'user_id' => (int) ($row['shared_with_user_id'] ?? 0),
				'first_name' => (string) ($row['firstName'] ?? ''),
				'permission' => $perm,
			];
		}
		$res->free();
		return $out;
	}
}
