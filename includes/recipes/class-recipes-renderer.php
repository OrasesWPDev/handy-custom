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
		// Use a small page size for count queries - we only need found_posts
		// This allows the existing caching system in get_filtered_recipes to work
		$query = Handy_Custom_Recipes_Filters::get_filtered_recipes($filters, array(
			'posts_per_page' => 1,  // Minimal query for count only
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

		// Extract variables for use in template - explicit assignments for security
		$filters = isset($variables['filters']) ? $variables['filters'] : array();
		$recipes = isset($variables['recipes']) ? $variables['recipes'] : array();
		$filter_options = isset($variables['filter_options']) ? $variables['filter_options'] : array();
		$total_recipes = isset($variables['total_recipes']) ? $variables['total_recipes'] : 0;
		
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

	/**
	 * Render specific recipes by post IDs
	 * Used for featured recipes sections - bypasses taxonomy filtering
	 *
	 * @param array $recipe_ids Array of recipe post IDs to display
	 * @param array $options Options array for layout customization
	 * @return string HTML for specific recipe cards
	 */
	public function render_specific_recipes($recipe_ids, $options = array()) {
		// Validate recipe IDs
		if (empty($recipe_ids) || !is_array($recipe_ids)) {
			Handy_Custom_Logger::log("Invalid recipe IDs provided to render_specific_recipes", 'warning');
			return '';
		}

		// Sanitize recipe IDs to ensure they're all integers
		$recipe_ids = array_map('intval', $recipe_ids);
		$recipe_ids = array_filter($recipe_ids, function($id) {
			return $id > 0;
		});

		if (empty($recipe_ids)) {
			Handy_Custom_Logger::log("No valid recipe IDs after sanitization", 'warning');
			return '';
		}

		// Default options
		$defaults = array(
			'columns' => 3,
			'show_wrapper' => true,
			'wrapper_class' => 'handy-featured-recipes-grid'
		);
		$options = array_merge($defaults, $options);

		// Query specific recipes
		$query_args = array(
			'post_type' => 'recipe',
			'post_status' => 'publish',
			'post__in' => $recipe_ids,
			'orderby' => 'post__in', // Maintain the order specified in recipe_ids
			'posts_per_page' => count($recipe_ids)
		);

		$query = new WP_Query($query_args);

		if (!$query->have_posts()) {
			Handy_Custom_Logger::log("No published recipes found for IDs: " . implode(', ', $recipe_ids), 'info');
			return '';
		}

		$recipes = $query->posts;
		wp_reset_postdata();

		Handy_Custom_Logger::log("Rendering " . count($recipes) . " specific recipes for featured section", 'info');

		// Start output buffering
		ob_start();

		if ($options['show_wrapper']) {
			echo '<div class="' . esc_attr($options['wrapper_class']) . '" data-columns="' . esc_attr($options['columns']) . '">';
		}

		foreach ($recipes as $recipe) {
			$card_data = Handy_Custom_Recipes_Display::get_recipe_card_data($recipe->ID);
			
			if (empty($card_data)) {
				Handy_Custom_Logger::log("No card data for recipe ID: {$recipe->ID}", 'warning');
				continue;
			}
			?>
			<div class="recipe-card" data-recipe-id="<?php echo esc_attr($card_data['id']); ?>">
				<a href="<?php echo esc_url($card_data['url']); ?>" class="recipe-card-link">
					
					<!-- Recipe Featured Image -->
					<div class="recipe-card-image-container">
						<?php if ($card_data['has_image']): ?>
							<img src="<?php echo esc_url($card_data['featured_image']); ?>" 
								 alt="<?php echo esc_attr($card_data['title']); ?>" 
								 class="recipe-card-image" 
								 loading="lazy" />
						<?php else: ?>
							<div class="recipe-card-image-placeholder">
								<span>No Image</span>
							</div>
						<?php endif; ?>
					</div>
					
					<!-- Recipe Card Content -->
					<div class="recipe-card-content">
						
						<!-- Recipe Title -->
						<h3 class="recipe-card-title"><?php echo esc_html($card_data['title']); ?></h3>
						
						<!-- Recipe Description -->
						<?php if ($card_data['description']): ?>
							<p class="recipe-card-description"><?php echo esc_html($card_data['description']); ?></p>
						<?php endif; ?>
						
						<!-- Recipe Meta: Prep Time & Servings -->
						<div class="recipe-card-meta">
							<div class="recipe-prep-time">
								<i class="fa-regular fa-clock recipe-icon recipe-clock-icon" aria-hidden="true"></i>
								<span class="recipe-prep-time-text"><?php echo esc_html($card_data['prep_time']); ?></span>
							</div>
							
							<div class="recipe-servings">
								<i class="fa-regular fa-user recipe-icon recipe-person-icon" aria-hidden="true"></i>
								<span class="recipe-servings-text"><?php echo esc_html($card_data['servings']); ?></span>
							</div>
						</div>
						
					</div>
					
				</a>
			</div>
			<?php
		}

		if ($options['show_wrapper']) {
			echo '</div>';
		}

		return ob_get_clean();
	}

	/**
	 * Extract recipe post ID from URL
	 * Domain-agnostic approach using recipe slug extraction
	 *
	 * @param string $url Recipe URL from ACF link field
	 * @return int|false Recipe post ID or false if not found
	 */
	public static function extract_recipe_id_from_url($url) {
		if (empty($url) || !is_string($url)) {
			return false;
		}

		// Extract slug from URL path (works with any domain)
		if (preg_match('/\/recipe\/([^\/\?#]+)/', $url, $matches)) {
			$slug = sanitize_title($matches[1]);
			$recipe = get_page_by_path($slug, OBJECT, 'recipe');
			
			if ($recipe && $recipe->post_status === 'publish') {
				Handy_Custom_Logger::log("Found recipe ID {$recipe->ID} for slug: {$slug}", 'info');
				return $recipe->ID;
			}
			
			Handy_Custom_Logger::log("No published recipe found for slug: {$slug}", 'warning');
		} else {
			Handy_Custom_Logger::log("Invalid recipe URL format: {$url}", 'warning');
		}

		return false;
	}
}