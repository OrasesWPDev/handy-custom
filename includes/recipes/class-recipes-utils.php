<?php
/**
 * Recipes utility functions
 *
 * @package Handy_Custom
 */

if (!defined('ABSPATH')) {
	exit;
}

class Handy_Custom_Recipes_Utils extends Handy_Custom_Base_Utils {

	/**
	 * Taxonomy mapping for recipe filter keys
	 * Maps shortcode parameters to actual WordPress taxonomy names
	 *
	 * @return array
	 */
	public static function get_taxonomy_mapping() {
		return array(
			'category' => 'recipe-category',
			'cooking_method' => 'recipe-cooking-method',
			'menu_occasion' => 'recipe-menu-occasion'
		);
	}

	/**
	 * Get recipe icon path based on category slug
	 * Uses same naming convention as products: {category-slug}-icon.png
	 *
	 * @param string $category_slug Recipe category slug
	 * @return string Icon URL or empty string if not found
	 */
	public static function get_category_icon($category_slug) {
		if (empty($category_slug)) {
			return '';
		}

		$icon_filename = sanitize_file_name($category_slug . '-icon.png');
		$icon_path = plugin_dir_path(dirname(__FILE__)) . 'assets/images/' . $icon_filename;
		$icon_url = plugin_dir_url(dirname(__FILE__)) . 'assets/images/' . $icon_filename;

		// Check if icon file exists
		if (file_exists($icon_path)) {
			return $icon_url;
		}

		Handy_Custom_Logger::log("Recipe category icon not found: {$icon_filename}", 'warning');
		return '';
	}

	/**
	 * Get formatted prep time display
	 *
	 * @param string $prep_time Raw prep time from ACF field
	 * @return string Formatted prep time or default message
	 */
	public static function format_prep_time($prep_time) {
		if (empty($prep_time)) {
			return 'Prep time TBD';
		}

		// Clean up the prep time string
		$formatted = sanitize_text_field($prep_time);
		
		// Add 'min' suffix if it's just a number
		if (is_numeric($formatted)) {
			$formatted .= 'min';
		}

		return $formatted;
	}

	/**
	 * Get formatted servings display
	 *
	 * @param string $servings Raw servings from ACF field
	 * @return string Formatted servings or default message
	 */
	public static function format_servings($servings) {
		if (empty($servings)) {
			return 'Servings TBD';
		}

		$formatted = sanitize_text_field($servings);
		
		// Add proper grammar for servings
		if (is_numeric($formatted)) {
			$formatted .= ($formatted == '1') ? ' serving' : ' servings';
		}

		return $formatted;
	}

	/**
	 * Get recipe permalink for clickable cards
	 *
	 * @param int $recipe_id Recipe post ID
	 * @return string Recipe URL
	 */
	public static function get_recipe_url($recipe_id) {
		return get_permalink($recipe_id);
	}

	/**
	 * Truncate recipe description to match product character limit
	 *
	 * @param string $content Recipe content or excerpt
	 * @param int $length Character limit (default matches products)
	 * @return string Truncated content
	 */
	public static function truncate_description($content, $length = 150) {
		if (empty($content)) {
			return '';
		}

		// Strip HTML tags and get plain text
		$content = wp_strip_all_tags($content);
		
		if (strlen($content) <= $length) {
			return $content;
		}

		// Truncate and add ellipsis
		$truncated = substr($content, 0, $length);
		$last_space = strrpos($truncated, ' ');
		
		if ($last_space !== false) {
			$truncated = substr($truncated, 0, $last_space);
		}

		return $truncated . '...';
	}
}