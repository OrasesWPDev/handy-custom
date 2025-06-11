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
	 * GitHub API base URL
	 * 
	 * @var string
	 */
	private $github_api_url;

	/**
	 * Cache key for version data
	 * 
	 * @var string
	 */
	private $cache_key;

	/**
	 * Cache expiration time (12 hours)
	 * 
	 * @var int
	 */
	private $cache_expiration;

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
		$this->github_api_url = "https://api.github.com/repos/{$github_owner}/{$github_repo}";
		$this->cache_key = "handy_custom_updater_" . md5($this->plugin_basename);
		$this->cache_expiration = 12 * HOUR_IN_SECONDS;

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

	/**
	 * Check for plugin updates from GitHub
	 *
	 * @param bool $force_check Force check even if cached data exists
	 * @return array|false Update data or false if no update
	 */
	public function check_for_updates($force_check = false) {
		// Get cached data first
		if (!$force_check) {
			$cached_data = get_transient($this->cache_key);
			if (false !== $cached_data) {
				return $cached_data;
			}
		}

		// Fetch remote version data
		$remote_data = $this->get_remote_version_data();
		
		if (!$remote_data) {
			Handy_Custom_Logger::log('Failed to fetch remote version data', 'warning');
			return false;
		}

		// Compare versions
		$update_available = version_compare($this->version, $remote_data['version'], '<');
		
		$result = array(
			'version' => $remote_data['version'],
			'update_available' => $update_available,
			'download_url' => $remote_data['download_url'],
			'details_url' => $remote_data['details_url'],
			'tested' => $remote_data['tested'] ?? '',
			'requires_php' => $remote_data['requires_php'] ?? '',
			'last_updated' => $remote_data['last_updated'] ?? '',
		);

		// Cache the result
		set_transient($this->cache_key, $result, $this->cache_expiration);

		Handy_Custom_Logger::log("Version check complete. Current: {$this->version}, Remote: {$remote_data['version']}, Update available: " . ($update_available ? 'yes' : 'no'), 'info');

		return $result;
	}

	/**
	 * Get remote version data from GitHub API
	 *
	 * @return array|false Remote version data or false on failure
	 */
	private function get_remote_version_data() {
		$api_url = $this->github_api_url . '/releases/latest';
		
		Handy_Custom_Logger::log("Fetching remote version from: {$api_url}", 'debug');

		// Make API request
		$response = wp_remote_get($api_url, array(
			'timeout' => 15,
			'headers' => array(
				'Accept' => 'application/vnd.github.v3+json',
				'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url()
			)
		));

		// Check for WP errors
		if (is_wp_error($response)) {
			Handy_Custom_Logger::log('GitHub API request failed: ' . $response->get_error_message(), 'error');
			return false;
		}

		// Check response code
		$response_code = wp_remote_retrieve_response_code($response);
		if (200 !== $response_code) {
			Handy_Custom_Logger::log("GitHub API returned non-200 response: {$response_code}", 'error');
			return false;
		}

		// Parse JSON response
		$body = wp_remote_retrieve_body($response);
		$data = json_decode($body, true);

		if (json_last_error() !== JSON_ERROR_NONE) {
			Handy_Custom_Logger::log('Failed to parse GitHub API response JSON', 'error');
			return false;
		}

		// Validate required fields
		if (!isset($data['tag_name']) || !isset($data['zipball_url'])) {
			Handy_Custom_Logger::log('GitHub API response missing required fields', 'error');
			return false;
		}

		// Parse version (remove 'v' prefix if present)
		$version = ltrim($data['tag_name'], 'v');

		// Build download URL (prefer zipball for consistency)
		$download_url = $data['zipball_url'];

		return array(
			'version' => $version,
			'download_url' => $download_url,
			'details_url' => $data['html_url'] ?? $this->get_repository_url(),
			'last_updated' => $data['published_at'] ?? '',
			'tested' => '', // Could be parsed from release notes if needed
			'requires_php' => '', // Could be parsed from release notes if needed
		);
	}

	/**
	 * Clear version cache
	 *
	 * @return bool True if cache was cleared
	 */
	public function clear_cache() {
		$result = delete_transient($this->cache_key);
		Handy_Custom_Logger::log('Version cache cleared', 'debug');
		return $result;
	}
}