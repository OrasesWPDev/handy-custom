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
}