<?php
/**
 * Plugin Auto-Updater Class
 *
 * Handles automatic plugin updates from GitHub releases
 *
 * @package Handy_Custom
 */

if (!defined('ABSPATH')) {
	exit;
}

class Handy_Custom_Plugin_Updater {

	/**
	 * Plugin file path
	 * 
	 * @var string
	 */
	private $plugin_file;

	/**
	 * Plugin basename
	 * 
	 * @var string
	 */
	private $plugin_basename;

	/**
	 * Current plugin version
	 * 
	 * @var string
	 */
	private $version;

	/**
	 * GitHub repository owner
	 * 
	 * @var string
	 */
	private $github_owner;

	/**
	 * GitHub repository name
	 * 
	 * @var string
	 */
	private $github_repo;

	/**
	 * Plugin slug
	 * 
	 * @var string
	 */
	private $plugin_slug;

	/**
	 * Constructor
	 *
	 * @param string $plugin_file Full path to main plugin file
	 * @param string $version Current plugin version
	 * @param string $github_owner GitHub repository owner
	 * @param string $github_repo GitHub repository name
	 */
	public function __construct($plugin_file, $version, $github_owner, $github_repo) {
		$this->plugin_file = $plugin_file;
		$this->plugin_basename = plugin_basename($plugin_file);
		$this->version = $version;
		$this->github_owner = $github_owner;
		$this->github_repo = $github_repo;
		$this->plugin_slug = dirname($this->plugin_basename);

		$this->init();
	}

	/**
	 * Initialize updater hooks
	 */
	private function init() {
		// Only run updater in admin
		if (!is_admin()) {
			return;
		}

		// Log initialization
		Handy_Custom_Logger::log('Plugin updater initialized for ' . $this->plugin_basename, 'info');
	}

	/**
	 * Get current plugin version
	 *
	 * @return string Current version
	 */
	public function get_current_version() {
		return $this->version;
	}

	/**
	 * Get GitHub repository URL
	 *
	 * @return string Repository URL
	 */
	public function get_repository_url() {
		return "https://github.com/{$this->github_owner}/{$this->github_repo}";
	}

	/**
	 * Get plugin slug
	 *
	 * @return string Plugin slug
	 */
	public function get_plugin_slug() {
		return $this->plugin_slug;
	}

	/**
	 * Get plugin basename
	 *
	 * @return string Plugin basename
	 */
	public function get_plugin_basename() {
		return $this->plugin_basename;
	}
}