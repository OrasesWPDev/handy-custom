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
 * @var array $filters Current filter values (includes subcategory if specified)
 * @var array $categories Product categories to display (categories mode)
 * @var WP_Query $products Product posts query object (list mode)
 * @var string $display_mode Display mode: 'categories' or 'list'
 * @var array $filter_options All available filter options
 * @var string $subcategory_context Current subcategory slug if filtering by subcategory
 */

if (!defined('ABSPATH')) {
    exit;
}

# Log template context and determine CSS classes
$context_info = 'Loading products archive template with ' . count($categories) . ' categories';
if (!empty($filters['subcategory'])) {
    $context_info .= " (subcategory: {$filters['subcategory']})";
}

// Determine container CSS class based on context
$container_class = 'handy-products-archive';
if ($display_mode === 'list') {
    $container_class .= ' products-list';
} elseif (!empty($filters['category'])) {
    $container_class .= ' products-subcategory';
} else {
    $container_class .= ' products-top-level';
}

Handy_Custom_Logger::log($context_info . " (CSS class: {$container_class})", 'info');
?>

<div class="<?php echo esc_attr($container_class); ?>" data-shortcode="products" data-display-mode="<?php echo esc_attr($display_mode); ?>"
     <?php if (!empty($filters['category'])): ?>data-category="<?php echo esc_attr($filters['category']); ?>"<?php endif; ?>
     <?php if (!empty($filters['subcategory'])): ?>data-subcategory="<?php echo esc_attr($filters['subcategory']); ?>"<?php endif; ?>>
    
    <!-- Filter Controls Removed: Use [filter-products] shortcode instead -->

    <!-- Products Grid -->
    <div class="handy-products-grid <?php echo esc_attr($display_mode === 'list' ? 'products-list-view' : 'products-category-view'); ?>" id="products-results">
        
        <?php if ($display_mode === 'list'): ?>
            <!-- Product List Display Mode -->
            <?php if ($products && $products->have_posts()): ?>
                
                <?php while ($products->have_posts()): $products->the_post(); ?>
                    <?php
                    // Get product data
                    $product_id = get_the_ID();
                    $product_thumbnail = Handy_Custom_Products_Display::get_product_thumbnail($product_id);
                    $product_excerpt = Handy_Custom_Products_Display::get_product_excerpt($product_id);
                    $product_url = Handy_Custom_Products_Display::get_product_single_url($product_id);
                    ?>
                    
                    <div class="product-list-card" data-product="<?php echo esc_attr($product_id); ?>">
                        
                        <!-- Product Thumbnail -->
                        <div class="product-thumbnail">
                            <?php if ($product_thumbnail): ?>
                                <img src="<?php echo esc_url($product_thumbnail); ?>" 
                                     alt="<?php echo esc_attr(get_the_title()); ?>" 
                                     loading="lazy">
                            <?php else: ?>
                                <div class="image-placeholder">
                                    <span><?php echo esc_html(get_the_title()); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Product Info -->
                        <div class="product-info">
                            
                            <!-- Product Title -->
                            <h3 class="product-title"><?php echo esc_html(get_the_title()); ?></h3>
                            
                            <!-- Product Excerpt -->
                            <?php if (!empty($product_excerpt)): ?>
                                <div class="product-excerpt">
                                    <p><?php echo esc_html($product_excerpt); ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Product Link Arrow -->
                            <div class="product-link">
                                <a href="<?php echo esc_url($product_url); ?>" class="product-arrow-link">
                                    <!-- TODO: Replace with actual arrow image when uploaded to assets/images/ -->
                                    <span class="arrow-placeholder">→</span>
                                </a>
                            </div>
                            
                        </div>
                        
                    </div>
                    
                <?php endwhile; ?>
                <?php wp_reset_postdata(); ?>
                
            <?php else: ?>
                
                <!-- No Products Found -->
                <div class="no-results">
                    <p>No products found matching the selected filters.</p>
                </div>
                
            <?php endif; ?>
            
        <?php else: ?>
            <!-- Category Display Mode (Default) -->
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
                                <?php
                                // Determine button text based on whether category has children
                                $has_children = Handy_Custom_Products_Utils::has_child_categories($category->term_id);
                                $button_text = $has_children ? 'See Options' : 'See Products';
                                ?>
                                <a href="/product-locator/" class="btn btn-shop"><i class="fa-regular fa-circle-ellipsis"></i>Shop Now</a>
                                <a href="<?php echo esc_url($shop_url); ?>" class="btn btn-learn"><i class="fa-regular fa-cart-shopping"></i><?php echo esc_html($button_text); ?></a>
                            </div>
                            
                        </div>
                        
                    </div>
                    
                <?php endforeach; ?>
                
            <?php else: ?>
                
                <!-- No Categories Found -->
                <div class="no-results">
                    <p>No categories found matching the selected filters.</p>
                </div>
                
            <?php endif; ?>
            
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
