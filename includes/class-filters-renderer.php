<?php
/**
 * Unified filter rendering functionality for both products and recipes
 *
 * User request: "let's keep the files to a minimum and combine when it makes sense. 
 * I think 1 css file for all filters and 1 js file for all filters"
 *
 * @package Handy_Custom
 */

if (!defined('ABSPATH')) {
	exit;
}

class Handy_Custom_Filters_Renderer {

	/**
	 * Render filters based on content type
	 * 
	 * @param string $content_type 'products' or 'recipes'
	 * @param array $attributes Shortcode attributes (display, exclude)
	 * @return string Rendered filter HTML
	 */
	public function render($content_type, $attributes = array()) {
		Handy_Custom_Logger::log("Rendering {$content_type} filters with attributes: " . wp_json_encode($attributes), 'info');
		
		// Get taxonomies based on content type
		$taxonomies = $this->get_taxonomies_for_content_type($content_type);
		Handy_Custom_Logger::log("Available taxonomies for {$content_type}: " . wp_json_encode(array_keys($taxonomies)), 'info');
		
		// Apply display/exclude parameters to filter taxonomies
		$filtered_taxonomies = $this->filter_taxonomies($taxonomies, $attributes);
		Handy_Custom_Logger::log("Filtered taxonomies for display: " . wp_json_encode(array_keys($filtered_taxonomies)), 'info');
		
		// Extract context filters from attributes
		$context_filters = array();
		if (!empty($attributes['category'])) {
			$context_filters['category'] = $attributes['category'];
		}
		if (!empty($attributes['subcategory'])) {
			$context_filters['subcategory'] = $attributes['subcategory'];
		}
		
		// Generate filter options for each taxonomy with context filtering
		$filter_options = $this->generate_filter_options($filtered_taxonomies, $content_type, $context_filters);
		$context_info = !empty($context_filters) ? ' with context: ' . wp_json_encode($context_filters) : '';
		Handy_Custom_Logger::log("Generated filter options for {$content_type}: " . count($filter_options) . " taxonomies with terms{$context_info}", 'info');
		
		// Get current URL parameters for pre-selecting filters
		$current_filters = $this->get_current_url_parameters($content_type);
		if (!empty($current_filters)) {
			Handy_Custom_Logger::log("Current URL filter parameters: " . wp_json_encode($current_filters), 'info');
		}
		
		// Load unified template
		return $this->load_template($content_type, $filter_options, $current_filters, $attributes);
	}

	/**
	 * Get taxonomies for specific content type
	 *
	 * @param string $content_type 'products' or 'recipes'
	 * @return array Taxonomy mapping array
	 */
	private function get_taxonomies_for_content_type($content_type) {
		switch ($content_type) {
			case 'products':
				if (class_exists('Handy_Custom_Products_Utils')) {
					return Handy_Custom_Products_Utils::get_taxonomy_mapping();
				}
				Handy_Custom_Logger::log("Products utils class not found", 'error');
				return array();
				
			case 'recipes':
				if (class_exists('Handy_Custom_Recipes_Utils')) {
					return Handy_Custom_Recipes_Utils::get_taxonomy_mapping();
				}
				Handy_Custom_Logger::log("Recipes utils class not found", 'error');
				return array();
				
			default:
				Handy_Custom_Logger::log("Unknown content type: {$content_type}", 'error');
				return array();
		}
	}

	/**
	 * Filter taxonomies based on display/exclude parameters
	 *
	 * @param array $taxonomies Available taxonomies
	 * @param array $attributes Shortcode attributes
	 * @return array Filtered taxonomies
	 */
	private function filter_taxonomies($taxonomies, $attributes) {
		// If display parameter is specified, only show those taxonomies
		if (!empty($attributes['display'])) {
			$display_list = array_map('trim', explode(',', $attributes['display']));
			$filtered = array();
			
			if (!empty($display_list) && is_array($display_list)) {
				foreach ($display_list as $key) {
					if (is_string($key) && !empty($key) && isset($taxonomies[$key])) {
						$filtered[$key] = $taxonomies[$key];
						Handy_Custom_Logger::log("Including taxonomy in display: {$key}", 'debug');
					} else {
						Handy_Custom_Logger::log("Requested taxonomy not found or invalid: {$key}", 'warning');
					}
				}
			} else {
				Handy_Custom_Logger::log('Display list is empty or invalid', 'warning');
			}
			
			return $filtered;
		}
		
		// If exclude parameter is specified, remove those taxonomies
		if (!empty($attributes['exclude'])) {
			$exclude_list = array_map('trim', explode(',', $attributes['exclude']));
			$filtered = $taxonomies;
			
			if (!empty($exclude_list) && is_array($exclude_list)) {
				foreach ($exclude_list as $key) {
					if (is_string($key) && !empty($key) && isset($filtered[$key])) {
						unset($filtered[$key]);
						Handy_Custom_Logger::log("Excluding taxonomy from display: {$key}", 'debug');
					}
				}
			} else {
				Handy_Custom_Logger::log('Exclude list is empty or invalid', 'warning');
			}
			
			return $filtered;
		}
		
		// Default: return all taxonomies
		Handy_Custom_Logger::log("No display/exclude filters applied, showing all taxonomies", 'debug');
		return $taxonomies;
	}

	/**
	 * Generate filter options for each taxonomy
	 * Now supports contextual filtering based on category/subcategory
	 * Only shows terms that are actually used by products in the specified context
	 *
	 * @param array $taxonomies Filtered taxonomies to include
	 * @param string $content_type Content type for utils class selection
	 * @param array $context_filters Category/subcategory context filters
	 * @return array Filter options with terms
	 */
	private function generate_filter_options($taxonomies, $content_type, $context_filters = array()) {
		$options = array();
		
		if (empty($taxonomies) || !is_array($taxonomies)) {
			Handy_Custom_Logger::log('Generate filter options: taxonomies array is empty or invalid', 'warning');
			return $options;
		}
		
		foreach ($taxonomies as $key => $taxonomy_slug) {
			if (!is_string($key) || empty($key) || !is_string($taxonomy_slug) || empty($taxonomy_slug)) {
				Handy_Custom_Logger::log("Invalid taxonomy mapping: key='{$key}', taxonomy='{$taxonomy_slug}'", 'warning');
				continue;
			}
			// Skip certain taxonomies that shouldn't appear in filters
			if ($this->should_skip_taxonomy($key, $content_type)) {
				Handy_Custom_Logger::log("Skipping taxonomy for filters: {$key}", 'debug');
				continue;
			}
			
			// Get terms for this taxonomy with context filtering
			$terms = $this->get_contextual_taxonomy_terms($taxonomy_slug, $content_type, $context_filters);
			
			// Always include the taxonomy, even if empty - this allows dynamic updates
			$options[$key] = $terms;
			
			$term_count = is_array($terms) ? count($terms) : 0;
			$context_info = !empty($context_filters) ? ' (context: ' . wp_json_encode($context_filters) . ')' : '';
			Handy_Custom_Logger::log("Taxonomy {$key} ({$taxonomy_slug}): {$term_count} terms{$context_info}", 'debug');
		}
		
		return $options;
	}

	/**
	 * Check if taxonomy should be skipped for filters
	 *
	 * @param string $key Taxonomy key
	 * @param string $content_type Content type
	 * @return bool True if should skip
	 */
	private function should_skip_taxonomy($key, $content_type) {
		// For products: skip category in standalone filters (it's handled by products shortcode display mode)
		if ($content_type === 'products' && $key === 'category') {
			return true;
		}
		
		// For products: skip subcategory (it's handled differently)
		if ($content_type === 'products' && $key === 'subcategory') {
			return true;
		}
		
		return false;
	}

	/**
	 * Get taxonomy terms filtered by context (category/subcategory)
	 * Only returns terms that are actually used by products in the specified context
	 * 
	 * User requirement: "same with the [filter-products subcategory="{subcategory}"] is used, 
	 * it needs to list all filters that are used for that subcategory - if there are 0 options 
	 * selected for menu-occation- then menu occasion shouldn't even be a filter seen"
	 *
	 * @param string $taxonomy_slug Taxonomy slug
	 * @param string $content_type Content type for utils class
	 * @param array $context_filters Category/subcategory context filters
	 * @return array Array of term objects that are actually used in context
	 */
	private function get_contextual_taxonomy_terms($taxonomy_slug, $content_type, $context_filters = array()) {
		// If no context filters, return all terms
		if (empty($context_filters)) {
			return $this->get_taxonomy_terms($taxonomy_slug, $content_type);
		}
		
		// Build query args to find products in the specified context
		$query_args = array(
			'post_type' => $content_type === 'products' ? 'product' : 'recipe',
			'post_status' => 'publish',
			'posts_per_page' => 1000,  // High limit for contextual filtering
			'fields' => 'ids'  // Only get IDs for performance
		);
		
		// Add taxonomy query for context filtering
		$tax_query = array('relation' => 'AND');
		
		// Handle subcategory filtering (specific subcategory only)
		if (!empty($context_filters['subcategory'])) {
			$tax_query[] = array(
				'taxonomy' => $content_type === 'products' ? 'product-category' : 'recipe-category',
				'field' => 'slug',
				'terms' => $context_filters['subcategory'],
				'include_children' => false  // Exact subcategory match only
			);
			Handy_Custom_Logger::log("Filtering by subcategory: {$context_filters['subcategory']}", 'info');
		}
		// Handle category filtering (category and its subcategories if no specific subcategory)
		elseif (!empty($context_filters['category'])) {
			$category_term = get_term_by('slug', $context_filters['category'], $content_type === 'products' ? 'product-category' : 'recipe-category');
			
			if ($category_term && !is_wp_error($category_term)) {
				// Check if this category has subcategories
				$subcategories = get_terms(array(
					'taxonomy' => $content_type === 'products' ? 'product-category' : 'recipe-category',
					'parent' => $category_term->term_id,
					'hide_empty' => false
				));
				
				if (!empty($subcategories) && !is_wp_error($subcategories)) {
					// Category has subcategories - include children in filter
					$tax_query[] = array(
						'taxonomy' => $content_type === 'products' ? 'product-category' : 'recipe-category',
						'field' => 'slug',
						'terms' => $context_filters['category'],
						'include_children' => true
					);
					Handy_Custom_Logger::log("Filtering by category with subcategories: {$context_filters['category']}", 'info');
				} else {
					// Category has no subcategories - exact match only
					$tax_query[] = array(
						'taxonomy' => $content_type === 'products' ? 'product-category' : 'recipe-category',
						'field' => 'slug',
						'terms' => $context_filters['category'],
						'include_children' => false
					);
					Handy_Custom_Logger::log("Filtering by category without subcategories: {$context_filters['category']}", 'info');
				}
			}
		}
		
		if (count($tax_query) > 1) {
			$query_args['tax_query'] = $tax_query;
		}
		
		// Get product/recipe IDs in the specified context with caching
		$cache_key = Handy_Custom_Base_Utils::generate_query_cache_key($query_args, $content_type . '_context');
		$cached_query = Handy_Custom_Base_Utils::get_cached_query($cache_key);
		
		if (false !== $cached_query) {
			// Return cached post IDs
			$posts_in_context = wp_list_pluck($cached_query->posts, 'ID');
			Handy_Custom_Logger::log("Using cached contextual query for {$content_type}: " . count($posts_in_context) . " posts", 'info');
		} else {
			// Execute query and cache results
			$wp_query = new WP_Query($query_args);
			$posts_in_context = wp_list_pluck($wp_query->posts, 'ID');
			
			// Cache the query results
			Handy_Custom_Base_Utils::cache_query_results($cache_key, $wp_query);
			
			// Log if we hit the limit (may need to increase)
			if (count($posts_in_context) >= 1000) {
				Handy_Custom_Logger::log("Contextual query for {$content_type} hit limit of 1000 posts - consider increasing if taxonomy filtering seems incomplete", 'warning');
			}
			
			Handy_Custom_Logger::log("Executed and cached contextual query for {$content_type}: " . count($posts_in_context) . " posts", 'info');
		}
		
		if (empty($posts_in_context)) {
			Handy_Custom_Logger::log("No {$content_type} found in context: " . wp_json_encode($context_filters), 'info');
			return array();
		}
		
		Handy_Custom_Logger::log("Found " . count($posts_in_context) . " {$content_type} in context: " . wp_json_encode($context_filters), 'info');
		
		// Get all terms actually used by these products/recipes
		$used_terms = wp_get_object_terms($posts_in_context, $taxonomy_slug, array(
			'orderby' => 'name',
			'order' => 'ASC'
		));
		
		if (is_wp_error($used_terms)) {
			Handy_Custom_Logger::log("Error getting used terms for {$taxonomy_slug}: " . $used_terms->get_error_message(), 'error');
			return array();
		}
		
		// Remove duplicates and return unique terms
		$unique_terms = array();
		$term_ids = array();
		
		foreach ($used_terms as $term) {
			if (!in_array($term->term_id, $term_ids)) {
				$unique_terms[] = $term;
				$term_ids[] = $term->term_id;
			}
		}
		
		Handy_Custom_Logger::log("Contextual filtering for {$taxonomy_slug}: " . count($unique_terms) . " unique terms used in context", 'info');
		
		return $unique_terms;
	}

	/**
	 * Get taxonomy terms using appropriate utils class (fallback method)
	 *
	 * @param string $taxonomy_slug Taxonomy slug
	 * @param string $content_type Content type for utils class
	 * @return array Array of term objects
	 */
	private function get_taxonomy_terms($taxonomy_slug, $content_type) {
		$args = array(
			'hide_empty' => false,  // Show all terms for dynamic updates
			'orderby' => 'name',
			'order' => 'ASC'
		);
		
		switch ($content_type) {
			case 'products':
				if (class_exists('Handy_Custom_Products_Utils')) {
					return Handy_Custom_Products_Utils::get_taxonomy_terms($taxonomy_slug, $args);
				}
				break;
				
			case 'recipes':
				if (class_exists('Handy_Custom_Recipes_Utils')) {
					return Handy_Custom_Recipes_Utils::get_taxonomy_terms($taxonomy_slug, $args);
				}
				break;
		}
		
		// Fallback to direct WordPress function
		$terms = get_terms(array_merge($args, array('taxonomy' => $taxonomy_slug)));
		
		if (is_wp_error($terms)) {
			Handy_Custom_Logger::log("Error getting terms for {$taxonomy_slug}: " . $terms->get_error_message(), 'error');
			return array();
		}
		
		return is_array($terms) ? $terms : array();
	}

	/**
	 * Get current URL parameters for filter pre-selection
	 *
	 * @param string $content_type Content type to determine relevant parameters
	 * @return array Current filter values from URL
	 */
	private function get_current_url_parameters($content_type) {
		$current_filters = array();
		$taxonomies = $this->get_taxonomies_for_content_type($content_type);
		
		foreach (array_keys($taxonomies) as $key) {
			if (isset($_GET[$key]) && !empty($_GET[$key])) {
				$current_filters[$key] = sanitize_text_field($_GET[$key]);
			}
		}
		
		return $current_filters;
	}

	/**
	 * Check if any filters are currently active
	 * Used to determine whether to show Clear Filters button
	 *
	 * @param array $filters Current filter values from URL or form
	 * @return bool True if any filters are active
	 */
	public function has_active_filters($filters = array()) {
		// If no filters passed, get from URL parameters
		if (empty($filters)) {
			$filters = $_GET;
		}

		// Define filter keys to check (excluding pagination and display parameters)
		$filter_keys = array(
			'category', 'subcategory', 'grade', 'market_segment', 
			'cooking_method', 'menu_occasion', 'product_type', 'size'
		);

		foreach ($filter_keys as $key) {
			if (!empty($filters[$key]) && $filters[$key] !== '') {
				Handy_Custom_Logger::log("Active filter detected: {$key} = {$filters[$key]}", 'debug');
				return true;
			}
		}

		Handy_Custom_Logger::log('No active filters detected', 'debug');
		return false;
	}

	/**
	 * Load unified filter template
	 *
	 * @param string $content_type Content type (products/recipes)
	 * @param array $filter_options Generated filter options
	 * @param array $current_filters Current filter values from URL
	 * @param array $attributes Shortcode attributes
	 * @return string Rendered HTML
	 */
	private function load_template($content_type, $filter_options, $current_filters, $attributes) {
		$template_path = HANDY_CUSTOM_PLUGIN_DIR . 'templates/shortcodes/filters/archive.php';

		if (!file_exists($template_path)) {
			Handy_Custom_Logger::log("Filter template not found: {$template_path}", 'error');
			return '<div class="filter-error"><p>Filter template not found.</p></div>';
		}

		// Start output buffering
		ob_start();

		// Extract variables for template use
		$filters = $current_filters;
		
		// Add active filter detection for template
		$has_active_filters = $this->has_active_filters($current_filters);
		
		Handy_Custom_Logger::log("Loading filter template for {$content_type} with " . count($filter_options) . " filter groups, active filters: " . ($has_active_filters ? 'yes' : 'no'), 'info');

		// Include template
		include $template_path;

		return ob_get_clean();
	}
}