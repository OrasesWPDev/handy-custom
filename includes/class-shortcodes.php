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
		
		// Recipe category images shortcode
		add_shortcode('recipe-category-images', array(__CLASS__, 'recipe_category_images_shortcode'));
		
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
			
			// Generate cascading filter options based on current selections
			$updated_filter_options = self::get_cascading_filter_options($filters, $context_category, $context_subcategory);
			
			Handy_Custom_Logger::log('AJAX: Generated cascading filter options for ' . count($updated_filter_options) . ' taxonomies', 'info');
			
			// Debug: Log what options are being returned for each taxonomy
			foreach ($updated_filter_options as $taxonomy_key => $terms) {
				$term_count = is_array($terms) ? count($terms) : 0;
				$term_names = array();
				if (is_array($terms) && $term_count > 0) {
					$term_names = array_slice(array_map(function($term) {
						return isset($term->name) ? $term->name : 'Unknown';
					}, $terms), 0, 3);
				}
				$sample_terms = $term_count > 0 ? ' (samples: ' . implode(', ', $term_names) . ($term_count > 3 ? '...' : '') . ')' : '';
				Handy_Custom_Logger::log("AJAX: Returning {$term_count} options for {$taxonomy_key}{$sample_terms}", 'info');
			}
			
			wp_send_json_success(array(
				'html' => $output,
				'updated_filter_options' => $updated_filter_options
			));
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
			// CRITICAL FIX: Add context boundaries support for recipes
			$context_category = isset($_POST['context_category']) ? sanitize_text_field($_POST['context_category']) : '';
			$context_subcategory = isset($_POST['context_subcategory']) ? sanitize_text_field($_POST['context_subcategory']) : '';
			
			// Load the recipes renderer
			$renderer = new Handy_Custom_Recipes_Renderer();
			$output = $renderer->render($filters);
			
			// CRITICAL FIX: Generate cascading filter options for recipes (like products)
			$updated_filter_options = self::get_cascading_recipe_filter_options($filters, $context_category, $context_subcategory);
			
			Handy_Custom_Logger::log('Recipe AJAX: Generated cascading filter options for ' . count($updated_filter_options) . ' taxonomies', 'info');
			
			// Log filter options for debugging
			foreach ($updated_filter_options as $taxonomy_key => $terms) {
				Handy_Custom_Logger::log("AJAX: Returning " . count($terms) . " options for {$taxonomy_key}", 'info');
			}
			
			Handy_Custom_Logger::log('Recipe AJAX filter successful', 'info');
			wp_send_json_success(array(
				'html' => $output,
				'updated_filter_options' => $updated_filter_options
			));
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
	 * Recipe category images shortcode handler
	 * Displays recipe categories with featured images linking to recipe filter pages
	 * Based on design from assets/images/category-images-shortcode-design-example.png
	 *
	 * @param array $atts Shortcode attributes
	 * @return string HTML for recipe category images grid
	 */
	public static function recipe_category_images_shortcode($atts) {
		$atts = shortcode_atts(array(
			'limit' => 6,
			'size' => 'medium'
		), $atts, 'recipe-category-images');

		Handy_Custom_Logger::log('[recipe-category-images] shortcode called with limit: ' . $atts['limit'], 'info');

		try {
			$renderer = new Handy_Custom_Recipes_Category_Images_Renderer();
			return $renderer->render($atts);
		} catch (Exception $e) {
			Handy_Custom_Logger::log('Recipe category images shortcode error: ' . $e->getMessage(), 'error');
			return '';
		}
	}

	/**
	 * Get cascading filter options based on current filter selections
	 * Returns updated filter options that only show terms available in the current filtered product set
	 *
	 * @param array $current_filters Current filter selections
	 * @param string $context_category Context category boundary
	 * @param string $context_subcategory Context subcategory boundary
	 * @return array Updated filter options array
	 */
	private static function get_cascading_filter_options($current_filters, $context_category = '', $context_subcategory = '') {
		Handy_Custom_Logger::log('CASCADING: Starting filter options generation with filters: ' . wp_json_encode($current_filters), 'info');
		
		// Get products matching current filter selections
		$matching_products = self::get_products_matching_current_filters($current_filters, $context_category, $context_subcategory);
		
		if (empty($matching_products)) {
			Handy_Custom_Logger::log('CASCADING: No products found matching current filters', 'info');
			// Return empty options for all taxonomies
			$taxonomy_mapping = Handy_Custom_Products_Utils::get_taxonomy_mapping();
			$empty_options = array();
			foreach (array_keys($taxonomy_mapping) as $key) {
				if (!self::should_skip_taxonomy_for_cascading($key, $context_category, $context_subcategory)) {
					$empty_options[$key] = array();
				}
			}
			return $empty_options;
		}
		
		Handy_Custom_Logger::log('CASCADING: Found ' . count($matching_products) . ' products matching current filters', 'info');
		
		// Extract available taxonomy terms from matching products
		return self::extract_available_taxonomy_terms_from_products($matching_products, $current_filters, $context_category, $context_subcategory);
	}

	/**
	 * Get products matching current filter selections
	 * This mimics the logic used by the products renderer to find matching products
	 *
	 * @param array $current_filters Current filter selections
	 * @param string $context_category Context category boundary
	 * @param string $context_subcategory Context subcategory boundary
	 * @return array Array of product IDs matching current filters
	 */
	private static function get_products_matching_current_filters($current_filters, $context_category = '', $context_subcategory = '') {
		Handy_Custom_Logger::log('CASCADING: Getting products matching filters: ' . wp_json_encode($current_filters), 'info');
		
		// Build query args similar to how the renderer does it
		$query_args = array(
			'post_type' => 'product',
			'post_status' => 'publish',
			'posts_per_page' => 1000, // High limit for comprehensive filtering
			'fields' => 'ids' // Only need IDs for performance
		);
		
		// Build tax_query from current filters with enhanced validation
		$tax_query_clauses = array(); // Store individual tax query clauses
		$taxonomy_mapping = Handy_Custom_Products_Utils::get_taxonomy_mapping();
		$valid_filters_count = 0;
		
		foreach ($current_filters as $key => $value) {
			if (empty($value) || !isset($taxonomy_mapping[$key])) {
				if (!empty($value)) {
					Handy_Custom_Logger::log("CASCADING: Skipping unknown filter key: {$key} = {$value}", 'warning');
				}
				continue;
			}
			
			$taxonomy_slug = $taxonomy_mapping[$key];
			
			// CRITICAL: Validate term exists before adding to query
			$term = get_term_by('slug', $value, $taxonomy_slug);
			if (!$term || is_wp_error($term)) {
				Handy_Custom_Logger::log("CASCADING: TERM NOT FOUND - {$key} ({$taxonomy_slug}): '{$value}' does not exist in database", 'error');
				
				// Try fallback lookups for common variations
				$fallback_term = self::find_term_fallback($value, $taxonomy_slug);
				if ($fallback_term) {
					Handy_Custom_Logger::log("CASCADING: FALLBACK FOUND - Using '{$fallback_term->slug}' instead of '{$value}' for {$key}", 'info');
					$value = $fallback_term->slug;
					$term = $fallback_term;
				} else {
					Handy_Custom_Logger::log("CASCADING: NO FALLBACK - Skipping filter {$key} = {$value}", 'error');
					continue; // Skip this filter entirely
				}
			} else {
				Handy_Custom_Logger::log("CASCADING: TERM VALIDATED - {$key} ({$taxonomy_slug}): '{$value}' exists (ID: {$term->term_id}, Name: '{$term->name}')", 'info');
			}
			
			$tax_query_clauses[] = array(
				'taxonomy' => $taxonomy_slug,
				'field' => 'slug',
				'terms' => $value
			);
			
			$valid_filters_count++;
			Handy_Custom_Logger::log("CASCADING: Added validated tax query for {$key} ({$taxonomy_slug}): {$value}", 'debug');
		}
		
		// Add context boundaries to tax_query if they exist
		if (!empty($context_subcategory)) {
			$context_term = get_term_by('slug', $context_subcategory, 'product-category');
			if ($context_term && !is_wp_error($context_term)) {
				$tax_query_clauses[] = array(
					'taxonomy' => 'product-category',
					'field' => 'slug',
					'terms' => $context_subcategory,
					'include_children' => true
				);
				$valid_filters_count++;
				Handy_Custom_Logger::log("CASCADING: Added validated context subcategory constraint: {$context_subcategory} (ID: {$context_term->term_id})", 'debug');
			} else {
				Handy_Custom_Logger::log("CASCADING: INVALID CONTEXT SUBCATEGORY: '{$context_subcategory}' not found", 'error');
			}
		} elseif (!empty($context_category)) {
			$context_term = get_term_by('slug', $context_category, 'product-category');
			if ($context_term && !is_wp_error($context_term)) {
				$tax_query_clauses[] = array(
					'taxonomy' => 'product-category',
					'field' => 'slug',
					'terms' => $context_category,
					'include_children' => true
				);
				$valid_filters_count++;
				Handy_Custom_Logger::log("CASCADING: Added validated context category constraint: {$context_category} (ID: {$context_term->term_id})", 'debug');
			} else {
				Handy_Custom_Logger::log("CASCADING: INVALID CONTEXT CATEGORY: '{$context_category}' not found", 'error');
			}
		}
		
		// Only add tax_query if we have valid filters
		if ($valid_filters_count > 0) {
			// CRITICAL FIX: Always use proper WordPress tax_query structure
			// WordPress expects the tax_query to be a proper indexed array, not associative with numbered keys
			$query_args['tax_query'] = array('relation' => 'AND');
			
			// Add each clause as a properly indexed array element
			foreach ($tax_query_clauses as $clause) {
				$query_args['tax_query'][] = $clause;
			}
			
			Handy_Custom_Logger::log("CASCADING: Built tax_query with {$valid_filters_count} valid filters: " . wp_json_encode($query_args['tax_query']), 'debug');
		} else {
			Handy_Custom_Logger::log("CASCADING: NO VALID FILTERS - Query will return all products", 'warning');
		}
		
		// Execute query with enhanced logging
		Handy_Custom_Logger::log("CASCADING: Executing WP_Query with args: " . wp_json_encode($query_args), 'debug');
		
		// CRITICAL: Add database verification BEFORE running WP_Query
		global $wpdb;
		$direct_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish'");
		Handy_Custom_Logger::log("CASCADING: Direct DB count of published products: " . $direct_count, 'info');
		
		$wp_query = new WP_Query($query_args);
		
		// Enhanced logging of WP_Query results
		Handy_Custom_Logger::log("CASCADING: WP_Query object debug:", 'debug');
		Handy_Custom_Logger::log("  - found_posts: " . $wp_query->found_posts, 'debug');
		Handy_Custom_Logger::log("  - post_count: " . $wp_query->post_count, 'debug');
		Handy_Custom_Logger::log("  - is_main_query: " . ($wp_query->is_main_query() ? 'true' : 'false'), 'debug');
		Handy_Custom_Logger::log("  - query_vars: " . wp_json_encode($wp_query->query_vars), 'debug');
		
		// Log SQL query for debugging
		if (!empty($wpdb->last_query)) {
			Handy_Custom_Logger::log("CASCADING: Full SQL Query: " . $wpdb->last_query, 'debug');
		}
		
		// Log SQL queries that ran during WP_Query
		if (!empty($wpdb->queries)) {
			$recent_queries = array_slice($wpdb->queries, -3); // Last 3 queries
			foreach ($recent_queries as $i => $query_data) {
				Handy_Custom_Logger::log("CASCADING: Recent SQL Query " . ($i+1) . ": " . $query_data[0], 'debug');
			}
		}
		
		$product_ids = wp_list_pluck($wp_query->posts, 'ID');
		
		Handy_Custom_Logger::log('CASCADING: Query found ' . count($product_ids) . ' matching products' . (!empty($product_ids) ? ' (IDs: ' . implode(', ', array_slice($product_ids, 0, 5)) . (count($product_ids) > 5 ? '...' : '') . ')' : ''), 'info');
		
		// If no products found, implement database fallback
		if (empty($product_ids)) {
			Handy_Custom_Logger::log("CASCADING: ZERO PRODUCTS FOUND - Attempting database fallback", 'error');
			
			// Compare WordPress count vs direct database count
			$wp_count = wp_count_posts('product');
			Handy_Custom_Logger::log("CASCADING: wp_count_posts() results: " . wp_json_encode($wp_count), 'info');
			Handy_Custom_Logger::log("CASCADING: Direct DB count: " . $direct_count, 'info');
			
			// Check for plugin interference by testing a simple query
			$simple_query = new WP_Query(array(
				'post_type' => 'product',
				'post_status' => 'any',
				'posts_per_page' => 5,
				'fields' => 'ids',
				'suppress_filters' => true // Bypass plugin filters
			));
			Handy_Custom_Logger::log("CASCADING: Simple query with suppress_filters found: " . count($simple_query->posts) . " products", 'info');
			
			// CRITICAL FIX: Try direct database query fallback
			if ($valid_filters_count > 0) {
				$fallback_product_ids = self::get_products_by_direct_database_query($tax_query_clauses);
				if (!empty($fallback_product_ids)) {
					Handy_Custom_Logger::log("CASCADING: DATABASE FALLBACK SUCCESS - Found " . count($fallback_product_ids) . " products via direct query", 'info');
					$product_ids = $fallback_product_ids;
				} else {
					Handy_Custom_Logger::log("CASCADING: DATABASE FALLBACK FAILED - Direct query also returned 0 products", 'error');
					// CRITICAL DATABASE VERIFICATION: Check what's actually in the database
					self::verify_database_term_relationships($current_filters, $taxonomy_mapping);
				}
			} else {
				// CRITICAL FIX: Handle unfiltered queries (clear button case)
				Handy_Custom_Logger::log("CASCADING: NO FILTERS APPLIED - Attempting direct query for all published products", 'info');
				$unfiltered_product_ids = self::get_all_published_products_direct();
				if (!empty($unfiltered_product_ids)) {
					Handy_Custom_Logger::log("CASCADING: UNFILTERED FALLBACK SUCCESS - Found " . count($unfiltered_product_ids) . " total products", 'info');
					$product_ids = $unfiltered_product_ids;
				} else {
					Handy_Custom_Logger::log("CASCADING: UNFILTERED FALLBACK FAILED - No published products found in database", 'error');
				}
			}
		}
		
		return $product_ids;
	}

	/**
	 * Extract available taxonomy terms from a set of products
	 * Returns terms used by products PLUS ensures current user selections are preserved
	 *
	 * @param array $product_ids Array of product IDs
	 * @param array $current_filters Current filter selections (must be preserved in results)
	 * @param string $context_category Context category boundary
	 * @param string $context_subcategory Context subcategory boundary
	 * @return array Array of available filter options
	 */
	private static function extract_available_taxonomy_terms_from_products($product_ids, $current_filters, $context_category = '', $context_subcategory = '') {
		$filter_options = array();
		$taxonomy_mapping = Handy_Custom_Products_Utils::get_taxonomy_mapping();
		
		Handy_Custom_Logger::log('CASCADING: Extracting terms from ' . count($product_ids) . ' products for ' . count($taxonomy_mapping) . ' taxonomies', 'info');
		Handy_Custom_Logger::log('CASCADING: Current filters to preserve: ' . wp_json_encode($current_filters), 'info');
		
		foreach ($taxonomy_mapping as $key => $taxonomy_slug) {
			// Skip taxonomies that shouldn't appear in cascading filters
			if (self::should_skip_taxonomy_for_cascading($key, $context_category, $context_subcategory)) {
				Handy_Custom_Logger::log("CASCADING: Skipping taxonomy {$key} for cascading updates", 'debug');
				continue;
			}
			
			// Get all terms used by these products for this taxonomy
			$used_terms = wp_get_object_terms($product_ids, $taxonomy_slug, array(
				'orderby' => 'name',
				'order' => 'ASC'
			));
			
			if (is_wp_error($used_terms)) {
				Handy_Custom_Logger::log("CASCADING: Error getting terms for {$taxonomy_slug}: " . $used_terms->get_error_message(), 'error');
				$filter_options[$key] = array();
				continue;
			}
			
			// CRITICAL FIX: Always include current user selection for this taxonomy
			$current_selection = isset($current_filters[$key]) ? $current_filters[$key] : '';
			if (!empty($current_selection)) {
				// Get the current selected term to ensure it's included
				$current_term = get_term_by('slug', $current_selection, $taxonomy_slug);
				if ($current_term && !is_wp_error($current_term)) {
					// Add current term to used_terms array if not already present
					$current_term_exists = false;
					foreach ($used_terms as $term) {
						if ($term->term_id === $current_term->term_id) {
							$current_term_exists = true;
							break;
						}
					}
					
					if (!$current_term_exists) {
						array_unshift($used_terms, $current_term); // Add at beginning
						Handy_Custom_Logger::log("CASCADING: Added current selection '{$current_selection}' to {$key} options (was missing from product results)", 'info');
					} else {
						Handy_Custom_Logger::log("CASCADING: Current selection '{$current_selection}' already present in {$key} options", 'debug');
					}
				}
			}
			
			// Remove duplicates and format for frontend
			$unique_terms = array();
			$term_ids = array();
			
			foreach ($used_terms as $term) {
				if (!in_array($term->term_id, $term_ids)) {
					$unique_terms[] = $term;
					$term_ids[] = $term->term_id;
				}
			}
			
			$filter_options[$key] = $unique_terms;
			
			$selection_info = !empty($current_selection) ? " (preserving current selection: {$current_selection})" : '';
			Handy_Custom_Logger::log("CASCADING: Taxonomy {$key} ({$taxonomy_slug}): " . count($unique_terms) . " available terms{$selection_info}", 'debug');
		}
		
		return $filter_options;
	}

	/**
	 * Get cascading filter options for recipes based on current filter selections
	 * Similar to products but adapted for recipe post type and taxonomies
	 *
	 * @param array $current_filters Current filter selections
	 * @param string $context_category Context category boundary
	 * @param string $context_subcategory Context subcategory boundary
	 * @return array Array of available filter options with cascading logic
	 */
	public static function get_cascading_recipe_filter_options($current_filters, $context_category = '', $context_subcategory = '') {
		Handy_Custom_Logger::log('RECIPE CASCADING: Starting filter options generation with filters: ' . wp_json_encode($current_filters), 'info');
		
		// Get matching recipes using the same logic as products but for recipes
		$matching_recipes = self::get_recipes_matching_current_filters($current_filters, $context_category, $context_subcategory);
		
		if (empty($matching_recipes)) {
			Handy_Custom_Logger::log('RECIPE CASCADING: No recipes found matching current filters', 'info');
			return array(); // Return empty options if no recipes match
		}
		
		// Extract available taxonomy terms from matching recipes
		$filter_options = self::extract_available_taxonomy_terms_from_recipes($matching_recipes, $current_filters, $context_category, $context_subcategory);
		
		Handy_Custom_Logger::log('RECIPE CASCADING: Generated options for ' . count($filter_options) . ' taxonomies from ' . count($matching_recipes) . ' recipes', 'info');
		
		return $filter_options;
	}

	/**
	 * Check if taxonomy should be skipped for cascading filters
	 * More selective logic - only skip when there's a hard context boundary constraint
	 *
	 * @param string $key Taxonomy key
	 * @param string $context_category Context category boundary
	 * @param string $context_subcategory Context subcategory boundary
	 * @return bool True if should skip
	 */
	private static function should_skip_taxonomy_for_cascading($key, $context_category = '', $context_subcategory = '') {
		// Always skip category (handled by products shortcode display mode)
		if ($key === 'category') {
			return true;
		}
		
		// CRITICAL FIX: Don't skip subcategory unless we're in a hard-coded subcategory context
		// This allows user-selected subcategory filters to still be updated with cascading options
		// Only skip if there's a context_subcategory set from shortcode attributes (not user selections)
		
		Handy_Custom_Logger::log("CASCADING: Taxonomy skip check for {$key} - context_category: '{$context_category}', context_subcategory: '{$context_subcategory}'", 'debug');
		
		return false; // For now, don't skip any taxonomies - let all be updatable
	}
	
	/**
	 * Find term fallback for common slug variations
	 * Handles cases like "crab meat" vs "crab-meat" vs "crab_meat" vs "crabmeat"
	 *
	 * @param string $search_value The value we're looking for
	 * @param string $taxonomy_slug The taxonomy to search in
	 * @return WP_Term|false Found term or false if no fallback found
	 */
	private static function find_term_fallback($search_value, $taxonomy_slug) {
		Handy_Custom_Logger::log("CASCADING: Searching for fallback term for '{$search_value}' in taxonomy '{$taxonomy_slug}'", 'debug');
		
		// Create variations of the search value
		$variations = array();
		
		// Original value
		$variations[] = $search_value;
		
		// Replace spaces with hyphens
		$variations[] = str_replace(' ', '-', $search_value);
		
		// Replace spaces with underscores  
		$variations[] = str_replace(' ', '_', $search_value);
		
		// Remove spaces entirely
		$variations[] = str_replace(' ', '', $search_value);
		
		// Lowercase version
		$variations[] = strtolower($search_value);
		$variations[] = strtolower(str_replace(' ', '-', $search_value));
		$variations[] = strtolower(str_replace(' ', '_', $search_value));
		
		// Remove duplicates
		$variations = array_unique($variations);
		
		Handy_Custom_Logger::log("CASCADING: Trying " . count($variations) . " variations: " . implode(', ', $variations), 'debug');
		
		foreach ($variations as $variation) {
			$term = get_term_by('slug', $variation, $taxonomy_slug);
			if ($term && !is_wp_error($term)) {
				Handy_Custom_Logger::log("CASCADING: Found fallback term: '{$variation}' -> '{$term->name}' (ID: {$term->term_id})", 'info');
				return $term;
			}
		}
		
		// Try searching by name instead of slug
		Handy_Custom_Logger::log("CASCADING: No slug match found, trying name search for '{$search_value}'", 'debug');
		foreach ($variations as $variation) {
			$term = get_term_by('name', $variation, $taxonomy_slug);
			if ($term && !is_wp_error($term)) {
				Handy_Custom_Logger::log("CASCADING: Found fallback term by name: '{$variation}' -> '{$term->name}' (Slug: {$term->slug}, ID: {$term->term_id})", 'info');
				return $term;
			}
		}
		
		Handy_Custom_Logger::log("CASCADING: No fallback term found for '{$search_value}' in taxonomy '{$taxonomy_slug}'", 'warning');
		return false;
	}
	
	/**
	 * Verify database term relationships when WP_Query fails
	 * Directly checks the database to see what products are assigned to terms
	 * 
	 * @param array $current_filters Current filter selections
	 * @param array $taxonomy_mapping Taxonomy mapping
	 */
	private static function verify_database_term_relationships($current_filters, $taxonomy_mapping) {
		global $wpdb;
		
		Handy_Custom_Logger::log("🔍 DATABASE VERIFICATION: Starting direct database investigation", 'info');
		
		foreach ($current_filters as $key => $value) {
			if (empty($value) || !isset($taxonomy_mapping[$key])) {
				continue;
			}
			
			$taxonomy_slug = $taxonomy_mapping[$key];
			
			// Get the term and its term_taxonomy_id
			$term_data = $wpdb->get_row($wpdb->prepare(
				"SELECT t.term_id, t.name, t.slug, tt.term_taxonomy_id, tt.taxonomy 
				 FROM {$wpdb->terms} t 
				 INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id 
				 WHERE t.slug = %s AND tt.taxonomy = %s",
				$value,
				$taxonomy_slug
			));
			
			if (!$term_data) {
				Handy_Custom_Logger::log("🔍 DB VERIFY: Term '{$value}' not found in taxonomy '{$taxonomy_slug}'", 'error');
				continue;
			}
			
			Handy_Custom_Logger::log("🔍 DB VERIFY: Found term - name: '{$term_data->name}', slug: '{$term_data->slug}', term_id: {$term_data->term_id}, term_taxonomy_id: {$term_data->term_taxonomy_id}", 'info');
			
			// Check how many products are assigned to this term using term_taxonomy_id
			$assigned_products = $wpdb->get_results($wpdb->prepare(
				"SELECT p.ID, p.post_title, p.post_status 
				 FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
				 WHERE tr.term_taxonomy_id = %d 
				 AND p.post_type = 'product'
				 LIMIT 10",
				$term_data->term_taxonomy_id
			));
			
			$product_count = count($assigned_products);
			Handy_Custom_Logger::log("🔍 DB VERIFY: Found {$product_count} products directly assigned to term '{$term_data->name}' (term_taxonomy_id: {$term_data->term_taxonomy_id})", 'info');
			
			if (!empty($assigned_products)) {
				Handy_Custom_Logger::log("🔍 DB VERIFY: Sample products assigned to '{$term_data->name}':", 'info');
				foreach (array_slice($assigned_products, 0, 5) as $product) {
					$status_info = $product->post_status !== 'publish' ? " [STATUS: {$product->post_status}]" : '';
					Handy_Custom_Logger::log("  - Product ID {$product->ID}: '{$product->post_title}'{$status_info}", 'info');
				}
				
				// If products exist but WP_Query failed, there's a query issue
				if ($product_count > 0) {
					Handy_Custom_Logger::log("🚨 QUERY BUG DETECTED: Products exist in database but WP_Query failed to find them!", 'error');
					
					// Test a simple direct query to see what WP_Query should find
					$test_query = new WP_Query(array(
						'post_type' => 'product',
						'post_status' => 'publish',
						'posts_per_page' => 5,
						'tax_query' => array(
							array(
								'taxonomy' => $taxonomy_slug,
								'field' => 'slug',
								'terms' => $value
							)
						)
					));
					
					$test_results = count($test_query->posts);
					Handy_Custom_Logger::log("🔧 SIMPLE QUERY TEST: Found {$test_results} products with simple tax_query", 'info');
					
					if ($test_results > 0) {
						Handy_Custom_Logger::log("🔧 SIMPLE QUERY SUCCESS: The issue is with complex tax_query structure!", 'error');
					} else {
						Handy_Custom_Logger::log("🔧 SIMPLE QUERY ALSO FAILED: Deeper WP_Query issue detected", 'error');
					}
				}
			} else {
				Handy_Custom_Logger::log("🔍 DB VERIFY: No products assigned to term '{$term_data->name}' - this explains the empty results", 'warning');
			}
		}
		
		Handy_Custom_Logger::log("🔍 DATABASE VERIFICATION: Investigation complete", 'info');
	}
	
	/**
	 * Get products using direct database query as fallback when WP_Query fails
	 * This bypasses WordPress query system issues and queries the database directly
	 * 
	 * @param array $tax_query_clauses Array of tax query clauses from WP_Query
	 * @return array Array of product IDs found by direct database query
	 */
	private static function get_products_by_direct_database_query($tax_query_clauses) {
		global $wpdb;
		
		Handy_Custom_Logger::log("🔧 DATABASE FALLBACK: Starting direct database query", 'info');
		Handy_Custom_Logger::log("🔧 DATABASE FALLBACK: Processing " . count($tax_query_clauses) . " tax query clauses", 'debug');
		
		if (empty($tax_query_clauses)) {
			Handy_Custom_Logger::log("🔧 DATABASE FALLBACK: No tax query clauses provided", 'warning');
			return array();
		}
		
		// Start with all published products
		$sql = "SELECT DISTINCT p.ID 
				FROM {$wpdb->posts} p";
		
		$joins = array();
		$where_conditions = array();
		$where_conditions[] = "p.post_type = 'product'";
		$where_conditions[] = "p.post_status = 'publish'";
		
		// Process each taxonomy filter
		foreach ($tax_query_clauses as $index => $clause) {
			$taxonomy = $clause['taxonomy'];
			$field = $clause['field']; // should be 'slug'
			$terms = is_array($clause['terms']) ? $clause['terms'] : array($clause['terms']);
			
			Handy_Custom_Logger::log("🔧 DATABASE FALLBACK: Processing clause {$index} - taxonomy: {$taxonomy}, field: {$field}, terms: " . implode(', ', $terms), 'debug');
			
			// Create unique table aliases
			$tr_alias = "tr{$index}";
			$tt_alias = "tt{$index}";
			$t_alias = "t{$index}";
			
			// Join term relationships table
			$joins[] = "INNER JOIN {$wpdb->term_relationships} {$tr_alias} ON p.ID = {$tr_alias}.object_id";
			
			// Join term taxonomy table
			$joins[] = "INNER JOIN {$wpdb->term_taxonomy} {$tt_alias} ON {$tr_alias}.term_taxonomy_id = {$tt_alias}.term_taxonomy_id";
			
			// Join terms table for slug/name lookup
			$joins[] = "INNER JOIN {$wpdb->terms} {$t_alias} ON {$tt_alias}.term_id = {$t_alias}.term_id";
			
			// Add taxonomy constraint
			$where_conditions[] = $wpdb->prepare("{$tt_alias}.taxonomy = %s", $taxonomy);
			
			// Add term constraints
			if ($field === 'slug') {
				$term_placeholders = implode(',', array_fill(0, count($terms), '%s'));
				$where_conditions[] = $wpdb->prepare("{$t_alias}.slug IN ({$term_placeholders})", $terms);
			} else {
				// Fallback for other field types
				$term_placeholders = implode(',', array_fill(0, count($terms), '%s'));
				$where_conditions[] = $wpdb->prepare("{$t_alias}.{$field} IN ({$term_placeholders})", $terms);
			}
		}
		
		// Build the complete SQL query
		$complete_sql = $sql . ' ' . implode(' ', $joins) . ' WHERE ' . implode(' AND ', $where_conditions) . ' ORDER BY p.ID';
		
		Handy_Custom_Logger::log("🔧 DATABASE FALLBACK: Executing SQL query: " . $complete_sql, 'debug');
		
		// Execute the query
		$results = $wpdb->get_col($complete_sql);
		
		if ($wpdb->last_error) {
			Handy_Custom_Logger::log("🔧 DATABASE FALLBACK: SQL Error: " . $wpdb->last_error, 'error');
			return array();
		}
		
		$product_count = count($results);
		Handy_Custom_Logger::log("🔧 DATABASE FALLBACK: Direct query found {$product_count} products" . (!empty($results) ? ' (IDs: ' . implode(', ', array_slice($results, 0, 5)) . (count($results) > 5 ? '...' : '') . ')' : ''), 'info');
		
		return array_map('intval', $results); // Ensure integer IDs
	}
	
	/**
	 * Get all published products using direct database query
	 * Used as fallback when WP_Query fails to return any products (including unfiltered queries)
	 * 
	 * @return array Array of all published product IDs
	 */
	private static function get_all_published_products_direct() {
		global $wpdb;
		
		Handy_Custom_Logger::log("🔧 UNFILTERED FALLBACK: Getting all published products via direct query", 'info');
		
		// Simple direct query for all published products
		$sql = "SELECT p.ID 
				FROM {$wpdb->posts} p
				WHERE p.post_type = 'product' 
				AND p.post_status = 'publish'
				ORDER BY p.ID";
		
		Handy_Custom_Logger::log("🔧 UNFILTERED FALLBACK: Executing SQL: " . $sql, 'debug');
		
		// Execute the query
		$results = $wpdb->get_col($sql);
		
		if ($wpdb->last_error) {
			Handy_Custom_Logger::log("🔧 UNFILTERED FALLBACK: SQL Error: " . $wpdb->last_error, 'error');
			return array();
		}
		
		$product_count = count($results);
		Handy_Custom_Logger::log("🔧 UNFILTERED FALLBACK: Direct query found {$product_count} published products" . (!empty($results) ? ' (sample IDs: ' . implode(', ', array_slice($results, 0, 5)) . (count($results) > 5 ? '...' : '') . ')' : ''), 'info');
		
		return array_map('intval', $results); // Ensure integer IDs
	}

	/**
	 * Get recipes matching current filters with database fallback
	 * Similar to get_products_matching_current_filters but for recipes
	 *
	 * @param array $current_filters Current filter selections
	 * @param string $context_category Context category boundary
	 * @param string $context_subcategory Context subcategory boundary
	 * @return array Array of recipe IDs
	 */
	private static function get_recipes_matching_current_filters($current_filters, $context_category = '', $context_subcategory = '') {
		Handy_Custom_Logger::log('RECIPE CASCADING: Getting recipes matching filters: ' . wp_json_encode($current_filters), 'info');
		
		// Build query args similar to products but for recipes
		$query_args = array(
			'post_type' => 'recipe',
			'post_status' => 'publish',
			'posts_per_page' => 1000, // High limit for comprehensive filtering
			'fields' => 'ids' // Only need IDs for performance
		);
		
		// Build tax_query from current filters with enhanced validation
		$tax_query_clauses = array(); // Store individual tax query clauses
		$taxonomy_mapping = Handy_Custom_Recipes_Utils::get_taxonomy_mapping();
		$valid_filters_count = 0;
		
		foreach ($current_filters as $key => $value) {
			if (empty($value) || !isset($taxonomy_mapping[$key])) {
				if (!empty($value)) {
					Handy_Custom_Logger::log("RECIPE CASCADING: Skipping unknown filter key: {$key} = {$value}", 'warning');
				}
				continue;
			}
			
			$taxonomy_slug = $taxonomy_mapping[$key];
			
			// CRITICAL: Validate term exists before adding to query
			$term = get_term_by('slug', $value, $taxonomy_slug);
			if (!$term || is_wp_error($term)) {
				Handy_Custom_Logger::log("RECIPE CASCADING: TERM NOT FOUND - {$key} ({$taxonomy_slug}): '{$value}' does not exist in database", 'error');
				continue; // Skip this filter entirely
			} else {
				Handy_Custom_Logger::log("RECIPE CASCADING: TERM VALIDATED - {$key} ({$taxonomy_slug}): '{$value}' exists (ID: {$term->term_id}, Name: '{$term->name}')", 'info');
			}
			
			$tax_query_clauses[] = array(
				'taxonomy' => $taxonomy_slug,
				'field' => 'slug',
				'terms' => $value
			);
			
			$valid_filters_count++;
			Handy_Custom_Logger::log("RECIPE CASCADING: Added validated tax query for {$key} ({$taxonomy_slug}): {$value}", 'debug');
		}
		
		// Add context boundaries if they exist (similar to products)
		if (!empty($context_subcategory)) {
			$context_term = get_term_by('slug', $context_subcategory, 'recipe-category');
			if ($context_term && !is_wp_error($context_term)) {
				$tax_query_clauses[] = array(
					'taxonomy' => 'recipe-category',
					'field' => 'slug',
					'terms' => $context_subcategory,
					'include_children' => true
				);
				$valid_filters_count++;
				Handy_Custom_Logger::log("RECIPE CASCADING: Added validated context subcategory constraint: {$context_subcategory}", 'debug');
			}
		} elseif (!empty($context_category)) {
			$context_term = get_term_by('slug', $context_category, 'recipe-category');
			if ($context_term && !is_wp_error($context_term)) {
				$tax_query_clauses[] = array(
					'taxonomy' => 'recipe-category',
					'field' => 'slug',
					'terms' => $context_category,
					'include_children' => true
				);
				$valid_filters_count++;
				Handy_Custom_Logger::log("RECIPE CASCADING: Added validated context category constraint: {$context_category}", 'debug');
			}
		}
		
		// Only add tax_query if we have valid filters
		if ($valid_filters_count > 0) {
			$query_args['tax_query'] = array('relation' => 'AND');
			
			// Add each clause as a properly indexed array element
			foreach ($tax_query_clauses as $clause) {
				$query_args['tax_query'][] = $clause;
			}
			
			Handy_Custom_Logger::log("RECIPE CASCADING: Built tax_query with {$valid_filters_count} valid filters: " . wp_json_encode($query_args['tax_query']), 'debug');
		} else {
			Handy_Custom_Logger::log("RECIPE CASCADING: NO VALID FILTERS - Query will return all recipes", 'warning');
		}
		
		// Execute query with enhanced logging
		Handy_Custom_Logger::log("RECIPE CASCADING: Executing WP_Query with args: " . wp_json_encode($query_args), 'debug');
		
		// Add database verification
		global $wpdb;
		$direct_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'recipe' AND post_status = 'publish'");
		Handy_Custom_Logger::log("RECIPE CASCADING: Direct DB count of published recipes: " . $direct_count, 'info');
		
		$wp_query = new WP_Query($query_args);
		$recipe_ids = wp_list_pluck($wp_query->posts, 'ID');
		
		Handy_Custom_Logger::log('RECIPE CASCADING: Query found ' . count($recipe_ids) . ' matching recipes' . (!empty($recipe_ids) ? ' (IDs: ' . implode(', ', array_slice($recipe_ids, 0, 5)) . (count($recipe_ids) > 5 ? '...' : '') . ')' : ''), 'info');
		
		// If no recipes found, implement database fallback (similar to products)
		if (empty($recipe_ids)) {
			Handy_Custom_Logger::log("RECIPE CASCADING: ZERO RECIPES FOUND - Attempting database fallback", 'error');
			
			// Try database fallback
			if ($valid_filters_count > 0) {
				$fallback_recipe_ids = self::get_recipes_by_direct_database_query($tax_query_clauses);
				if (!empty($fallback_recipe_ids)) {
					Handy_Custom_Logger::log("RECIPE CASCADING: DATABASE FALLBACK SUCCESS - Found " . count($fallback_recipe_ids) . " recipes via direct query", 'info');
					$recipe_ids = $fallback_recipe_ids;
				} else {
					Handy_Custom_Logger::log("RECIPE CASCADING: DATABASE FALLBACK FAILED - Direct query also returned 0 recipes", 'error');
				}
			} else {
				// Handle unfiltered queries (clear button case)
				Handy_Custom_Logger::log("RECIPE CASCADING: NO FILTERS APPLIED - Attempting direct query for all published recipes", 'info');
				$unfiltered_recipe_ids = self::get_all_published_recipes_direct();
				if (!empty($unfiltered_recipe_ids)) {
					Handy_Custom_Logger::log("RECIPE CASCADING: UNFILTERED FALLBACK SUCCESS - Found " . count($unfiltered_recipe_ids) . " total recipes", 'info');
					$recipe_ids = $unfiltered_recipe_ids;
				} else {
					Handy_Custom_Logger::log("RECIPE CASCADING: UNFILTERED FALLBACK FAILED - No published recipes found in database", 'error');
				}
			}
		}
		
		return $recipe_ids;
	}

	/**
	 * Extract available taxonomy terms from a set of recipes
	 * Similar to extract_available_taxonomy_terms_from_products but for recipes
	 *
	 * @param array $recipe_ids Array of recipe IDs
	 * @param array $current_filters Current filter selections (must be preserved in results)
	 * @param string $context_category Context category boundary
	 * @param string $context_subcategory Context subcategory boundary
	 * @return array Array of available filter options
	 */
	private static function extract_available_taxonomy_terms_from_recipes($recipe_ids, $current_filters, $context_category = '', $context_subcategory = '') {
		$filter_options = array();
		$taxonomy_mapping = Handy_Custom_Recipes_Utils::get_taxonomy_mapping();
		
		Handy_Custom_Logger::log('RECIPE CASCADING: Extracting terms from ' . count($recipe_ids) . ' recipes for ' . count($taxonomy_mapping) . ' taxonomies', 'info');
		
		foreach ($taxonomy_mapping as $key => $taxonomy_slug) {
			// Skip taxonomies that shouldn't appear in cascading filters (similar logic to products)
			if ($key === 'category') {
				continue; // Skip main category like products
			}
			
			// Get all terms used by these recipes for this taxonomy
			$used_terms = wp_get_object_terms($recipe_ids, $taxonomy_slug, array(
				'orderby' => 'name',
				'order' => 'ASC'
			));
			
			if (is_wp_error($used_terms)) {
				Handy_Custom_Logger::log("RECIPE CASCADING: Error getting terms for {$taxonomy_slug}: " . $used_terms->get_error_message(), 'error');
				$filter_options[$key] = array();
				continue;
			}
			
			// Always include current user selection for this taxonomy
			$current_selection = isset($current_filters[$key]) ? $current_filters[$key] : '';
			if (!empty($current_selection)) {
				$current_term = get_term_by('slug', $current_selection, $taxonomy_slug);
				if ($current_term && !is_wp_error($current_term)) {
					// Add current term if not already present
					$current_term_exists = false;
					foreach ($used_terms as $term) {
						if ($term->term_id === $current_term->term_id) {
							$current_term_exists = true;
							break;
						}
					}
					
					if (!$current_term_exists) {
						array_unshift($used_terms, $current_term);
						Handy_Custom_Logger::log("RECIPE CASCADING: Added current selection '{$current_selection}' to {$key} options", 'info');
					}
				}
			}
			
			// Remove duplicates and format for frontend
			$unique_terms = array();
			$term_ids = array();
			
			foreach ($used_terms as $term) {
				if (!in_array($term->term_id, $term_ids)) {
					$unique_terms[] = $term;
					$term_ids[] = $term->term_id;
				}
			}
			
			$filter_options[$key] = $unique_terms;
			
			$selection_info = !empty($current_selection) ? " (preserving current selection: {$current_selection})" : '';
			Handy_Custom_Logger::log("RECIPE CASCADING: Taxonomy {$key} ({$taxonomy_slug}): " . count($unique_terms) . " available terms{$selection_info}", 'debug');
		}
		
		return $filter_options;
	}

	/**
	 * Get recipes using direct database query as fallback when WP_Query fails
	 * Similar to get_products_by_direct_database_query but for recipes
	 * 
	 * @param array $tax_query_clauses Array of tax query clauses from WP_Query
	 * @return array Array of recipe IDs found by direct database query
	 */
	private static function get_recipes_by_direct_database_query($tax_query_clauses) {
		global $wpdb;
		
		Handy_Custom_Logger::log("🔧 RECIPE DATABASE FALLBACK: Starting direct database query", 'info');
		
		if (empty($tax_query_clauses)) {
			return array();
		}
		
		// Start with all published recipes
		$sql = "SELECT DISTINCT p.ID 
				FROM {$wpdb->posts} p";
		
		$joins = array();
		$where_conditions = array();
		$where_conditions[] = "p.post_type = 'recipe'";
		$where_conditions[] = "p.post_status = 'publish'";
		
		// Process each taxonomy filter (same logic as products)
		foreach ($tax_query_clauses as $index => $clause) {
			$taxonomy = $clause['taxonomy'];
			$field = $clause['field'];
			$terms = is_array($clause['terms']) ? $clause['terms'] : array($clause['terms']);
			
			// Create unique table aliases
			$tr_alias = "tr{$index}";
			$tt_alias = "tt{$index}";
			$t_alias = "t{$index}";
			
			// Join term relationships table
			$joins[] = "INNER JOIN {$wpdb->term_relationships} {$tr_alias} ON p.ID = {$tr_alias}.object_id";
			$joins[] = "INNER JOIN {$wpdb->term_taxonomy} {$tt_alias} ON {$tr_alias}.term_taxonomy_id = {$tt_alias}.term_taxonomy_id";
			$joins[] = "INNER JOIN {$wpdb->terms} {$t_alias} ON {$tt_alias}.term_id = {$t_alias}.term_id";
			
			$where_conditions[] = $wpdb->prepare("{$tt_alias}.taxonomy = %s", $taxonomy);
			
			if ($field === 'slug') {
				$term_placeholders = implode(',', array_fill(0, count($terms), '%s'));
				$where_conditions[] = $wpdb->prepare("{$t_alias}.slug IN ({$term_placeholders})", $terms);
			}
		}
		
		// Build the complete SQL query
		$complete_sql = $sql . ' ' . implode(' ', $joins) . ' WHERE ' . implode(' AND ', $where_conditions) . ' ORDER BY p.ID';
		
		Handy_Custom_Logger::log("🔧 RECIPE DATABASE FALLBACK: Executing SQL: " . $complete_sql, 'debug');
		
		// Execute the query
		$results = $wpdb->get_col($complete_sql);
		
		if ($wpdb->last_error) {
			Handy_Custom_Logger::log("🔧 RECIPE DATABASE FALLBACK: SQL Error: " . $wpdb->last_error, 'error');
			return array();
		}
		
		Handy_Custom_Logger::log("🔧 RECIPE DATABASE FALLBACK: Direct query found " . count($results) . " recipes", 'info');
		
		return array_map('intval', $results);
	}

	/**
	 * Get all published recipes using direct database query
	 * Similar to get_all_published_products_direct but for recipes
	 * 
	 * @return array Array of all published recipe IDs
	 */
	private static function get_all_published_recipes_direct() {
		global $wpdb;
		
		Handy_Custom_Logger::log("🔧 RECIPE UNFILTERED FALLBACK: Getting all published recipes via direct query", 'info');
		
		$sql = "SELECT p.ID 
				FROM {$wpdb->posts} p
				WHERE p.post_type = 'recipe' 
				AND p.post_status = 'publish'
				ORDER BY p.ID";
		
		$results = $wpdb->get_col($sql);
		
		if ($wpdb->last_error) {
			Handy_Custom_Logger::log("🔧 RECIPE UNFILTERED FALLBACK: SQL Error: " . $wpdb->last_error, 'error');
			return array();
		}
		
		Handy_Custom_Logger::log("🔧 RECIPE UNFILTERED FALLBACK: Direct query found " . count($results) . " published recipes", 'info');
		
		return array_map('intval', $results);
	}
}