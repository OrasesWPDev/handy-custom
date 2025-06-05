<?php
/**
 * Products display functionality
 *
 * @package Handy_Custom
 */

if (!defined('ABSPATH')) {
	exit;
}

class Handy_Custom_Products_Display {

	/**
	 * Get category featured image URL
	 *
	 * @param int $category_id Category term ID
	 * @return string|false
	 */
	public static function get_category_featured_image($category_id) {
		if (!function_exists('get_field')) {
			Handy_Custom_Logger::log('ACF get_field function not available', 'warning');
			return false;
		}

		$image = get_field('category_featured_image', 'product-category_' . $category_id);
		
		if ($image && is_array($image) && isset($image['url'])) {
			return $image['url'];
		}

		Handy_Custom_Logger::log("No featured image found for category ID: {$category_id}", 'info');
		return false;
	}

	/**
	 * Get category icon URL
	 *
	 * @param string $category_slug Category slug
	 * @return string
	 */
	public static function get_category_icon($category_slug) {
		// TODO: Replace with actual icon files when available
		// Expected naming pattern: {category-slug}-icon.png
		$icon_filename = $category_slug . '-icon.png';
		$icon_path = HANDY_CUSTOM_PLUGIN_DIR . 'assets/images/' . $icon_filename;
		$icon_url = HANDY_CUSTOM_PLUGIN_URL . 'assets/images/' . $icon_filename;
		
		// Check if icon file exists
		if (file_exists($icon_path)) {
			return $icon_url;
		}
		
		// Log missing icon for reference
		Handy_Custom_Logger::log("Icon not found: {$icon_filename}", 'info');
		
		// Return placeholder or empty for now
		return '';
	}

	/**
	 * Truncate category description
	 *
	 * @param string $description Category description
	 * @param int $length Character limit (default 270)
	 * @return array Array with 'truncated' text and 'is_truncated' boolean
	 */
	public static function truncate_description($description, $length = 270) {
		if (empty($description)) {
			return array(
				'truncated' => '',
				'is_truncated' => false,
				'full' => ''
			);
		}

		$full_description = strip_tags($description);
		
		if (strlen($full_description) <= $length) {
			return array(
				'truncated' => $full_description,
				'is_truncated' => false,
				'full' => $full_description
			);
		}

		$truncated = substr($full_description, 0, $length);
		
		// Try to cut at last complete word
		$last_space = strrpos($truncated, ' ');
		if ($last_space !== false) {
			$truncated = substr($truncated, 0, $last_space);
		}

		return array(
			'truncated' => $truncated,
			'is_truncated' => true,
			'full' => $full_description
		);
	}

	/**
	 * Get category page URL
	 *
	 * @param object $category Category term object
	 * @return string
	 */
	public static function get_category_page_url($category) {
		// TODO: This should link to the category-specific page built with UX Builder
		// For now, return the default category archive URL
		// This will need to be updated to point to the custom pages once they're created
		
		$category_url = get_term_link($category);
		
		if (is_wp_error($category_url)) {
			Handy_Custom_Logger::log("Error getting category URL for: {$category->slug}", 'error');
			return '#';
		}
		
		return $category_url;
	}

	/**
	 * Generate shop now URL (placeholder)
	 *
	 * @param object $category Category term object
	 * @return string
	 */
	public static function get_shop_now_url($category) {
		// TODO: Implement shop now functionality later
		// Return dead link as requested
		return '#';
	}
}