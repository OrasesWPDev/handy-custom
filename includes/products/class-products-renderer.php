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
	 * Render specific products by IDs for featured product sections
	 *
	 * @param array $product_ids Array of product post IDs
	 * @param array $options Display options (columns, wrapper_class, etc.)
	 * @return string
	 */
	public function render_specific_products($product_ids, $options = array()) {
		// Validate product IDs
		if (empty($product_ids) || !is_array($product_ids)) {
			Handy_Custom_Logger::log("Invalid product IDs provided to render_specific_products", 'warning');
			return '';
		}

		// Default options with dynamic column count
		$defaults = array(
			'columns' => count($product_ids),
			'show_wrapper' => true,
			'wrapper_class' => 'handy-featured-products-grid'
		);
		$options = array_merge($defaults, $options);

		// Query specific products
		$products_query = new WP_Query(array(
			'post_type' => 'product',
			'post_status' => 'publish',
			'post__in' => $product_ids,
			'orderby' => 'post__in', // Maintain the order of IDs
			'posts_per_page' => count($product_ids),
			'no_found_rows' => true, // Performance optimization
			'update_post_meta_cache' => false // Performance optimization
		));

		if (!$products_query->have_posts()) {
			Handy_Custom_Logger::log("No valid products found for IDs: " . implode(', ', $product_ids), 'warning');
			return '';
		}

		// Log success
		Handy_Custom_Logger::log("Rendering " . $products_query->post_count . " specific products", 'info');

		// Start output buffering
		ob_start();

		// Create wrapper with dynamic column data attribute
		if ($options['show_wrapper']) {
			echo '<div class="' . esc_attr($options['wrapper_class']) . '" data-columns="' . esc_attr($options['columns']) . '">';
		}

		// Render each product as a card using existing display methods
		while ($products_query->have_posts()) {
			$products_query->the_post();
			
			// Get product data using existing individual methods
			$product_id = get_the_ID();
			$product_thumbnail = Handy_Custom_Products_Display::get_product_thumbnail($product_id);
			$product_excerpt = Handy_Custom_Products_Display::get_product_excerpt($product_id);
			$product_url = Handy_Custom_Products_Display::get_product_single_url($product_id);
			
			// Render product card HTML directly (matching archive template structure)
			echo '<div class="product-list-card" data-product="' . esc_attr($product_id) . '">';
			echo '<a href="' . esc_url($product_url) . '" class="product-card-link">';
			
			// Product thumbnail
			echo '<div class="product-thumbnail">';
			if ($product_thumbnail) {
				echo '<img src="' . esc_url($product_thumbnail) . '" alt="' . esc_attr(get_the_title()) . '" loading="lazy">';
			} else {
				echo '<div class="image-placeholder"><span>' . esc_html(get_the_title()) . '</span></div>';
			}
			echo '</div>';
			
			// Product info
			echo '<div class="product-info">';
			echo '<div class="product-content">';
			echo '<h3 class="product-title">' . esc_html(get_the_title()) . '</h3>';
			
			if (!empty($product_excerpt)) {
				echo '<div class="product-excerpt"><p>' . esc_html($product_excerpt) . '</p></div>';
			}
			echo '</div>';
			
			// Product actions
			echo '<div class="product-actions">';
			echo '<a href="' . esc_url($product_url) . '" class="btn-see-details">See Product Details</a>';
			echo '</div>';
			echo '</div>';
			
			echo '</a>';
			echo '</div>';
		}

		if ($options['show_wrapper']) {
			echo '</div>';
		}

		// Reset post data
		wp_reset_postdata();

		return ob_get_clean();
	}

	/**
	 * Extract product ID from URL (domain-agnostic)
	 * Handles both URL formats:
	 * - /products/{category}/{product-slug}/
	 * - /products/{parent-category}/{child-category}/{product-slug}/
	 *
	 * @param string $url Product URL
	 * @return int|false Product post ID or false if not found
	 */
	public static function extract_product_id_from_url($url) {
		// Use regex to extract the final slug from product URLs
		if (preg_match('/\/products\/(?:[^\/]+\/)*([^\/\?#]+)\/?$/', $url, $matches)) {
			$slug = sanitize_title($matches[1]);
			$product = get_page_by_path($slug, OBJECT, 'product');
			
			if ($product && $product->post_status === 'publish') {
				Handy_Custom_Logger::log("Extracted product ID {$product->ID} from URL: {$url}", 'info');
				return $product->ID;
			}
		}
		
		Handy_Custom_Logger::log("Could not extract valid product ID from URL: {$url}", 'warning');
		return false;
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