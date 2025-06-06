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
	 *
	 * @param array $filters Filter parameters
	 * @return array
	 */
	public static function get_filtered_categories($filters) {
		// If no filters applied, get all categories
		if (empty(array_filter($filters))) {
			return self::get_all_categories();
		}

		// Build tax query for filtering
		$tax_query = self::build_tax_query($filters);

		// Query products with filters
		$query = new WP_Query(array(
			'post_type' => 'product',
			'posts_per_page' => -1,
			'post_status' => 'publish',
			'tax_query' => $tax_query
		));

		// Get unique categories from filtered products
		$category_slugs = array();
		if ($query->have_posts()) {
			while ($query->have_posts()) {
				$query->the_post();
				$categories = get_the_terms(get_the_ID(), 'product-category');
				if ($categories && !is_wp_error($categories)) {
					foreach ($categories as $category) {
						$category_slugs[] = $category->slug;
					}
				}
			}
			wp_reset_postdata();
		}

		// Get category objects for the unique slugs
		if (empty($category_slugs)) {
			return array();
		}

		$categories = get_terms(array(
			'taxonomy' => 'product-category',
			'slug' => array_unique($category_slugs),
			'hide_empty' => false,
			'orderby' => 'name',
			'order' => 'ASC'
		));

		return is_wp_error($categories) ? array() : $categories;
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
	 * @param array $filters Filter parameters including subcategory
	 * @param array $args Additional WP_Query arguments
	 * @return WP_Query Query object with filtered products
	 */
	public static function get_filtered_products($filters, $args = array()) {
		$default_args = array(
			'post_type' => 'product',
			'post_status' => 'publish',
			'posts_per_page' => -1,
			'orderby' => 'title',
			'order' => 'ASC'
		);

		// Build tax query if filters exist
		if (!empty(array_filter($filters))) {
			$default_args['tax_query'] = self::build_tax_query($filters);
		}

		// Merge with any additional arguments
		$query_args = wp_parse_args($args, $default_args);
		
		Handy_Custom_Logger::log("Executing product query with subcategory support. Filters: " . wp_json_encode($filters), 'info');
		
		return new WP_Query($query_args);
	}

	/**
	 * Get all product categories
	 * For default display, only return top-level categories (parent = 0)
	 *
	 * @param bool $top_level_only Whether to return only top-level categories
	 * @return array
	 */
	private static function get_all_categories($top_level_only = true) {
		$args = array();
		
		if ($top_level_only) {
			$args['parent'] = 0;
		}
		
		return Handy_Custom_Products_Utils::get_taxonomy_terms('product-category', $args);
	}
}