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
	const VERSION = '1.8.5';

	/**
	 * Single instance of the class
	 */
	private static $instance = null;

	/**
	 * Plugin updater instance
	 */
	private $updater = null;

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
		
		// Permalink generation hooks
		add_filter('post_type_link', array($this, 'custom_product_permalink'), 10, 2);
		
		// Post and category management hooks - regenerate rewrite rules when posts or categories change
		add_action('created_product-category', array($this, 'regenerate_rewrite_rules'));
		add_action('edited_product-category', array($this, 'regenerate_rewrite_rules'));
		add_action('deleted_product-category', array($this, 'regenerate_rewrite_rules'));
		add_action('created_recipe-category', array($this, 'regenerate_rewrite_rules'));
		add_action('edited_recipe-category', array($this, 'regenerate_rewrite_rules'));
		add_action('deleted_recipe-category', array($this, 'regenerate_rewrite_rules'));
		
		// Post hooks - regenerate rewrite rules when posts are created/updated/deleted
		add_action('save_post_product', array($this, 'regenerate_rewrite_rules'));
		add_action('save_post_recipe', array($this, 'regenerate_rewrite_rules'));
		add_action('before_delete_post', array($this, 'regenerate_rewrite_rules'));

		// Breadcrumb hooks for single products
		add_filter('wpseo_breadcrumb_links', array($this, 'modify_yoast_breadcrumbs'));
		add_filter('breadcrumb_trail_get_items', array($this, 'modify_breadcrumb_trail_items'));
		add_filter('woocommerce_get_breadcrumb', array($this, 'modify_woocommerce_breadcrumbs'));
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
			require_once HANDY_CUSTOM_PLUGIN_DIR . 'includes/class-plugin-updater.php';
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
			$this->init_updater();
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
	 * Regenerate rewrite rules when posts or categories are modified
	 * This ensures dynamic URL support for new/updated posts
	 */
	public function regenerate_rewrite_rules() {
		Handy_Custom_Logger::log('Posts or categories modified - regenerating rewrite rules', 'info');
		flush_rewrite_rules();
	}

	/**
	 * Add specific URL rewrite rules for actual published products and recipes only
	 * Generates individual rules for each published post to prevent WordPress page interference
	 * 
	 * User requirement: "there is to be no interference with any page creation via this code base 
	 * unless it is specifically a single post title in the products or recipe custom post type"
	 */
	public function add_rewrite_rules() {
		$product_rules_added = $this->add_specific_post_rewrite_rules('product', 'products');
		$recipe_rules_added = $this->add_specific_post_rewrite_rules('recipe', 'recipes');
		
		Handy_Custom_Logger::log("Specific rewrite rules added: {$product_rules_added} products, {$recipe_rules_added} recipes", 'info');
	}

	/**
	 * Add specific rewrite rules for actual published posts of given post type
	 * Only creates rules for posts that actually exist - no broad patterns
	 *
	 * @param string $post_type Post type slug (product or recipe)
	 * @param string $url_prefix URL prefix (products or recipes)
	 * @return int Number of rules added
	 */
	private function add_specific_post_rewrite_rules($post_type, $url_prefix) {
		// Get all published posts of this type
		$posts = get_posts(array(
			'post_type' => $post_type,
			'post_status' => 'publish',
			'posts_per_page' => -1,
			'fields' => 'ids'
		));

		if (empty($posts)) {
			Handy_Custom_Logger::log("No published {$post_type} posts found - no rewrite rules added", 'info');
			return 0;
		}

		$rules_added = 0;
		$taxonomy = $post_type . '-category';

		foreach ($posts as $post_id) {
			$post = get_post($post_id);
			if (!$post) continue;

			// Get the primary category for this post
			$primary_category = $this->get_post_primary_category($post_id, $taxonomy);
			if (!$primary_category) continue;

			// Create specific rewrite rule for this exact post
			$pattern = '^' . $url_prefix . '/' . preg_quote($primary_category->slug) . '/' . preg_quote($post->post_name) . '/?$';
			$rewrite = 'index.php?post_type=' . $post_type . '&' . $post_type . '_category=' . $primary_category->slug . '&' . $post_type . '_slug=' . $post->post_name;

			add_rewrite_rule($pattern, $rewrite, 'top');
			$rules_added++;

			Handy_Custom_Logger::log("Added specific rewrite rule: /{$url_prefix}/{$primary_category->slug}/{$post->post_name}/ -> {$post_type} ID {$post_id}", 'debug');
		}

		return $rules_added;
	}

	/**
	 * Get primary category for a post (top-level category preferred)
	 *
	 * @param int $post_id Post ID
	 * @param string $taxonomy Taxonomy name
	 * @return WP_Term|false Primary category term or false if none found
	 */
	private function get_post_primary_category($post_id, $taxonomy) {
		$categories = wp_get_post_terms($post_id, $taxonomy);
		
		if (is_wp_error($categories) || empty($categories)) {
			return false;
		}

		// Find top-level category (parent = 0) first
		foreach ($categories as $category) {
			if ($category->parent == 0) {
				return $category;
			}
		}

		// Fallback to first assigned category
		return $categories[0];
	}

	/**
	 * Add custom query variables for single product and recipe URLs
	 */
	public function add_query_vars($vars) {
		// Product query vars
		$vars[] = 'product_category';
		$vars[] = 'product_slug';
		
		// Recipe query vars
		$vars[] = 'recipe_category';
		$vars[] = 'recipe_slug';
		
		return $vars;
	}

	/**
	 * Handle single product and recipe URLs only
	 * Only processes specific post URLs that have been registered via rewrite rules
	 * All other URLs remain under WordPress page control
	 */
	public function handle_product_urls() {
		// Skip processing entirely in admin contexts to prevent editing interference
		if (is_admin()) {
			return;
		}
		
		// Check for product URLs
		$product_category = get_query_var('product_category');
		$product_slug = get_query_var('product_slug');
		
		if (!empty($product_category) && !empty($product_slug)) {
			$this->handle_single_post_url('product', $product_category, $product_slug);
			return;
		}
		
		// Check for recipe URLs
		$recipe_category = get_query_var('recipe_category');
		$recipe_slug = get_query_var('recipe_slug');
		
		if (!empty($recipe_category) && !empty($recipe_slug)) {
			$this->handle_single_post_url('recipe', $recipe_category, $recipe_slug);
			return;
		}

		// If we don't have the required parameters, this is not a custom post URL
		// Let WordPress handle all other URLs as regular pages
		Handy_Custom_Logger::log('Not a custom post URL - letting WordPress handle page routing', 'debug');
	}

	/**
	 * Handle single post URL requests for products and recipes
	 * Serves content directly on custom URLs
	 *
	 * @param string $post_type Post type (product or recipe)
	 * @param string $category Category slug
	 * @param string $post_slug Post slug
	 */
	private function handle_single_post_url($post_type, $category, $post_slug) {
		Handy_Custom_Logger::log("Single {$post_type} URL detected: category={$category}, slug={$post_slug}", 'info');
		
		// Find post by slug and type
		$posts = get_posts(array(
			'name' => $post_slug,
			'post_type' => $post_type,
			'post_status' => 'publish',
			'numberposts' => 1
		));
		
		if (empty($posts)) {
			Handy_Custom_Logger::log("{$post_type} not found for slug: {$post_slug}", 'warning');
			// Let WordPress handle the 404
			return;
		}
		
		$post = $posts[0];
		$taxonomy = $post_type . '-category';
		
		// Verify the post belongs to the specified category
		$post_categories = wp_get_post_terms($post->ID, $taxonomy);
		$category_match = false;
		
		foreach ($post_categories as $term) {
			if ($term->slug === $category || 
				($term->parent && get_term($term->parent)->slug === $category)) {
				$category_match = true;
				break;
			}
		}
		
		if (!$category_match) {
			Handy_Custom_Logger::log("{$post_type} {$post_slug} does not belong to category {$category}", 'warning');
			// Let WordPress handle the 404
			return;
		}
		
		// Set up WordPress to display this post directly
		$this->setup_single_post_display($post, $category, $post_type);
		
		$url_prefix = $post_type === 'product' ? 'products' : 'recipes';
		Handy_Custom_Logger::log("Serving {$post_type} directly on clean URL: /{$url_prefix}/{$category}/{$post_slug}/", 'info');
	}

	/**
	 * Setup WordPress to display a single post on clean URLs (products or recipes)
	 * 
	 * @param WP_Post $post Post object
	 * @param string $category Category slug for breadcrumb context
	 * @param string $post_type Post type (product or recipe)
	 */
	private function setup_single_post_display($post, $category, $post_type) {
		global $wp_query;
		
		// Set up the main query as if this is a single post page
		$wp_query->is_single = true;
		$wp_query->is_singular = true;
		$wp_query->is_404 = false;
		$wp_query->is_page = false;
		$wp_query->is_home = false;
		$wp_query->is_archive = false;
		
		// Set the queried object
		$wp_query->queried_object = $post;
		$wp_query->queried_object_id = $post->ID;
		
		// Set post data
		$wp_query->post = $post;
		$wp_query->posts = array($post);
		$wp_query->post_count = 1;
		$wp_query->found_posts = 1;
		
		// Set global post
		$GLOBALS['post'] = $post;
		setup_postdata($post);
		
		// Store category context for breadcrumbs (maintain backward compatibility for products)
		if ($post_type === 'product') {
			$GLOBALS['handy_custom_single_product_category'] = $category;
		} else {
			$GLOBALS['handy_custom_single_' . $post_type . '_category'] = $category;
		}
		
		Handy_Custom_Logger::log("WordPress query setup for single {$post_type} display: ID={$post->ID}", 'debug');
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

	/**
	 * Get URL parameters from the current request
	 * Extracts category and product slug from rewrite rules
	 * 
	 * @return array URL parameters array
	 */
	public static function get_url_parameters() {
		$params = array();
		
		// Get category from query var (set by rewrite rules)
		$category = get_query_var('product_category');
		if (!empty($category)) {
			$params['category'] = $category;
		}
		
		// Get product slug from query var (set by rewrite rules)
		$product_slug = get_query_var('product_slug');
		if (!empty($product_slug)) {
			$params['product_slug'] = $product_slug;
		}
		
		return $params;
	}

	/**
	 * Generate custom permalink for product post type
	 * Creates URLs in format: /products/{category}/{product-slug}/
	 * 
	 * @param string $post_link The post's permalink
	 * @param WP_Post $post The post object
	 * @return string The custom permalink or original if not applicable
	 */
	public function custom_product_permalink($post_link, $post) {
		// Only apply to product post type
		if ($post->post_type !== 'product') {
			return $post_link;
		}

		// Only apply to published posts (not drafts, revisions, etc.)
		if ($post->post_status !== 'publish') {
			return $post_link;
		}

		// Get product categories
		$categories = wp_get_post_terms($post->ID, 'product-category');
		
		if (is_wp_error($categories) || empty($categories)) {
			// Log warning and return default permalink if no categories
			Handy_Custom_Logger::log("Product {$post->ID} has no categories assigned, using default permalink", 'warning');
			return $post_link;
		}

		// Find primary top-level category (parent = 0)
		$primary_category = null;
		foreach ($categories as $category) {
			// Use first top-level category found
			if ($category->parent == 0) {
				$primary_category = $category;
				break;
			}
		}

		// If no top-level category found, use the first category regardless
		if (!$primary_category) {
			$primary_category = $categories[0];
			Handy_Custom_Logger::log("Product {$post->ID} has no top-level category, using first assigned category: {$primary_category->slug}", 'info');
		}

		// Construct custom URL: /products/{category}/{product-slug}/
		$custom_url = home_url("/products/{$primary_category->slug}/{$post->post_name}/");
		
		Handy_Custom_Logger::log("Generated custom permalink for product {$post->ID}: {$custom_url}", 'debug');
		
		return $custom_url;
	}

	/**
	 * Modify Yoast SEO breadcrumbs for single products
	 * Creates hierarchy: Home / Products / Category / Sub Category / Post Title
	 *
	 * @param array $breadcrumbs Yoast breadcrumb array
	 * @return array Modified breadcrumb array
	 */
	public function modify_yoast_breadcrumbs($breadcrumbs) {
		if (!is_singular('product')) {
			return $breadcrumbs;
		}

		global $post;
		$category_context = self::get_single_product_category();
		
		// Get product categories
		$categories = wp_get_post_terms($post->ID, 'product-category', array('orderby' => 'parent'));
		
		if (empty($categories) || is_wp_error($categories)) {
			return $breadcrumbs;
		}
		
		// Build custom breadcrumb structure
		$custom_breadcrumbs = array();
		
		// Keep Home link from existing breadcrumbs
		if (!empty($breadcrumbs) && isset($breadcrumbs[0])) {
			$custom_breadcrumbs[] = $breadcrumbs[0];
		}
		
		// Add Products link
		$custom_breadcrumbs[] = array(
			'text' => 'Products',
			'url'  => home_url('/products/'), // Adjust URL as needed
		);
		
		// Find primary category (with context preference)
		$primary_category = $this->get_primary_product_category($categories, $category_context);
		
		if ($primary_category) {
			// Add parent category if exists
			if ($primary_category->parent > 0) {
				$parent_category = get_term($primary_category->parent, 'product-category');
				if ($parent_category && !is_wp_error($parent_category)) {
					$custom_breadcrumbs[] = array(
						'text' => $parent_category->name,
						'url'  => home_url("/products/{$parent_category->slug}/"),
					);
				}
			}
			
			// Add the primary category
			$custom_breadcrumbs[] = array(
				'text' => $primary_category->name,
				'url'  => home_url("/products/{$primary_category->slug}/"),
			);
		}
		
		// Add current product (no URL - final item)
		$custom_breadcrumbs[] = array(
			'text' => get_the_title($post->ID),
			'url'  => false,
		);
		
		return $custom_breadcrumbs;
	}

	/**
	 * Modify Breadcrumb Trail plugin breadcrumbs for single products
	 *
	 * @param array $items Breadcrumb trail items
	 * @return array Modified breadcrumb items
	 */
	public function modify_breadcrumb_trail_items($items) {
		if (!is_singular('product')) {
			return $items;
		}

		// Use similar logic to Yoast but adapt to Breadcrumb Trail format
		// This would need to be implemented based on the Breadcrumb Trail plugin structure
		return $items;
	}

	/**
	 * Modify WooCommerce breadcrumbs for single products
	 *
	 * @param array $breadcrumbs WooCommerce breadcrumb array
	 * @return array Modified breadcrumb array
	 */
	public function modify_woocommerce_breadcrumbs($breadcrumbs) {
		if (!is_singular('product')) {
			return $breadcrumbs;
		}

		// Use similar logic to Yoast but adapt to WooCommerce format
		// This would need to be implemented based on WooCommerce breadcrumb structure
		return $breadcrumbs;
	}

	/**
	 * Get primary product category with context preference
	 *
	 * @param array $categories Array of product category terms
	 * @param string|false $context_category Preferred category from URL context
	 * @return WP_Term|false Primary category or false if none found
	 */
	private function get_primary_product_category($categories, $context_category = false) {
		if (empty($categories)) {
			return false;
		}

		// If we have URL context, prefer that category
		if ($context_category) {
			foreach ($categories as $category) {
				if ($category->slug === $context_category) {
					return $category;
				}
			}
		}

		// Find top-level category (parent = 0)
		foreach ($categories as $category) {
			if ($category->parent == 0) {
				return $category;
			}
		}

		// Fallback to first category
		return $categories[0];
	}

	/**
	 * Initialize plugin updater
	 */
	private function init_updater() {
		// Get plugin file path from main plugin file
		$plugin_file = HANDY_CUSTOM_PLUGIN_DIR . 'handy-custom.php';
		
		// Initialize updater with GitHub repository info
		$this->updater = new Handy_Custom_Plugin_Updater(
			$plugin_file,
			self::VERSION,
			'OrasesWPDev',
			'handy-custom'
		);
		
		Handy_Custom_Logger::log('Plugin updater initialized', 'info');
	}

	/**
	 * Get updater instance (for testing/debugging)
	 *
	 * @return Handy_Custom_Plugin_Updater|null Updater instance
	 */
	public function get_updater() {
		return $this->updater;
	}
}