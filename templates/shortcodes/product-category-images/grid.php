<?php
/**
 * Product Category Images Grid Template
 * Simple grid of circular category images with names for Flatsome integration
 * Based on design from assets/images/category-images-shortcode-design-example.png
 * 
 * @package Handy_Custom
 */

if (!defined('ABSPATH')) {
    exit;
}

if (empty($categories)) {
    return;
}
?>

<div class="handy-category-images-grid" data-shortcode="product-category-images">
    <?php foreach ($categories as $category) : ?>
        <?php 
        // Use same method as [products] shortcode for image consistency
        $featured_image = Handy_Custom_Products_Display::get_category_featured_image($category->term_id);
        if ($featured_image) :
            // Build the product archive URL in format: /products/{category-slug}/
            $category_url = home_url('/products/' . $category->slug . '/');
        ?>
            <a href="<?php echo esc_url($category_url); ?>" class="category-image-item-link">
                <div class="category-image-item">
                    <div class="category-image-circle">
                        <img src="<?php echo esc_url($featured_image); ?>" 
                             alt="<?php echo esc_attr($category->name); ?>" 
                             class="category-featured-image"
                             loading="lazy">
                    </div>
                    <div class="category-name"><?php echo esc_html($category->name); ?></div>
                </div>
            </a>
        <?php endif; ?>
    <?php endforeach; ?>
</div>