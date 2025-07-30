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
		Handy_Custom_Logger::log("🚀 RENDERER ENTRY POINT - Content type: {$content_type}, Attributes: " . wp_json_encode($attributes), 'info');
		
		// Get taxonomies based on content type
		Handy_Custom_Logger::log("🚀 RENDERER: Getting taxonomies for content type: {$content_type}", 'info');
		$taxonomies = $this->get_taxonomies_for_content_type($content_type);
		Handy_Custom_Logger::log("Available taxonomies for {$content_type}: " . wp_json_encode(array_keys($taxonomies)), 'info');
		
		// Apply display/exclude parameters to filter taxonomies
		$filtered_taxonomies = $this->filter_taxonomies($taxonomies, $attributes);
		Handy_Custom_Logger::log("Filtered taxonomies for display: " . wp_json_encode(array_keys($filtered_taxonomies)), 'info');
		
		// Extract context filters from attributes
		$context_filters = array();
		if (!empty($attributes['category'])) {
			$context_filters['category'] = $attributes['category'];
			Handy_Custom_Logger::log("RENDERER: Category context set to: " . $attributes['category'], 'info');
		}
		if (!empty($attributes['subcategory'])) {
			$context_filters['subcategory'] = $attributes['subcategory'];
			Handy_Custom_Logger::log("RENDERER: Subcategory context set to: " . $attributes['subcategory'], 'info');
		}
		
		Handy_Custom_Logger::log("RENDERER: Final context filters: " . wp_json_encode($context_filters), 'info');
		
		// If no explicit context provided, try to auto-detect from current URL
		if (empty($context_filters)) {
			$auto_detected_context = $this->detect_current_category_context($content_type);
			if (!empty($auto_detected_context)) {
				$context_filters = $auto_detected_context;
				Handy_Custom_Logger::log("Using auto-detected context for {$content_type} filters: " . wp_json_encode($context_filters), 'info');
			}
		} else {
			Handy_Custom_Logger::log("Using explicit context for {$content_type} filters: " . wp_json_encode($context_filters), 'info');
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
		
		// Prepare context data for JavaScript (preserves boundaries)
		$context_data = array(
			'context_category' => !empty($context_filters['category']) ? $context_filters['category'] : '',
			'context_subcategory' => !empty($context_filters['subcategory']) ? $context_filters['subcategory'] : ''
		);
		
		// Load unified template
		return $this->load_template($content_type, $filter_options, $current_filters, $attributes, $context_data);
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
			if ($this->should_skip_taxonomy($key, $content_type, $context_filters)) {
				Handy_Custom_Logger::log("Skipping taxonomy for filters: {$key}", 'debug');
				continue;
			}
			
			// Get terms for this taxonomy with context filtering
			$terms = $this->get_contextual_taxonomy_terms($taxonomy_slug, $content_type, $context_filters);
			
			// CRITICAL DEBUG: Log actual term data being sent to template
			if (!empty($terms) && is_array($terms)) {
				Handy_Custom_Logger::log("DROPDOWN DEBUG: Terms for {$key} ({$taxonomy_slug}):", 'info');
				foreach (array_slice($terms, 0, 5) as $term) {
					if (is_object($term) && isset($term->slug, $term->name)) {
						Handy_Custom_Logger::log("  - Term: name='{$term->name}', slug='{$term->slug}', ID={$term->term_id}", 'info');
					} else {
						Handy_Custom_Logger::log("  - Invalid term object: " . wp_json_encode($term), 'error');
					}
				}
				if (count($terms) > 5) {
					Handy_Custom_Logger::log("  - ... and " . (count($terms) - 5) . " more terms", 'info');
				}
			}
			
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
	 * Enhanced to handle context-aware filtering
	 *
	 * @param string $key Taxonomy key
	 * @param string $content_type Content type
	 * @param array $context_filters Current context filters (optional)
	 * @return bool True if should skip
	 */
	private function should_skip_taxonomy($key, $content_type, $context_filters = array()) {
		// For products: skip category in standalone filters (it's handled by products shortcode display mode)
		if ($content_type === 'products' && $key === 'category') {
			return true;
		}
		
		// Skip subcategory filter when we're already in a specific subcategory context
		// This prevents users from breaking out of the intended filtering scope
		if ($content_type === 'products' && $key === 'subcategory' && !empty($context_filters['subcategory'])) {
			Handy_Custom_Logger::log("Skipping subcategory filter - already in subcategory context: {$context_filters['subcategory']}", 'info');
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
		Handy_Custom_Logger::log("CONTEXTUAL TERMS: Starting for taxonomy '{$taxonomy_slug}' with context: " . wp_json_encode($context_filters), 'info');
		
		// If no context filters, return all terms
		if (empty($context_filters)) {
			Handy_Custom_Logger::log("CONTEXTUAL TERMS: No context filters, returning all terms for {$taxonomy_slug}", 'info');
			return $this->get_taxonomy_terms($taxonomy_slug, $content_type);
		}
		
		// CRITICAL: Clear corrupted cache data before contextual queries
		Handy_Custom_Logger::log("CONTEXTUAL TERMS: Clearing corrupted cache data before query", 'info');
		$this->clear_contextual_cache();
		
		// Build query args to find products in the specified context
		$query_args = array(
			'post_type' => $content_type === 'products' ? 'product' : 'recipe',
			'post_status' => 'publish',
			'posts_per_page' => 1000,  // High limit for contextual filtering
			'fields' => 'ids'  // Only get IDs for performance
		);
		
		// Add taxonomy query for context filtering
		$tax_query = array('relation' => 'AND');
		
		// Handle subcategory filtering with flexible category matching
		if (!empty($context_filters['subcategory'])) {
			$subcategory_slug = $context_filters['subcategory'];
			$taxonomy_name = $content_type === 'products' ? 'product-category' : 'recipe-category';
			
			// DEBUG: Check if the term actually exists
			$term_check = get_term_by('slug', $subcategory_slug, $taxonomy_name);
			if ($term_check && !is_wp_error($term_check)) {
				Handy_Custom_Logger::log("✅ TERM FOUND: slug='{$subcategory_slug}' -> ID={$term_check->term_id}, name='{$term_check->name}', parent={$term_check->parent}", 'info');
				
				// Check if this term has child categories (making it a parent)
				$child_terms = get_terms(array(
					'taxonomy' => $taxonomy_name,
					'parent' => $term_check->term_id,
					'hide_empty' => false,
					'fields' => 'ids'
				));
				
				$has_children = !empty($child_terms) && !is_wp_error($child_terms);
				$include_children = $has_children; // Include children if this is a parent category
				
				if ($has_children) {
					Handy_Custom_Logger::log("🌳 PARENT CATEGORY: '{$subcategory_slug}' has " . count($child_terms) . " child categories - including children in query", 'info');
				} else {
					Handy_Custom_Logger::log("🍃 LEAF CATEGORY: '{$subcategory_slug}' has no children - exact match only", 'info');
				}
				
				$tax_query[] = array(
					'taxonomy' => $taxonomy_name,
					'field' => 'slug',
					'terms' => $subcategory_slug,
					'include_children' => $include_children
				);
				Handy_Custom_Logger::log("Filtering by subcategory: {$subcategory_slug} (include_children: " . ($include_children ? 'true' : 'false') . ")", 'info');
				
			} else {
				Handy_Custom_Logger::log("❌ TERM NOT FOUND: slug='{$subcategory_slug}' in taxonomy '{$taxonomy_name}'", 'error');
				Handy_Custom_Logger::log("❌ get_term_by result: " . wp_json_encode($term_check), 'error');
				
				// Still add the query even if term not found - let WordPress handle the empty result
				$tax_query[] = array(
					'taxonomy' => $taxonomy_name,
					'field' => 'slug',
					'terms' => $subcategory_slug,
					'include_children' => false
				);
			}
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
		
		// Execute primary query with fallback logic
		$posts_in_context = $this->execute_contextual_query_with_fallback($query_args, $content_type, $context_filters);
		
		if (empty($posts_in_context)) {
			Handy_Custom_Logger::log("CONTEXTUAL TERMS: ❌ No {$content_type} found in context: " . wp_json_encode($context_filters), 'info');
			Handy_Custom_Logger::log("CONTEXTUAL TERMS: ❌ Query args were: " . wp_json_encode($query_args), 'info');
			
			// DEBUG: Let's investigate what products actually exist and their category assignments
			if (!empty($context_filters['subcategory'])) {
				$debug_subcategory = $context_filters['subcategory'];
				Handy_Custom_Logger::log("🔍 DEBUGGING: Investigating products and their category assignments for subcategory '{$debug_subcategory}'", 'info');
				
				// Get ALL products to see what exists
				$all_products_query = new WP_Query(array(
					'post_type' => $content_type === 'products' ? 'product' : 'recipe',
					'post_status' => 'publish',
					'posts_per_page' => 20, // Just a sample
					'fields' => 'ids'
				));
				
				if (!empty($all_products_query->posts)) {
					Handy_Custom_Logger::log("🔍 Found " . count($all_products_query->posts) . " total {$content_type} in database. Checking first 5 for category assignments:", 'info');
					
					$sample_products = array_slice($all_products_query->posts, 0, 5);
					foreach ($sample_products as $product_id) {
						$product_title = get_the_title($product_id);
						$product_categories = wp_get_post_terms($product_id, $content_type === 'products' ? 'product-category' : 'recipe-category');
						
						if (!is_wp_error($product_categories) && !empty($product_categories)) {
							$category_info = array();
							foreach ($product_categories as $cat) {
								$category_info[] = "{$cat->name} (slug: {$cat->slug}, ID: {$cat->term_id})";
							}
							Handy_Custom_Logger::log("🔍 Product '{$product_title}' (ID: {$product_id}) assigned to: " . implode(', ', $category_info), 'info');
						} else {
							Handy_Custom_Logger::log("🔍 Product '{$product_title}' (ID: {$product_id}) has NO categories assigned!", 'info');
						}
					}
					
					// Check if any products contain "crab" in title
					$crab_query = new WP_Query(array(
						'post_type' => $content_type === 'products' ? 'product' : 'recipe',
						'post_status' => 'publish',
						'posts_per_page' => 10,
						's' => 'crab',
						'fields' => 'ids'
					));
					
					if (!empty($crab_query->posts)) {
						Handy_Custom_Logger::log("🔍 Found " . count($crab_query->posts) . " {$content_type} with 'crab' in title/content", 'info');
						foreach (array_slice($crab_query->posts, 0, 3) as $crab_product_id) {
							$crab_title = get_the_title($crab_product_id);
							$crab_categories = wp_get_post_terms($crab_product_id, $content_type === 'products' ? 'product-category' : 'recipe-category');
							
							if (!is_wp_error($crab_categories) && !empty($crab_categories)) {
								$crab_category_info = array();
								foreach ($crab_categories as $cat) {
									$crab_category_info[] = "{$cat->name} (slug: {$cat->slug})";
								}
								Handy_Custom_Logger::log("🔍 Crab product '{$crab_title}' categories: " . implode(', ', $crab_category_info), 'info');
							}
						}
					} else {
						Handy_Custom_Logger::log("🔍 No {$content_type} found with 'crab' in title/content", 'info');
					}
				} else {
					Handy_Custom_Logger::log("🔍 No {$content_type} found in database at all!", 'error');
				}
			}
			
			return array();
		}
		
		Handy_Custom_Logger::log("CONTEXTUAL TERMS: ✅ Found " . count($posts_in_context) . " {$content_type} in context: " . wp_json_encode($context_filters), 'info');
		Handy_Custom_Logger::log("CONTEXTUAL TERMS: ✅ Product IDs found: " . implode(', ', array_slice($posts_in_context, 0, 10)) . (count($posts_in_context) > 10 ? '...' : ''), 'info');
		
		// Get all terms actually used by these products/recipes
		Handy_Custom_Logger::log("CONTEXTUAL TERMS: Getting terms for taxonomy '{$taxonomy_slug}' from " . count($posts_in_context) . " products", 'info');
		
		$used_terms = wp_get_object_terms($posts_in_context, $taxonomy_slug, array(
			'orderby' => 'name',
			'order' => 'ASC'
		));
		
		if (is_wp_error($used_terms)) {
			Handy_Custom_Logger::log("CONTEXTUAL TERMS: ❌ Error getting used terms for {$taxonomy_slug}: " . $used_terms->get_error_message(), 'error');
			return array();
		}
		
		Handy_Custom_Logger::log("CONTEXTUAL TERMS: Raw terms found: " . count($used_terms) . " for taxonomy {$taxonomy_slug}", 'info');
		if (!empty($used_terms)) {
			$term_names = array_map(function($term) { return $term->name; }, array_slice($used_terms, 0, 5));
			Handy_Custom_Logger::log("CONTEXTUAL TERMS: Sample terms: " . implode(', ', $term_names) . (count($used_terms) > 5 ? '...' : ''), 'info');
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
			'hide_empty' => true,  // Only show terms used by published content
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
	 * Auto-detect current category context from URL
	 * Extracts category/subcategory when on category pages like /products/crab-cakes/
	 *
	 * @param string $content_type Content type (products/recipes)
	 * @return array Context filters array with detected category/subcategory
	 */
	private function detect_current_category_context($content_type) {
		$context_filters = array();
		
		// Get current URL path
		$current_url = $_SERVER['REQUEST_URI'] ?? '';
		$current_path = parse_url($current_url, PHP_URL_PATH);
		
		Handy_Custom_Logger::log("Auto-detecting category context from URL: {$current_path}", 'info');
		
		// Handle products URLs: /products/category-slug/ or /products/category/subcategory/
		if ($content_type === 'products' && preg_match('/^\/products\/([^\/]+)\/(?:([^\/]+)\/)?$/', $current_path, $matches)) {
			$first_segment = $matches[1];
			$second_segment = isset($matches[2]) ? $matches[2] : null;
			
			// Check if first segment is a valid product category
			$category_term = get_term_by('slug', $first_segment, 'product-category');
			
			if ($category_term && !is_wp_error($category_term)) {
				// Check if this is a parent category (has children) or subcategory
				$child_categories = get_terms(array(
					'taxonomy' => 'product-category',
					'parent' => $category_term->term_id,
					'hide_empty' => false
				));
				
				if (!empty($child_categories) && !is_wp_error($child_categories)) {
					// This is a parent category - first segment is category
					$context_filters['category'] = $first_segment;
					Handy_Custom_Logger::log("Auto-detected parent category: {$first_segment}", 'info');
					
					// Check if second segment is a valid subcategory
					if ($second_segment) {
						$subcategory_term = get_term_by('slug', $second_segment, 'product-category');
						if ($subcategory_term && !is_wp_error($subcategory_term) && $subcategory_term->parent == $category_term->term_id) {
							$context_filters['subcategory'] = $second_segment;
							Handy_Custom_Logger::log("Auto-detected subcategory: {$second_segment}", 'info');
						}
					}
				} else {
					// This is a subcategory (no children) - first segment is subcategory
					$context_filters['subcategory'] = $first_segment;
					
					// Get parent category
					if ($category_term->parent > 0) {
						$parent_term = get_term($category_term->parent, 'product-category');
						if ($parent_term && !is_wp_error($parent_term)) {
							$context_filters['category'] = $parent_term->slug;
						}
					}
					
					Handy_Custom_Logger::log("Auto-detected subcategory: {$first_segment} with parent category: " . ($context_filters['category'] ?? 'none'), 'info');
				}
			}
		}
		
		// Handle recipes URLs: /recipes/ or /recipe/recipe-slug/ (recipes don't have subcategories like products)
		elseif ($content_type === 'recipes' && preg_match('/^\/recipe\/([^\/]+)\/$/', $current_path, $matches)) {
			// On individual recipe pages, we could detect recipe categories, but this is less common
			// For now, recipes context detection is simpler since they don't have the complex category structure
			Handy_Custom_Logger::log("Recipe URL detected, but no automatic context filtering implemented yet", 'debug');
		}
		
		if (!empty($context_filters)) {
			Handy_Custom_Logger::log("Auto-detected context filters: " . wp_json_encode($context_filters), 'info');
		} else {
			Handy_Custom_Logger::log("No category context auto-detected from URL", 'debug');
		}
		
		return $context_filters;
	}

	/**
	 * Load unified filter template
	 *
	 * @param string $content_type Content type (products/recipes)
	 * @param array $filter_options Generated filter options
	 * @param array $current_filters Current filter values from URL
	 * @param array $attributes Shortcode attributes
	 * @param array $context_data Context boundaries for JavaScript
	 * @return string Rendered HTML
	 */
	private function load_template($content_type, $filter_options, $current_filters, $attributes, $context_data = array()) {
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

	/**
	 * Clear contextual query cache to prevent corrupted data issues
	 * This addresses the "Invalid post object in cached data" warnings
	 */
	private function clear_contextual_cache() {
		// Clear all query caches that may be corrupted
		Handy_Custom_Base_Utils::clear_query_cache('context');
		
		// Also clear WordPress object cache for good measure
		if (wp_cache_supports('flush_group')) {
			wp_cache_flush_group('handy_custom_queries');
		}
		
		Handy_Custom_Logger::log("CACHE: Cleared contextual query cache", 'info');
	}

	/**
	 * Execute contextual query with robust fallback logic
	 * Primary method tries WP_Query tax_query, fallback uses direct term relationships
	 *
	 * @param array $query_args WP_Query arguments
	 * @param string $content_type Content type (products/recipes)
	 * @param array $context_filters Context filters for debugging
	 * @return array Array of post IDs found in context
	 */
	private function execute_contextual_query_with_fallback($query_args, $content_type, $context_filters) {
		Handy_Custom_Logger::log("QUERY: Attempting primary WP_Query with tax_query", 'info');
		Handy_Custom_Logger::log("QUERY: Query args: " . wp_json_encode($query_args), 'info');
		
		// Try primary WP_Query method
		$wp_query = new WP_Query($query_args);
		$posts_in_context = wp_list_pluck($wp_query->posts, 'ID');
		
		Handy_Custom_Logger::log("QUERY: Primary query found " . count($posts_in_context) . " posts", 'info');
		
		// Add SQL debugging for primary query
		if (empty($posts_in_context)) {
			global $wpdb;
			Handy_Custom_Logger::log("QUERY: Primary query SQL: " . $wpdb->last_query, 'info');
			Handy_Custom_Logger::log("QUERY: WordPress found_posts: " . $wp_query->found_posts, 'info');
		}

		// If primary query fails and we have subcategory context, try fallback
		if (empty($posts_in_context) && !empty($context_filters['subcategory'])) {
			Handy_Custom_Logger::log("QUERY: Primary query failed, attempting fallback method", 'warning');
			$posts_in_context = $this->execute_fallback_contextual_query($context_filters, $content_type);
		}
		
		// Log if we hit the limit (may need to increase)
		if (count($posts_in_context) >= 1000) {
			Handy_Custom_Logger::log("QUERY: Contextual query hit limit of 1000 posts - consider increasing if taxonomy filtering seems incomplete", 'warning');
		}
		
		Handy_Custom_Logger::log("QUERY: Final result: " . count($posts_in_context) . " posts found in context", 'info');
		
		return $posts_in_context;
	}

	/**
	 * Fallback contextual query using direct term relationships
	 * Used when WP_Query tax_query fails to find expected results
	 * Fixed to properly use term_taxonomy_id for post relationships
	 *
	 * @param array $context_filters Context filters  
	 * @param string $content_type Content type (products/recipes)
	 * @return array Array of post IDs
	 */
	private function execute_fallback_contextual_query($context_filters, $content_type) {
		global $wpdb;
		
		if (empty($context_filters['subcategory'])) {
			return array();
		}
		
		$subcategory_slug = $context_filters['subcategory'];
		$taxonomy_name = $content_type === 'products' ? 'product-category' : 'recipe-category';
		$post_type = $content_type === 'products' ? 'product' : 'recipe';
		
		Handy_Custom_Logger::log("FALLBACK: Searching for posts with term slug '{$subcategory_slug}' in taxonomy '{$taxonomy_name}'", 'info');
		
		// First, validate that WordPress can find this term
		$wp_term_check = get_term_by('slug', $subcategory_slug, $taxonomy_name);
		if ($wp_term_check && !is_wp_error($wp_term_check)) {
			Handy_Custom_Logger::log("FALLBACK: WordPress confirms term exists - ID: {$wp_term_check->term_id}, name: '{$wp_term_check->name}'", 'info');
		}
		
		// CORRECTED: Get both term_id AND term_taxonomy_id (they're different!)
		$term_data = $wpdb->get_row($wpdb->prepare(
			"SELECT t.term_id, tt.term_taxonomy_id 
			 FROM {$wpdb->terms} t 
			 INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id 
			 WHERE t.slug = %s AND tt.taxonomy = %s",
			$subcategory_slug,
			$taxonomy_name
		));
		
		if (!$term_data) {
			Handy_Custom_Logger::log("FALLBACK: Term not found in database query", 'error');
			// Add SQL debugging
			$debug_sql = $wpdb->prepare(
				"SELECT t.term_id, tt.term_taxonomy_id FROM {$wpdb->terms} t INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id WHERE t.slug = %s AND tt.taxonomy = %s",
				$subcategory_slug,
				$taxonomy_name
			);
			Handy_Custom_Logger::log("FALLBACK: Debug SQL: {$debug_sql}", 'error');
			return array();
		}
		
		Handy_Custom_Logger::log("FALLBACK: Found term - term_id: {$term_data->term_id}, term_taxonomy_id: {$term_data->term_taxonomy_id}", 'info');
		
		// CORRECTED: Use term_taxonomy_id for the relationship query (this is the key fix!)
		$post_ids = $wpdb->get_col($wpdb->prepare(
			"SELECT DISTINCT p.ID 
			 FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
			 WHERE tr.term_taxonomy_id = %d 
			 AND p.post_type = %s 
			 AND p.post_status = 'publish'
			 LIMIT 1000",
			$term_data->term_taxonomy_id,  // Using term_taxonomy_id instead of term_id
			$post_type
		));
		
		$post_count = is_array($post_ids) ? count($post_ids) : 0;
		Handy_Custom_Logger::log("FALLBACK: Direct database query found {$post_count} posts using term_taxonomy_id: {$term_data->term_taxonomy_id}", 'info');
		
		if ($post_count > 0) {
			Handy_Custom_Logger::log("FALLBACK: Sample post IDs: " . implode(', ', array_slice($post_ids, 0, 5)) . ($post_count > 5 ? '...' : ''), 'info');
		} else {
			// Additional debugging if still no posts found
			$debug_sql = $wpdb->prepare(
				"SELECT DISTINCT p.ID FROM {$wpdb->posts} p INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id WHERE tr.term_taxonomy_id = %d AND p.post_type = %s AND p.post_status = 'publish'",
				$term_data->term_taxonomy_id,
				$post_type
			);
			Handy_Custom_Logger::log("FALLBACK: No posts found. Debug SQL: {$debug_sql}", 'error');
		}
		
		return is_array($post_ids) ? array_map('intval', $post_ids) : array();
	}
}