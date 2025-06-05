<?php
/**
 * Products archive template
 * 
 * Template for [products] shortcode with filtering
 * Handles all filter parameters and displays category cards
 *
 * @package Handy_Custom
 * 
 * Available variables:
 * @var array $filters Current filter values
 * @var array $categories Product categories to display
 * @var array $filter_options All available filter options
 */

if (!defined('ABSPATH')) {
    exit;
}

Handy_Custom_Logger::log('Loading products archive template with ' . count($categories) . ' categories');
?>

<div class="handy-products-archive" data-shortcode="products">
    
    <?php if (!empty($filter_options) && empty(array_filter($filters))): ?>
    <!-- Filter Controls - Only show on main archive (no filters applied) -->
    <div class="handy-products-filters">
        <div class="filters-row">
            
            <?php foreach ($filter_options as $filter_key => $terms): ?>
                <?php if (!empty($terms)): ?>
                <div class="filter-group">
                    <label for="filter-<?php echo esc_attr($filter_key); ?>">
                        <?php echo esc_html(ucwords(str_replace('_', ' ', rtrim($filter_key, 's')))); ?>:
                    </label>
                    <select id="filter-<?php echo esc_attr($filter_key); ?>" 
                            name="<?php echo esc_attr(rtrim($filter_key, 's')); ?>" 
                            class="product-filter">
                        <option value="">All <?php echo esc_html(ucwords(str_replace('_', ' ', $filter_key))); ?></option>
                        <?php foreach ($terms as $term): ?>
                            <option value="<?php echo esc_attr($term->slug); ?>" 
                                    <?php selected(isset($filters[rtrim($filter_key, 's')]) ? $filters[rtrim($filter_key, 's')] : '', $term->slug); ?>>
                                <?php echo esc_html($term->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
            <?php endforeach; ?>
            
        </div>
    </div>
    <?php endif; ?>

    <!-- Products Grid -->
    <div class="handy-products-grid" id="products-results">
        
        <?php if (!empty($categories)): ?>
            
            <?php foreach ($categories as $category): ?>
                <?php
                // Get category data
                $category_image = Handy_Custom_Products_Display::get_category_featured_image($category->term_id);
                $category_icon = Handy_Custom_Products_Display::get_category_icon($category->slug);
                $description_data = Handy_Custom_Products_Display::truncate_description($category->description);
                $category_url = Handy_Custom_Products_Display::get_category_page_url($category);
                $shop_url = Handy_Custom_Products_Display::get_shop_now_url($category);
                ?>
                
                <div class="product-category-card" data-category="<?php echo esc_attr($category->slug); ?>">
                    
                    <!-- Category Featured Image -->
                    <div class="category-image">
                        <?php if ($category_image): ?>
                            <img src="<?php echo esc_url($category_image); ?>" 
                                 alt="<?php echo esc_attr($category->name); ?>" 
                                 loading="lazy">
                        <?php else: ?>
                            <div class="image-placeholder">
                                <span><?php echo esc_html($category->name); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Category Info -->
                    <div class="category-info">
                        
                        <!-- Category Icon & Title -->
                        <div class="category-header">
                            <?php if ($category_icon): ?>
                                <img src="<?php echo esc_url($category_icon); ?>" 
                                     alt="<?php echo esc_attr($category->name); ?> icon" 
                                     class="category-icon">
                            <?php else: ?>
                                <!-- TODO: Add category icons to assets/images/ using naming pattern: {slug}-icon.png -->
                                <div class="icon-placeholder"></div>
                            <?php endif; ?>
                            <h3 class="category-title"><?php echo esc_html($category->name); ?></h3>
                        </div>
                        
                        <!-- Category Description -->
                        <div class="category-description">
                            <?php if (!empty($description_data['truncated'])): ?>
                                <p class="description-text">
                                    <?php echo esc_html($description_data['truncated']); ?>
                                    <?php if ($description_data['is_truncated']): ?>
                                        <span class="description-toggle" data-full-text="<?php echo esc_attr($description_data['full']); ?>">...</span>
                                    <?php endif; ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="category-actions">
                            <a href="<?php echo esc_url($shop_url); ?>" class="btn btn-shop">Shop Now</a>
                            <a href="<?php echo esc_url($category_url); ?>" class="btn btn-learn">Find Out More</a>
                        </div>
                        
                    </div>
                    
                </div>
                
            <?php endforeach; ?>
            
        <?php else: ?>
            
            <!-- No Results -->
            <div class="no-results">
                <p>No products found matching the selected filters.</p>
                <?php if (!empty(array_filter($filters))): ?>
                    <button type="button" class="btn btn-clear-filters">Clear Filters</button>
                <?php endif; ?>
            </div>
            
        <?php endif; ?>
        
    </div>
    
    <!-- Loading Indicator for AJAX -->
    <div class="loading-indicator" style="display: none;">
        <p>Loading products...</p>
    </div>
    
</div>

<?php
// Log template completion
Handy_Custom_Logger::log('Products archive template rendered successfully');
?>