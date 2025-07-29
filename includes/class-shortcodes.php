<?php
/**
 * Shortcodes functionality
 *
 * @package Handy_Custom
 */

if (!defined('ABSPATH')) {
	exit;
}

class Handy_Custom_Shortcodes {

	/**
	 * Initialize shortcodes
	 */
	public static function init() {
		add_shortcode('products', array(__CLASS__, 'products_shortcode'));
		add_shortcode('recipes', array(__CLASS__, 'recipes_shortcode'));
		
		// Featured recipes shortcode
		add_shortcode('featured-recipes', array(__CLASS__, 'featured_recipes_shortcode'));
		
		// Product category images shortcode
		add_shortcode('product-category-images', array(__CLASS__, 'product_category_images_shortcode'));
		
		// New filter shortcodes
		add_shortcode('filter-products', array(__CLASS__, 'filter_products_shortcode'));
		add_shortcode('filter-recipes', array(__CLASS__, 'filter_recipes_shortcode'));
		
		// AJAX handlers for filtering
		add_action('wp_ajax_filter_products', array(__CLASS__, 'ajax_filter_products'));
		add_action('wp_ajax_nopriv_filter_products', array(__CLASS__, 'ajax_filter_products'));
		add_action('wp_ajax_filter_recipes', array(__CLASS__, 'ajax_filter_recipes'));
		add_action('wp_ajax_nopriv_filter_recipes', array(__CLASS__, 'ajax_filter_recipes'));
	}

	/**
	 * Products shortcode handler
	 * Now supports subcategory parameter with automatic parent detection
	 * Integrates with URL rewrite system for /products/{category}/{subcategory}/ URLs
	 * Supports display parameter: 'categories' (default) or 'list' for product catalog
	 *
	 * @param array $atts Shortcode attributes
	 * @return string
	 */
	public static function products_shortcode($atts) {
		// Validate input attributes
		if (!is_array($atts)) {
			$atts = array();
			Handy_Custom_Logger::log('Products shortcode: Invalid attributes provided, using defaults', 'warning');
		}

		// Get taxonomy mapping safely
		$taxonomy_mapping = Handy_Custom_Products_Utils::get_taxonomy_mapping();
		if (empty($taxonomy_mapping) || !is_array($taxonomy_mapping)) {
			Handy_Custom_Logger::log('Products shortcode: Taxonomy mapping is empty or invalid', 'error');
			return '<div class="product-error"><p>Error: Product taxonomy configuration is missing.</p></div>';
		}

		// Include display and pagination parameters in defaults
		$defaults = array_merge(
			array_fill_keys(array_keys($taxonomy_mapping), ''),
			array(
				'display' => 'categories',
				'per_page' => '',
				'page' => '1'
			)
		);
		$atts = shortcode_atts($defaults, $atts, 'products');

		// Validate and sanitize pagination parameters
		if (!empty($atts['per_page'])) {
			$atts['per_page'] = absint($atts['per_page']);
			if ($atts['per_page'] < 1 || $atts['per_page'] > 100) {
				Handy_Custom_Logger::log('Products shortcode: Invalid per_page value, using default: ' . $atts['per_page'], 'warning');
				$atts['per_page'] = '';
			}
		}

		$atts['page'] = absint($atts['page']);
		if ($atts['page'] < 1) {
			$atts['page'] = 1;
		}

		// Validate display parameter
		if (!in_array($atts['display'], array('categories', 'list'), true)) {
			Handy_Custom_Logger::log('Products shortcode: Invalid display mode, defaulting to categories: ' . $atts['display'], 'warning');
			$atts['display'] = 'categories';
		}

		// Merge URL parameters with shortcode attributes (URL takes precedence)
		$url_params = Handy_Custom_Products_Utils::get_current_url_parameters();
		$atts = array_merge($atts, $url_params);

		// Sanitize attributes
		$atts = Handy_Custom_Products_Utils::sanitize_filters($atts);

		// Handle subcategory auto-detection
		if (!empty($atts['subcategory']) && empty($atts['category'])) {
			$parent_category = Handy_Custom_Products_Utils::get_parent_category_from_subcategory($atts['subcategory']);
			if ($parent_category && $parent_category !== $atts['subcategory']) {
				$atts['category'] = $parent_category;
				Handy_Custom_Logger::log("Auto-detected parent category '{$parent_category}' for subcategory '{$atts['subcategory']}'", 'info');
			}
		}

		// Enhanced logging with URL context
		$log_message = 'Products shortcode called with attributes: ' . wp_json_encode($atts);
		if (!empty($url_params)) {
			$log_message .= " (URL parameters: " . wp_json_encode($url_params) . ")";
		}
		if (!empty($atts['subcategory'])) {
			$log_message .= " (subcategory filtering enabled)";
		}
		Handy_Custom_Logger::log($log_message, 'info');

		try {
			$renderer = new Handy_Custom_Products_Renderer();
			return $renderer->render($atts);
		} catch (Exception $e) {
			Handy_Custom_Logger::log('Products shortcode error: ' . $e->getMessage(), 'error');
			return '<div class="product-error"><p>Error loading products. Please try again later.</p></div>';
		}
	}

	/**
	 * Recipes shortcode handler
	 * Renders recipe archive with filtering functionality
	 *
	 * @param array $atts Shortcode attributes
	 * @return string HTML for recipe archive
	 */
	public static function recipes_shortcode($atts) {
		// Validate input attributes
		if (!is_array($atts)) {
			$atts = array();
			Handy_Custom_Logger::log('Recipes shortcode: Invalid attributes provided, using defaults', 'warning');
		}

		// Get taxonomy mapping safely
		$taxonomy_mapping = Handy_Custom_Recipes_Utils::get_taxonomy_mapping();
		if (empty($taxonomy_mapping) || !is_array($taxonomy_mapping)) {
			Handy_Custom_Logger::log('Recipes shortcode: Taxonomy mapping is empty or invalid', 'error');
			return '<div class="recipe-error"><p>Error: Recipe taxonomy configuration is missing.</p></div>';
		}

		// Define defaults based on recipe taxonomy mapping with pagination
		$defaults = array_merge(
			array_fill_keys(array_keys($taxonomy_mapping), ''),
			array(
				'per_page' => '',
				'page' => '1'
			)
		);
		$atts = shortcode_atts($defaults, $atts, 'recipes');

		// Validate and sanitize pagination parameters
		if (!empty($atts['per_page'])) {
			$atts['per_page'] = absint($atts['per_page']);
			if ($atts['per_page'] < 1 || $atts['per_page'] > 100) {
				Handy_Custom_Logger::log('Recipes shortcode: Invalid per_page value, using default: ' . $atts['per_page'], 'warning');
				$atts['per_page'] = '';
			}
		}

		$atts['page'] = absint($atts['page']);
		if ($atts['page'] < 1) {
			$atts['page'] = 1;
		}

		// Sanitize attributes
		$atts = Handy_Custom_Recipes_Utils::sanitize_filters($atts);

		Handy_Custom_Logger::log('Recipes shortcode called with attributes: ' . wp_json_encode($atts), 'info');

		try {
			$renderer = new Handy_Custom_Recipes_Renderer();
			return $renderer->render($atts);
		} catch (Exception $e) {
			Handy_Custom_Logger::log('Recipes shortcode error: ' . $e->getMessage(), 'error');
			return '<div class="recipe-error"><p>Error loading recipes. Please try again later.</p></div>';
		}
	}

	/**
	 * Featured recipes shortcode handler
	 * Simple implementation following the same pattern as product featured recipes
	 *
	 * @param array $atts Shortcode attributes
	 * @return string HTML for featured recipes
	 */
	public static function featured_recipes_shortcode($atts) {
		$defaults = array(
			'limit' => 3
		);
		$atts = shortcode_atts($defaults, $atts, 'featured-recipes');

		// Validate limit
		$atts['limit'] = absint($atts['limit']);
		if ($atts['limit'] < 1 || $atts['limit'] > 10) {
			$atts['limit'] = 3;
		}

		Handy_Custom_Logger::log('Featured recipes shortcode called with limit: ' . $atts['limit'], 'info');

		try {
			// Get featured recipe IDs directly (published only)
			$featured_recipe_ids = get_posts(array(
				'post_type' => 'recipe',
				'post_status' => 'publish',
				'meta_key' => Handy_Custom_Recipes_Featured_Admin::FEATURED_META_KEY,
				'meta_value' => '1',
				'posts_per_page' => $atts['limit'],
				'orderby' => 'date',
				'order' => 'DESC',
				'fields' => 'ids'
			));
			
			if (empty($featured_recipe_ids)) {
				Handy_Custom_Logger::log('Featured recipes shortcode: No featured recipes found', 'info');
				return ''; // Return empty if no featured recipes
			}
			
			Handy_Custom_Logger::log('Found ' . count($featured_recipe_ids) . ' featured recipes: ' . implode(', ', $featured_recipe_ids), 'info');
			
			// Use existing recipes renderer with standard recipe archive wrapper
			$renderer = new Handy_Custom_Recipes_Renderer();
			$recipe_count = count($featured_recipe_ids);
			
			$options = array(
				'wrapper_class' => 'handy-featured-recipes-grid',
				'columns' => $recipe_count,
				'show_wrapper' => true
			);
			
			// Render just the recipe cards (no title needed)
			return $renderer->render_specific_recipes($featured_recipe_ids, $options);
			
		} catch (Exception $e) {
			Handy_Custom_Logger::log('Featured recipes shortcode error: ' . $e->getMessage(), 'error');
			return '<div class="featured-recipes-error"><p>Error loading featured recipes. Please try again later.</p></div>';
		}
	}

	/**
	 * AJAX handler for product filtering
	 * Now supports display parameter for categories/list modes and preserves context boundaries
	 */
	public static function ajax_filter_products() {
		// Verify nonce
		if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'handy_custom_nonce')) {
			wp_send_json_error('Security check failed');
		}

		// Get filter parameters using utility function
		$raw_filters = array();
		$taxonomy_mapping = Handy_Custom_Products_Utils::get_taxonomy_mapping();
		
		if (!empty($taxonomy_mapping) && is_array($taxonomy_mapping)) {
			foreach (array_keys($taxonomy_mapping) as $key) {
				if (is_string($key) && !empty($key)) {
					$raw_filters[$key] = isset($_POST[$key]) ? sanitize_text_field($_POST[$key]) : '';
				}
			}
		} else {
			Handy_Custom_Logger::log('AJAX products filter: Taxonomy mapping is empty or invalid', 'error');
			wp_send_json_error('Invalid taxonomy configuration');
			return;
		}
		
		// CRITICAL: Preserve original context boundaries from shortcode
		// These parameters define the filtering context and should not be overridden by user selections
		$context_category = isset($_POST['context_category']) ? sanitize_text_field($_POST['context_category']) : '';
		$context_subcategory = isset($_POST['context_subcategory']) ? sanitize_text_field($_POST['context_subcategory']) : '';
		
		// If we have context boundaries, enforce them regardless of user filter selections
		if (!empty($context_subcategory)) {
			$raw_filters['subcategory'] = $context_subcategory;
			
			// Auto-detect parent category if not provided
			if (empty($context_category)) {
				$parent_category = Handy_Custom_Products_Utils::get_parent_category_from_subcategory($context_subcategory);
				if ($parent_category && $parent_category !== $context_subcategory) {
					$context_category = $parent_category;
				}
			}
			
			Handy_Custom_Logger::log("AJAX: Enforcing subcategory context boundary: {$context_subcategory}", 'info');
		}
		
		if (!empty($context_category)) {
			$raw_filters['category'] = $context_category;
			Handy_Custom_Logger::log("AJAX: Enforcing category context boundary: {$context_category}", 'info');
		}
		
		// Add display and pagination parameters with validation
		$display = isset($_POST['display']) ? sanitize_text_field($_POST['display']) : 'categories';
		if (!in_array($display, array('categories', 'list'), true)) {
			Handy_Custom_Logger::log('AJAX products filter: Invalid display mode, defaulting to categories: ' . $display, 'warning');
			$display = 'categories';
		}
		$raw_filters['display'] = $display;

		$per_page = isset($_POST['per_page']) ? absint($_POST['per_page']) : '';
		if (!empty($per_page) && ($per_page < 1 || $per_page > 100)) {
			Handy_Custom_Logger::log('AJAX products filter: Invalid per_page value, using default: ' . $per_page, 'warning');
			$per_page = '';
		}
		$raw_filters['per_page'] = $per_page;

		$page = isset($_POST['page']) ? absint($_POST['page']) : 1;
		if ($page < 1) {
			$page = 1;
		}
		$raw_filters['page'] = $page;
		
		$filters = Handy_Custom_Products_Utils::sanitize_filters($raw_filters);

		$context_info = '';
		if (!empty($context_category) || !empty($context_subcategory)) {
			$context_info = " (context boundaries: category={$context_category}, subcategory={$context_subcategory})";
		}
		Handy_Custom_Logger::log('AJAX filter request with display mode: ' . wp_json_encode($filters) . $context_info);

		try {
			// Load the products renderer
			$renderer = new Handy_Custom_Products_Renderer();
			$output = $renderer->render($filters);
			wp_send_json_success(array('html' => $output));
		} catch (Exception $e) {
			Handy_Custom_Logger::log('AJAX filter error: ' . $e->getMessage(), 'error');
			wp_send_json_error('Filter processing failed');
		}
	}

	/**
	 * AJAX handler for recipe filtering
	 * Processes recipe filter requests and returns updated HTML
	 */
	public static function ajax_filter_recipes() {
		// Verify nonce for security
		if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'handy_custom_nonce')) {
			Handy_Custom_Logger::log('Recipe AJAX: Security check failed', 'warning');
			wp_send_json_error('Security check failed');
		}

		// Get filter parameters using recipe utility function
		$raw_filters = array();
		$taxonomy_mapping = Handy_Custom_Recipes_Utils::get_taxonomy_mapping();
		
		if (!empty($taxonomy_mapping) && is_array($taxonomy_mapping)) {
			foreach (array_keys($taxonomy_mapping) as $key) {
				if (is_string($key) && !empty($key)) {
					$raw_filters[$key] = isset($_POST[$key]) ? sanitize_text_field($_POST[$key]) : '';
				}
			}
		} else {
			Handy_Custom_Logger::log('AJAX recipes filter: Taxonomy mapping is empty or invalid', 'error');
			wp_send_json_error('Invalid taxonomy configuration');
			return;
		}
		
		// Add pagination parameters with validation
		$per_page = isset($_POST['per_page']) ? absint($_POST['per_page']) : '';
		if (!empty($per_page) && ($per_page < 1 || $per_page > 100)) {
			Handy_Custom_Logger::log('AJAX recipes filter: Invalid per_page value, using default: ' . $per_page, 'warning');
			$per_page = '';
		}
		$raw_filters['per_page'] = $per_page;

		$page = isset($_POST['page']) ? absint($_POST['page']) : 1;
		if ($page < 1) {
			$page = 1;
		}
		$raw_filters['page'] = $page;
		
		$filters = Handy_Custom_Recipes_Utils::sanitize_filters($raw_filters);

		Handy_Custom_Logger::log('Recipe AJAX filter request: ' . wp_json_encode($filters), 'info');

		try {
			// Load the recipes renderer
			$renderer = new Handy_Custom_Recipes_Renderer();
			$output = $renderer->render($filters);
			
			Handy_Custom_Logger::log('Recipe AJAX filter successful', 'info');
			wp_send_json_success(array('html' => $output));
		} catch (Exception $e) {
			Handy_Custom_Logger::log('Recipe AJAX filter error: ' . $e->getMessage(), 'error');
			wp_send_json_error('Recipe filter processing failed');
		}
	}

	/**
	 * Filter Products shortcode handler
	 * Renders only product taxonomy filters with URL parameter integration
	 * Now supports category/subcategory context filtering
	 *
	 * User request: "create a new shortcode: [filter-products] to only show 
	 * the taxonomies in products"
	 * 
	 * Update request: "when a category flag is applied, it needs to iterate through all products 
	 * under the shrimp category, and only show the filters that have been attached to those products"
	 *
	 * @param array $atts Shortcode attributes
	 * @return string HTML for product filters
	 */
	public static function filter_products_shortcode($atts) {
		// VERY EARLY DEBUG - Log raw input before any processing
		Handy_Custom_Logger::log('🔥 [filter-products] SHORTCODE ENTRY POINT - Raw $atts: ' . wp_json_encode($atts), 'info');
		
		$defaults = array(
			'display' => '',      // Comma-separated list of taxonomies to show
			'exclude' => '',      // Comma-separated list of taxonomies to exclude
			'category' => '',     // Filter context to specific category
			'subcategory' => ''   // Filter context to specific subcategory
		);
		$atts = shortcode_atts($defaults, $atts, 'filter-products');

		Handy_Custom_Logger::log('🔥 [filter-products] After shortcode_atts processing: ' . wp_json_encode($atts), 'info');
		
		// Enhanced debug logging for contextual filtering
		if (!empty($atts['subcategory'])) {
			Handy_Custom_Logger::log('🔥 [filter-products] SUBCATEGORY CONTEXT DETECTED: ' . $atts['subcategory'], 'info');
		}
		if (!empty($atts['category'])) {
			Handy_Custom_Logger::log('🔥 [filter-products] CATEGORY CONTEXT DETECTED: ' . $atts['category'], 'info');
		}
		
		Handy_Custom_Logger::log('🔥 [filter-products] About to create renderer...', 'info');

		try {
			Handy_Custom_Logger::log('🔥 [filter-products] Creating renderer instance...', 'info');
			$renderer = new Handy_Custom_Filters_Renderer();
			
			Handy_Custom_Logger::log('🔥 [filter-products] Calling renderer->render() with attributes: ' . wp_json_encode($atts), 'info');
			$result = $renderer->render('products', $atts);
			
			Handy_Custom_Logger::log('🔥 [filter-products] Renderer completed successfully, result length: ' . strlen($result), 'info');
			return $result;
		} catch (Exception $e) {
			Handy_Custom_Logger::log('🔥 [filter-products] EXCEPTION CAUGHT: ' . $e->getMessage(), 'error');
			Handy_Custom_Logger::log('🔥 [filter-products] EXCEPTION STACK: ' . $e->getTraceAsString(), 'error');
			return '<div class="filter-error"><p>Error loading product filters. Please try again later.</p></div>';
		}
	}

	/**
	 * Filter Recipes shortcode handler  
	 * Renders only recipe taxonomy filters with URL parameter integration
	 * Now supports category/subcategory context filtering
	 *
	 * User request: "create a new shortcode: [filter-recipe] to only show
	 * the taxonomies in recipes"
	 *
	 * @param array $atts Shortcode attributes
	 * @return string HTML for recipe filters
	 */
	public static function filter_recipes_shortcode($atts) {
		$defaults = array(
			'display' => '',      // Comma-separated list of taxonomies to show
			'exclude' => '',      // Comma-separated list of taxonomies to exclude
			'category' => '',     // Filter context to specific category (if applicable to recipes)
			'subcategory' => ''   // Filter context to specific subcategory (if applicable to recipes)
		);
		$atts = shortcode_atts($defaults, $atts, 'filter-recipes');

		Handy_Custom_Logger::log('[filter-recipes] shortcode called with attributes: ' . wp_json_encode($atts), 'info');

		try {
			$renderer = new Handy_Custom_Filters_Renderer();
			return $renderer->render('recipes', $atts);
		} catch (Exception $e) {
			Handy_Custom_Logger::log('Filter-recipes shortcode error: ' . $e->getMessage(), 'error');
			return '<div class="filter-error"><p>Error loading recipe filters. Please try again later.</p></div>';
		}
	}

	/**
	 * Product Category Images shortcode handler
	 * Displays a grid of circular category featured images with names
	 * Based on design from assets/images/category-images-shortcode-design-example.png
	 *
	 * @param array $atts Shortcode attributes
	 * @return string HTML for category images grid
	 */
	public static function product_category_images_shortcode($atts) {
		$atts = shortcode_atts(array(
			'limit' => 6,
			'size' => 'medium'
		), $atts, 'product-category-images');

		Handy_Custom_Logger::log('[product-category-images] shortcode called with limit: ' . $atts['limit'], 'info');

		try {
			$renderer = new Handy_Custom_Products_Category_Images_Renderer();
			return $renderer->render($atts);
		} catch (Exception $e) {
			Handy_Custom_Logger::log('Product category images shortcode error: ' . $e->getMessage(), 'error');
			return '';
		}
	}
}