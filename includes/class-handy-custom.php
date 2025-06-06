<?php
/**
 * Main plugin class
 *
 * @package Handy_Custom
 */

if (!defined('ABSPATH')) {
	exit;
}

class Handy_Custom {

	/**
	 * Plugin version
	 */
	const VERSION = '1.1.0';

	/**
	 * Single instance of the class
	 */
	private static $instance = null;

	/**
	 * Get instance
	 */
	public static function get_instance() {
		if (null === self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		$this->init_hooks();
		$this->load_includes();
	}

	/**
	 * Initialize hooks
	 */
	private function init_hooks() {
		add_action('plugins_loaded', array($this, 'init'));
		add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
		add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
	}

	/**
	 * Load include files
	 */
	private function load_includes() {
		// Core functionality
		require_once HANDY_CUSTOM_PLUGIN_DIR . 'includes/class-logger.php';
		
		// Frontend functionality (load conditionally)
		if (!is_admin()) {
			require_once HANDY_CUSTOM_PLUGIN_DIR . 'includes/class-shortcodes.php';
			
			// Product-specific functionality
			require_once HANDY_CUSTOM_PLUGIN_DIR . 'includes/products/class-products-utils.php';
			require_once HANDY_CUSTOM_PLUGIN_DIR . 'includes/products/class-products-filters.php';
			require_once HANDY_CUSTOM_PLUGIN_DIR . 'includes/products/class-products-display.php';
			require_once HANDY_CUSTOM_PLUGIN_DIR . 'includes/products/class-products-renderer.php';
			
			// Recipe-specific functionality
			require_once HANDY_CUSTOM_PLUGIN_DIR . 'includes/recipes/class-recipes-utils.php';
			require_once HANDY_CUSTOM_PLUGIN_DIR . 'includes/recipes/class-recipes-filters.php';
			require_once HANDY_CUSTOM_PLUGIN_DIR . 'includes/recipes/class-recipes-display.php';
			require_once HANDY_CUSTOM_PLUGIN_DIR . 'includes/recipes/class-recipes-renderer.php';
		}
	}

	/**
	 * Initialize plugin
	 */
	public function init() {
		// Setup logging if enabled
		if (defined('HANDY_CUSTOM_DEBUG') && HANDY_CUSTOM_DEBUG === true) {
			$this->setup_logging();
		}
		
		// Initialize logger
		Handy_Custom_Logger::init();
		
		// Initialize frontend functionality
		if (!is_admin()) {
			Handy_Custom_Shortcodes::init();
		}

		Handy_Custom_Logger::log('Plugin initialized');
	}

	/**
	 * Setup logging directory when logging is enabled
	 */
	private function setup_logging() {
		$log_dir = HANDY_CUSTOM_PLUGIN_DIR . 'logs/';
		
		// Create logs directory if it doesn't exist
		if (!file_exists($log_dir)) {
			wp_mkdir_p($log_dir);
			// Secure the logs directory
			file_put_contents($log_dir . 'index.php', '<?php // Silence is golden');
			file_put_contents($log_dir . '.htaccess', 'deny from all');
		}
	}

	/**
	 * Enqueue frontend assets
	 */
	public function enqueue_frontend_assets() {
		// Check if we're on a page with shortcodes
		global $post;
		$has_products_shortcode = false;
		$has_recipes_shortcode = false;
		
		if ($post) {
			$has_products_shortcode = has_shortcode($post->post_content, 'products');
			$has_recipes_shortcode = has_shortcode($post->post_content, 'recipes');
		}
		
		// Enqueue post-type-specific assets
		if ($has_products_shortcode) {
			$this->enqueue_products_assets();
		}
		
		if ($has_recipes_shortcode) {
			$this->enqueue_recipes_assets();
		}
		
		// Legacy support - load old custom files if they exist
		$this->enqueue_legacy_assets();
	}
	
	/**
	 * Enqueue products-specific assets
	 */
	private function enqueue_products_assets() {
		$css_file = HANDY_CUSTOM_PLUGIN_DIR . 'assets/css/products/archive.css';
		$js_file = HANDY_CUSTOM_PLUGIN_DIR . 'assets/js/products/archive.js';

		// Enqueue products CSS
		if (file_exists($css_file)) {
			$css_version = filemtime($css_file);
			wp_enqueue_style(
				'handy-custom-products',
				HANDY_CUSTOM_PLUGIN_URL . 'assets/css/products/archive.css',
				array(),
				$css_version
			);
		}

		// Enqueue products JS
		if (file_exists($js_file)) {
			$js_version = filemtime($js_file);
			wp_enqueue_script(
				'handy-custom-products',
				HANDY_CUSTOM_PLUGIN_URL . 'assets/js/products/archive.js',
				array('jquery'),
				$js_version,
				true
			);

			// Localize script for AJAX
			wp_localize_script('handy-custom-products', 'handyCustomAjax', array(
				'ajaxUrl' => admin_url('admin-ajax.php'),
				'nonce' => wp_create_nonce('handy_custom_nonce')
			));
		}
	}
	
	/**
	 * Enqueue recipes-specific assets
	 */
	private function enqueue_recipes_assets() {
		$css_file = HANDY_CUSTOM_PLUGIN_DIR . 'assets/css/recipes/archive.css';
		$js_file = HANDY_CUSTOM_PLUGIN_DIR . 'assets/js/recipes/archive.js';

		// Enqueue recipes CSS
		if (file_exists($css_file)) {
			$css_version = filemtime($css_file);
			wp_enqueue_style(
				'handy-custom-recipes',
				HANDY_CUSTOM_PLUGIN_URL . 'assets/css/recipes/archive.css',
				array(),
				$css_version
			);
		}

		// Enqueue recipes JS
		if (file_exists($js_file)) {
			$js_version = filemtime($js_file);
			wp_enqueue_script(
				'handy-custom-recipes',
				HANDY_CUSTOM_PLUGIN_URL . 'assets/js/recipes/archive.js',
				array('jquery'),
				$js_version,
				true
			);

			// Localize script for AJAX
			wp_localize_script('handy-custom-recipes', 'handyCustomRecipesAjax', array(
				'ajaxUrl' => admin_url('admin-ajax.php'),
				'nonce' => wp_create_nonce('handy_custom_nonce'),
				'action' => 'filter_recipes'
			));
		}
	}
	
	/**
	 * Enqueue legacy assets for backward compatibility
	 */
	private function enqueue_legacy_assets() {
		$css_file = HANDY_CUSTOM_PLUGIN_DIR . 'assets/css/custom.css';
		$js_file = HANDY_CUSTOM_PLUGIN_DIR . 'assets/js/custom.js';

		// Enqueue legacy CSS if it exists
		if (file_exists($css_file)) {
			$css_version = filemtime($css_file);
			wp_enqueue_style(
				'handy-custom-legacy',
				HANDY_CUSTOM_PLUGIN_URL . 'assets/css/custom.css',
				array(),
				$css_version
			);
		}

		// Enqueue legacy JS if it exists
		if (file_exists($js_file)) {
			$js_version = filemtime($js_file);
			wp_enqueue_script(
				'handy-custom-legacy',
				HANDY_CUSTOM_PLUGIN_URL . 'assets/js/custom.js',
				array('jquery'),
				$js_version,
				true
			);
		}
	}

	/**
	 * Enqueue admin assets
	 */
	public function enqueue_admin_assets() {
		// Admin-specific assets if needed in the future
	}
}