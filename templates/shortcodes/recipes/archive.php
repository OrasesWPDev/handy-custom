<?php
/**
 * Recipes archive template
 * 
 * Template for [recipes] shortcode with AJAX filtering
 * Displays recipe cards with prep time and servings
 *
 * @package Handy_Custom
 * 
 * Available variables:
 * @var array $filters Current filter values
 * @var array $recipes Recipe posts to display
 * @var array $filter_options All available filter options
 * @var int $total_recipes Total number of recipes found
 */

if (!defined('ABSPATH')) {
    exit;
}

Handy_Custom_Logger::log('Loading recipes archive template with ' . count($recipes) . ' recipes');
?>

<div class="handy-recipes-archive" data-shortcode="recipes">
    
    <!-- Recipe Archive Header -->
    <div class="recipes-archive-header">
        <h2 class="recipes-archive-title">
            <?php if (!empty(array_filter($filters))): ?>
                Filtered Recipes
            <?php else: ?>
                All Recipes
            <?php endif; ?>
        </h2>
        <div class="recipes-count">
            <span class="recipes-count-number"><?php echo esc_html($total_recipes); ?></span>
            <span class="recipes-count-text">
                <?php echo $total_recipes === 1 ? 'recipe' : 'recipes'; ?>
                <?php if (!empty(array_filter($filters))): ?>
                    found
                <?php endif; ?>
            </span>
        </div>
    </div>
    
    <!-- Filter Controls - Always show for recipes -->
    <div class="handy-recipes-filters">
        <div class="filters-row">
            
            <?php if (!empty($filter_options['categories'])): ?>
            <div class="filter-group">
                <label for="filter-recipe-category">Category:</label>
                <select id="filter-recipe-category" 
                        name="category" 
                        class="recipe-filter">
                    <option value="">All Categories</option>
                    <?php foreach ($filter_options['categories'] as $term): ?>
                        <option value="<?php echo esc_attr($term->slug); ?>" 
                                <?php selected(isset($filters['category']) ? $filters['category'] : '', $term->slug); ?>>
                            <?php echo esc_html($term->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($filter_options['cooking_methods'])): ?>
            <div class="filter-group">
                <label for="filter-recipe-cooking-method">Cooking Method:</label>
                <select id="filter-recipe-cooking-method" 
                        name="cooking_method" 
                        class="recipe-filter">
                    <option value="">All Cooking Methods</option>
                    <?php foreach ($filter_options['cooking_methods'] as $term): ?>
                        <option value="<?php echo esc_attr($term->slug); ?>" 
                                <?php selected(isset($filters['cooking_method']) ? $filters['cooking_method'] : '', $term->slug); ?>>
                            <?php echo esc_html($term->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($filter_options['menu_occasions'])): ?>
            <div class="filter-group">
                <label for="filter-recipe-menu-occasion">Menu Occasion:</label>
                <select id="filter-recipe-menu-occasion" 
                        name="menu_occasion" 
                        class="recipe-filter">
                    <option value="">All Menu Occasions</option>
                    <?php foreach ($filter_options['menu_occasions'] as $term): ?>
                        <option value="<?php echo esc_attr($term->slug); ?>" 
                                <?php selected(isset($filters['menu_occasion']) ? $filters['menu_occasion'] : '', $term->slug); ?>>
                            <?php echo esc_html($term->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            
        </div>
    </div>

    <!-- Recipes Grid -->
    <div class="handy-recipes-grid" id="recipes-results">
        
        <?php if (!empty($recipes)): ?>
            
            <?php foreach ($recipes as $recipe): ?>
                <?php
                // Get recipe card data using display helper
                $card_data = Handy_Custom_Recipes_Display::get_recipe_card_data($recipe->ID);
                
                if (empty($card_data)) {
                    continue; // Skip if no data available
                }
                ?>
                
                <div class="recipe-card" data-recipe-id="<?php echo esc_attr($card_data['id']); ?>">
                    <a href="<?php echo esc_url($card_data['url']); ?>" class="recipe-card-link">
                        
                        <!-- Recipe Featured Image with Category Icon -->
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
                            
                            <?php if ($card_data['category'] && $card_data['category_icon']): ?>
                                <div class="recipe-category-icon">
                                    <img src="<?php echo esc_url($card_data['category_icon']); ?>" 
                                         alt="<?php echo esc_attr($card_data['category']->name); ?> icon" />
                                </div>
                            <?php elseif ($card_data['category']): ?>
                                <!-- Placeholder icon when actual icon not available -->
                                <div class="recipe-category-icon recipe-category-icon-placeholder">
                                    <span><?php echo esc_html(substr($card_data['category']->name, 0, 1)); ?></span>
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
                                    <!-- TODO: Replace with actual clock icon when provided -->
                                    <span class="recipe-icon recipe-clock-icon">⏰</span>
                                    <span class="recipe-prep-time-text"><?php echo esc_html($card_data['prep_time']); ?></span>
                                </div>
                                
                                <div class="recipe-servings">
                                    <!-- TODO: Replace with actual person icon when provided -->
                                    <span class="recipe-icon recipe-person-icon">👤</span>
                                    <span class="recipe-servings-text"><?php echo esc_html($card_data['servings']); ?></span>
                                </div>
                            </div>
                            
                        </div>
                        
                    </a>
                </div>
                
            <?php endforeach; ?>
            
        <?php else: ?>
            
            <!-- No Results -->
            <div class="no-results">
                <p>No recipes found matching your criteria. Please try adjusting your filters.</p>
                <?php if (!empty(array_filter($filters))): ?>
                    <button type="button" class="btn btn-clear-filters" id="clear-recipe-filters">Clear Filters</button>
                <?php endif; ?>
            </div>
            
        <?php endif; ?>
        
    </div>
    
    <!-- Loading Indicator for AJAX -->
    <div class="loading-indicator" style="display: none;">
        <p>Loading recipes...</p>
    </div>
    
</div>

<?php
// Log template completion
Handy_Custom_Logger::log('Recipes archive template rendered successfully with ' . count($recipes) . ' recipes');
?>