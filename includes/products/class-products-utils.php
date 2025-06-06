<?php
/**
 * Products utility functions
 *
 * @package Handy_Custom
 */

if (!defined('ABSPATH')) {
	exit;
}

class Handy_Custom_Products_Utils {

	/**
	 * Taxonomy mapping for filter keys
	 *
	 * @return array
	 */
	public static function get_taxonomy_mapping() {
		return array(
			'category' => 'product-category',
			'subcategory' => 'product-category',
			'grade' => 'grade',
			'market_segment' => 'market-segment',
			'cooking_method' => 'product-cooking-method',
			'menu_occasion' => 'product-menu-occasion',
			'product_type' => 'product-type',
			'size' => 'size'
		);
	}

	/**
	 * Convert filter key to taxonomy name
	 *
	 * @param string $key Filter key
	 * @return string|false
	 */
	public static function get_taxonomy_name($key) {
		$mapping = self::get_taxonomy_mapping();
		return isset($mapping[$key]) ? $mapping[$key] : false;
	}

	/**
	 * Get terms for a specific taxonomy with error handling
	 *
	 * @param string $taxonomy Taxonomy name
	 * @param array $args Additional arguments
	 * @return array
	 */
	public static function get_taxonomy_terms($taxonomy, $args = array()) {
		$default_args = array(
			'taxonomy' => $taxonomy,
			'hide_empty' => false,
			'orderby' => 'name',
			'order' => 'ASC'
		);

		$args = wp_parse_args($args, $default_args);
		$terms = get_terms($args);

		if (is_wp_error($terms)) {
			Handy_Custom_Logger::log("Error getting terms for taxonomy {$taxonomy}: " . $terms->get_error_message(), 'error');
			return array();
		}

		return $terms;
	}

	/**
	 * Validate and sanitize filter parameters
	 *
	 * @param array $filters Raw filter parameters
	 * @return array Sanitized filters
	 */
	public static function sanitize_filters($filters) {
		$allowed_keys = array_keys(self::get_taxonomy_mapping());
		$sanitized = array();

		foreach ($allowed_keys as $key) {
			$sanitized[$key] = isset($filters[$key]) ? sanitize_text_field($filters[$key]) : '';
		}

		return $sanitized;
	}

	/**
	 * Get parent category slug from subcategory slug
	 *
	 * @param string $subcategory_slug Subcategory slug
	 * @return string|false Parent category slug or false if not found
	 */
	public static function get_parent_category_from_subcategory($subcategory_slug) {
		if (empty($subcategory_slug)) {
			return false;
		}

		$term = get_term_by('slug', $subcategory_slug, 'product-category');
		
		if (!$term || is_wp_error($term)) {
			Handy_Custom_Logger::log("Subcategory not found: {$subcategory_slug}", 'warning');
			return false;
		}

		// If term has no parent, it's already a top-level category
		if (empty($term->parent)) {
			return $subcategory_slug;
		}

		// Get parent term
		$parent_term = get_term($term->parent, 'product-category');
		
		if (!$parent_term || is_wp_error($parent_term)) {
			Handy_Custom_Logger::log("Parent category not found for subcategory: {$subcategory_slug}", 'warning');
			return false;
		}

		return $parent_term->slug;
	}

	/**
	 * Check if a term is a subcategory (has a parent)
	 *
	 * @param string $term_slug Term slug to check
	 * @return bool True if subcategory, false if top-level category
	 */
	public static function is_subcategory($term_slug) {
		if (empty($term_slug)) {
			return false;
		}

		$term = get_term_by('slug', $term_slug, 'product-category');
		
		if (!$term || is_wp_error($term)) {
			return false;
		}

		return !empty($term->parent);
	}

	/**
	 * Get all subcategories for a parent category
	 *
	 * @param string $parent_slug Parent category slug
	 * @return array Array of subcategory term objects
	 */
	public static function get_subcategories($parent_slug) {
		if (empty($parent_slug)) {
			return array();
		}

		$parent_term = get_term_by('slug', $parent_slug, 'product-category');
		
		if (!$parent_term || is_wp_error($parent_term)) {
			return array();
		}

		$subcategories = get_terms(array(
			'taxonomy' => 'product-category',
			'parent' => $parent_term->term_id,
			'hide_empty' => false,
			'orderby' => 'name',
			'order' => 'ASC'
		));

		if (is_wp_error($subcategories)) {
			Handy_Custom_Logger::log("Error getting subcategories for {$parent_slug}: " . $subcategories->get_error_message(), 'error');
			return array();
		}

		return $subcategories;
	}
}