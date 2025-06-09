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
	const VERSION = '1.6.4';

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
		
		// URL rewrite hooks
		add_action('init', array($this, 'add_rewrite_rules'));
		add_filter('query_vars', array($this, 'add_query_vars'));
		add_action('template_redirect', array($this, 'handle_product_urls'));
	}

	/**
	 * Load include files
	 */
	private function load_includes() {
		// Core functionality
		require_once HANDY_CUSTOM_PLUGIN_DIR . 'includes/class-logger.php';
		require_once HANDY_CUSTOM_PLUGIN_DIR . 'includes/class-base-utils.php';
		
		// Admin functionality (load conditionally)
		if (is_admin()) {
			require_once HANDY_CUSTOM_PLUGIN_DIR . 'includes/class-admin.php';
		}
		
		// Frontend functionality (load conditionally)
		if (!is_admin()) {
			require_once HANDY_CUSTOM_PLUGIN_DIR . 'includes/class-shortcodes.php';
			
			// Unified filter system
			require_once HANDY_CUSTOM_PLUGIN_DIR . 'includes/class-filters-renderer.php';
			
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
		
		// Initialize cache invalidation hooks
		Handy_Custom_Base_Utils::init_cache_invalidation();
		
		// Clear cache if plugin version has changed
		Handy_Custom_Base_Utils::clear_version_cache();
		
		// Initialize admin functionality
		if (is_admin()) {
			Handy_Custom_Admin::init();
		}
		
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
		$has_filter_shortcode = false;
		
		if ($post) {
			$has_products_shortcode = has_shortcode($post->post_content, 'products');
			$has_recipes_shortcode = has_shortcode($post->post_content, 'recipes');
			$has_filter_shortcode = has_shortcode($post->post_content, 'filter-products') || 
									has_shortcode($post->post_content, 'filter-recipes');
		}
		
		// Enqueue filter assets if filter shortcodes are present
		if ($has_filter_shortcode) {
			$this->enqueue_filter_assets();
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
	 * Enqueue post-type-specific assets
	 *
	 * @param string $type Post type (products or recipes)
	 * @param array $localize_data Additional data for script localization
	 */
	private function enqueue_post_type_assets($type, $localize_data = array()) {
		$css_file = HANDY_CUSTOM_PLUGIN_DIR . "assets/css/{$type}/archive.css";
		$js_file = HANDY_CUSTOM_PLUGIN_DIR . "assets/js/{$type}/archive.js";

		// Enqueue CSS
		if (file_exists($css_file)) {
			$css_version = filemtime($css_file);
			wp_enqueue_style(
				"handy-custom-{$type}",
				HANDY_CUSTOM_PLUGIN_URL . "assets/css/{$type}/archive.css",
				array(),
				$css_version
			);
		}

		// Enqueue JS
		if (file_exists($js_file)) {
			$js_version = filemtime($js_file);
			wp_enqueue_script(
				"handy-custom-{$type}",
				HANDY_CUSTOM_PLUGIN_URL . "assets/js/{$type}/archive.js",
				array('jquery'),
				$js_version,
				true
			);

			// Default localization data
			$default_localize_data = array(
				'ajaxUrl' => admin_url('admin-ajax.php'),
				'nonce' => wp_create_nonce('handy_custom_nonce')
			);

			// Merge with any additional data passed
			$localize_data = array_merge($default_localize_data, $localize_data);

			// Use appropriate localization variable name
			$localize_var = ($type === 'recipes') ? 'handyCustomRecipesAjax' : 'handyCustomAjax';
			wp_localize_script("handy-custom-{$type}", $localize_var, $localize_data);
		}
	}

	/**
	 * Enqueue unified filter assets
	 * Used for [filter-products] and [filter-recipes] shortcodes
	 */
	private function enqueue_filter_assets() {
		$css_file = HANDY_CUSTOM_PLUGIN_DIR . 'assets/css/filters.css';
		$js_file = HANDY_CUSTOM_PLUGIN_DIR . 'assets/js/filters.js';

		// Enqueue filter CSS
		if (file_exists($css_file)) {
			$css_version = filemtime($css_file);
			wp_enqueue_style(
				'handy-custom-filters',
				HANDY_CUSTOM_PLUGIN_URL . 'assets/css/filters.css',
				array(),
				$css_version
			);
			
			Handy_Custom_Logger::log('Filter CSS enqueued', 'debug');
		}

		// Enqueue filter JS
		if (file_exists($js_file)) {
			$js_version = filemtime($js_file);
			wp_enqueue_script(
				'handy-custom-filters',
				HANDY_CUSTOM_PLUGIN_URL . 'assets/js/filters.js',
				array('jquery'),
				$js_version,
				true
			);

			// Localize script with AJAX data and debug flag
			$localize_data = array(
				'ajaxUrl' => admin_url('admin-ajax.php'),
				'nonce' => wp_create_nonce('handy_custom_nonce'),
				'debug' => defined('HANDY_CUSTOM_DEBUG') && HANDY_CUSTOM_DEBUG === true
			);

			wp_localize_script('handy-custom-filters', 'handyCustomFiltersAjax', $localize_data);
			
			Handy_Custom_Logger::log('Filter JS enqueued with debug: ' . ($localize_data['debug'] ? 'enabled' : 'disabled'), 'debug');
		}
	}

	/**
	 * Enqueue products-specific assets
	 */
	private function enqueue_products_assets() {
		$this->enqueue_post_type_assets('products');
	}
	
	/**
	 * Enqueue recipes-specific assets
	 */
	private function enqueue_recipes_assets() {
		$this->enqueue_post_type_assets('recipes', array(
			'action' => 'filter_recipes'
		));
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

	/**
	 * Add URL rewrite rules for product categories, subcategories, and single products
	 */
	public function add_rewrite_rules() {
		// /products/{category}/{product-slug}/ - single product page
		add_rewrite_rule(
			'^products/([^/]+)/([^/]+)/?$',
			'index.php?post_type=product&product_category=$matches[1]&product_slug=$matches[2]',
			'top'
		);

		// /products/{category}/ - category page
		add_rewrite_rule(
			'^products/([^/]+)/?$',
			'index.php?pagename=products&product_category=$matches[1]',
			'top'
		);

		Handy_Custom_Logger::log('Product URL rewrite rules added (including single products)', 'info');
	}

	/**
	 * Add custom query variables
	 */
	public function add_query_vars($vars) {
		$vars[] = 'product_category';
		$vars[] = 'product_subcategory';
		$vars[] = 'product_slug';
		return $vars;
	}

	/**
	 * Handle product URL redirects and parameter injection
	 * Handles both category pages and single product pages
	 */
	public function handle_product_urls() {
		// Skip processing entirely in admin contexts to prevent editing interference
		if (is_admin()) {
			return;
		}
		
		$category = get_query_var('product_category');
		$subcategory = get_query_var('product_subcategory');
		$product_slug = get_query_var('product_slug');

		// Handle single product URLs first
		if (!empty($category) && !empty($product_slug)) {
			$this->handle_single_product_url($category, $product_slug);
			return;
		}

		// Only process category/subcategory if we have those parameters
		if (empty($category) && empty($subcategory)) {
			return;
		}

		// Validate that we're on the products page for category handling
		if (!is_page('products')) {
			return;
		}

		// Only set global parameters if page contains product shortcodes
		// This prevents forcing shortcode behavior on pages meant for UX Builder editing
		global $post;
		if ($post && !empty($post->post_content)) {
			$has_product_shortcodes = has_shortcode($post->post_content, 'products') || 
									  has_shortcode($post->post_content, 'filter-products');
			
			if (!$has_product_shortcodes) {
				Handy_Custom_Logger::log("URL parameters ignored - no product shortcodes found on page", 'info');
				return;
			}
		}

		// Store parameters for shortcode access
		if (!empty($category)) {
			$GLOBALS['handy_custom_url_category'] = sanitize_text_field($category);
			Handy_Custom_Logger::log("URL category parameter detected: {$category}", 'info');
		}

		if (!empty($subcategory)) {
			$GLOBALS['handy_custom_url_subcategory'] = sanitize_text_field($subcategory);
			Handy_Custom_Logger::log("URL subcategory parameter detected: {$subcategory}", 'info');
		}
	}

	/**
	 * Handle single product URL requests
	 * Serves content directly on /products/{category}/{product-slug}/ URLs
	 *
	 * @param string $category Product category slug
	 * @param string $product_slug Product post slug
	 */
	private function handle_single_product_url($category, $product_slug) {
		Handy_Custom_Logger::log("Single product URL detected: category={$category}, slug={$product_slug}", 'info');
		
		// Find product by slug
		$product = get_posts(array(
			'name' => $product_slug,
			'post_type' => 'product',
			'post_status' => 'publish',
			'numberposts' => 1
		));
		
		if (empty($product)) {
			Handy_Custom_Logger::log("Product not found for slug: {$product_slug}", 'warning');
			// Let WordPress handle the 404
			return;
		}
		
		$product_post = $product[0];
		
		// Verify the product belongs to the specified category
		$product_categories = wp_get_post_terms($product_post->ID, 'product-category');
		$category_match = false;
		
		foreach ($product_categories as $term) {
			if ($term->slug === $category || 
				($term->parent && get_term($term->parent)->slug === $category)) {
				$category_match = true;
				break;
			}
		}
		
		if (!$category_match) {
			Handy_Custom_Logger::log("Product {$product_slug} does not belong to category {$category}", 'warning');
			// Let WordPress handle the 404
			return;
		}
		
		// Set up WordPress to display this product directly
		$this->setup_single_product_display($product_post, $category);
		
		Handy_Custom_Logger::log("Serving product directly on clean URL: /products/{$category}/{$product_slug}/", 'info');
	}

	/**
	 * Setup WordPress to display a single product on clean URLs
	 * 
	 * @param WP_Post $product_post Product post object
	 * @param string $category Category slug for breadcrumb context
	 */
	private function setup_single_product_display($product_post, $category) {
		global $wp_query, $post;
		
		// Set up the main query as if this is a single product page
		$wp_query->is_single = true;
		$wp_query->is_singular = true;
		$wp_query->is_404 = false;
		$wp_query->is_page = false;
		$wp_query->is_home = false;
		$wp_query->is_archive = false;
		
		// Set the queried object
		$wp_query->queried_object = $product_post;
		$wp_query->queried_object_id = $product_post->ID;
		
		// Set post data
		$wp_query->post = $product_post;
		$wp_query->posts = array($product_post);
		$wp_query->post_count = 1;
		$wp_query->found_posts = 1;
		
		// Set global post
		$post = $product_post;
		setup_postdata($post);
		
		// Store category context for breadcrumbs
		$GLOBALS['handy_custom_single_product_category'] = $category;
		
		Handy_Custom_Logger::log("WordPress query setup for single product display: ID={$product_post->ID}", 'debug');
	}

	/**
	 * Get URL-based product parameters for shortcode use
	 * 
	 * @return array Array of URL-based parameters
	 */
	public static function get_url_parameters() {
		$params = array();

		if (isset($GLOBALS['handy_custom_url_category'])) {
			$params['category'] = $GLOBALS['handy_custom_url_category'];
		}

		if (isset($GLOBALS['handy_custom_url_subcategory'])) {
			$params['subcategory'] = $GLOBALS['handy_custom_url_subcategory'];
		}

		return $params;
	}

	/**
	 * Get single product category context for breadcrumbs
	 * Used by Yoast and other breadcrumb systems
	 * 
	 * @return string|false Category slug if on single product URL, false otherwise
	 */
	public static function get_single_product_category() {
		return isset($GLOBALS['handy_custom_single_product_category']) 
			? $GLOBALS['handy_custom_single_product_category'] 
			: false;
	}
}