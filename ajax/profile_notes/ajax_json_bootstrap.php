<?php
/**
 * Profile-notes AJAX: output buffer + JSON exit + debug logging to logs/profile_notes_ajax.log
 * Require this first in each JSON endpoint (before lib-initialize.php).
 */
if (!defined('PROFILE_NOTES_AJAX_JSON_BOOTSTRAP')) {
	define('PROFILE_NOTES_AJAX_JSON_BOOTSTRAP', true);
	if (ob_get_level() === 0) {
		ob_start();
	}
}

if (!function_exists('profile_notes_ajax_debug_log')) {
	function profile_notes_ajax_debug_log(string $message, array $context = []): void {
		$root = dirname(__DIR__, 2);
		$dir = $root . DIRECTORY_SEPARATOR . 'logs';
		if (!is_dir($dir)) {
			@mkdir($dir, 0755, true);
		}
		$path = $dir . DIRECTORY_SEPARATOR . 'profile_notes_ajax.log';
		$ctx = $context;
		if (isset($ctx['preview']) && is_string($ctx['preview']) && strlen($ctx['preview']) > 8000) {
			$ctx['preview'] = substr($ctx['preview'], 0, 8000) . '...[truncated]';
		}
		$line = date('Y-m-d H:i:s') . "\t" . $message;
		if ($ctx !== []) {
			$line .= "\t" . json_encode($ctx, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
		}
		$line .= "\n";
		@file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
	}
}

if (!function_exists('profile_notes_ajax_json_exit')) {
	function profile_notes_ajax_json_exit(array $payload, int $httpCode = 200): void {
		if (ob_get_level() > 0) {
			$buf = (string) ob_get_contents();
			if ($buf !== '' && trim($buf) !== '') {
				profile_notes_ajax_debug_log('discarded_output_buffer', [
					'script' => basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'unknown')),
					'http_code' => $httpCode,
					'payload_status' => $payload['status'] ?? null,
					'bytes' => strlen($buf),
					'preview' => $buf,
				]);
			}
		}
		while (ob_get_level() > 0) {
			ob_end_clean();
		}
		http_response_code($httpCode);
		if (!headers_sent()) {
			header('Content-Type: application/json; charset=utf-8');
		}
		echo json_encode($payload);
		exit;
	}
}

if (!defined('PROFILE_NOTES_AJAX_ERR_HANDLER')) {
	define('PROFILE_NOTES_AJAX_ERR_HANDLER', true);
	set_error_handler(static function ($errno, $errstr, $errfile, $errline) {
		$sf = (string) ($_SERVER['SCRIPT_FILENAME'] ?? '');
		$norm = str_replace('\\', '/', strtolower($sf));
		if (strpos($norm, '/ajax/profile_notes/') === false) {
			return false;
		}
		if (!(bool) (error_reporting() & $errno)) {
			return false;
		}
		if (function_exists('profile_notes_ajax_debug_log')) {
			profile_notes_ajax_debug_log('php_error', [
				'errno' => $errno,
				'message' => $errstr,
				'file' => $errfile,
				'line' => $errline,
			]);
		}
		return false;
	});
}

if (!defined('PROFILE_NOTES_AJAX_SHUTDOWN_REGISTERED')) {
	define('PROFILE_NOTES_AJAX_SHUTDOWN_REGISTERED', true);
	register_shutdown_function(static function () {
		$sf = (string) ($_SERVER['SCRIPT_FILENAME'] ?? '');
		$norm = str_replace('\\', '/', strtolower($sf));
		if (strpos($norm, '/ajax/profile_notes/') === false) {
			return;
		}
		$err = error_get_last();
		if ($err === null) {
			return;
		}
		$fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
		if (!in_array((int) ($err['type'] ?? 0), $fatalTypes, true)) {
			return;
		}
		if (!function_exists('profile_notes_ajax_debug_log')) {
			return;
		}
		profile_notes_ajax_debug_log('shutdown_fatal', [
			'type' => $err['type'] ?? null,
			'message' => $err['message'] ?? '',
			'file' => $err['file'] ?? '',
			'line' => $err['line'] ?? 0,
		]);
	});
}
