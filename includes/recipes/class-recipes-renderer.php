<?php
/**
 * Recipes rendering functionality
 * Main renderer for recipe shortcode and AJAX responses
 *
 * @package Handy_Custom
 */

if (!defined('ABSPATH')) {
	exit;
}

class Handy_Custom_Recipes_Renderer {

	/**
	 * Render recipe archive display
	 * Main entry point for [recipes] shortcode rendering
	 *
	 * @param array $filters Filter parameters from shortcode or AJAX
	 * @return string Complete HTML for recipe archive
	 */
	public function render($filters = array()) {
		Handy_Custom_Logger::log("Starting recipe archive render with filters: " . json_encode($filters), 'info');
		
		// Sanitize and validate filters
		$sanitized_filters = Handy_Custom_Recipes_Utils::sanitize_filters($filters);
		$validated_filters = Handy_Custom_Recipes_Filters::validate_filters($sanitized_filters);
		
		// Start output buffering
		ob_start();

		// Load the main archive template with all necessary data
		$this->load_template('recipes/archive', array(
			'filters' => $validated_filters,
			'recipes' => $this->get_filtered_recipes($validated_filters),
			'filter_options' => $this->get_filter_options(),
			'total_recipes' => $this->get_recipe_count($validated_filters)
		));

		$output = ob_get_clean();
		
		Handy_Custom_Logger::log("Recipe archive render completed", 'info');
		return $output;
	}

	/**
	 * Render just the recipe cards (for AJAX responses)
	 * Returns only the recipe grid without filters/wrapper
	 *
	 * @param array $filters Filter parameters
	 * @return string HTML for recipe cards only
	 */
	public function render_recipe_cards_only($filters = array()) {
		$sanitized_filters = Handy_Custom_Recipes_Utils::sanitize_filters($filters);
		$validated_filters = Handy_Custom_Recipes_Filters::validate_filters($sanitized_filters);
		
		$recipes = $this->get_filtered_recipes($validated_filters);
		
		if (empty($recipes)) {
			return Handy_Custom_Recipes_Display::render_no_recipes_message();
		}

		ob_start();
		?>
		<div class="recipe-cards-grid">
			<?php foreach ($recipes as $recipe): ?>
				<?php 
				$card_data = Handy_Custom_Recipes_Display::get_recipe_card_data($recipe->ID);
				echo Handy_Custom_Recipes_Display::render_recipe_card($card_data);
				?>
			<?php endforeach; ?>
		</div>
		<?php
		
		return ob_get_clean();
	}

	/**
	 * Get filtered recipes based on applied filters
	 *
	 * @param array $filters Validated filter parameters
	 * @return array Array of recipe post objects
	 */
	private function get_filtered_recipes($filters) {
		$query = Handy_Custom_Recipes_Filters::get_filtered_recipes($filters);
		
		if ($query->have_posts()) {
			return $query->posts;
		}
		
		Handy_Custom_Logger::log("No recipes found for applied filters", 'info');
		return array();
	}

	/**
	 * Get filter options for all recipe taxonomy dropdowns
	 *
	 * @return array Filter options for template
	 */
	private function get_filter_options() {
		$options = Handy_Custom_Recipes_Filters::get_filter_options();
		
		// Ensure we have the expected structure
		$expected_keys = array('categories', 'cooking_methods', 'menu_occasions');
		foreach ($expected_keys as $key) {
			if (!isset($options[$key])) {
				$options[$key] = array();
			}
		}
		
		return $options;
	}

	/**
	 * Get total count of recipes matching filters
	 *
	 * @param array $filters Filter parameters
	 * @return int Recipe count
	 */
	private function get_recipe_count($filters) {
		$query = Handy_Custom_Recipes_Filters::get_filtered_recipes($filters, array(
			'posts_per_page' => -1,
			'fields' => 'ids'
		));
		
		return $query->found_posts;
	}

	/**
	 * Load a template file with error handling
	 *
	 * @param string $template Template name (without .php extension)
	 * @param array $variables Variables to pass to template
	 */
	private function load_template($template, $variables = array()) {
		$template_path = HANDY_CUSTOM_PLUGIN_DIR . 'templates/shortcodes/' . $template . '.php';

		if (!file_exists($template_path)) {
			Handy_Custom_Logger::log("Recipe template not found: {$template_path}", 'error');
			echo '<div class="recipe-error"><p>Error: Recipe template not found.</p></div>';
			return;
		}

		// Extract variables for use in template
		extract($variables);
		
		Handy_Custom_Logger::log("Loading recipe template: {$template}", 'info');
		
		// Include template
		include $template_path;
	}

	/**
	 * Render recipe filter dropdowns
	 * Generates the three filter dropdowns for recipes
	 *
	 * @param array $filter_options Available filter options
	 * @param array $current_filters Currently applied filters
	 * @return string HTML for filter dropdowns
	 */
	public function render_filter_dropdowns($filter_options, $current_filters = array()) {
		ob_start();
		?>
		<div class="recipe-filters">
			<div class="recipe-filters-container">
				<?php
				// Render category filter
				if (!empty($filter_options['categories'])) {
					echo Handy_Custom_Recipes_Display::render_filter_dropdown(
						'category',
						$filter_options['categories'],
						isset($current_filters['category']) ? $current_filters['category'] : ''
					);
				}
				
				// Render cooking method filter
				if (!empty($filter_options['cooking_methods'])) {
					echo Handy_Custom_Recipes_Display::render_filter_dropdown(
						'cooking_method',
						$filter_options['cooking_methods'],
						isset($current_filters['cooking_method']) ? $current_filters['cooking_method'] : ''
					);
				}
				
				// Render menu occasion filter
				if (!empty($filter_options['menu_occasions'])) {
					echo Handy_Custom_Recipes_Display::render_filter_dropdown(
						'menu_occasion',
						$filter_options['menu_occasions'],
						isset($current_filters['menu_occasion']) ? $current_filters['menu_occasion'] : ''
					);
				}
				?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Generate recipe archive header
	 *
	 * @param int $total_recipes Total number of recipes
	 * @param array $filters Applied filters
	 * @return string HTML for archive header
	 */
	public function render_archive_header($total_recipes, $filters = array()) {
		$has_filters = !empty(array_filter($filters));
		
		ob_start();
		?>
		<div class="recipe-archive-header">
			<h2 class="recipe-archive-title">
				<?php if ($has_filters): ?>
					Filtered Recipes
				<?php else: ?>
					All Recipes
				<?php endif; ?>
			</h2>
			<div class="recipe-count">
				<span class="recipe-count-number"><?php echo esc_html($total_recipes); ?></span>
				<span class="recipe-count-text">
					<?php echo $total_recipes === 1 ? 'recipe' : 'recipes'; ?>
					<?php if ($has_filters): ?>
						found
					<?php endif; ?>
				</span>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}