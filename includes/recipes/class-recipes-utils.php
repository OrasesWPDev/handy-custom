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

		// Check for multiple file extensions in order of preference
		$extensions = ['webp', 'png', 'jpg', 'jpeg'];
		
		foreach ($extensions as $ext) {
			$icon_filename = sanitize_file_name($category_slug . '-icon.' . $ext);
			$icon_path = plugin_dir_path(dirname(__FILE__)) . 'assets/images/' . $icon_filename;
			$icon_url = plugin_dir_url(dirname(__FILE__)) . 'assets/images/' . $icon_filename;

			// Check if icon file exists
			if (file_exists($icon_path)) {
				return $icon_url;
			}
		}

		Handy_Custom_Logger::log("Recipe category icon not found for category: {$category_slug}", 'warning');
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
	 * Truncate recipe description to match design requirements
	 *
	 * @param string $content Recipe content or excerpt
	 * @param int $length Character limit (120 as per design spec)
	 * @return string Truncated content
	 */
	public static function truncate_description($content, $length = 120) {
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

	/**
	 * Get current URL parameters for recipe filtering
	 * Based on products utils but adapted for recipe taxonomies
	 *
	 * @return array Current URL parameters
	 */
	public static function get_current_url_parameters() {
		// Only apply URL parameters if page has recipe shortcodes
		// This prevents forcing shortcode behavior on pages meant for UX Builder editing
		if (!self::page_has_recipe_shortcodes()) {
			return array();
		}
		
		// Check for URL-based parameters first
		$url_params = Handy_Custom::get_url_parameters();
		
		if (!empty($url_params)) {
			return $url_params;
		}

		// Fallback to query parameters
		$params = array();
		
		if (!empty($_GET['category'])) {
			$params['category'] = sanitize_text_field($_GET['category']);
		}
		
		if (!empty($_GET['cooking_method'])) {
			$params['cooking_method'] = sanitize_text_field($_GET['cooking_method']);
		}
		
		if (!empty($_GET['menu_occasion'])) {
			$params['menu_occasion'] = sanitize_text_field($_GET['menu_occasion']);
		}

		return $params;
	}

	/**
	 * Check if current page contains recipe-related shortcodes
	 *
	 * @return bool True if page has recipe shortcodes
	 */
	public static function page_has_recipe_shortcodes() {
		global $post;
		
		if (!$post || empty($post->post_content)) {
			return false;
		}
		
		// Check for any recipe-related shortcodes
		$recipe_shortcodes = array('recipes', 'filter-recipes');
		
		foreach ($recipe_shortcodes as $shortcode) {
			if (has_shortcode($post->post_content, $shortcode)) {
				return true;
			}
		}
		
		return false;
	}

	/**
	 * Check if current page is a recipe category page
	 *
	 * @return bool True if on recipe category page
	 */
	public static function is_recipe_category_page() {
		$params = self::get_current_url_parameters();
		return !empty($params['category']) || !empty($params['cooking_method']) || !empty($params['menu_occasion']);
	}
}