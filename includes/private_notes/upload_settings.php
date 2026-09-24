<?php

function privateNotesEnsureUploadSettingsTable($database)
{
    static $ensured = false;
    if ($ensured) {
        return;
    }
    $ensured = true;
    $sql = "CREATE TABLE IF NOT EXISTS private_note_upload_settings (
                id INT UNSIGNED NOT NULL PRIMARY KEY,
                max_file_size_mb INT UNSIGNED NOT NULL DEFAULT 8,
                allowed_extensions VARCHAR(255) NOT NULL DEFAULT 'jpg,jpeg,png,gif,webp',
                updated_at DATETIME NULL DEFAULT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $database->query($sql);
}

function privateNotesSanitizeAllowedExtensions($value)
{
    $parts = preg_split('/[\s,]+/', strtolower((string)$value));
    $clean = [];
    foreach ($parts as $part) {
        $ext = preg_replace('/[^a-z0-9]/', '', (string)$part);
        if ($ext === '') {
            continue;
        }
        $clean[$ext] = true;
    }
    if (empty($clean)) {
        return ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    }
    return array_keys($clean);
}

function privateNotesGetUploadSettings($database)
{
    privateNotesEnsureUploadSettingsTable($database);
    $defaults = [
        'max_file_size_mb' => 8,
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp']
    ];

    $sql = "SELECT max_file_size_mb, allowed_extensions FROM private_note_upload_settings WHERE id = 1 LIMIT 1";
    $res = $database->query($sql);
    $row = $res ? $database->fetchArray($res) : null;
    if (!$row) {
        return $defaults;
    }

    $maxMb = (int)($row['max_file_size_mb'] ?? 8);
    if ($maxMb <= 0) {
        $maxMb = 8;
    }
    $exts = privateNotesSanitizeAllowedExtensions((string)($row['allowed_extensions'] ?? ''));
    return [
        'max_file_size_mb' => $maxMb,
        'allowed_extensions' => $exts
    ];
}

function privateNotesSaveUploadSettings($database, $maxMb, $allowedExtensionsCsv)
{
    privateNotesEnsureUploadSettingsTable($database);
    $maxMb = max(1, min(100, (int)$maxMb));
    $exts = privateNotesSanitizeAllowedExtensions($allowedExtensionsCsv);
    $extCsv = implode(',', $exts);
    $esc = $database->escapeValue($extCsv);
    $sql = "INSERT INTO private_note_upload_settings (id, max_file_size_mb, allowed_extensions, updated_at)
            VALUES (1, {$maxMb}, '{$esc}', NOW())
            ON DUPLICATE KEY UPDATE
              max_file_size_mb = VALUES(max_file_size_mb),
              allowed_extensions = VALUES(allowed_extensions),
              updated_at = VALUES(updated_at)";
    return (bool)$database->query($sql);
}

if (!function_exists('privateNotesValidateUploadedImage')) {
	/**
	 * Resolves a reliable image/* MIME for temp upload files. On some Windows/PHP stacks,
	 * mime_content_type() returns empty or application/octet-stream for valid PNG/JPEG.
	 *
	 * @return string|null The MIME to store, or null if the file is not a valid image for the allowed ext list.
	 */
	function privateNotesValidateUploadedImage(string $tmpPath, string $ext, array $allowedExt): ?string
	{
		$ext = strtolower($ext);
		if (!is_file($tmpPath) || $ext === '' || !in_array($ext, $allowedExt, true)) {
			return null;
		}

		$byExt = [
			'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
			'gif' => 'image/gif', 'webp' => 'image/webp',
		];
		$cands = [];
		if (function_exists('mime_content_type')) {
			$mc = @mime_content_type($tmpPath);
			if (is_string($mc) && $mc !== '') {
				$cands[] = $mc;
			}
		}
		if (function_exists('finfo_open')) {
			$fi = @finfo_open(FILEINFO_MIME_TYPE);
			if ($fi) {
				$ft = (string) @finfo_file($fi, $tmpPath);
				finfo_close($fi);
				if ($ft !== '') {
					$cands[] = $ft;
				}
			}
		}
		$img = @getimagesize($tmpPath);
		if (is_array($img) && !empty($img['mime']) && is_string($img['mime'])) {
			$cands[] = (string) $img['mime'];
		}
		if (is_array($img) && !empty($img[0]) && (int) $img[0] > 0 && (int) ($img[1] ?? 0) > 0 && isset($byExt[$ext])) {
			$cands[] = $byExt[$ext];
		}
		if (isset($byExt[$ext])) {
			$cands[] = $byExt[$ext];
		}
		$cands = array_values(array_filter(array_unique($cands), static function ($m) {
			return is_string($m) && $m !== '';
		}));
		foreach ($cands as $m) {
			if (strpos($m, 'image/') === 0) {
				return $m;
			}
		}
		// getimagesize dimensions without mime (rare) — require extension + dimensions
		if (is_array($img) && isset($img[0], $img[1]) && (int) $img[0] > 0 && (int) $img[1] > 0) {
			return $byExt[$ext] ?? null;
		}
		return null;
	}
}
