<?php
/**
 * Logger class
 *
 * @package Handy_Custom
 */

if (!defined('ABSPATH')) {
	exit;
}

class Handy_Custom_Logger {

	/**
	 * Initialize logger
	 */
	public static function init() {
		// Make global function available
		if (!function_exists('handy_custom_log')) {
			function handy_custom_log($message, $level = 'info') {
				Handy_Custom_Logger::log($message, $level);
			}
		}
	}

	/**
	 * Log a message
	 *
	 * @param string $message Log message
	 * @param string $level   Log level (info, warning, error)
	 */
	public static function log($message, $level = 'info') {
		// Check if logging is enabled - early return if disabled
		if (!self::is_logging_enabled()) {
			return;
		}

		$timestamp = current_time('Y-m-d H:i:s');
		$log_file = HANDY_CUSTOM_PLUGIN_DIR . 'logs/handy-custom-' . current_time('Y-m-d') . '.log';
		$formatted_message = "[$timestamp] [" . strtoupper($level) . "] $message" . PHP_EOL;

		// Write to log file with error handling
		if (file_put_contents($log_file, $formatted_message, FILE_APPEND | LOCK_EX) === false) {
			error_log('Handy Custom Plugin: Failed to write to log file');
		}
	}

	/**
	 * Check if logging is enabled
	 *
	 * @return bool
	 */
	private static function is_logging_enabled() {
		return defined('HANDY_CUSTOM_DEBUG') && HANDY_CUSTOM_DEBUG === true;
	}
}