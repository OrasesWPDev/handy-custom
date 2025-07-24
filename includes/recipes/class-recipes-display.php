<?php
/**
 * Recipes display functionality
 * Handles recipe-specific display helpers and formatting
 *
 * @package Handy_Custom
 */

if (!defined('ABSPATH')) {
	exit;
}

class Handy_Custom_Recipes_Display {

	/**
	 * Get recipe category featured image URL
	 * Uses same ACF field as products but for recipe-category taxonomy
	 *
	 * @param int $category_id Recipe category term ID
	 * @return string|false Featured image URL or false if not found
	 */
	public static function get_category_featured_image($category_id) {
		if (!function_exists('get_field')) {
			Handy_Custom_Logger::log('ACF get_field function not available for recipe category image', 'warning');
			return false;
		}

		// ACF field is shared between product-category and recipe-category
		$image = get_field('category_featured_image', 'recipe-category_' . $category_id);
		
		if ($image && is_array($image) && isset($image['url'])) {
			Handy_Custom_Logger::log("Found featured image for recipe category ID: {$category_id}", 'info');
			return $image['url'];
		}

		Handy_Custom_Logger::log("No featured image found for recipe category ID: {$category_id}", 'info');
		return false;
	}

	/**
	 * Get recipe category icon URL
	 * Uses same naming convention as products: {category-slug}-icon.png
	 *
	 * @param string $category_slug Recipe category slug
	 * @return string Icon URL or empty string if not found
	 */
	public static function get_category_icon($category_slug) {
		// Use the utility method that handles file existence checking
		return Handy_Custom_Recipes_Utils::get_category_icon($category_slug);
	}

	/**
	 * Get recipe card data for display
	 * Combines all recipe information needed for archive cards
	 *
	 * @param int $recipe_id Recipe post ID
	 * @return array Recipe card data
	 */
	public static function get_recipe_card_data($recipe_id) {
		$recipe = get_post($recipe_id);
		if (!$recipe) {
			return array();
		}

		// Get featured image
		$featured_image = get_the_post_thumbnail_url($recipe_id, 'medium');
		
		// Get recipe categories
		$categories = get_the_terms($recipe_id, 'recipe-category');
		$primary_category = is_array($categories) && !empty($categories) ? $categories[0] : null;
		
		// Get ACF fields with null checks
		$prep_time = '';
		$servings = '';
		
		if (function_exists('get_field')) {
			$prep_time_raw = get_field('prep_time', $recipe_id);
			$prep_time = !empty($prep_time_raw) ? $prep_time_raw : '';
			
			$servings_raw = get_field('servings', $recipe_id);
			$servings = !empty($servings_raw) ? $servings_raw : '';
		} else {
			Handy_Custom_Logger::log('ACF get_field function not available for recipe ACF fields', 'warning');
		}
		
		// Get recipe excerpt or content for description
		$description = !empty($recipe->post_excerpt) ? $recipe->post_excerpt : $recipe->post_content;
		$truncated_description = Handy_Custom_Recipes_Utils::truncate_description($description);

		return array(
			'id' => $recipe_id,
			'title' => get_the_title($recipe_id),
			'url' => Handy_Custom_Recipes_Utils::get_recipe_url($recipe_id),
			'featured_image' => $featured_image,
			'description' => $truncated_description,
			'prep_time' => Handy_Custom_Recipes_Utils::format_prep_time($prep_time),
			'servings' => Handy_Custom_Recipes_Utils::format_servings($servings),
			'category' => $primary_category,
			'category_icon' => $primary_category ? self::get_category_icon($primary_category->slug) : '',
			'has_image' => !empty($featured_image)
		);
	}

	/**
	 * Get recipe permalink for clickable cards
	 *
	 * @param int $recipe_id Recipe post ID
	 * @return string Recipe URL
	 */
	public static function get_recipe_url($recipe_id) {
		return Handy_Custom_Recipes_Utils::get_recipe_url($recipe_id);
	}

	/**
	 * Generate recipe card HTML
	 * Creates the HTML structure for a single recipe card
	 *
	 * @param array $card_data Recipe card data from get_recipe_card_data()
	 * @return string HTML for recipe card
	 */
	public static function render_recipe_card($card_data) {
		if (empty($card_data)) {
			return '';
		}

		ob_start();
		?>
		<div class="recipe-card" data-recipe-id="<?php echo esc_attr($card_data['id']); ?>">
			<a href="<?php echo esc_url($card_data['url']); ?>" class="recipe-card-link">
				<div class="recipe-card-image-container">
					<?php if ($card_data['has_image']): ?>
						<img src="<?php echo esc_url($card_data['featured_image']); ?>" 
							 alt="<?php echo esc_attr($card_data['title']); ?>" 
							 class="recipe-card-image" />
					<?php else: ?>
						<div class="recipe-card-image-placeholder">
							<span>No Image</span>
						</div>
					<?php endif; ?>
					
					<?php if ($card_data['category'] && $card_data['category_icon']): ?>
						<div class="recipe-category-icon">
							<img src="<?php echo esc_url($card_data['category_icon']); ?>" 
								 alt="<?php echo esc_attr($card_data['category']->name); ?> icon" />
						</div>
					<?php elseif ($card_data['category']): ?>
						<!-- TODO: Replace with actual category icon when available -->
						<div class="recipe-category-icon recipe-category-icon-placeholder">
							<span><?php echo esc_html(substr($card_data['category']->name, 0, 1)); ?></span>
						</div>
					<?php endif; ?>
				</div>
				
				<div class="recipe-card-content">
					<h3 class="recipe-card-title"><?php echo esc_html($card_data['title']); ?></h3>
					
					<?php if ($card_data['description']): ?>
						<p class="recipe-card-description"><?php echo esc_html($card_data['description']); ?></p>
					<?php endif; ?>
					
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
		return ob_get_clean();
	}

	/**
	 * Generate filter dropdown HTML
	 * Creates dropdown select elements for recipe filtering
	 *
	 * @param string $filter_key Filter key (category, cooking_method, menu_occasion)
	 * @param array $terms Array of term objects for the dropdown
	 * @param string $selected_value Currently selected value
	 * @return string HTML for filter dropdown
	 */
	public static function render_filter_dropdown($filter_key, $terms, $selected_value = '') {
		if (empty($terms) || !is_array($terms)) {
			Handy_Custom_Logger::log("Filter dropdown terms empty or invalid for: {$filter_key}", 'info');
			return '';
		}

		// Generate readable label from filter key
		$label = ucwords(str_replace('_', ' ', $filter_key));
		
		ob_start();
		?>
		<div class="recipe-filter-dropdown">
			<label for="recipe-<?php echo esc_attr($filter_key); ?>" class="recipe-filter-label">
				<?php echo esc_html($label); ?>
			</label>
			<select id="recipe-<?php echo esc_attr($filter_key); ?>" 
					name="<?php echo esc_attr($filter_key); ?>" 
					class="recipe-filter-select" 
					data-filter="<?php echo esc_attr($filter_key); ?>">
				<option value="">All <?php echo esc_html($label); ?></option>
				<?php foreach ($terms as $term): ?>
					<?php if (is_object($term) && isset($term->slug, $term->name)): ?>
						<option value="<?php echo esc_attr($term->slug); ?>" 
								<?php selected($selected_value, $term->slug); ?>>
							<?php echo esc_html($term->name); ?>
						</option>
					<?php endif; ?>
				<?php endforeach; ?>
			</select>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Generate "no recipes found" message
	 *
	 * @return string HTML for no results message
	 */
	public static function render_no_recipes_message() {
		ob_start();
		?>
		<div class="recipe-no-results">
			<p>No recipes found matching your criteria. Please try adjusting your filters.</p>
		</div>
		<?php
		return ob_get_clean();
	}
}