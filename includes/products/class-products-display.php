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
	 * Get product thumbnail URL for list display
	 * Returns smaller thumbnail than category images
	 *
	 * @param int $post_id Product post ID
	 * @return string|false
	 */
	public static function get_product_thumbnail($post_id) {
		$thumbnail_id = get_post_thumbnail_id($post_id);
		
		if ($thumbnail_id) {
			// Use 'medium' size for smaller product thumbnails (vs 'large' for categories)
			$thumbnail_url = wp_get_attachment_image_url($thumbnail_id, 'medium');
			if ($thumbnail_url) {
				return $thumbnail_url;
			}
		}
		
		Handy_Custom_Logger::log("No thumbnail found for product ID: {$post_id}", 'info');
		return false;
	}

	/**
	 * Get truncated product excerpt for list display
	 * Caps at 150 characters with ellipsis
	 *
	 * @param int $post_id Product post ID
	 * @return string
	 */
	public static function get_product_excerpt($post_id) {
		$excerpt = get_the_excerpt($post_id);
		
		if (empty($excerpt)) {
			// Fallback to content if no excerpt
			$content = get_post_field('post_content', $post_id);
			$excerpt = wp_strip_all_tags($content);
		}
		
		// Truncate to 150 characters
		if (strlen($excerpt) > 150) {
			$excerpt = substr($excerpt, 0, 147) . '...';
		}
		
		return $excerpt;
	}

	/**
	 * Get product single post URL
	 *
	 * @param int $post_id Product post ID
	 * @return string
	 */
	public static function get_product_single_url($post_id) {
		return get_permalink($post_id);
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
		// Use custom products URL structure instead of WordPress taxonomy archive
		// This ensures Shop Now buttons use /products/{category}/ format
		
		if (empty($category->slug)) {
			Handy_Custom_Logger::log("Invalid category object - missing slug", 'error');
			return '#';
		}
		
		$category_url = Handy_Custom_Products_Utils::get_category_url($category->slug);
		
		Handy_Custom_Logger::log("Generated category URL for {$category->slug}: {$category_url}", 'debug');
		
		return $category_url;
	}

	/**
	 * Get Shop Now URL from ACF field for category
	 *
	 * @param object $category Category term object
	 * @return string Shop Now URL or fallback URL
	 */
	public static function get_shop_now_url($category) {
		if (empty($category) || !isset($category->term_id)) {
			Handy_Custom_Logger::log('Invalid category object passed to get_shop_now_url', 'warning');
			return '#';
		}

		// Use the utility function to get validated URL
		$shop_url = Handy_Custom_Products_Utils::get_shop_now_url($category);
		
		if ($shop_url) {
			Handy_Custom_Logger::log("Shop Now URL found for category {$category->slug}: {$shop_url}", 'debug');
			return $shop_url;
		}

		// Fallback: if no shop URL is set, return the category page URL as fallback
		$fallback_url = self::get_category_page_url($category);
		Handy_Custom_Logger::log("No Shop Now URL set for category {$category->slug}, using fallback: {$fallback_url}", 'info');
		
		return $fallback_url;
	}
}