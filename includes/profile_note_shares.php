<?php
/**
 * Profile tab notes — team shares (view/edit) with other users (e.g. client on Documents).
 * Avoids mysqli_stmt::get_result() (requires mysqlnd); uses mysqli::query for reads.
 */
if (!function_exists('profile_note_shares_identifier_quote')) {
	function profile_note_shares_identifier_quote(string $name): string {
		return '`' . str_replace('`', '``', $name) . '`';
	}
}

if (!function_exists('profile_note_shares_drop_foreign_keys')) {
	function profile_note_shares_drop_foreign_keys(mysqli $connect): void {
		$resDb = $connect->query('SELECT DATABASE() AS db');
		if (!$resDb) {
			return;
		}
		$dbRow = $resDb->fetch_assoc();
		$resDb->free();
		$schema = isset($dbRow['db']) ? (string) $dbRow['db'] : '';
		if ($schema === '') {
			return;
		}
		$schemaEsc = $connect->real_escape_string($schema);
		$sql = "SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
			WHERE TABLE_SCHEMA = '{$schemaEsc}' AND TABLE_NAME = 'profile_note_shares' AND CONSTRAINT_TYPE = 'FOREIGN KEY'";
		$res = $connect->query($sql);
		if (!$res) {
			return;
		}
		$names = [];
		while ($row = $res->fetch_assoc()) {
			if (!empty($row['CONSTRAINT_NAME'])) {
				$names[] = (string) $row['CONSTRAINT_NAME'];
			}
		}
		$res->free();
		foreach ($names as $name) {
			if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
				continue;
			}
			$connect->query('ALTER TABLE `profile_note_shares` DROP FOREIGN KEY `' . $name . '`');
		}
	}
}

if (!function_exists('profile_note_shares_field_map')) {
	/**
	 * @return array<string, string> lower_field => exact Field from SHOW COLUMNS
	 */
	function profile_note_shares_field_map(mysqli $connect): array {
		$fields = [];
		$res = $connect->query('SHOW COLUMNS FROM `profile_note_shares`');
		if (!$res) {
			return $fields;
		}
		while ($row = $res->fetch_assoc()) {
			$fn = isset($row['Field']) ? strtolower((string) $row['Field']) : '';
			if ($fn !== '') {
				$fields[$fn] = (string) $row['Field'];
			}
		}
		$res->free();
		return $fields;
	}
}

if (!function_exists('profile_note_shares_migrate_legacy_columns')) {
	/**
	 * Some DBs have profile_note_shares created with private_note_shares column names
	 * (note_id, owner_user_id) or other legacy names. CREATE TABLE IF NOT EXISTS does not fix that.
	 */
	function profile_note_shares_migrate_legacy_columns(mysqli $connect): void {
		$fields = profile_note_shares_field_map($connect);
		if (isset($fields['profile_note_id']) && isset($fields['creator_user_id'])) {
			return;
		}

		profile_note_shares_drop_foreign_keys($connect);

		if (!isset($fields['profile_note_id']) && isset($fields['note_id'])) {
			$from = profile_note_shares_identifier_quote($fields['note_id']);
			if (!$connect->query("ALTER TABLE `profile_note_shares` CHANGE COLUMN {$from} `profile_note_id` INT NOT NULL")) {
				error_log('[profile_note_shares] migrate note_id→profile_note_id failed: ' . $connect->error);
			}
		}

		$fields = profile_note_shares_field_map($connect);
		$creatorFromNames = [
			'owner_user_id',
			'creator_id',
			'sharer_user_id',
			'author_user_id',
			'from_user_id',
			'created_by',
		];
		foreach ($creatorFromNames as $legacyName) {
			$fields = profile_note_shares_field_map($connect);
			if (isset($fields['creator_user_id'])) {
				break;
			}
			if (!isset($fields[$legacyName])) {
				continue;
			}
			$from = profile_note_shares_identifier_quote($fields[$legacyName]);
			if (!$connect->query("ALTER TABLE `profile_note_shares` CHANGE COLUMN {$from} `creator_user_id` INT NOT NULL")) {
				error_log('[profile_note_shares] migrate ' . $legacyName . '→creator_user_id failed: ' . $connect->error);
			}
		}

		$fields = profile_note_shares_field_map($connect);
		if (!isset($fields['creator_user_id'])) {
			if (!$connect->query('ALTER TABLE `profile_note_shares` ADD COLUMN `creator_user_id` INT NOT NULL DEFAULT 0')) {
				error_log('[profile_note_shares] ADD creator_user_id failed: ' . $connect->error);
			}
		}

		$fields = profile_note_shares_field_map($connect);
		if (!isset($fields['profile_note_id'])) {
			if (!$connect->query('ALTER TABLE `profile_note_shares` ADD COLUMN `profile_note_id` INT NOT NULL DEFAULT 0')) {
				error_log('[profile_note_shares] ADD profile_note_id failed: ' . $connect->error);
			}
		}
	}
}

if (!function_exists('profile_note_shares_ensure_missing_standard_columns')) {
	/**
	 * Legacy or hand-made tables may omit columns the app expects (e.g. share_add ON DUPLICATE KEY UPDATE updated_at).
	 */
	function profile_note_shares_ensure_missing_standard_columns(mysqli $connect): void {
		$f = profile_note_shares_field_map($connect);
		if (empty($f)) {
			return;
		}
		if (!isset($f['created_at'])) {
			if (!$connect->query(
				'ALTER TABLE `profile_note_shares` ADD COLUMN `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP'
			)) {
				error_log('[profile_note_shares] ADD created_at failed: ' . $connect->error);
			}
			$f = profile_note_shares_field_map($connect);
		}
		if (!isset($f['updated_at'])) {
			if (!$connect->query(
				'ALTER TABLE `profile_note_shares` ADD COLUMN `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
			)) {
				error_log('[profile_note_shares] ADD updated_at failed: ' . $connect->error);
			}
			$f = profile_note_shares_field_map($connect);
		}
		if (!isset($f['permission'])) {
			if (!$connect->query(
				"ALTER TABLE `profile_note_shares` ADD COLUMN `permission` ENUM('view','edit') NOT NULL DEFAULT 'view'"
			)) {
				error_log('[profile_note_shares] ADD permission failed: ' . $connect->error);
			}
			$f = profile_note_shares_field_map($connect);
		}
		if (!isset($f['shared_with_user_id'])) {
			if (!$connect->query('ALTER TABLE `profile_note_shares` ADD COLUMN `shared_with_user_id` INT NOT NULL DEFAULT 0')) {
				error_log('[profile_note_shares] ADD shared_with_user_id failed: ' . $connect->error);
			}
		}
		$f = profile_note_shares_field_map($connect);
		$hasUq = false;
		$idx = $connect->query('SHOW INDEX FROM `profile_note_shares`');
		if ($idx) {
			while ($row = $idx->fetch_assoc()) {
				if (($row['Key_name'] ?? '') === 'uq_profile_note_share') {
					$hasUq = true;
					break;
				}
			}
			$idx->free();
		}
		if (!$hasUq && isset($f['profile_note_id']) && isset($f['shared_with_user_id'])) {
			if (!$connect->query(
				'ALTER TABLE `profile_note_shares` ADD UNIQUE KEY `uq_profile_note_share` (`profile_note_id`,`shared_with_user_id`)'
			)) {
				error_log('[profile_note_shares] ADD UNIQUE uq_profile_note_share failed: ' . $connect->error);
			}
		}
	}
}

if (!function_exists('profile_note_shares_ensure_table')) {
	function profile_note_shares_ensure_table(mysqli $connect): void {
		$r = $connect->query("SHOW TABLES LIKE 'profile_note_shares'");
		$tableExists = $r && $r->num_rows > 0;
		if ($r instanceof mysqli_result) {
			$r->free();
		}
		if (!$tableExists) {
			$sql = "CREATE TABLE IF NOT EXISTS `profile_note_shares` (
				`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
				`profile_note_id` INT NOT NULL,
				`creator_user_id` INT NOT NULL,
				`shared_with_user_id` INT NOT NULL,
				`permission` ENUM('view','edit') NOT NULL DEFAULT 'view',
				`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				`updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY (`id`),
				UNIQUE KEY `uq_profile_note_share` (`profile_note_id`,`shared_with_user_id`),
				KEY `idx_pn_shares_creator` (`creator_user_id`),
				KEY `idx_pn_shares_target` (`shared_with_user_id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
			$connect->query($sql);
			return;
		}
		profile_note_shares_migrate_legacy_columns($connect);
		profile_note_shares_ensure_missing_standard_columns($connect);
	}
}

if (!function_exists('profile_note_share_get_note_for_creator')) {
	function profile_note_share_get_note_for_creator(mysqli $connect, int $noteId, int $subjectUserId, int $creatorId, string $creatorType): ?array {
		$ct = ($creatorType === 'staff') ? 'staff' : 'admin';
		$noteId = (int) $noteId;
		$subjectUserId = (int) $subjectUserId;
		$creatorId = (int) $creatorId;
		$ctEsc = $connect->real_escape_string($ct);
		$sql = "SELECT id, user_id, creator_id, creator_type FROM profile_notes WHERE id={$noteId} AND user_id={$subjectUserId} AND creator_id={$creatorId} AND creator_type='{$ctEsc}' LIMIT 1";
		$res = $connect->query($sql);
		if (!$res) {
			return null;
		}
		$row = $res->fetch_assoc();
		$res->free();
		return $row ?: null;
	}
}

if (!function_exists('profile_note_share_recipient_access')) {
	/**
	 * Client (or any user) access to a profile note via profile_note_shares.
	 *
	 * @return array{can_view:bool,can_edit:bool,permission:string}
	 */
	function profile_note_share_recipient_access(mysqli $connect, int $profileNoteId, int $recipientUserId): array {
		$profileNoteId = (int) $profileNoteId;
		$recipientUserId = (int) $recipientUserId;
		if ($profileNoteId <= 0 || $recipientUserId <= 0) {
			return ['can_view' => false, 'can_edit' => false, 'permission' => 'none'];
		}
		$sql = "SELECT s.permission FROM profile_note_shares s
			INNER JOIN profile_notes n ON n.id = s.profile_note_id
			WHERE s.profile_note_id = {$profileNoteId} AND s.shared_with_user_id = {$recipientUserId} LIMIT 1";
		$res = $connect->query($sql);
		$row = $res ? $res->fetch_assoc() : null;
		if ($res instanceof mysqli_result) {
			$res->free();
		}
		$perm = ($row && isset($row['permission'])) ? (string) $row['permission'] : '';
		if ($perm !== 'view' && $perm !== 'edit') {
			return ['can_view' => false, 'can_edit' => false, 'permission' => 'none'];
		}
		return [
			'can_view' => true,
			'can_edit' => $perm === 'edit',
			'permission' => $perm,
		];
	}
}

if (!function_exists('profile_note_shares_team_rows_by_note_ids')) {
	/**
	 * Rows for "Shared with" avatars on Documents cards (profile_note_shares → users).
	 *
	 * @return array<int, array<int, array{user_id:int, first_name:string, permission:string}>> profile_note_id => list
	 */
	function profile_note_shares_team_rows_by_note_ids(mysqli $connect, array $profileNoteIds): array {
		$ids = array_values(array_unique(array_filter(array_map('intval', $profileNoteIds), static function ($v) {
			return $v > 0;
		})));
		$out = [];
		if ($ids === []) {
			return $out;
		}
		$in = implode(',', $ids);
		$sql = "SELECT s.profile_note_id, s.shared_with_user_id, s.permission, u.firstName
			FROM profile_note_shares s
			LEFT JOIN users u ON u.id = s.shared_with_user_id
			WHERE s.profile_note_id IN ({$in})
			ORDER BY s.created_at ASC";
		$res = $connect->query($sql);
		if (!$res) {
			return $out;
		}
		while ($row = $res->fetch_assoc()) {
			$pid = (int) ($row['profile_note_id'] ?? 0);
			if ($pid <= 0) {
				continue;
			}
			if (!isset($out[$pid])) {
				$out[$pid] = [];
			}
			$perm = (string) ($row['permission'] ?? 'view');
			if ($perm !== 'edit' && $perm !== 'view') {
				$perm = 'view';
			}
			$out[$pid][] = [
				'user_id' => (int) ($row['shared_with_user_id'] ?? 0),
				'first_name' => (string) ($row['firstName'] ?? ''),
				'permission' => $perm,
			];
		}
		$res->free();
		return $out;
	}
}
