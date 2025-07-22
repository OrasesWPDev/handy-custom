<?php
/**
 * Recipes archive template
 * 
 * Template for [recipes] shortcode - displays recipe cards in 4-column grid
 * Paginated at 16 recipes per page (4 rows × 4 columns)
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
    
    <!-- Filter Controls Removed: Use [filter-recipes] shortcode instead -->

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
                            
                            <?php // Category icon removed per user feedback - not requested ?>
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