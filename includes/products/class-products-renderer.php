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
		
		// Load the main archive template with appropriate data
		$template_vars = array(
			'filters' => $filters,
			'display_mode' => $display_mode
		);

		// Determine display mode based on filter system logic
		if ($display_mode === 'categories') {
			// Get categories from filter system - it will return empty array if should show products
			$categories = $this->get_filtered_categories($filters);
			$template_vars['categories'] = $categories;
			
			// If no categories returned, switch to list mode for products
			if (empty($categories)) {
				$display_mode = 'list';
				$template_vars['display_mode'] = $display_mode;
				$template_vars['products'] = $this->get_filtered_products($filters);
				Handy_Custom_Logger::log("No categories found, switching to list mode for filter: " . wp_json_encode($filters), 'info');
			} else {
				Handy_Custom_Logger::log("Showing " . count($categories) . " categories for filter: " . wp_json_encode($filters), 'info');
			}
		} else {
			// Explicit list mode requested
			$template_vars['products'] = $this->get_filtered_products($filters);
			Handy_Custom_Logger::log("Explicit list mode requested for filter: " . wp_json_encode($filters), 'info');
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
		
		// Include template
		include $template_path;
	}
}