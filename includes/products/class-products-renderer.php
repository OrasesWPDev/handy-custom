<?php
/**
 * Products rendering functionality
 *
 * @package Handy_Custom
 */

if (!defined('ABSPATH')) {
	exit;
}

class Handy_Custom_Products_Renderer {

	/**
	 * Render products display
	 *
	 * @param array $filters Filter parameters including display mode
	 * @return string
	 */
	public function render($filters = array()) {
		// Start output buffering
		ob_start();

		// Get display mode (default to 'categories')
		$display_mode = isset($filters['display']) ? $filters['display'] : 'categories';
		
		// Always show filters in both modes, but category filter only in list mode
		$include_category_filter = ($display_mode === 'list');

		// Load the main archive template with appropriate data
		$template_vars = array(
			'filters' => $filters,
			'display_mode' => $display_mode,
			'filter_options' => $this->get_filter_options($include_category_filter, true) // Always show filters
		);

		// Add data based on display mode
		if ($display_mode === 'list') {
			$template_vars['products'] = $this->get_filtered_products($filters);
		} else {
			$template_vars['categories'] = $this->get_filtered_categories($filters);
		}

		$this->load_template('products/archive', $template_vars);

		return ob_get_clean();
	}

	/**
	 * Get filtered product categories
	 *
	 * @param array $filters Filter parameters
	 * @return array
	 */
	private function get_filtered_categories($filters) {
		return Handy_Custom_Products_Filters::get_filtered_categories($filters);
	}

	/**
	 * Get filtered products for list display mode
	 *
	 * @param array $filters Filter parameters
	 * @return WP_Query
	 */
	private function get_filtered_products($filters) {
		return Handy_Custom_Products_Filters::get_filtered_products($filters);
	}

	/**
	 * Get filter options for dropdowns
	 *
	 * @param bool $include_category_filter Whether to include category filter
	 * @param bool $force_show_filters Whether to force showing filters even in categories mode
	 * @return array
	 */
	private function get_filter_options($include_category_filter = false, $force_show_filters = false) {
		if (!$force_show_filters && !$include_category_filter) {
			return array(); // Don't show filters unless forced or in list mode
		}
		return Handy_Custom_Products_Filters::get_filter_options(array(), $include_category_filter);
	}

	/**
	 * Load a template file
	 *
	 * @param string $template Template name (without .php extension)
	 * @param array $variables Variables to pass to template
	 */
	private function load_template($template, $variables = array()) {
		$template_path = HANDY_CUSTOM_PLUGIN_DIR . 'templates/shortcodes/' . $template . '.php';

		if (!file_exists($template_path)) {
			Handy_Custom_Logger::log("Template not found: {$template_path}", 'error');
			echo '<p>Error: Template not found.</p>';
			return;
		}

		// Extract variables for use in template - explicit assignments for security
		$filters = isset($variables['filters']) ? $variables['filters'] : array();
		$categories = isset($variables['categories']) ? $variables['categories'] : array();
		$products = isset($variables['products']) ? $variables['products'] : null;
		$display_mode = isset($variables['display_mode']) ? $variables['display_mode'] : 'categories';
		$filter_options = isset($variables['filter_options']) ? $variables['filter_options'] : array();
		
		// Include template
		include $template_path;
	}
}