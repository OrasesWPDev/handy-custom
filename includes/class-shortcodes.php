<?php
/**
 * Shortcodes functionality
 *
 * @package Handy_Custom
 */

if (!defined('ABSPATH')) {
	exit;
}

class Handy_Custom_Shortcodes {

	/**
	 * Initialize shortcodes
	 */
	public static function init() {
		add_shortcode('products', array(__CLASS__, 'products_shortcode'));
		add_shortcode('recipes', array(__CLASS__, 'recipes_shortcode'));
		
		// AJAX handlers for filtering
		add_action('wp_ajax_filter_products', array(__CLASS__, 'ajax_filter_products'));
		add_action('wp_ajax_nopriv_filter_products', array(__CLASS__, 'ajax_filter_products'));
		add_action('wp_ajax_filter_recipes', array(__CLASS__, 'ajax_filter_recipes'));
		add_action('wp_ajax_nopriv_filter_recipes', array(__CLASS__, 'ajax_filter_recipes'));
	}

	/**
	 * Products shortcode handler
	 * Now supports subcategory parameter with automatic parent detection
	 *
	 * @param array $atts Shortcode attributes
	 * @return string
	 */
	public static function products_shortcode($atts) {
		$defaults = array_fill_keys(array_keys(Handy_Custom_Products_Utils::get_taxonomy_mapping()), '');
		$atts = shortcode_atts($defaults, $atts, 'products');

		// Sanitize attributes
		$atts = Handy_Custom_Products_Utils::sanitize_filters($atts);

		// Handle subcategory auto-detection
		if (!empty($atts['subcategory']) && empty($atts['category'])) {
			$parent_category = Handy_Custom_Products_Utils::get_parent_category_from_subcategory($atts['subcategory']);
			if ($parent_category && $parent_category !== $atts['subcategory']) {
				$atts['category'] = $parent_category;
				Handy_Custom_Logger::log("Auto-detected parent category '{$parent_category}' for subcategory '{$atts['subcategory']}'", 'info');
			}
		}

		// Log shortcode usage with subcategory support
		$log_message = 'Products shortcode called with attributes: ' . wp_json_encode($atts);
		if (!empty($atts['subcategory'])) {
			$log_message .= " (subcategory filtering enabled)";
		}
		Handy_Custom_Logger::log($log_message, 'info');

		try {
			$renderer = new Handy_Custom_Products_Renderer();
			return $renderer->render($atts);
		} catch (Exception $e) {
			Handy_Custom_Logger::log('Products shortcode error: ' . $e->getMessage(), 'error');
			return '<div class="product-error"><p>Error loading products. Please try again later.</p></div>';
		}
	}

	/**
	 * Recipes shortcode handler
	 * Renders recipe archive with filtering functionality
	 *
	 * @param array $atts Shortcode attributes
	 * @return string HTML for recipe archive
	 */
	public static function recipes_shortcode($atts) {
		// Define defaults based on recipe taxonomy mapping
		$defaults = array_fill_keys(array_keys(Handy_Custom_Recipes_Utils::get_taxonomy_mapping()), '');
		$atts = shortcode_atts($defaults, $atts, 'recipes');

		// Sanitize attributes
		$atts = Handy_Custom_Recipes_Utils::sanitize_filters($atts);

		Handy_Custom_Logger::log('Recipes shortcode called with attributes: ' . wp_json_encode($atts), 'info');

		try {
			$renderer = new Handy_Custom_Recipes_Renderer();
			return $renderer->render($atts);
		} catch (Exception $e) {
			Handy_Custom_Logger::log('Recipes shortcode error: ' . $e->getMessage(), 'error');
			return '<div class="recipe-error"><p>Error loading recipes. Please try again later.</p></div>';
		}
	}

	/**
	 * AJAX handler for product filtering
	 */
	public static function ajax_filter_products() {
		// Verify nonce
		if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'handy_custom_nonce')) {
			wp_send_json_error('Security check failed');
		}

		// Get filter parameters using utility function
		$raw_filters = array();
		foreach (array_keys(Handy_Custom_Products_Utils::get_taxonomy_mapping()) as $key) {
			$raw_filters[$key] = isset($_POST[$key]) ? $_POST[$key] : '';
		}
		$filters = Handy_Custom_Products_Utils::sanitize_filters($raw_filters);

		Handy_Custom_Logger::log('AJAX filter request: ' . wp_json_encode($filters));

		try {
			// Load the products renderer
			$renderer = new Handy_Custom_Products_Renderer();
			$output = $renderer->render($filters);
			wp_send_json_success(array('html' => $output));
		} catch (Exception $e) {
			Handy_Custom_Logger::log('AJAX filter error: ' . $e->getMessage(), 'error');
			wp_send_json_error('Filter processing failed');
		}
	}

	/**
	 * AJAX handler for recipe filtering
	 * Processes recipe filter requests and returns updated HTML
	 */
	public static function ajax_filter_recipes() {
		// Verify nonce for security
		if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'handy_custom_nonce')) {
			Handy_Custom_Logger::log('Recipe AJAX: Security check failed', 'warning');
			wp_send_json_error('Security check failed');
		}

		// Get filter parameters using recipe utility function
		$raw_filters = array();
		foreach (array_keys(Handy_Custom_Recipes_Utils::get_taxonomy_mapping()) as $key) {
			$raw_filters[$key] = isset($_POST[$key]) ? $_POST[$key] : '';
		}
		$filters = Handy_Custom_Recipes_Utils::sanitize_filters($raw_filters);

		Handy_Custom_Logger::log('Recipe AJAX filter request: ' . wp_json_encode($filters), 'info');

		try {
			// Load the recipes renderer
			$renderer = new Handy_Custom_Recipes_Renderer();
			$output = $renderer->render($filters);
			
			Handy_Custom_Logger::log('Recipe AJAX filter successful', 'info');
			wp_send_json_success(array('html' => $output));
		} catch (Exception $e) {
			Handy_Custom_Logger::log('Recipe AJAX filter error: ' . $e->getMessage(), 'error');
			wp_send_json_error('Recipe filter processing failed');
		}
	}
}