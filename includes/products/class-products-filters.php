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
	 *
	 * @return array
	 */
	public static function get_filter_options() {
		$mapping = Handy_Custom_Products_Utils::get_taxonomy_mapping();
		$options = array();

		foreach ($mapping as $key => $taxonomy) {
			$label = str_replace('_', ' ', $key);
			$options[$key . 's'] = Handy_Custom_Products_Utils::get_taxonomy_terms($taxonomy);
		}

		return $options;
	}

	/**
	 * Build tax query from filter parameters
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
					$tax_query[] = array(
						'taxonomy' => $taxonomy,
						'field'    => 'slug',
						'terms'    => $value,
					);
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
	 * Get all product categories
	 *
	 * @return array
	 */
	private static function get_all_categories() {
		return Handy_Custom_Products_Utils::get_taxonomy_terms('product-category');
	}
}