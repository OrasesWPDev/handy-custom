<?php
/**
 * Recipes filtering functionality
 * Handles AJAX filtering for recipe archive with 3 taxonomy dropdowns
 *
 * @package Handy_Custom
 */

if (!defined('ABSPATH')) {
	exit;
}

class Handy_Custom_Recipes_Filters {

	/**
	 * Get all filter options for recipe dropdowns
	 * Returns options for the 3 recipe taxonomy filters
	 *
	 * @return array Associative array with filter options
	 */
	public static function get_filter_options() {
		$mapping = Handy_Custom_Recipes_Utils::get_taxonomy_mapping();
		$options = array();

		foreach ($mapping as $key => $taxonomy) {
			// Convert key to plural for consistency (e.g., 'category' => 'categories')
			$plural_key = $key . 's';
			$options[$plural_key] = Handy_Custom_Recipes_Utils::get_taxonomy_terms($taxonomy);
		}

		Handy_Custom_Logger::log("Retrieved recipe filter options: " . count($options) . " taxonomies", 'info');
		return $options;
	}

	/**
	 * Build taxonomy query from filter parameters
	 * Creates WP_Query tax_query array based on applied filters
	 *
	 * @param array $filters Filter parameters from shortcode or AJAX
	 * @return array Tax query array for WP_Query
	 */
	public static function build_tax_query($filters) {
		$tax_query = array('relation' => 'AND');

		foreach ($filters as $key => $value) {
			if (!empty($value)) {
				$taxonomy = Handy_Custom_Recipes_Utils::get_taxonomy_name($key);
				if ($taxonomy) {
					$tax_query[] = array(
						'taxonomy' => $taxonomy,
						'field'    => 'slug',
						'terms'    => $value,
					);
					Handy_Custom_Logger::log("Added recipe filter: {$taxonomy} = {$value}", 'info');
				}
			}
		}

		return $tax_query;
	}

	/**
	 * Get filtered recipe categories based on applied filters
	 * Returns categories that have recipes matching the current filters
	 *
	 * @param array $filters Filter parameters
	 * @return array Array of category term objects
	 */
	public static function get_filtered_categories($filters) {
		// If no filters applied, get all categories
		if (empty(array_filter($filters))) {
			return self::get_all_categories();
		}

		// Build tax query for filtering
		$tax_query = self::build_tax_query($filters);

		// Query recipes with filters to find available categories
		$query = new WP_Query(array(
			'post_type' => 'recipe',
			'posts_per_page' => -1,
			'post_status' => 'publish',
			'tax_query' => $tax_query
		));

		// Get unique categories from filtered recipes
		$category_slugs = array();
		if ($query->have_posts()) {
			while ($query->have_posts()) {
				$query->the_post();
				$categories = get_the_terms(get_the_ID(), 'recipe-category');
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
			Handy_Custom_Logger::log("No recipe categories found for applied filters", 'warning');
			return array();
		}

		$categories = get_terms(array(
			'taxonomy' => 'recipe-category',
			'slug' => array_unique($category_slugs),
			'hide_empty' => false,
			'orderby' => 'name',
			'order' => 'ASC'
		));

		if (is_wp_error($categories)) {
			Handy_Custom_Logger::log("Error getting filtered recipe categories: " . $categories->get_error_message(), 'error');
			return array();
		}

		Handy_Custom_Logger::log("Found " . count($categories) . " recipe categories for applied filters", 'info');
		return $categories;
	}

	/**
	 * Get all recipe categories
	 * Used when no filters are applied
	 *
	 * @return array Array of all recipe category term objects
	 */
	private static function get_all_categories() {
		$categories = Handy_Custom_Recipes_Utils::get_taxonomy_terms('recipe-category');
		Handy_Custom_Logger::log("Retrieved all recipe categories: " . count($categories) . " found", 'info');
		return $categories;
	}

	/**
	 * Get recipes matching the applied filters
	 * Main query function for filtered recipe results
	 *
	 * @param array $filters Filter parameters
	 * @param array $args Additional WP_Query arguments
	 * @return WP_Query Query object with filtered recipes
	 */
	public static function get_filtered_recipes($filters, $args = array()) {
		$default_args = array(
			'post_type' => 'recipe',
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
		
		Handy_Custom_Logger::log("Executing recipe query with " . count($filters) . " filters", 'info');
		
		return new WP_Query($query_args);
	}

	/**
	 * Validate filter parameters against available terms
	 * Ensures filter values exist in their respective taxonomies
	 *
	 * @param array $filters Raw filter parameters
	 * @return array Validated filters with invalid values removed
	 */
	public static function validate_filters($filters) {
		$validated = array();
		$mapping = Handy_Custom_Recipes_Utils::get_taxonomy_mapping();

		foreach ($filters as $key => $value) {
			if (empty($value) || !isset($mapping[$key])) {
				continue;
			}

			$taxonomy = $mapping[$key];
			$term = get_term_by('slug', $value, $taxonomy);

			if ($term && !is_wp_error($term)) {
				$validated[$key] = $value;
			} else {
				Handy_Custom_Logger::log("Invalid recipe filter value: {$key} = {$value}", 'warning');
			}
		}

		return $validated;
	}
}