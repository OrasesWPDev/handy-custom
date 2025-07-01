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

		// Load the YahnisElsts Plugin Update Checker library
		require_once HANDY_CUSTOM_PLUGIN_DIR . 'includes/vendor/plugin-update-checker/plugin-update-checker.php';
		
		// Initialize the update checker for GitHub with 1-minute check period (using full namespace)
		$this->update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
			'https://github.com/OrasesWPDev/handy-custom/',
			$this->plugin_file,
			'handy-custom',
			1/60  // Check every minute for fast update detection
		);

		// Enable release assets for GitHub releases
		$this->update_checker->getVcsApi()->enableReleaseAssets();

		Handy_Custom_Logger::log('YahnisElsts Plugin Update Checker initialized with 1-minute check period (v1.9.9 auto-updater test)', 'info');
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