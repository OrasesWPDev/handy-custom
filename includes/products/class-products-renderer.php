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
	 * @param array $filters Filter parameters
	 * @return string
	 */
	public function render($filters = array()) {
		// Start output buffering
		ob_start();

		// Load the main archive template
		$this->load_template('products/archive', array(
			'filters' => $filters,
			'categories' => $this->get_filtered_categories($filters),
			'filter_options' => $this->get_filter_options()
		));

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
	 * Get filter options for dropdowns
	 *
	 * @return array
	 */
	private function get_filter_options() {
		return Handy_Custom_Products_Filters::get_filter_options();
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
		$filter_options = isset($variables['filter_options']) ? $variables['filter_options'] : array();
		$subcategory_context = isset($variables['subcategory_context']) ? $variables['subcategory_context'] : '';
		
		// Include template
		include $template_path;
	}
}