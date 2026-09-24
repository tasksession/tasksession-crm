<?php
if (!defined('PROJECT_NOTES_AJAX_JSON_BOOTSTRAP')) {
	define('PROJECT_NOTES_AJAX_JSON_BOOTSTRAP', true);
	if (ob_get_level() === 0) {
		ob_start();
	}
}

if (!function_exists('project_notes_ajax_debug_log')) {
	function project_notes_ajax_debug_log(string $message, array $context = []): void {
		// No-op in production mode.
		return;
	}
}

if (!function_exists('project_notes_ajax_json_exit')) {
	function project_notes_ajax_json_exit(array $payload, int $httpCode = 200): void {
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
