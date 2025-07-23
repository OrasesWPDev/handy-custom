<?php
/**
 * Products utility functions
 *
 * @package Handy_Custom
 */

if (!defined('ABSPATH')) {
	exit;
}

class Handy_Custom_Products_Utils extends Handy_Custom_Base_Utils {

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
	 * Get parent category slug from subcategory slug
	 *
	 * @param string $subcategory_slug Subcategory slug
	 * @return string|false Parent category slug or false if not found
	 */
	public static function get_parent_category_from_subcategory($subcategory_slug) {
		if (empty($subcategory_slug)) {
			return false;
		}

		$term = self::get_term_by_slug($subcategory_slug, 'product-category');
		
		if (!$term) {
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

		$term = self::get_term_by_slug($term_slug, 'product-category');
		
		if (!$term) {
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

		$parent_term = self::get_term_by_slug($parent_slug, 'product-category');
		
		if (!$parent_term) {
			return array();
		}

		return self::get_taxonomy_terms('product-category', array(
			'parent' => $parent_term->term_id,
			'hide_empty' => false,
			'orderby' => 'name',
			'order' => 'ASC'
		));
	}

	/**
	 * Generate product category URL
	 *
	 * @param string $category_slug Category slug
	 * @return string Category URL
	 */
	public static function get_category_url($category_slug) {
		if (empty($category_slug)) {
			return home_url('/products/');
		}

		return home_url("/products/{$category_slug}/");
	}

	/**
	 * Generate product subcategory URL
	 *
	 * @param string $subcategory_slug Subcategory slug
	 * @param string $parent_slug Optional parent category slug (auto-detected if not provided)
	 * @return string Subcategory URL
	 */
	public static function get_subcategory_url($subcategory_slug, $parent_slug = '') {
		if (empty($subcategory_slug)) {
			Handy_Custom_Logger::log("get_subcategory_url: Empty subcategory slug provided", 'warning');
			return home_url('/products/');
		}

		Handy_Custom_Logger::log("get_subcategory_url: Called with subcategory_slug='{$subcategory_slug}', parent_slug='{$parent_slug}'", 'debug');

		// Auto-detect parent if not provided
		if (empty($parent_slug)) {
			$parent_slug = self::get_parent_category_from_subcategory($subcategory_slug);
			Handy_Custom_Logger::log("get_subcategory_url: Auto-detected parent slug: '{$parent_slug}'", 'debug');
		}

		// Check if this is actually a child category by verifying the term has a parent
		$term = self::get_term_by_slug($subcategory_slug, 'product-category');
		if (!$term) {
			Handy_Custom_Logger::log("get_subcategory_url: Term not found for slug '{$subcategory_slug}'", 'warning');
			return home_url("/products/{$subcategory_slug}/");
		}

		Handy_Custom_Logger::log("get_subcategory_url: Term found - name: '{$term->name}', parent: {$term->parent}", 'debug');

		if (empty($term->parent)) {
			// This is a top-level category, use flat URL
			$flat_url = home_url("/products/{$subcategory_slug}/");
			Handy_Custom_Logger::log("get_subcategory_url: Top-level category detected, returning flat URL: {$flat_url}", 'debug');
			return $flat_url;
		}

		// This is a child category, use hierarchical URL
		if (!empty($parent_slug) && $parent_slug !== $subcategory_slug) {
			$hierarchical_url = home_url("/products/{$parent_slug}/{$subcategory_slug}/");
			Handy_Custom_Logger::log("get_subcategory_url: Child category detected, returning hierarchical URL: {$hierarchical_url}", 'debug');
			return $hierarchical_url;
		}

		// Fallback to category-only URL if parent detection failed
		$fallback_url = home_url("/products/{$subcategory_slug}/");
		Handy_Custom_Logger::log("get_subcategory_url: Parent detection failed (parent_slug='{$parent_slug}'), falling back to flat URL: {$fallback_url}", 'warning');
		return $fallback_url;
	}

	/**
	 * Generate product URL based on category and subcategory
	 *
	 * @param string $category_slug Category slug (optional)
	 * @param string $subcategory_slug Subcategory slug (optional)
	 * @return string Product URL
	 */
	public static function get_product_url($category_slug = '', $subcategory_slug = '') {
		// If both are provided, use subcategory URL
		if (!empty($subcategory_slug)) {
			return self::get_subcategory_url($subcategory_slug, $category_slug);
		}

		// If only category provided, use category URL
		if (!empty($category_slug)) {
			return self::get_category_url($category_slug);
		}

		// Default to main products page
		return home_url('/products/');
	}

	/**
	 * Get current URL parameters from the request
	 * Integrates with URL rewrite system
	 *
	 * @return array Current URL parameters
	 */
	public static function get_current_url_parameters() {
		// Only apply URL parameters if page has product shortcodes
		// This prevents forcing shortcode behavior on pages meant for UX Builder editing
		if (!self::page_has_product_shortcodes()) {
			return array();
		}
		
		// Check for URL-based parameters first
		$url_params = Handy_Custom::get_url_parameters();
		
		if (!empty($url_params)) {
			return $url_params;
		}

		// Fallback to query parameters
		$params = array();
		
		if (!empty($_GET['product_category'])) {
			$params['category'] = sanitize_text_field($_GET['product_category']);
		}
		
		if (!empty($_GET['product_subcategory'])) {
			$params['subcategory'] = sanitize_text_field($_GET['product_subcategory']);
		}

		return $params;
	}

	/**
	 * Check if current page contains product-related shortcodes
	 *
	 * @return bool True if page has product shortcodes
	 */
	public static function page_has_product_shortcodes() {
		global $post;
		
		if (!$post || empty($post->post_content)) {
			return false;
		}
		
		// Check for any product-related shortcodes
		$product_shortcodes = array('products', 'filter-products');
		
		foreach ($product_shortcodes as $shortcode) {
			if (has_shortcode($post->post_content, $shortcode)) {
				return true;
			}
		}
		
		return false;
	}

	/**
	 * Check if current page is a product category page
	 *
	 * @return bool True if on product category page
	 */
	public static function is_product_category_page() {
		$params = self::get_current_url_parameters();
		return !empty($params['category']) || !empty($params['subcategory']);
	}

	/**
	 * Get breadcrumb data for current product page
	 *
	 * @return array Breadcrumb data
	 */
	public static function get_breadcrumb_data() {
		$params = self::get_current_url_parameters();
		$breadcrumbs = array();

		// Add products home
		$breadcrumbs[] = array(
			'title' => 'Products',
			'url' => home_url('/products/'),
			'current' => empty($params)
		);

		// Add category if present
		if (!empty($params['category'])) {
			$category_term = self::get_term_by_slug($params['category'], 'product-category');
			if ($category_term) {
				$breadcrumbs[] = array(
					'title' => $category_term->name,
					'url' => self::get_category_url($params['category']),
					'current' => empty($params['subcategory'])
				);
			}
		}

		// Add subcategory if present
		if (!empty($params['subcategory'])) {
			$subcategory_term = self::get_term_by_slug($params['subcategory'], 'product-category');
			if ($subcategory_term) {
				$breadcrumbs[] = array(
					'title' => $subcategory_term->name,
					'url' => self::get_subcategory_url($params['subcategory'], isset($params['category']) ? $params['category'] : ''),
					'current' => true
				);
			}
		}

		return $breadcrumbs;
	}

	/**
	 * Validate internal URL for Shop Now buttons
	 * Ensures URL starts with /products/ and is internal to the site
	 *
	 * @param string $url URL to validate
	 * @return bool True if valid internal products URL
	 */
	public static function validate_shop_now_url($url) {
		if (empty($url)) {
			return false;
		}

		// Sanitize the URL
		$url = sanitize_text_field($url);
		
		// Check if URL starts with /products/
		if (strpos($url, '/products/') !== 0) {
			Handy_Custom_Logger::log("Shop Now URL validation failed - must start with /products/: {$url}", 'warning');
			return false;
		}

		// Ensure it's a relative URL (starts with /) - no external links
		if (strpos($url, 'http') === 0 || strpos($url, '//') === 0) {
			Handy_Custom_Logger::log("Shop Now URL validation failed - external URLs not allowed: {$url}", 'warning');
			return false;
		}

		// Additional security: no javascript or suspicious content
		if (preg_match('/javascript:|data:|vbscript:/i', $url)) {
			Handy_Custom_Logger::log("Shop Now URL validation failed - suspicious content detected: {$url}", 'error');
			return false;
		}

		Handy_Custom_Logger::log("Shop Now URL validated successfully: {$url}", 'debug');
		return true;
	}

	/**
	 * Get Shop Now URL for a category term from ACF field
	 *
	 * @param WP_Term|int $term_or_id Category term object or term ID
	 * @return string|false Validated Shop Now URL or false if invalid/empty
	 */
	public static function get_shop_now_url($term_or_id) {
		if (empty($term_or_id)) {
			return false;
		}

		// Get term ID
		$term_id = is_object($term_or_id) ? $term_or_id->term_id : absint($term_or_id);
		
		if (empty($term_id)) {
			return false;
		}

		// Get ACF field value
		$shop_url = get_field('internal_url_for_this_product_category_or_subcategory', 'product-category_' . $term_id);
		
		// Validate the URL
		if (self::validate_shop_now_url($shop_url)) {
			return esc_url($shop_url);
		}

		return false;
	}

	/**
	 * Check if a category has child categories
	 *
	 * @param int $category_id Category term ID
	 * @return bool True if category has children
	 */
	public static function has_child_categories($category_id) {
		if (empty($category_id)) {
			return false;
		}

		$child_terms = get_terms(array(
			'taxonomy' => 'product-category',
			'parent' => $category_id,
			'hide_empty' => false,
			'fields' => 'ids'
		));

		if (is_wp_error($child_terms)) {
			Handy_Custom_Logger::log("Error checking child categories for ID {$category_id}: " . $child_terms->get_error_message(), 'error');
			return false;
		}

		return !empty($child_terms);
	}
}