<?php
/**
 * Simple Plugin Updater using YahnisElsts library
 *
 * Replaces custom WordPress hook implementation with industry-standard library
 * that doesn't interfere with WordPress core update processes.
 *
 * @package Handy_Custom
 */

if (!defined('ABSPATH')) {
	exit;
}

class Handy_Custom_Simple_Updater {

	/**
	 * Plugin update checker instance
	 * 
	 * @var object
	 */
	private $update_checker;

	/**
	 * Plugin file path
	 * 
	 * @var string
	 */
	private $plugin_file;

	/**
	 * Constructor
	 *
	 * @param string $plugin_file Full path to main plugin file
	 */
	public function __construct($plugin_file) {
		$this->plugin_file = $plugin_file;
		$this->init();
	}

	/**
	 * Initialize the updater
	 */
	private function init() {
		// Only run updater in admin
		if (!is_admin()) {
			return;
		}

		try {
			// Verify plugin file exists
			if (!file_exists($this->plugin_file)) {
				throw new Exception('Plugin file not found: ' . $this->plugin_file);
			}
			
			// Load the YahnisElsts Plugin Update Checker library
			require_once HANDY_CUSTOM_PLUGIN_DIR . 'includes/vendor/plugin-update-checker/plugin-update-checker.php';
			
			// Initialize the update checker for GitHub with minimal configuration
			$this->update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
				'https://github.com/OrasesWPDev/handy-custom/',
				$this->plugin_file,
				'handy-custom'  // Simplified - use defaults for other parameters
			);

			// Enable release assets for GitHub releases
			$this->update_checker->getVcsApi()->enableReleaseAssets();
			
			// Add custom filter to check if WordPress recognizes this plugin
			add_filter('plugins_api', array($this, 'debug_plugins_api'), 10, 3);
		} catch (Exception $e) {
			$error_msg = 'YahnisElsts Plugin Update Checker initialization failed: ' . $e->getMessage();
			Handy_Custom_Logger::log($error_msg, 'error');
			error_log('Handy Custom Plugin: ' . $error_msg);
			return;
		}
		
		// Add debug information
		$debug_info = array(
			'plugin_file' => $this->plugin_file,
			'update_checker_class' => get_class($this->update_checker),
			'is_admin' => is_admin(),
			'current_hook' => current_action()
		);

		$success_msg = 'YahnisElsts Plugin Update Checker initialized successfully. Debug: ' . wp_json_encode($debug_info);
		Handy_Custom_Logger::log($success_msg, 'info');
		error_log('Handy Custom Plugin: ' . $success_msg);
	}

	/**
	 * Get the update checker instance (for debugging)
	 *
	 * @return object|null Update checker instance
	 */
	public function get_update_checker() {
		return $this->update_checker;
	}

	/**
	 * Force check for updates (for testing)
	 *
	 * @return bool True if update check was performed
	 */
	public function force_update_check() {
		if (!$this->update_checker) {
			return false;
		}

		try {
			$this->update_checker->checkForUpdates();
			Handy_Custom_Logger::log('Forced update check completed', 'info');
			return true;
		} catch (Exception $e) {
			Handy_Custom_Logger::log('Forced update check failed: ' . $e->getMessage(), 'error');
			return false;
		}
	}

	/**
	 * Debug filter for plugins_api to see if WordPress recognizes our plugin
	 */
	public function debug_plugins_api($result, $action, $args) {
		if ($action === 'plugin_information' && isset($args->slug) && $args->slug === 'handy-custom') {
			$debug_msg = 'WordPress plugins_api called for handy-custom: ' . wp_json_encode($args);
			Handy_Custom_Logger::log($debug_msg, 'info');
			error_log('Handy Custom Plugin: ' . $debug_msg);
		}
		return $result;
	}

	/**
	 * Manual update check for testing
	 *
	 * @return array Result of update check
	 */
	public function manual_update_check() {
		if (!$this->update_checker) {
			return array('error' => 'Update checker not initialized');
		}

		try {
			$update = $this->update_checker->checkForUpdates();
			$result = array(
				'success' => true,
				'has_update' => !empty($update),
				'current_version' => HANDY_CUSTOM_VERSION,
				'remote_version' => $update ? $update->version : null,
				'check_time' => current_time('mysql')
			);
			
			$debug_msg = 'Manual update check completed: ' . wp_json_encode($result);
			Handy_Custom_Logger::log($debug_msg, 'info');
			error_log('Handy Custom Plugin: ' . $debug_msg);
			
			return $result;
		} catch (Exception $e) {
			$error_result = array('error' => $e->getMessage());
			$error_msg = 'Manual update check failed: ' . wp_json_encode($error_result);
			Handy_Custom_Logger::log($error_msg, 'error');
			error_log('Handy Custom Plugin: ' . $error_msg);
			return $error_result;
		}
	}

	/**
	 * Get updater status information (for debugging)
	 *
	 * @return array Status information
	 */
	public function get_status() {
		if (!$this->update_checker) {
			return array(
				'error' => 'Update checker not initialized'
			);
		}

		$status = array(
			'plugin_file' => $this->plugin_file,
			'library_version' => 'YahnisElsts v5.6',
			'repository_url' => 'https://github.com/OrasesWPDev/handy-custom/',
			'update_checker_class' => get_class($this->update_checker),
			'is_admin' => is_admin(),
		);

		// Try to get update information
		try {
			$update = $this->update_checker->getUpdate();
			$status['has_update'] = !empty($update);
			if ($update) {
				$status['remote_version'] = $update->version;
				$status['download_url'] = $update->download_url;
			}
		} catch (Exception $e) {
			$status['update_check_error'] = $e->getMessage();
		}

		return $status;
	}
}