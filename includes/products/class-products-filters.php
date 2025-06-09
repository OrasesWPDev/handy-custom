<?php
/**
 * Products filtering functionality
 *
 * @package Handy_Custom
 */

if (!defined('ABSPATH')) {
	exit;
}

class Handy_Custom_Products_Filters {

	/**
	 * Get all filter options for product dropdowns
	 * Handles both category and subcategory contexts
	 *
	 * @param array $context_filters Applied filters for context (e.g., subcategory filter)
	 * @param bool $include_category_filter Whether to include category filter (for list display mode)
	 * @return array
	 */
	public static function get_filter_options($context_filters = array(), $include_category_filter = false) {
		$mapping = Handy_Custom_Products_Utils::get_taxonomy_mapping();
		$options = array();

		foreach ($mapping as $key => $taxonomy) {
			// Skip subcategory in dropdown options - it's handled separately
			if ($key === 'subcategory') {
				continue;
			}

			// Skip category filter unless explicitly requested (for list display mode)
			if ($key === 'category' && !$include_category_filter) {
				continue;
			}

			$label = str_replace('_', ' ', $key);
			
			// For categories, respect subcategory context
			if ($key === 'category') {
				$options[$key . 's'] = self::get_contextual_categories($context_filters);
			} else {
				$options[$key . 's'] = Handy_Custom_Products_Utils::get_taxonomy_terms($taxonomy);
			}
		}

		return $options;
	}

	/**
	 * Build tax query from filter parameters
	 * Handles hierarchical filtering for subcategories
	 *
	 * @param array $filters Filter parameters
	 * @return array
	 */
	public static function build_tax_query($filters) {
		$tax_query = array('relation' => 'AND');

		foreach ($filters as $key => $value) {
			if (!empty($value)) {
				$taxonomy = Handy_Custom_Products_Utils::get_taxonomy_name($key);
				if ($taxonomy) {
					// Handle subcategory filtering
					if ($key === 'subcategory') {
						$tax_query[] = array(
							'taxonomy' => $taxonomy,
							'field'    => 'slug',
							'terms'    => $value,
							'include_children' => false // Exact match for subcategory
						);
						
						// Auto-detect and add parent category if not explicitly set
						if (empty($filters['category'])) {
							$parent_slug = Handy_Custom_Products_Utils::get_parent_category_from_subcategory($value);
							if ($parent_slug && $parent_slug !== $value) {
								Handy_Custom_Logger::log("Auto-detected parent category: {$parent_slug} for subcategory: {$value}", 'info');
							}
						}
					} else {
						$tax_query[] = array(
							'taxonomy' => $taxonomy,
							'field'    => 'slug',
							'terms'    => $value,
						);
					}
				}
			}
		}

		return $tax_query;
	}

	/**
	 * Get filtered product categories based on applied filters
	 * For frontend category display, returns top-level categories or subcategories based on context
	 * 
	 * User requirement: "subcategories will not display cards but will display a list of products 
	 * inside of that subcategory" and "any category flag is used that has no subcategory... will be 
	 * showing list of products in that {category}"
	 *
	 * @param array $filters Filter parameters
	 * @return array
	 */
	public static function get_filtered_categories($filters) {
		// If subcategory is specified, always show product list (never show cards)
		if (!empty($filters['subcategory'])) {
			Handy_Custom_Logger::log("Subcategory specified: {$filters['subcategory']}. Forcing list mode to show products from this subcategory.", 'info');
			return array(); // Force list mode for subcategories
		}
		
		// Check if a specific category is requested
		if (!empty($filters['category'])) {
			$category_slug = $filters['category'];
			
			// Get subcategories for this parent category using enhanced method
			$subcategories = self::get_all_categories(false, $category_slug);
			
			if (!empty($subcategories)) {
				// Return subcategories for card display
				Handy_Custom_Logger::log("Category {$category_slug} has subcategories. Showing subcategory cards.", 'info');
				return $subcategories;
			} else {
				// No subcategories found - return empty array to trigger list mode fallback
				Handy_Custom_Logger::log("Category {$category_slug} has no subcategories. Will fallback to product list mode.", 'info');
				return array();
			}
		}
		
		// Default: show the top-level categories
		return self::get_all_categories();
	}

	/**
	 * Get contextual categories based on applied filters
	 * If subcategory is specified, return relevant categories
	 *
	 * @param array $context_filters Applied context filters
	 * @return array Array of category terms
	 */
	private static function get_contextual_categories($context_filters = array()) {
		// If subcategory is specified, get its parent and siblings
		if (!empty($context_filters['subcategory'])) {
			$subcategory_slug = $context_filters['subcategory'];
			$parent_slug = Handy_Custom_Products_Utils::get_parent_category_from_subcategory($subcategory_slug);
			
			if ($parent_slug) {
				// Return parent category and its subcategories
				$parent_term = get_term_by('slug', $parent_slug, 'product-category');
				if ($parent_term && !is_wp_error($parent_term)) {
					$subcategories = Handy_Custom_Products_Utils::get_subcategories($parent_slug);
					$categories = array($parent_term);
					
					if (!empty($subcategories)) {
						$categories = array_merge($categories, $subcategories);
					}
					
					return $categories;
				}
			}
		}
		
		// Default: return all categories
		return self::get_all_categories();
	}

	/**
	 * Get filtered products with subcategory support
	 *
	 * @param array $filters Filter parameters including subcategory and pagination
	 * @param array $args Additional WP_Query arguments
	 * @return WP_Query Query object with filtered products
	 */
	public static function get_filtered_products($filters, $args = array()) {
		// Handle pagination parameters
		$posts_per_page = -1; // Default: show all
		$paged = 1;
		
		// Set pagination based on display mode and parameters
		if (!empty($filters['per_page']) && absint($filters['per_page']) > 0) {
			$posts_per_page = min(100, absint($filters['per_page'])); // Cap at 100 to prevent abuse
			$paged = !empty($filters['page']) ? max(1, absint($filters['page'])) : 1;
		} elseif (!empty($filters['display']) && $filters['display'] === 'list') {
			// Default pagination for list mode to prevent performance issues
			$posts_per_page = 12; // Sensible default for product listing
			$paged = !empty($filters['page']) ? max(1, absint($filters['page'])) : 1;
		}
		
		$default_args = array(
			'post_type' => 'product',
			'post_status' => 'publish',
			'posts_per_page' => $posts_per_page,
			'paged' => $paged,
			'orderby' => 'title',
			'order' => 'ASC'
		);

		// Build tax query if filters exist
		$filter_params = $filters;
		// Remove pagination params from tax query building
		unset($filter_params['per_page'], $filter_params['page'], $filter_params['display']);
		
		if (!empty(array_filter($filter_params))) {
			$default_args['tax_query'] = self::build_tax_query($filter_params);
		}

		// Merge with any additional arguments
		$query_args = wp_parse_args($args, $default_args);
		
		// Generate cache key for this query
		$cache_key = Handy_Custom_Base_Utils::generate_query_cache_key($query_args, 'products');
		
		// Try to get cached results first
		$cached_query = Handy_Custom_Base_Utils::get_cached_query($cache_key);
		if (false !== $cached_query) {
			return $cached_query;
		}
		
		Handy_Custom_Logger::log("Executing product query with pagination. Posts per page: {$posts_per_page}, Page: {$paged}. Filters: " . wp_json_encode($filters), 'info');
		
		// Execute query and cache results
		$query = new WP_Query($query_args);
		Handy_Custom_Base_Utils::cache_query_results($cache_key, $query);
		
		return $query;
	}

	/**
	 * Get all product categories ordered by display_order meta field
	 * For default display, only return top-level categories (parent = 0) 
	 *
	 * @param bool $top_level_only Whether to return only top-level categories
	 * @param string $parent_category_slug Optional parent category slug to get children of specific category
	 * @return array
	 */
	private static function get_all_categories($top_level_only = true, $parent_category_slug = null) {
		// First, get ALL categories without meta_key restriction
		$args = array(
			'hide_empty' => false,
			'orderby' => 'name',
			'order' => 'ASC'
		);
		
		if ($parent_category_slug) {
			// Get children of specific parent category
			$parent_term = get_term_by('slug', $parent_category_slug, 'product-category');
			if ($parent_term && !is_wp_error($parent_term)) {
				$args['parent'] = $parent_term->term_id;
			} else {
				Handy_Custom_Logger::log("Parent category not found: {$parent_category_slug}", 'warning');
				return array();
			}
		} elseif ($top_level_only) {
			$args['parent'] = 0;
		}
		
		$categories = Handy_Custom_Products_Utils::get_taxonomy_terms('product-category', $args);
		
		// Sort categories by display_order if we have any categories
		if (!empty($categories) && ($top_level_only || $parent_category_slug)) {
			$ordered_categories = array();
			$unordered_categories = array();
			
			foreach ($categories as $category) {
				$display_order = get_term_meta($category->term_id, 'display_order', true);
				
				if (!empty($display_order) && is_numeric($display_order)) {
					// Store with display order for sorting
					$category->display_order = (int) $display_order;
					$ordered_categories[] = $category;
				} else {
					$unordered_categories[] = $category;
				}
			}
			
			// Sort ordered categories by display_order value
			if (!empty($ordered_categories)) {
				usort($ordered_categories, function($a, $b) {
					return $a->display_order - $b->display_order;
				});
			}
			
			// Sort unordered categories alphabetically
			if (!empty($unordered_categories)) {
				usort($unordered_categories, function($a, $b) {
					return strcmp($a->name, $b->name);
				});
			}
			
			// Clean up temporary property
			foreach ($ordered_categories as $category) {
				unset($category->display_order);
			}
			
			return array_merge($ordered_categories, $unordered_categories);
		}
		
		return $categories;
	}
}