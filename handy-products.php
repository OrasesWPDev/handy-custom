<?php
/**
 * Handy Custom Plugin
 *
 * @package           Handy_Custom
 * @author            Orases
 * @copyright         2023 Orases
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       Handy Custom
 * Plugin URI:        https://github.com/OrasesWPDev/handy-custom
 * Description:       A handy collection of custom WordPress functionality and utilities.
 * Version:           1.0.0
 * Requires at least: 5.3
 * Requires PHP:      7.2
 * Author:            Orases
 * Author URI:        https://orases.com
 * Text Domain:       handy-custom
 * Domain Path:       /languages
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Update URI:        https://github.com/OrasesWPDev/handy-custom
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
	exit;
}

// Define plugin constants
define('HANDY_CUSTOM_VERSION', '1.0.0');
define('HANDY_CUSTOM_PLUGIN_DIR', plugin_dir_path(__FILE__));

// Debug mode - toggle to enable/disable logging
define('HANDY_CUSTOM_DEBUG', false);

// Simple logger function
function handy_custom_log($message, $level = 'info') {
	if (!defined('HANDY_CUSTOM_DEBUG') || !HANDY_CUSTOM_DEBUG) {
		return;
	}

	$log_dir = HANDY_CUSTOM_PLUGIN_DIR . 'logs/';

	// Create logs directory if it doesn't exist
	if (!file_exists($log_dir)) {
		wp_mkdir_p($log_dir);
		file_put_contents($log_dir . 'index.php', '<?php // Silence is golden');
	}

	$timestamp = date('Y-m-d H:i:s');
	$log_file = $log_dir . 'handy-custom-' . date('Y-m-d') . '.log';
	$formatted_message = "[$timestamp] [$level] $message" . PHP_EOL;

	file_put_contents($log_file, $formatted_message, FILE_APPEND);
}

// Basic plugin initialization
function handy_custom_init() {
	handy_custom_log('Plugin initialized');
}
add_action('plugins_loaded', 'handy_custom_init');