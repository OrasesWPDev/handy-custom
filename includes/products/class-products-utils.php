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
			return home_url('/products/');
		}

		// Auto-detect parent if not provided
		if (empty($parent_slug)) {
			$parent_slug = self::get_parent_category_from_subcategory($subcategory_slug);
		}

		// Fallback to category-only URL if no parent found
		if (empty($parent_slug) || $parent_slug === $subcategory_slug) {
			return home_url("/products/{$subcategory_slug}/");
		}

		return home_url("/products/{$parent_slug}/{$subcategory_slug}/");
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
}