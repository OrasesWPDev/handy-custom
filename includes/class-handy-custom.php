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
	const VERSION = '2.0.7';

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
		
		// Setup logging first if debug is enabled
		if (defined('HANDY_CUSTOM_DEBUG') && HANDY_CUSTOM_DEBUG === true) {
			$this->setup_logging();
		}
		
		// Initialize updater early (on plugins_loaded) for proper WordPress hook timing
		if (is_admin()) {
			$this->init_updater();
		}
	}

	/**
	 * Initialize hooks
	 */
	private function init_hooks() {
		add_action('init', array($this, 'init'));
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
		add_action('wp_after_insert_post', array($this, 'handle_post_insert_or_update'), 10, 4);
		add_action('before_delete_post', array($this, 'regenerate_rewrite_rules'));
		
		// Primary category validation hook
		add_action('save_post', array($this, 'validate_primary_category'), 20, 2);
		
		// Deferred rewrite rules hook (for draft-to-published transitions)
		add_action('handy_custom_deferred_rewrite_rules', array($this, 'handle_deferred_rewrite_rules'), 10, 2);

		// Template loading hooks
		add_filter('single_template', array($this, 'load_single_product_template'));
		add_filter('single_template', array($this, 'load_single_recipe_template'));
		
		// Breadcrumb hooks for single products and recipes
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
			require_once HANDY_CUSTOM_PLUGIN_DIR . 'includes/class-simple-updater.php';
		}
		
		// Shortcodes and AJAX functionality (load for both frontend and admin for AJAX)
		require_once HANDY_CUSTOM_PLUGIN_DIR . 'includes/class-shortcodes.php';
		
		// Unified filter system (needed for AJAX)
		require_once HANDY_CUSTOM_PLUGIN_DIR . 'includes/class-filters-renderer.php';
		
		// Product-specific functionality (needed for AJAX)
		require_once HANDY_CUSTOM_PLUGIN_DIR . 'includes/products/class-products-utils.php';
		require_once HANDY_CUSTOM_PLUGIN_DIR . 'includes/products/class-products-filters.php';
		require_once HANDY_CUSTOM_PLUGIN_DIR . 'includes/products/class-products-display.php';
		require_once HANDY_CUSTOM_PLUGIN_DIR . 'includes/products/class-products-renderer.php';
		
		// Recipe-specific functionality (needed for AJAX)
		require_once HANDY_CUSTOM_PLUGIN_DIR . 'includes/recipes/class-recipes-utils.php';
		require_once HANDY_CUSTOM_PLUGIN_DIR . 'includes/recipes/class-recipes-filters.php';
		require_once HANDY_CUSTOM_PLUGIN_DIR . 'includes/recipes/class-recipes-display.php';
		require_once HANDY_CUSTOM_PLUGIN_DIR . 'includes/recipes/class-recipes-renderer.php';
	}

	/**
	 * Initialize plugin
	 */
	public function init() {
		// Note: Logging setup moved to constructor for earlier availability
		
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
		
		// Initialize shortcodes and AJAX handlers (needed for both frontend and admin)
		Handy_Custom_Shortcodes::init();

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
		
		// Enqueue single product assets if on single product page
		if (is_singular('product')) {
			$this->enqueue_single_product_assets();
		}
		
		// Enqueue single recipe assets if on single recipe page
		if (is_singular('recipe')) {
			$this->enqueue_single_recipe_assets();
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
		$shared_equalizer_file = HANDY_CUSTOM_PLUGIN_DIR . "assets/js/shared/card-equalizer.js";

		// Enqueue shared card equalizer first (dependency for archive scripts)
		if (file_exists($shared_equalizer_file)) {
			$equalizer_version = filemtime($shared_equalizer_file);
			wp_enqueue_script(
				'handy-custom-card-equalizer',
				HANDY_CUSTOM_PLUGIN_URL . 'assets/js/shared/card-equalizer.js',
				array('jquery'),
				$equalizer_version,
				true
			);
		}

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

		// Enqueue JS (with card equalizer as dependency)
		if (file_exists($js_file)) {
			$js_version = filemtime($js_file);
			$dependencies = array('jquery');
			
			// Add card equalizer as dependency if it was enqueued
			if (file_exists($shared_equalizer_file)) {
				$dependencies[] = 'handy-custom-card-equalizer';
			}
			
			wp_enqueue_script(
				"handy-custom-{$type}",
				HANDY_CUSTOM_PLUGIN_URL . "assets/js/{$type}/archive.js",
				$dependencies,
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
	 * Enqueue single product template assets
	 */
	private function enqueue_single_product_assets() {
		$css_file = HANDY_CUSTOM_PLUGIN_DIR . 'assets/css/products/single-product.css';
		$js_file = HANDY_CUSTOM_PLUGIN_DIR . 'assets/js/products/single-product.js';

		// Enqueue single product CSS
		if (file_exists($css_file)) {
			$css_version = filemtime($css_file);
			wp_enqueue_style(
				'handy-custom-single-product',
				HANDY_CUSTOM_PLUGIN_URL . 'assets/css/products/single-product.css',
				array(),
				$css_version
			);
			
			Handy_Custom_Logger::log('Single product CSS enqueued', 'debug');
		}

		// Enqueue single product JS
		if (file_exists($js_file)) {
			$js_version = filemtime($js_file);
			wp_enqueue_script(
				'handy-custom-single-product',
				HANDY_CUSTOM_PLUGIN_URL . 'assets/js/products/single-product.js',
				array('jquery'),
				$js_version,
				true
			);

			// Localize script with debug flag
			$localize_data = array(
				'debug' => defined('HANDY_CUSTOM_DEBUG') && HANDY_CUSTOM_DEBUG === true
			);

			wp_localize_script('handy-custom-single-product', 'handyCustomSingleProduct', $localize_data);
			
			Handy_Custom_Logger::log('Single product JS enqueued with debug: ' . ($localize_data['debug'] ? 'enabled' : 'disabled'), 'debug');
		}
	}
	
	/**
	 * Enqueue single recipe assets
	 */
	private function enqueue_single_recipe_assets() {
		$css_file = HANDY_CUSTOM_PLUGIN_DIR . 'assets/css/recipes/single-recipe.css';
		$js_file = HANDY_CUSTOM_PLUGIN_DIR . 'assets/js/recipes/single-recipe.js';

		// Enqueue single recipe CSS
		if (file_exists($css_file)) {
			$css_version = filemtime($css_file);
			wp_enqueue_style(
				'handy-custom-single-recipe',
				HANDY_CUSTOM_PLUGIN_URL . 'assets/css/recipes/single-recipe.css',
				array(),
				$css_version
			);
			
			Handy_Custom_Logger::log('Single recipe CSS enqueued', 'debug');
		}

		// Enqueue single recipe JS if it exists
		if (file_exists($js_file)) {
			$js_version = filemtime($js_file);
			wp_enqueue_script(
				'handy-custom-single-recipe',
				HANDY_CUSTOM_PLUGIN_URL . 'assets/js/recipes/single-recipe.js',
				array('jquery'),
				$js_version,
				true
			);

			// Localize script with debug flag
			$localize_data = array(
				'debug' => defined('HANDY_CUSTOM_DEBUG') && HANDY_CUSTOM_DEBUG === true
			);

			wp_localize_script('handy-custom-single-recipe', 'handyCustomSingleRecipe', $localize_data);
			
			Handy_Custom_Logger::log('Single recipe JS enqueued with debug: ' . ($localize_data['debug'] ? 'enabled' : 'disabled'), 'debug');
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
		// Only load on product edit pages
		$screen = get_current_screen();
		if (!$screen || $screen->post_type !== 'product' || !in_array($screen->base, array('post', 'edit'))) {
			return;
		}
		
		// Enqueue primary category restrictions JavaScript
		$js_file = HANDY_CUSTOM_PLUGIN_DIR . 'assets/js/admin/primary-category-restrictions.js';
		if (file_exists($js_file)) {
			$js_version = filemtime($js_file);
			wp_enqueue_script(
				'handy-custom-admin-primary-restrictions',
				HANDY_CUSTOM_PLUGIN_URL . 'assets/js/admin/primary-category-restrictions.js',
				array('jquery'),
				$js_version,
				true
			);
			
			// Get all product categories with parent information
			$categories = get_terms(array(
				'taxonomy' => 'product-category',
				'hide_empty' => false,
				'fields' => 'all'
			));
			
			$category_data = array();
			if (!is_wp_error($categories)) {
				foreach ($categories as $category) {
					$category_data[$category->term_id] = array(
						'id' => $category->term_id,
						'name' => $category->name,
						'slug' => $category->slug,
						'parent' => $category->parent
					);
				}
			}
			
			// Localize script with category data
			wp_localize_script('handy-custom-admin-primary-restrictions', 'handyCustomAdmin', array(
				'categoryData' => $category_data,
				'nonce' => wp_create_nonce('handy_custom_admin_nonce'),
				'debug' => defined('HANDY_CUSTOM_DEBUG') && HANDY_CUSTOM_DEBUG
			));
			
			Handy_Custom_Logger::log('Primary category restrictions admin JS enqueued', 'debug');
		}
	}

	/**
	 * Handle post insert or update with smart rewrite rule regeneration
	 * Detects draft-to-published transitions and defers rewrite rule regeneration
	 * 
	 * @param int $post_id Post ID
	 * @param WP_Post $post Post object
	 * @param bool $update Whether this is an existing post being updated
	 * @param null|WP_Post $post_before Previous post object (null for new posts)
	 */
	public function handle_post_insert_or_update($post_id, $post, $update, $post_before) {
		// Only handle product and recipe post types
		if (!in_array($post->post_type, array('product', 'recipe'))) {
			return;
		}
		
		// Check if this is a draft-to-published transition
		$is_draft_to_published = false;
		if ($post_before) {
			$old_status = $post_before->post_status;
			$new_status = $post->post_status;
			
			$is_draft_to_published = in_array($old_status, array('draft', 'auto-draft', 'pending')) && 
									 $new_status === 'publish';
		} else {
			// New post being created as published
			$is_draft_to_published = $post->post_status === 'publish';
		}
		
		if ($is_draft_to_published) {
			Handy_Custom_Logger::log("Draft-to-published transition detected for {$post->post_type} ID {$post_id} - deferring rewrite rules", 'info');
			$this->schedule_deferred_rewrite_rules($post_id, $post->post_type);
		} else {
			// For regular updates to already-published posts, regenerate immediately
			if ($post->post_status === 'publish') {
				Handy_Custom_Logger::log("Regular update to published {$post->post_type} ID {$post_id} - regenerating rewrite rules", 'info');
				$this->regenerate_rewrite_rules();
			}
		}
	}
	
	/**
	 * Validate primary category to prevent subcategories from being marked as primary
	 * 
	 * @param int $post_id Post ID
	 * @param WP_Post $post Post object
	 */
	public function validate_primary_category($post_id, $post) {
		// Clear rewrite cache for any product or recipe post updates
		if (in_array($post->post_type, array('product', 'recipe'))) {
			$cache_key = 'handy_custom_rewrite_posts_' . $post->post_type;
			wp_cache_delete($cache_key, 'handy_custom_rewrite');
			Handy_Custom_Logger::log("Cleared rewrite cache for {$post->post_type} after post {$post_id} update", 'info');
		}
		
		// Only validate product post type
		if ($post->post_type !== 'product') {
			return;
		}
		
		// Skip during autosave, revisions, and other automatic saves
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			return;
		}
		
		if (wp_is_post_revision($post_id)) {
			return;
		}
		
		// Check if user has permission to edit this post
		if (!current_user_can('edit_post', $post_id)) {
			return;
		}
		
		// Check for Yoast SEO primary category
		$primary_cat_id = get_post_meta($post_id, '_yoast_wpseo_primary_category', true);
		
		if ($primary_cat_id) {
			$primary_category = get_term($primary_cat_id, 'product-category');
			
			if ($primary_category && !is_wp_error($primary_category) && $primary_category->parent > 0) {
				// This is a subcategory marked as primary - remove it
				delete_post_meta($post_id, '_yoast_wpseo_primary_category');
				
				Handy_Custom_Logger::log("Removed invalid primary category (subcategory) '{$primary_category->name}' from product {$post_id}", 'warning');
				
				// Add admin notice if in admin context
				if (is_admin()) {
					add_action('admin_notices', function() use ($primary_category) {
						echo '<div class="notice notice-warning is-dismissible">';
						echo '<p><strong>Primary Category Validation:</strong> Subcategories cannot be marked as primary. ';
						echo 'The subcategory "' . esc_html($primary_category->name) . '" has been removed as primary.</p>';
						echo '</div>';
					});
				}
			}
		}
		
		// Also check for other primary category mechanisms (custom fields, etc.)
		$this->validate_other_primary_category_methods($post_id);
	}
	
	/**
	 * Validate other primary category methods beyond Yoast SEO
	 * 
	 * @param int $post_id Post ID
	 */
	private function validate_other_primary_category_methods($post_id) {
		// Check for custom primary category fields
		$custom_primary = get_post_meta($post_id, 'primary_product_category', true);
		
		if ($custom_primary) {
			$primary_category = get_term($custom_primary, 'product-category');
			
			if ($primary_category && !is_wp_error($primary_category) && $primary_category->parent > 0) {
				// Remove invalid custom primary category
				delete_post_meta($post_id, 'primary_product_category');
				
				Handy_Custom_Logger::log("Removed invalid custom primary category (subcategory) '{$primary_category->name}' from product {$post_id}", 'warning');
			}
		}
		
		// Check for WordPress core primary category if it exists
		$wp_primary = get_post_meta($post_id, '_primary_category', true);
		
		if ($wp_primary) {
			$primary_category = get_term($wp_primary, 'product-category');
			
			if ($primary_category && !is_wp_error($primary_category) && $primary_category->parent > 0) {
				// Remove invalid WP primary category
				delete_post_meta($post_id, '_primary_category');
				
				Handy_Custom_Logger::log("Removed invalid WP primary category (subcategory) '{$primary_category->name}' from product {$post_id}", 'warning');
			}
		}
	}
	
	/**
	 * Get assigned subcategories under primary category for a product
	 * 
	 * @param int $post_id Product post ID
	 * @param WP_Term $primary_category Primary category term object
	 * @return array Array of subcategory terms assigned to the product under the primary category
	 */
	private function get_assigned_subcategories_under_primary($post_id, $primary_category) {
		if (!$primary_category) {
			Handy_Custom_Logger::log("get_assigned_subcategories_under_primary: No primary category provided", 'debug');
			return array();
		}
		
		Handy_Custom_Logger::log("get_assigned_subcategories_under_primary: Looking for subcategories under primary '{$primary_category->name}' (ID: {$primary_category->term_id})", 'debug');
		
		// Get all categories assigned to the product
		$assigned_categories = wp_get_post_terms($post_id, 'product-category');
		
		if (is_wp_error($assigned_categories) || empty($assigned_categories)) {
			Handy_Custom_Logger::log("get_assigned_subcategories_under_primary: No assigned categories found for product {$post_id}", 'debug');
			return array();
		}
		
		Handy_Custom_Logger::log("get_assigned_subcategories_under_primary: Found " . count($assigned_categories) . " assigned categories", 'debug');
		
		// Filter to find subcategories that are children of the primary category
		$subcategories = array();
		foreach ($assigned_categories as $category) {
			Handy_Custom_Logger::log("get_assigned_subcategories_under_primary: Checking category '{$category->name}' (ID: {$category->term_id}, parent: {$category->parent}) - Primary ID: {$primary_category->term_id}", 'debug');
			
			if ($category->parent == $primary_category->term_id) {
				Handy_Custom_Logger::log("get_assigned_subcategories_under_primary: Found matching subcategory '{$category->name}'", 'debug');
				$subcategories[] = $category;
			}
		}
		
		// Sort by name for consistent ordering
		if (!empty($subcategories)) {
			usort($subcategories, function($a, $b) {
				return strcmp($a->name, $b->name);
			});
		}
		
		Handy_Custom_Logger::log("get_assigned_subcategories_under_primary: Returning " . count($subcategories) . " subcategories", 'debug');
		return $subcategories;
	}
	
	/**
	 * Get primary category using Yoast SEO API with fallbacks
	 * 
	 * @param int $post_id Product post ID
	 * @return WP_Term|null Primary category term or null if not found
	 */
	private function get_primary_category_with_fallbacks($post_id) {
		$primary_category = null;
		
		Handy_Custom_Logger::log("get_primary_category_with_fallbacks: Starting primary category detection for product {$post_id}", 'debug');
		
		// Method 1: Try Yoast SEO function (preferred)
		if (function_exists('yoast_get_primary_term')) {
			Handy_Custom_Logger::log("get_primary_category_with_fallbacks: Trying yoast_get_primary_term", 'debug');
			$primary_category = yoast_get_primary_term('product-category', $post_id);
			if ($primary_category && !empty($primary_category->slug) && !empty($primary_category->term_id)) {
				Handy_Custom_Logger::log("Found primary category via yoast_get_primary_term: {$primary_category->name} (slug: {$primary_category->slug}, ID: {$primary_category->term_id})", 'debug');
				return $primary_category;
			} else if ($primary_category) {
				Handy_Custom_Logger::log("get_primary_category_with_fallbacks: Yoast returned invalid primary category (empty slug or ID), falling back", 'warning');
			}
		}
		
		// Method 2: Try Yoast SEO term ID function
		if (function_exists('yoast_get_primary_term_id')) {
			Handy_Custom_Logger::log("get_primary_category_with_fallbacks: Trying yoast_get_primary_term_id", 'debug');
			$primary_term_id = yoast_get_primary_term_id('product-category', $post_id);
			if ($primary_term_id) {
				Handy_Custom_Logger::log("get_primary_category_with_fallbacks: Found primary term ID: {$primary_term_id}", 'debug');
				$primary_category = get_term($primary_term_id, 'product-category');
				if ($primary_category && !is_wp_error($primary_category)) {
					Handy_Custom_Logger::log("Found primary category via yoast_get_primary_term_id: {$primary_category->name} (slug: {$primary_category->slug}, ID: {$primary_category->term_id})", 'debug');
					return $primary_category;
				}
			}
		}
		
		// Method 3: Try Yoast SEO post meta
		Handy_Custom_Logger::log("get_primary_category_with_fallbacks: Trying Yoast SEO post meta", 'debug');
		$primary_cat_id = get_post_meta($post_id, '_yoast_wpseo_primary_category', true);
		if ($primary_cat_id) {
			Handy_Custom_Logger::log("get_primary_category_with_fallbacks: Found primary category ID in post meta: {$primary_cat_id}", 'debug');
			$primary_category = get_term($primary_cat_id, 'product-category');
			if ($primary_category && !is_wp_error($primary_category)) {
				Handy_Custom_Logger::log("Found primary category via post meta: {$primary_category->name} (slug: {$primary_category->slug}, ID: {$primary_category->term_id})", 'debug');
				return $primary_category;
			}
		}
		
		// Method 4: Try custom primary category field
		Handy_Custom_Logger::log("get_primary_category_with_fallbacks: Trying custom primary category field", 'debug');
		$custom_primary = get_post_meta($post_id, 'primary_product_category', true);
		if ($custom_primary) {
			Handy_Custom_Logger::log("get_primary_category_with_fallbacks: Found custom primary category: {$custom_primary}", 'debug');
			$primary_category = get_term($custom_primary, 'product-category');
			if ($primary_category && !is_wp_error($primary_category)) {
				Handy_Custom_Logger::log("Found primary category via custom field: {$primary_category->name} (slug: {$primary_category->slug}, ID: {$primary_category->term_id})", 'debug');
				return $primary_category;
			}
		}
		
		// Method 5: Fallback to first top-level category
		Handy_Custom_Logger::log("get_primary_category_with_fallbacks: Using fallback methods", 'debug');
		$categories = wp_get_post_terms($post_id, 'product-category');
		if (!is_wp_error($categories) && !empty($categories)) {
			Handy_Custom_Logger::log("get_primary_category_with_fallbacks: Found " . count($categories) . " categories for fallback", 'debug');
			foreach ($categories as $category) {
				if ($category->parent == 0) {
					Handy_Custom_Logger::log("Using fallback primary category (first top-level): {$category->name} (slug: {$category->slug}, ID: {$category->term_id})", 'debug');
					return $category;
				}
			}
			
			// If no top-level categories, use first category
			$primary_category = $categories[0];
			Handy_Custom_Logger::log("Using fallback primary category (first assigned): {$primary_category->name} (slug: {$primary_category->slug}, ID: {$primary_category->term_id})", 'debug');
			return $primary_category;
		}
		
		Handy_Custom_Logger::log("get_primary_category_with_fallbacks: No primary category found", 'warning');
		return null;
	}
	
	/**
	 * Schedule deferred rewrite rule regeneration for new publications
	 * Uses WordPress cron to delay regeneration by a few seconds
	 * 
	 * @param int $post_id Post ID
	 * @param string $post_type Post type
	 */
	private function schedule_deferred_rewrite_rules($post_id, $post_type) {
		// Schedule rewrite rule regeneration in 3 seconds
		wp_schedule_single_event(time() + 3, 'handy_custom_deferred_rewrite_rules', array($post_id, $post_type));
		
		Handy_Custom_Logger::log("Scheduled deferred rewrite rule regeneration for {$post_type} ID {$post_id}", 'debug');
	}
	
	/**
	 * Handle deferred rewrite rule regeneration
	 * Called by WordPress cron after a delay
	 * 
	 * @param int $post_id Post ID
	 * @param string $post_type Post type
	 */
	public function handle_deferred_rewrite_rules($post_id, $post_type) {
		// Verify the post still exists and is published
		$post = get_post($post_id);
		if (!$post || $post->post_status !== 'publish') {
			Handy_Custom_Logger::log("Deferred rewrite rules: Post {$post_id} no longer exists or not published - skipping", 'warning');
			return;
		}
		
		Handy_Custom_Logger::log("Executing deferred rewrite rule regeneration for {$post_type} ID {$post_id}", 'info');
		$this->regenerate_rewrite_rules();
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
		// Get all published posts of this type with caching
		$cache_key = 'handy_custom_rewrite_posts_' . $post_type;
		$posts = wp_cache_get($cache_key, 'handy_custom_rewrite');
		
		if (false === $posts) {
			$posts = get_posts(array(
				'post_type' => $post_type,
				'post_status' => 'publish',
				'posts_per_page' => -1,  // Needed for complete rewrite rule generation
				'fields' => 'ids'
			));
			
			// Cache for 1 hour - rewrite rules don't change frequently
			wp_cache_set($cache_key, $posts, 'handy_custom_rewrite', HOUR_IN_SECONDS);
			Handy_Custom_Logger::log("Fetched and cached {$post_type} posts for rewrite rules: " . count($posts) . " posts", 'info');
		} else {
			Handy_Custom_Logger::log("Using cached {$post_type} posts for rewrite rules: " . count($posts) . " posts", 'info');
		}

		if (empty($posts)) {
			Handy_Custom_Logger::log("No published {$post_type} posts found - no rewrite rules added", 'info');
			return 0;
		}

		$rules_added = 0;
		$taxonomy = $post_type . '-category';

		foreach ($posts as $post_id) {
			$post = get_post($post_id);
			if (!$post) continue;

			// Get the primary category using Yoast SEO API with fallbacks
			$primary_category = $this->get_primary_category_with_fallbacks($post_id);
			if (!$primary_category) continue;

			// Check for assigned subcategories under the primary category
			$subcategories = $this->get_assigned_subcategories_under_primary($post_id, $primary_category);
			
			if (!empty($subcategories)) {
				// Generate hierarchical URL pattern: /products/{primary}/{subcategory}/{product}/
				$subcategory = $subcategories[0];
				$pattern = '^' . $url_prefix . '/' . preg_quote($primary_category->slug) . '/' . preg_quote($subcategory->slug) . '/' . preg_quote($post->post_name) . '/?$';
				$rewrite = 'index.php?post_type=' . $post_type . '&' . $post_type . '_category=' . $primary_category->slug . '&' . $post_type . '_subcategory=' . $subcategory->slug . '&' . $post_type . '_slug=' . $post->post_name;
				add_rewrite_rule($pattern, $rewrite, 'top');
				$rules_added++;
				Handy_Custom_Logger::log("Added hierarchical rewrite rule: /{$url_prefix}/{$primary_category->slug}/{$subcategory->slug}/{$post->post_name}/ -> {$post_type} ID {$post_id}", 'debug');
			} else {
				// Generate flat URL pattern: /products/{primary}/{product}/
				$pattern = '^' . $url_prefix . '/' . preg_quote($primary_category->slug) . '/' . preg_quote($post->post_name) . '/?$';
				$rewrite = 'index.php?post_type=' . $post_type . '&' . $post_type . '_category=' . $primary_category->slug . '&' . $post_type . '_slug=' . $post->post_name;
				add_rewrite_rule($pattern, $rewrite, 'top');
				$rules_added++;
				Handy_Custom_Logger::log("Added flat rewrite rule: /{$url_prefix}/{$primary_category->slug}/{$post->post_name}/ -> {$post_type} ID {$post_id}", 'debug');
			}
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
		$vars[] = 'product_subcategory';
		$vars[] = 'product_slug';
		
		// Recipe query vars
		$vars[] = 'recipe_category';
		$vars[] = 'recipe_subcategory';
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
		$product_subcategory = get_query_var('product_subcategory');
		$product_slug = get_query_var('product_slug');
		
		if (!empty($product_category) && !empty($product_slug)) {
			$this->handle_single_post_url('product', $product_category, $product_slug, $product_subcategory);
			return;
		}
		
		// Check for recipe URLs
		$recipe_category = get_query_var('recipe_category');
		$recipe_subcategory = get_query_var('recipe_subcategory');
		$recipe_slug = get_query_var('recipe_slug');
		
		if (!empty($recipe_category) && !empty($recipe_slug)) {
			$this->handle_single_post_url('recipe', $recipe_category, $recipe_slug, $recipe_subcategory);
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
	 * @param string $subcategory Optional subcategory slug for hierarchical URLs
	 */
	private function handle_single_post_url($post_type, $category, $post_slug, $subcategory = '') {
		$subcategory_info = !empty($subcategory) ? ", subcategory={$subcategory}" : '';
		Handy_Custom_Logger::log("Single {$post_type} URL detected: category={$category}{$subcategory_info}, slug={$post_slug}", 'info');
		
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
		
		// Verify the post belongs to the specified category (and subcategory if provided)
		$post_categories = wp_get_post_terms($post->ID, $taxonomy);
		$category_match = false;
		
		foreach ($post_categories as $term) {
			// For hierarchical URLs, check if post belongs to both primary category and subcategory
			if (!empty($subcategory)) {
				// Check if this term matches the subcategory
				if ($term->slug === $subcategory) {
					// Verify the subcategory's parent matches the primary category
					$parent_term = get_term($term->parent);
					if ($parent_term && $parent_term->slug === $category) {
						$category_match = true;
						break;
					}
				}
			} else {
				// For flat URLs, check if post belongs to the primary category
				if ($term->slug === $category || 
					($term->parent && get_term($term->parent)->slug === $category)) {
					$category_match = true;
					break;
				}
			}
		}
		
		if (!$category_match) {
			$url_context = !empty($subcategory) ? "category {$category} and subcategory {$subcategory}" : "category {$category}";
			Handy_Custom_Logger::log("{$post_type} {$post_slug} does not belong to {$url_context}", 'warning');
			// Let WordPress handle the 404
			return;
		}
		
		// Set up WordPress to display this post directly
		$this->setup_single_post_display($post, $category, $post_type);
		
		$url_prefix = $post_type === 'product' ? 'products' : 'recipes';
		$url_structure = !empty($subcategory) ? "/{$url_prefix}/{$category}/{$subcategory}/{$post_slug}/" : "/{$url_prefix}/{$category}/{$post_slug}/";
		Handy_Custom_Logger::log("Serving {$post_type} directly on clean URL: {$url_structure}", 'info');
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

		Handy_Custom_Logger::log("=== URL Generation Debug for Product {$post->ID} ({$post->post_name}) ===", 'debug');

		// Get primary category using Yoast SEO API with fallbacks
		$primary_category = $this->get_primary_category_with_fallbacks($post->ID);
		
		if (!$primary_category) {
			// Log warning and return default permalink if no primary category found
			Handy_Custom_Logger::log("Product {$post->ID} has no primary category found, using default permalink", 'warning');
			return $post_link;
		}

		Handy_Custom_Logger::log("Primary category found: {$primary_category->name} (slug: {$primary_category->slug}, ID: {$primary_category->term_id})", 'debug');

		// Get all assigned categories for debugging
		$all_categories = wp_get_post_terms($post->ID, 'product-category');
		if (!is_wp_error($all_categories) && !empty($all_categories)) {
			$category_debug = array();
			foreach ($all_categories as $cat) {
				$category_debug[] = "{$cat->name} (slug: {$cat->slug}, ID: {$cat->term_id}, parent: {$cat->parent})";
			}
			Handy_Custom_Logger::log("All assigned categories: " . implode(', ', $category_debug), 'debug');
		}

		// Check for assigned subcategories under the primary category
		$subcategories = $this->get_assigned_subcategories_under_primary($post->ID, $primary_category);
		
		if (!empty($subcategories)) {
			$subcategory_debug = array();
			foreach ($subcategories as $subcat) {
				$subcategory_debug[] = "{$subcat->name} (slug: {$subcat->slug}, ID: {$subcat->term_id}, parent: {$subcat->parent})";
			}
			Handy_Custom_Logger::log("Subcategories under primary: " . implode(', ', $subcategory_debug), 'debug');
			
			// Generate hierarchical URL: /products/{primary}/{subcategory}/{product}/
			$subcategory = $subcategories[0]; // Use first subcategory
			$custom_url = home_url("/products/{$primary_category->slug}/{$subcategory->slug}/{$post->post_name}/");
			
			Handy_Custom_Logger::log("Generated hierarchical permalink for product {$post->ID}: {$custom_url} (primary: {$primary_category->slug}, subcategory: {$subcategory->slug})", 'debug');
		} else {
			Handy_Custom_Logger::log("No subcategories found under primary category, using flat URL", 'debug');
			
			// Generate flat URL: /products/{primary}/{product}/
			$custom_url = home_url("/products/{$primary_category->slug}/{$post->post_name}/");
			
			Handy_Custom_Logger::log("Generated flat permalink for product {$post->ID}: {$custom_url} (primary: {$primary_category->slug})", 'debug');
		}
		
		Handy_Custom_Logger::log("=== Final URL returned: {$custom_url} ===", 'debug');
		return $custom_url;
	}

	/**
	 * Modify Yoast SEO breadcrumbs for single products and recipes
	 * Creates hierarchy: Home / Products / Category / Sub Category / Post Title (for products)
	 * Creates hierarchy: Home / Recipes / Recipe Title (for recipes)
	 *
	 * @param array $breadcrumbs Yoast breadcrumb array
	 * @return array Modified breadcrumb array
	 */
	public function modify_yoast_breadcrumbs($breadcrumbs) {
		if (!is_singular(array('product', 'recipe'))) {
			return $breadcrumbs;
		}

		global $post;
		
		Handy_Custom_Logger::log("modify_yoast_breadcrumbs: Starting breadcrumb generation for {$post->post_type} ID {$post->ID} ('{$post->post_title}')", 'debug');
		
		// Build custom breadcrumb structure
		$custom_breadcrumbs = array();
		
		// Keep Home link from existing breadcrumbs
		if (!empty($breadcrumbs) && isset($breadcrumbs[0])) {
			$custom_breadcrumbs[] = $breadcrumbs[0];
			Handy_Custom_Logger::log("modify_yoast_breadcrumbs: Added Home breadcrumb: " . json_encode($breadcrumbs[0]), 'debug');
		}
		
		// Handle breadcrumbs based on post type
		if ($post->post_type === 'product') {
			// Add Products link
			$products_crumb = array(
				'text' => 'Products',
				'url'  => home_url('/products/'),
			);
			$custom_breadcrumbs[] = $products_crumb;
			Handy_Custom_Logger::log("modify_yoast_breadcrumbs: Added Products breadcrumb: " . json_encode($products_crumb), 'debug');
		
			// Use the same primary category detection as URL generation system
			$primary_category = $this->get_primary_category_with_fallbacks($post->ID);
			
			if ($primary_category) {
				Handy_Custom_Logger::log("modify_yoast_breadcrumbs: Primary category detected: {$primary_category->name} (slug: {$primary_category->slug}, ID: {$primary_category->term_id}, parent: {$primary_category->parent})", 'debug');
				
				// Add parent category if exists (for hierarchical structure)
				if ($primary_category->parent > 0) {
					$parent_category = get_term($primary_category->parent, 'product-category');
					if ($parent_category && !is_wp_error($parent_category)) {
						$parent_url = Handy_Custom_Products_Utils::get_category_url($parent_category->slug);
						$parent_crumb = array(
							'text' => $parent_category->name,
							'url'  => $parent_url,
						);
						$custom_breadcrumbs[] = $parent_crumb;
						Handy_Custom_Logger::log("modify_yoast_breadcrumbs: Added parent category breadcrumb: " . json_encode($parent_crumb), 'debug');
					} else {
						Handy_Custom_Logger::log("modify_yoast_breadcrumbs: Failed to get parent category for ID {$primary_category->parent}", 'warning');
					}
				} else {
					Handy_Custom_Logger::log("modify_yoast_breadcrumbs: Primary category has no parent (top-level category)", 'debug');
				}
				
				// Always check for assigned subcategories under the primary category (same logic as URL generation)
				$subcategories = $this->get_assigned_subcategories_under_primary($post->ID, $primary_category);
				
				if (!empty($subcategories)) {
					// Product has subcategories - use hierarchical breadcrumb structure
					$subcategory = $subcategories[0];
					Handy_Custom_Logger::log("modify_yoast_breadcrumbs: Found assigned subcategory: {$subcategory->name} (slug: {$subcategory->slug}, ID: {$subcategory->term_id})", 'debug');
					
					// Add primary category breadcrumb
					$primary_url = Handy_Custom_Products_Utils::get_category_url($primary_category->slug);
					$primary_crumb = array(
						'text' => $primary_category->name,
						'url'  => $primary_url,
					);
					$custom_breadcrumbs[] = $primary_crumb;
					Handy_Custom_Logger::log("modify_yoast_breadcrumbs: Added primary category breadcrumb: " . json_encode($primary_crumb), 'debug');
					
					// Add subcategory breadcrumb with hierarchical URL
					$subcategory_url = Handy_Custom_Products_Utils::get_subcategory_url($subcategory->slug);
					$subcategory_crumb = array(
						'text' => $subcategory->name,
						'url'  => $subcategory_url,
					);
					$custom_breadcrumbs[] = $subcategory_crumb;
					Handy_Custom_Logger::log("modify_yoast_breadcrumbs: Added subcategory breadcrumb (hierarchical): " . json_encode($subcategory_crumb), 'debug');
				} else {
					// No subcategories - determine URL structure based on primary category parent status
					if ($primary_category->parent > 0) {
						// This is a child category - use hierarchical URL
						$primary_url = Handy_Custom_Products_Utils::get_subcategory_url($primary_category->slug);
						$primary_crumb = array(
							'text' => $primary_category->name,
							'url'  => $primary_url,
						);
						$custom_breadcrumbs[] = $primary_crumb;
						Handy_Custom_Logger::log("modify_yoast_breadcrumbs: Added child category breadcrumb (hierarchical): " . json_encode($primary_crumb), 'debug');
					} else {
						// This is a top-level category - use flat URL
						$primary_url = Handy_Custom_Products_Utils::get_category_url($primary_category->slug);
						$primary_crumb = array(
							'text' => $primary_category->name,
							'url'  => $primary_url,
						);
						$custom_breadcrumbs[] = $primary_crumb;
						Handy_Custom_Logger::log("modify_yoast_breadcrumbs: Added top-level category breadcrumb (flat): " . json_encode($primary_crumb), 'debug');
					}
				}
			} else {
				Handy_Custom_Logger::log("modify_yoast_breadcrumbs: No primary category detected for product {$post->ID}", 'warning');
			}
			
			// Add current product (no URL - final item)
			$post_crumb = array(
				'text' => get_the_title($post->ID),
				'url'  => false,
			);
			$custom_breadcrumbs[] = $post_crumb;
			Handy_Custom_Logger::log("modify_yoast_breadcrumbs: Added final product breadcrumb: " . json_encode($post_crumb), 'debug');
			
		} elseif ($post->post_type === 'recipe') {
			// Add Recipes link
			$recipes_crumb = array(
				'text' => 'Recipes',
				'url'  => home_url('/recipes/'),
			);
			$custom_breadcrumbs[] = $recipes_crumb;
			Handy_Custom_Logger::log("modify_yoast_breadcrumbs: Added Recipes breadcrumb: " . json_encode($recipes_crumb), 'debug');
			
			// Add current recipe (no URL - final item)
			$post_crumb = array(
				'text' => get_the_title($post->ID),
				'url'  => false,
			);
			$custom_breadcrumbs[] = $post_crumb;
			Handy_Custom_Logger::log("modify_yoast_breadcrumbs: Added final recipe breadcrumb: " . json_encode($post_crumb), 'debug');
		}
		
		Handy_Custom_Logger::log("modify_yoast_breadcrumbs: Final breadcrumb structure: " . json_encode($custom_breadcrumbs), 'debug');
		
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
	 * Initialize plugin updater
	 */
	private function init_updater() {
		// Get plugin file path from main plugin file
		$plugin_file = HANDY_CUSTOM_PLUGIN_DIR . 'handy-custom.php';
		
		// Initialize simple updater using YahnisElsts library
		$this->updater = new Handy_Custom_Simple_Updater($plugin_file);
		
		Handy_Custom_Logger::log('Simple updater (YahnisElsts) initialized', 'info');
	}

	/**
	 * Load single product template
	 * 
	 * @param string $template Current template path
	 * @return string Modified template path
	 */
	public function load_single_product_template($template) {
		if (is_singular('product')) {
			$custom_template = HANDY_CUSTOM_PLUGIN_DIR . 'templates/product/single.php';
			
			if (file_exists($custom_template)) {
				Handy_Custom_Logger::log('Loading custom single product template: ' . $custom_template, 'debug');
				return $custom_template;
			}
		}
		
		return $template;
	}

	/**
	 * Load custom single recipe template
	 *
	 * @param string $template Current template path
	 * @return string Modified template path
	 */
	public function load_single_recipe_template($template) {
		if (is_singular('recipe')) {
			$custom_template = HANDY_CUSTOM_PLUGIN_DIR . 'templates/recipe/single.php';
			
			if (file_exists($custom_template)) {
				Handy_Custom_Logger::log('Loading custom single recipe template: ' . $custom_template, 'debug');
				return $custom_template;
			}
		}
		
		return $template;
	}

	/**
	 * Get updater instance (for testing/debugging)
	 *
	 * @return Handy_Custom_Simple_Updater|null Updater instance
	 */
	public function get_updater() {
		return $this->updater;
	}
}